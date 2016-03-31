<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Facebook\Facebook;
use DB;
use Event;
use Log;

class FbConversationCallbackController extends Controller
{

    private $fb;
    private $fbIdsToUserIds = array();
    private $idsToUsers = array();
    private $availableUsers = array();
    private $matchedUsers = array();

    /**
     * Process facebook message callback.
     *
     * @param  Request  $request
     * @return null
     */
    public function processConversationCallbackRequest(Request $request)
    {
        Log::info('POST - fbNewMessage');
        $content = $request->json();
        Log::debug('Callback Content: ' . print_r($content, true));

        //TODO check validity
        $valid = true;
        if ($valid) {
            Log::debug('Valid update received');

            $this->fb = new Facebook([
                'app_id' => env('APP_ID'),
                'app_secret' => env('APP_SECRET'),
                'default_graph_version' => 'v2.5',
            ]);
            $this->fb->setDefaultAccessToken(env('PAGE_ACCESS_TOKEN'));

            $this->matchedUsers = DB::table('userMatch')->pluck('matchId');
            $userRows = DB::table('user')->get();
            if ($userRows === null) {
                Log::error('Error retrieving users');
                exit();
            }
            foreach($userRows as $user)
            {
                $this->fbIdsToUserIds[$user->fbId] = $user->id;
                $this->idsToUsers[$user->id] = $user;
                if (!in_array($user->id, $this->matchedUsers)) {
                    $this->availableUsers[] = $user;
                }
            }

            $entries = $content->get('entry');


            //first count how many messages were sent and find initial time sent
            $newMessageCountByConversationId = array();
            $timestampByConversationId = array();
            foreach ($entries as $entry) {
                $changes = $entry['changes'];
                $timestamp = (int) $entry['time'];

                foreach ($changes as $change) {
                    if ($change['value'] !== null && $change['value']['thread_id'] !== null) {
                        $conversationId = $change['value']['thread_id'];

                        if (!array_key_exists($conversationId, $timestampByConversationId)) {
                            $timestampByConversationId[$conversationId] = $timestamp;
                        } else if (array_key_exists($conversationId, $timestampByConversationId) &&
                            $timestampByConversationId[$conversationId] > $timestamp) {
                            $timestampByConversationId[$conversationId] = $timestamp;
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
                //subtract 1 from timestamp because facebook seems to not use it inclusively
                $this->processNewMessages($conversationId, $count, $timestampByConversationId[$conversationId]);
            }
        }
    }

    private function processNewMessages($conversationId, $count, $timestamp)
    {
        Log::debug("Count:  $count, timestamp: $timestamp" );
        $conversationResponse = $this->fb->get($conversationId . "/messages?&limit=3&fields=message,created_time,from,to");
        $conversationEdge = $conversationResponse->getGraphEdge();
        $conversationNodesChronological = array();
        Log::debug('Edge:  ' . print_r($conversationEdge, true));
        foreach ($conversationEdge as $conversationNode) {
            array_unshift($conversationNodesChronological, $conversationNode);
        }
        foreach ($conversationNodesChronological as $conversationNode) {
            $this->processMessage($conversationNode, $conversationId);
        }

    }

    private function processMessage($singleMessage, $conversationId)
    {
        $sender = $singleMessage->getField('from');
        $senderFbId = $sender->getField('id');
        $senderFbName = $sender->getField('name');
        $messageText = $singleMessage->getField('message');

        $senderIdFromDb = isset($this->fbIdsToUserIds[$senderFbId]) ?
            $this->fbIdsToUserIds[$senderFbId] : null;

        if ($senderIdFromDb === null) {
            //TODO make this a transaction
            $id = DB::table('user')->insertGetId(['fbId' => $senderFbId,'fbName' => $senderFbName,'fbConversationId' => $conversationId]);
            $this->fbIdsToUserIds[$senderFbId] = $id;
            DB::table('userMatch')->insert(['userId' => $id]);
            Log::info("Saved $senderFbId to db");
        } else {

            //TODO logic for processing special commands should go here
            if ($messageText === '/n') {
                $this->findAndSetMatchedUser($senderIdFromDb);

                return;
            }

            $matchId = DB::table('userMatch')->where('userId', $senderIdFromDb)->value('matchId');
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

    private function routeMessageToMatchedUser($matchId, $messageText)
    {
        $matchedUserConversation = $this->idsToUsers[$matchId]->fbConversationId;
        $this->fb->post($matchedUserConversation . '/messages', array('message' => $messageText));
        Log::debug("Successfully routed message to $matchedUserConversation");
    }

    private function findAndSetMatchedUser($senderIdFromDb)
    {
        $eligibleMatches = array();
        foreach ($this->availableUsers as $potentialMatch) {
            if ($potentialMatch->id === $senderIdFromDb) {
                continue;
            }
            $eligibleMatches[] = $potentialMatch->id;
        }
        $numMatches = count($eligibleMatches);
        if ($numMatches > 0) {
            $matchId = $eligibleMatches[mt_rand(0, $numMatches - 1)];

            DB::table('userMatch')->where('userId', $senderIdFromDb)->update(['matchId' => $matchId]);
            DB::table('userMatch')->where('userId', $matchId)->update(['matchId' => $senderIdFromDb]);

            return $matchId;
        }
    }
}