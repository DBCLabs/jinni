<?php
require_once __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('America/New_York');

define("PAGE_ID", 1567438433575561);

$fb = new Facebook\Facebook([
    'app_id' => '181667948873255',
    'app_secret' => '2a1e55c3ddb06a3d98e3f4f9fbb0bd70',
    'default_graph_version' => 'v2.5',
]);

$fb->setDefaultAccessToken('CAAClOd2PRicBAMW5W8cIS6ANrdycNZBlHPTXDHQLkFDP3Rgfdw1e4vCqLX65fwQB37m13g5zcXZCtEDtS4GDdDAjfH00ing0aIYkaeOgayuBvLilVqilCHL5iFvBADtoLZCiSSFA1gVQ9xDCtTz7Rj3l4j6SwmLZBk9ZCGUs766byU0ShcaDSj4s80sDFrpPBWiTU5TpYXwZDZD');

$conversationID='t_mid.1455767691069:ff53c2fd9e7337ee60';

// Version 1: just take the snippet
$response = $fb->get($conversationID.'?fields=snippet');
$facebookNode = $response->getGraphNode();

$snippet = $facebookNode->getField('snippet');

print_r($snippet);

// Version 2: get all messages
$response2 = $fb->get($conversationID.'/messages?fields=message,created_time,from,to');
$facebookEdge = $response2->getGraphEdge();

$messages = array();

foreach($facebookEdge as $singleMessage) {
    array_push($messages,['message'=>$singleMessage->getField('message'),'created_time'=>$singleMessage->getField('created_time'),'from'=>$singleMessage->getField('from')->getField('name')]);
}

print_r($messages);



?>