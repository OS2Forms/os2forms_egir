<?php

class HelloWorldTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testSomeFeature()
    {
        $one = 1;
        $this->assertEquals($one, 1);
        $this->assertFalse($one == 2);
        // PHP gotcha.
        $this->assertTrue(TRUE == 'Hello');
        $this->assertFalse(TRUE === 'World');

    }
}
