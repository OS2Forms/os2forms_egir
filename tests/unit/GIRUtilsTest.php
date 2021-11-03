<?php

use Drupal\user\Entity\User;
use Drupal\taxonomy\Entity\Term;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

use Drupal\os2forms_egir\GIRUtils;


class GIRUtilsTest extends \Codeception\Test\Unit
{
  /**
   * @var \UnitTester
   */
  protected $tester;
  protected $utils;
    
    protected function _before()
    {
      $this->utils = new GIRUTils();
    }

    protected function _after()
    {
    }

    // tests
    public function testFormsLog() {
      $logger = $this->utils->formsLog();
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
      $name = $this->utils->getUserData($user->id(), 'name');
          $this->assertEquals($name, 'user');

          $user->delete();

    }

    public function testGetTermData()
    {
      $term = Term::create(['name' => 'egir_test', 'vid' => 'client']);
      $term->save();

      $name = $this->utils->getTermData($term->id(), 'name');

      $this->assertEquals($name, 'egir_test');

      $term->delete();

    }

    public function testGetTermIdByName()
    {
      $term = Term::create(['name' => 'testid', 'vid' => 'client']);
      $term->save();
      $id = $term->id();

      $storedId = $this->utils->getTermIdByName('testid');

      $this->assertEquals($id, $storedId);

      $term->delete();

    }

    public function testGetUserByGirUuid()
    {
      $uuid = '598c225a-3728-11ec-a511-3341ab9aa960';
      // Create new user.
      $user = \Drupal\user\Entity\User::create();
      $user->enforceIsNew();
      $user->setPassword('password');
      $user->setEmail('uid@example.com');
      $user->setUsername('uid');
      $user->activate();
      $user->field_uuid = $uuid;
      $user->save();

      $stored_user_id = $this->utils->getUserByGirUuid($uuid);

      $this->assertEquals($stored_user_id, $user->id());

      $user->delete();
    }

    public function testGetJsonFromApi()
    {
      $body = file_get_contents(__DIR__ . '/test_data/mo_create_org_func.json');
      $mock = new MockHandler([
        new Response(200, [], $body),
      ]);
      $handler = HandlerStack::create($mock);
      $mockHttp = new Client(['handler' => $handler]);
      $utils = new GIRUtils($mockHttp, false);
      
      $response = $utils->getJsonFromApi('/no/real/path');

      $this->assertEquals($response, json_decode($body, TRUE));
    }

    public function testPostJsonToApi()
    {
      $data = file_get_contents(__DIR__ . '/test_data/mo_create_org_func.json');
      $mock = new MockHandler([
        new Response(201, [], '{}'),
      ]);
      $handler = HandlerStack::create($mock);
      $mockHttp = new Client(['handler' => $handler]);
      $utils = new GIRUtils($mockHttp, FALSE);

      $response = $utils->postJsonToApi('/some/path/', $data);

      $this->assertEquals(json_encode($response), '{}');
    }
}
