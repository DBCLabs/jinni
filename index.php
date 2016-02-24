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
/*
$response = $fb->post(PAGE_ID . '/feed', array ('message' => 'Hope this works'));
$graphObject = $response->getGraphNode();
*/

$request = new Facebook\FacebookRequest(
    $session,
    'GET',
    '/'.PAGE_ID.'/conversations'
);

$response = $fb->get(PAGE_ID . '/conversations');

$graphEdge = $response->getGraphEdge();

foreach ($graphEdge as $graphNode) {
    print_r($graphNode);
}



//$conversationsArray = $graphEdge => ['items'];

//print_r($conversationsArray);

//echo $graphObject;


?>