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
        $valid = true;
        if ($valid) {
            Log::debug('Valid update received');

            $this->fb = new Facebook([
                'app_id' => env('APP_ID'),
                'app_secret' => env('APP_SECRET'),
                'default_graph_version' => 'v2.5',
            ]);
            $this->fb->setDefaultAccessToken(env('PAGE_ACCESS_TOKEN'));

            $userRows = DB::select('SELECT * FROM user');
            if ($userRows === null) {
                Log::error('Error retrieving users');
                exit();
            }
            foreach($userRows as $user)
            {
                $this->fbIdsToUserIds[$user->fbId] = $user->id;
                $this->idsToUsers[$user->id] = $user;
                if ($user->matchedUser === null) {
                    $this->availableUsers[] = $user;
                }
            }

            $entries = $content->get('entry');
            foreach ($entries as $entry) {
                $changes = $entry['changes'];

                foreach ($changes as $change) {
                    $this->processChange($change);
                }
            }
        }
    }

    private function processChange($change)
    {
        if ($change['value'] !== null && $change['value']['thread_id'] !== null) {
            $conversationID = $change['value']['thread_id'];
            $conversationResponse = $this->fb->get($conversationID . '/messages?limit=1&fields=message,created_time,from,to');
            $conversationEdge = $conversationResponse->getGraphEdge();

            foreach ($conversationEdge as $singleMessage) {
                $this->processMessage($singleMessage, $conversationID);
            }
        }
    }

    private function processMessage($singleMessage, $conversationID)
    {
        $sender = $singleMessage->getField('from');
        $senderFbId = $sender->getField('id');
        $senderFbName = $sender->getField('name');
        $messageText = $singleMessage->getField('message');

        $senderIdFromDb = isset($this->fbIdsToUserIds[$senderFbId]) ?
            $this->fbIdsToUserIds[$senderFbId] : null;

        if ($senderIdFromDb === null) {
            //TODO switch to prepared statements
            DB::insert("INSERT INTO user (fbId,fbName,fbConversationId) VALUES ('$senderFbId','$senderFbName', '$conversationID')");
            $this->fbIdsToUserIds[$senderFbId] = DB::connection()->getPdo()->lastInsertId();
            Log::info("Saved $senderFbId to db");
        } else {
            //TODO logic for processing special commands should go here
            if ($messageText === '/n') {
                $this->findAndSetMatchedUser($senderIdFromDb);
            }


            if (isset($this->idsToUsers[$senderIdFromDb]) && isset($this->idsToUsers[$this->idsToUsers[$senderIdFromDb]->matchedUser])) {
                Log::info("$senderFbId already exists in db and is already matched, routing message");
                $this->routeMessageToMatchedUser($senderIdFromDb, $messageText);
            } else if (isset($this->idsToUsers[$senderIdFromDb])) {
                Log::info("$senderFbId already exists in db and is not matched, finding a match and then routing message");
                $this->findAndSetMatchedUser($senderIdFromDb);
                $this->routeMessageToMatchedUser($senderIdFromDb, $messageText);
            }

        }
    }

    private function routeMessageToMatchedUser($senderIdFromDb, $messageText)
    {
        $matchedUserConversation = $this->idsToUsers[$this->idsToUsers[$senderIdFromDb]->matchedUser]->fbConversationId;
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

            DB::update("UPDATE user SET matchedUser=$matchId WHERE id=$senderIdFromDb");
        }
    }
}