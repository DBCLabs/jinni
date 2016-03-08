<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Facebook\Facebook;
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

            $entries = $content->get('entry');
            foreach ($entries as $entry) {
                $changes = $entry['changes'];

                foreach ($changes as $change) {
                    if ($change['value'] !== NULL && $change['value']['thread_id'] !== NULL) {
                        $conversationID = $change['value']['thread_id'];
                        $conversationResponse = $fb->get($conversationID . '/messages?limit=1&fields=message,created_time,from,to');
                        $conversationEdge = $conversationResponse->getGraphEdge();

                        foreach($conversationEdge as $singleMessage) {
                            $sender = $singleMessage->getField('from');
                            $senderId = $sender->getField('id');
                            Log::info($senderId);
                            $senderName = $sender->getField('name');
                        }
                    }
                }
            }
        }
    }
}