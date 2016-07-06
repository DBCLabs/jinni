<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Facebook\Facebook;
use App\Models\User;
use App\Models\UserMatch;
use Log;

class FbConversationCallbackController extends Controller
{

    //Facebook sdk object, used for sending requests to fb
    private $fb;

    private $fbIdsToUserIds = array();
    private $idsToUsers = array();

    /**
     * When Jinni receives a new message, facebook will send a callback here.
     * This controller handles routing the message to the appropriate user.
     *
     * @param  Request  $request - callback from facebook
     */
    public function processConversationCallbackRequest(Request $request) {
        Log::info('POST - fbNewMessage');
        $content = $request->json();
        Log::debug('Callback Content: ' . print_r($content, true));

        if ($this->checkValidity()) {
            Log::debug('Valid update received');

            $this->fb = new Facebook([
                'app_id' => env('APP_ID'),
                'app_secret' => env('APP_SECRET'),
                'default_graph_version' => 'v2.5',
            ]);
            $this->fb->setDefaultAccessToken(env('PAGE_ACCESS_TOKEN'));

            //TODO cache these so we don't have to query for all users every request. maybe redis?
            $userRows = User::all();
            if ($userRows === null) {
                Log::error('Error retrieving users');
                exit();
            }
            foreach($userRows as $user) {
                $this->fbIdsToUserIds[$user->fbId] = $user->id;
                $this->idsToUsers[$user->id] = $user;
            }

            $entries = $content->get('entry');

            //facebook batches message updates, so first count how many messages were sent and find initial time sent
            $newMessageCountByConversationId = array();
            $earliestTimestampByConversationId = array();
            foreach ($entries as $entry) {
                $changes = $entry['changes'];
                $timestamp = (int) $entry['time'];

                foreach ($changes as $change) {
                    if ($change['value'] !== null && $change['value']['thread_id'] !== null) {
                        $conversationId = $change['value']['thread_id'];

                        if (!array_key_exists($conversationId, $earliestTimestampByConversationId)) {
                            $earliestTimestampByConversationId[$conversationId] = $timestamp;
                        } else if (array_key_exists($conversationId, $earliestTimestampByConversationId) &&
                            $earliestTimestampByConversationId[$conversationId] > $timestamp) {
                            $earliestTimestampByConversationId[$conversationId] = $timestamp;
                        }

                        if (array_key_exists($conversationId, $newMessageCountByConversationId)) {
                            $newMessageCountByConversationId[$conversationId] += 1;
                        } else {
                            $newMessageCountByConversationId[$conversationId] = 1;
                        }
                    }
                }
            }

            foreach ($newMessageCountByConversationId as $conversationId => $count) {
                $this->processNewMessages($conversationId, $count, $earliestTimestampByConversationId[$conversationId]);
            }
        }
    }

    //TODO check validity
    private function checkValidity() {
        return true;
    }

    private function processNewMessages($conversationId, $count, $timestamp) {
        Log::debug("Count:  $count, timestamp: $timestamp" );
        $conversationResponse = $this->fb->get($conversationId . "/messages?since=$timestamp&limit=$count&fields=message,created_time,from,to");
        $conversationEdge = $conversationResponse->getGraphEdge();

        //messages come in reverse order so we reverse them here
        $conversationNodesChronological = array();
        Log::debug('Edge:  ' . print_r($conversationEdge, true));
        foreach ($conversationEdge as $conversationNode) {
            array_unshift($conversationNodesChronological, $conversationNode);
        }

        foreach ($conversationNodesChronological as $conversationNode) {
            $this->processMessage($conversationNode, $conversationId);
        }

    }

    private function processMessage($singleMessage, $conversationId) {
        $sender = $singleMessage->getField('from');
        $senderFbId = $sender->getField('id');
        $senderFbName = $sender->getField('name');
        $messageText = $singleMessage->getField('message');

        $senderIdFromDb = isset($this->fbIdsToUserIds[$senderFbId]) ?
            $this->fbIdsToUserIds[$senderFbId] : null;

        if ($senderIdFromDb === null) {
            $this->saveNewUser($conversationId, $senderFbId, $senderFbName);

        } else {

            //TODO logic for processing special commands should go here
            if ($messageText === '/n') {
                $this->findAndSetMatchedUser($senderIdFromDb);

                return;
            }

            $matchId = UserMatch::where('userId', $senderIdFromDb)->value('matchId');
            if ($matchId !== null && isset($this->idsToUsers[$matchId])) {
                Log::info("$senderFbId already exists in db and is already matched, routing message");
                $this->routeMessageToMatchedUser($matchId, $messageText);
            } else {
                Log::info("$senderFbId already exists in db and is not matched, finding a match and then routing message");
                $matchId = $this->findAndSetMatchedUser($senderIdFromDb);
                if ($matchId !== null && isset($this->idsToUsers[$matchId])) {
                    $this->routeMessageToMatchedUser($matchId, $messageText);
                }
            }

        }
    }

    //TODO make this block a transaction
    private function saveNewUser($conversationId, $senderFbId, $senderFbName)
    {
        $newUser = new User;
        $newUser->fbId = $senderFbId;
        $newUser->fbName = $senderFbName;
        $newUser->fbConversationId = $conversationId;
        $newUser->save();

        $id = $newUser->id;
        $this->fbIdsToUserIds[$senderFbId] = $id;

        $userMatch = new UserMatch;
        $userMatch->userId = $id;
        $userMatch->save();
        Log::info("Saved $senderFbId to db");
    }

    private function routeMessageToMatchedUser($matchId, $messageText) {
        $matchedUserConversation = $this->idsToUsers[$matchId]->fbConversationId;
        $this->fb->post($matchedUserConversation . '/messages', array('message' => $messageText));
        Log::debug("Successfully routed message to $matchedUserConversation");
    }

    //for now we just match randomly to any available user
    private function findAndSetMatchedUser($senderIdFromDb) {
        $availableUserIds = UserMatch::pluck('userId')->where('matchId', null);
        $eligibleMatches = $this->filterIneligibleMatches($senderIdFromDb, $availableUserIds);

        $numMatches = count($eligibleMatches);
        if ($numMatches > 0) {
            $matchId = $eligibleMatches[mt_rand(0, $numMatches - 1)];

            UserMatch::where('userId', $senderIdFromDb)->update(['matchId' => $matchId]);
            UserMatch::where('userId', $matchId)->update(['matchId' => $senderIdFromDb]);

            return $matchId;
        }
    }

    //eligible matches is a subset of available users, for now we only filter out the sender's id but in the future
    //we'll want to filter based on other criteria
    private function filterIneligibleMatches($senderIdFromDb, $availableUserIds)
    {
        $eligibleMatches = array();

        foreach ($availableUserIds as $potentialMatchId) {
            if ($potentialMatchId === $senderIdFromDb) {
                continue;
            }
            $eligibleMatches[] = $potentialMatchId;
        }
        return $eligibleMatches;
    }
}