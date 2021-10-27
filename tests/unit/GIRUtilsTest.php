<?php

use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;

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
    public function testFormsLog() {
      $logger = GIRUtils::formsLog();
      $this->assertTrue(method_exists($logger, 'notice'));
      $this->assertTrue(method_exists($logger, 'error'));

    }
    public function testGetUserData()
    {
        $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
          
        $user = \Drupal\user\Entity\User::create();
        $user->enforceIsNew();
        $user->setPassword('password');
        $user->setEmail('egir@example.com');
        $user->setUsername('user');
        $user->activate();
        $user->save();
      $name = GIRUtils::getUserData($user->id(), 'name');
          $this->assertEquals($name, 'user');

          $user->delete();

    }

    public function testGetTermData()
    {
      $term = Term::create(['name' => 'egir_test', 'vid' => 'client']);
      $term->save();

      $name = GIRUtils::getTermData($term->id(), 'name');

      $this->assertEquals($name, 'egir_test');

      $term->delete();

    }

    public function testGetTermIdByName()
    {
      $term = Term::create(['name' => 'test_id', 'vid' => 'client']);
      $term->save();
      $id = $term->id();

      $storedId = GIRUtils::getTermIdByName('test_id');

      $this->assertEquals($id, $storedId);

    }

    public function testGetUserByGirUuid()
    {
      $uuid = 'a6207d6a-36ff-11ec-8c87-6763965e91e0';
      // Create new user.
      $user = \Drupal\user\Entity\User::create();
      $user->enforceIsNew();
      $user->setPassword('password');
      $user->setEmail('uuid_test@example.com');
      $user->setUsername('uuid_test');
      $user->activate();
      $user->field_uuid = $uuid;
      $user->save();

      $stored_user_id = GIRUtils::getUserByGirUuid($uuid);

      $this->assertEquals($stored_user_id, $user->id());
    }

}
