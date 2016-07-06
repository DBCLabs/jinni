<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;

//TODO
class FbNewMessageRoutePOSTFunctionalTest extends TestCase
{

    //rollback db after each test
    use DatabaseMigrations;

    public function testMessageFromNewUserInsertsIntoDB() {
    }

    public function testMessageFromAlreadyPairedUser() {
    }

    public function testMessageFromUnpairedUserWithMatchAvailable() {
    }

    public function testMessageFromUnpairedUserWithNoMatchAvailable() {
    }
}