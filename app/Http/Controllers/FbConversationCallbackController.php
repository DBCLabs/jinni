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


            //first count how many messages were sent
            $newMessageCountByConversationId = array();
            foreach ($entries as $entry) {
                $changes = $entry['changes'];

                foreach ($changes as $change) {
                    if ($change['value'] !== null && $change['value']['thread_id'] !== null) {
                        $conversationId = $change['value']['thread_id'];
                        if (array_key_exists($conversationId, $newMessageCountByConversationId)) {
                            $newMessageCountByConversationId[$conversationId] += 1;
                        } else {
                            $newMessageCountByConversationId[$conversationId] = 1;
                        }
                    }
                }
            }

            foreach ($newMessageCountByConversationId as $conversationId => $count) {
                $this->processNewMessages($conversationId, $count);
            }
        }
    }

    private function processNewMessages($conversationId, $count)
    {
        $conversationResponse = $this->fb->get($conversationId . "/messages?limit=$count&fields=message,created_time,from,to");
        $conversationEdge = $conversationResponse->getGraphEdge();

        foreach ($conversationEdge as $singleMessage) {
            $this->processMessage($singleMessage, $conversationId);
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

            $this->fb->post($conversationId. '/messages', array('message' =>
                'Hi bebe, I\'m Jinni. I match my friends with other friends all around the world anonymously for fun and spontaneous conversation. Let serendipity reign!
                You can message me at any time and I\'ll find you a buddy, friend, lover, pet, sweetheart, or whatever floats your boat to chat with. To get started
                what\'s your age?'));

        } else {

            //TODO logic for processing special commands should go here

            if ($this->idsToUsers[$senderIdFromDb]->age === null ) {
                if (ctype_digit($messageText)) {
                    DB::table('user')->where('userId', $senderIdFromDb)->update(['age' => $messageText]);
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'Cool! Next question - what gender are you? You can just say M or F.'));
                }
                else {
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'Hey there, didn\'t get that. Try one more time and make sure to enter your age as a number!'));
                }

                return;

            }

            if ($this->idsToUsers[$senderIdFromDb]->gender === null ) {
                if ($messageText === 'M' || $messageText === 'F') {
                    DB::table('user')->where('userId', $senderIdFromDb)->update(['gender' => $messageText]);
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'Awesome! Final question - what city are you located in?'));
                }
                else {
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'Ugh, didn\'t get that. Try one more time and make sure to enter your gender as M or F!'));
                }

                return;

            }

            if ($this->idsToUsers[$senderIdFromDb]->city === null ) {

                DB::table('user')->where('userId', $senderIdFromDb)->update(['city' => $messageText]);
                $this->fb->post($conversationId. '/messages', array('message' =>
                    'Perfection! A couple rules of the road. You can choose what type of conversation you want at any time by messaging me your taste of the moment.
                Send me /chat to get matched with a stranger for general chitchat, /romance to get matched for
                some more salacious conversation ;-) (ages 18+ only), and /explore to get matched with a new friend from another city. At any time
                you can switch to a new conversation by sending one of the above commands. Give it a try to get started - who can I set you up with?!
                '));
                return;
            }

            if ($messageText === '/chat') {
                $this->fb->post($conversationId. '/messages', array('message' => "I'm looking for a new match. Sit tight bebe :*."));
                DB::table('user')->where('userId', $senderIdFromDb)->update(['genre' => 0]);

                $this->findAndSetMatchedUser($senderIdFromDb);
                // Responding to commands if current user

                return;
            }

            if ($messageText === '/explore') {
                $this->fb->post($conversationId. '/messages', array('message' => "I'm looking for a new match. Sit tight bebe :*."));
                DB::table('user')->where('userId', $senderIdFromDb)->update(['genre' => 1]);

                $this->findAndSetMatchedUser($senderIdFromDb);
                // Responding to commands if current user

                return;
            }

            if ($messageText === '/romance') {
                $this->fb->post($conversationId. '/messages', array('message' => "I'm looking for a new match. Sit tight bebe :*."));
                DB::table('user')->where('userId', $senderIdFromDb)->update(['genre' => 2]);

                if ($this->idsToUsers[$senderIdFromDb]->romancePref === null) {
                    $this->fb->post($conversationId . '/messages', array('message' => 'Naughty! What gender are you looking to chat with? Just say M or F.'));
                }

                $this->findAndSetMatchedUser($senderIdFromDb);
                // Responding to commands if current user

                return;
            }

            if ($this->idsToUsers[$senderIdFromDb]->romancePref === null && $this->idsToUsers[$senderIdFromDb]->genre === 2) {
                if ($messageText === 'M' || $messageText === 'F') {
                    DB::table('user')->where('userId', $senderIdFromDb)->update(['romancePref' => $messageText]);
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'I\'m looking for a new match. Sit tight bebe :*.'));
                    $this->findAndSetMatchedUser($senderIdFromDb);

                }
                else {
                    $this->fb->post($conversationId. '/messages', array('message' =>
                        'Ugh, didn\'t get that. Try one more time and make sure to enter gender as M or F!'));
                }
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