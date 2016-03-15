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

            Log::info(env('APP_ID'));
            $fb = new Facebook([
                'app_id' => env('APP_ID'),
                'app_secret' => env('APP_SECRET'),
                'default_graph_version' => 'v2.5',
            ]);
            $fb->setDefaultAccessToken(env('PAGE_ACCESS_TOKEN'));

            $dbConn = mysqli_connect(env('DB_HOST'), env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'), env('DB_PORT'));
            if (mysqli_connect_errno()) {
                Log::error('Error connecting to db' . mysqli_connect_errno());
                abort(500);
            }

            $fbIds = array();
            $userRows = mysqli_query($dbConn, 'SELECT fbId FROM user');
            if (!$userRows) {
                Log::error("Error: " . mysqli_error($dbConn));
                exit();
            }
            while($row = mysqli_fetch_array($userRows))
            {
                $fbIds[] = $row['fbId'];
            }

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

                            if (!in_array($senderId, $fbIds)) {
                                $senderName = $sender->getField('name');
                                Log::info($senderName);
                                //TODO switch to prepared statements
                                $success = mysqli_query($dbConn,
                                    "INSERT INTO user (fbId,fbName) VALUES ('$senderId','$senderName')");
                                if (!$success) {
                                    Log::error("Error: " . mysqli_error($dbConn));
                                    exit();
                                }
                                Log::info("Saved $senderId to db");
                            }
                            else {
                                Log::info("$senderId already exists in db");
                            }
                        }
                    }
                }
            }
        }
    }
}