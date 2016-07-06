<?php

class HomepageTest extends TestCase
{
    public function testJinniDisplayed()
    {
        $this->visit('/')
             ->see('Jinni');
    }
}
