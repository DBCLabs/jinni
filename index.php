<?php
require_once __DIR__ . '/vendor/autoload.php';

require_once( '/vendor/facebook/FacebookSession.php' );
require_once( '/vendor/facebook/FacebookRedirectLoginHelper.php' );
require_once( '/vendor/facebook/FacebookRequest.php' );
require_once( '/vendor/facebook/FacebookResponse.php' );
require_once( '/vendor/facebook/FacebookSDKException.php' );
require_once( '/vendor/facebook/FacebookRequestException.php' );
require_once( '/vendor/facebook/FacebookAuthorizationException.php' );
require_once( '/vendor/facebook/GraphObject.php' );

use Facebook\FacebookSession;
use Facebook\FacebookRedirectLoginHelper;
use Facebook\FacebookRequest;
use Facebook\FacebookResponse;
use Facebook\FacebookSDKException;
use Facebook\FacebookRequestException;
use Facebook\FacebookAuthorizationException;
use Facebook\GraphObject;

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

$request = new FacebookRequest(
    $session,
    'GET',
    '/'.PAGE_ID.'/conversations'
);
/*
$response = $request->execute();
$graphObject = $response->getGraphObject();
echo $graphObject;
*/

?>