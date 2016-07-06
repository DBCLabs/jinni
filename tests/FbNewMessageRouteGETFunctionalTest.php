<?php

class FbNewMessageRouteGETFunctionalTest extends TestCase
{

    public function testValidGetEchoesHubChallenge() {
        $verify = env('HUB_VERIFY_TOKEN');
        $challenge = 'CHALLENGETOKEN';

        $response = $this->call('GET',
            "/fbNewMessage?hub_mode=subscribe&hub_challenge=$challenge&hub_verify_token=$verify");

        $this->assertEquals(200, $response->status());
        $this->assertEquals($challenge, $response->content());

    }

    public function testGetWithInvalidHubModeDoesNotEchoHubChallenge() {
        $verify = env('HUB_VERIFY_TOKEN');
        $challenge = 'CHALLENGETOKEN';

        //test invalid hub_mode
        $response = $this->call('GET',
            "/fbNewMessage?hub_mode=notsubscribe&hub_challenge=$challenge&hub_verify_token=$verify");
        $this->assertEquals(200, $response->status());
        $this->assertEquals('', $response->content());
    }

    public function testGetWithInvalidVerifyTokenDoesNotEchoHubChallenge() {
        $challenge = 'CHALLENGETOKEN';

        //test invalid hub_mode
        $response = $this->call('GET',
            "/fbNewMessage?hub_mode=subscribe&hub_challenge=$challenge&hub_verify_token=BADVERIFYTOKEN");
        $this->assertEquals(200, $response->status());
        $this->assertEquals('', $response->content());
    }


}
