<?php

use Drupal\user\Entity\User;

use Drupal\os2forms_egir\GIRUtils;


class GIRUtilsTest extends \Codeception\Test\Unit
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
    public function testGetUserData()
    {
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
          
        $user = \Drupal\user\Entity\User::create();
        $user->enforceIsNew();
          $user->setPassword('password');
          $user->setEmail('egir@example.com');
          $user->setUsername('user');
          $user->set("init", 'mail');
          $user->set("langcode", $language);
          $user->set("preferred_langcode", $language);
          $user->set("preferred_admin_langcode", $language);
          $user->activate();
          $user->save();
      $name = GIRUtils::getUserData($user->id(), 'name');
          $this->assertEquals($name, 'user');

          $user->delete();

    }
}
