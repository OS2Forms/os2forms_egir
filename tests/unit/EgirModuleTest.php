<?php

use \Drupal\os2forms_egir\os2forms_egir_show_url_results;


class EgirModuleTest extends \Codeception\Test\Unit
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
    public function testShowUrlResults()
    {
      $form = [];
      $form = os2forms_egir_show_url_results($form);

      $this->assertEquals($form['queueID']['#type'], 'hidden');

    }
}
