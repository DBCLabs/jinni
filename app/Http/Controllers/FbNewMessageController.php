<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Facebook\Facebook;
use DB;
use Event;
use Log;

class FbNewMessageController extends Controller
{
    /**
     * Process facebook message callback.
     *
     * @param  Request  $request
     * @return null
     */
    public function processMessage(Request $request)
    {
        Log::info('POST - fbNewMessage');
        $content = $request->json();
        Log::debug('Callback Content: ' . print_r($content, true));
        $valid = true;
        if ($valid) {
            Log::debug('Valid update received');

            $fb = new Facebook([
                'app_id' => env('APP_ID'),
                'app_secret' => env('APP_SECRET'),
                'default_graph_version' => 'v2.5',
            ]);
            $fb->setDefaultAccessToken(env('PAGE_ACCESS_TOKEN'));

            $fbIdsToUserIds = array();
            $idsToUsers = array();
            $userRows = DB::select('SELECT * FROM user');
            if ($userRows === null) {
                Log::error('Error retrieving users');
                exit();
            }
            foreach($userRows as $user)
            {
                $fbIdsToUserIds[$user->fbId] = $user->id;
                $idsToUsers[$user->id] = $user;
            }

            $entries = $content->get('entry');
            foreach ($entries as $entry) {
                $changes = $entry['changes'];

                foreach ($changes as $change) {
                    if ($change['value'] !== null && $change['value']['thread_id'] !== null) {
                        $conversationID = $change['value']['thread_id'];
                        $conversationResponse = $fb->get($conversationID . '/messages?limit=1&fields=message,created_time,from,to');
                        $conversationEdge = $conversationResponse->getGraphEdge();

                        foreach($conversationEdge as $singleMessage) {
                            $sender = $singleMessage->getField('from');
                            $senderFbId = $sender->getField('id');
                            $senderFbName = $sender->getField('name');
                            $messageText = $singleMessage->getField('message');

                            $senderIdFromDb = isset($fbIdsToUserIds[$senderFbId]) ? $fbIdsToUserIds[$senderFbId] : null;

                            if ($senderIdFromDb === null) {
                                //TODO switch to prepared statements
                                DB::insert("INSERT INTO user (fbId,fbName,fbConversationId) VALUES ('$senderFbId','$senderFbName', '$conversationID')");
                                $fbIdsToUserIds[$senderFbId] = DB::connection()->getPdo()->lastInsertId();
                                Log::info("Saved $senderFbId to db");
                            }
                            else {
                                Log::info("$senderFbId already exists in db, routing message");
                                if (isset($idsToUsers[$senderIdFromDb]) && isset($idsToUsers[$idsToUsers[$senderIdFromDb]->matchedUser])) {
                                    $matchedUserConversation = $idsToUsers[$idsToUsers[$senderIdFromDb]->matchedUser]->fbConversationId;
                                    $fb->post($matchedUserConversation . '/messages', array('message' => $messageText));
                                    Log::debug("Successfully routed message to $matchedUserConversation");
                                }

                            }
                        }
                    }
                }
            }
        }
    }
}