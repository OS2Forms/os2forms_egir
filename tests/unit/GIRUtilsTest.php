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
    $this->utils = new GIRUtils();
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
    $utils = new GIRUtils($mockHttp, FALSE);

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

  public function testGetOpenIdToken()
  {
    $body = json_encode([ 'access_token' => 'xyz' ]);
    $mock = new MockHandler([
      new Response(200, [], $body),
    ]);
    $handler = HandlerStack::create($mock);
    $mockHttp = new Client(['handler' => $handler]);
    $utils = new GIRUtils($mockHttp);

    $token = $utils->getOpenIdToken();

    $this->assertEquals($token, 'xyz');

  }

  public function testGetExternals()
  {
    // Get a functioning list of engagement associations somehow.
    // Mock for the getJsonFromAPI call.
    // Expect the correct externals to be extracted.
    // @ todo Implement this.
    $this->assertEquals(0, 0);
  }

  public function testGetEngagement()
  {
    // Supply list of details for the HTTP mock.
    // Supply resulting engagement for the HTTP mock.
    // Check the correct engagement is returned.
    // @ todo Implement this.
    $this->assertEquals(0, 0);
  }

  public function testGetEngagementAssociations()
  {
    $engagement_associations = file_get_contents(
      __DIR__ . '/test_data/mo_engagement_associations.json'
    );
    $mock = new MockHandler([
      new Response(200, [], $engagement_associations),
    ]);
    $handler = HandlerStack::create($mock);
    $mockHttp = new Client(['handler' => $handler]);
    $utils = new GIRUtils($mockHttp, FALSE);
    $some_uuid = 'cb23626d-907c-650a-5a58-5640cb4f6ea9';

    $expected_result = [
      [
        "uuid" => "2a48903c-fabf-07cd-7b4d-fc0ca18c11f6",
        "givenname" => "Awarth",
        "surname" => "Ahmad",
        "name" => "Awarth Ahmad",
        "nickname_givenname" => "",
        "nickname_surname" => "",
        "nickname" => "",
        "seniority" => "2020-08-01T00:00:00+02:00"
      ]
    ];
    $externals = $utils->getExternals($some_uuid);

    $this->assertEquals($externals, $expected_result);

  }

  public function testGetMoveData()
  {
    $engagement_associations = file_get_contents(
      __DIR__ . '/test_data/mo_engagement_associations.json'
    );
    $mock = new MockHandler([
      new Response(200, [], $engagement_associations),
    ]);
    $handler = HandlerStack::create($mock);
    $mockHttp = new Client(['handler' => $handler]);
    $utils = new GIRUtils($mockHttp, FALSE);
    $some_uuid = 'cb23626d-907c-650a-5a58-5640cb4f6ea9';

    $moveData = $utils->getMoveData($some_uuid, $some_uuid, $some_uuid);

    $today = date('Y-m-d');

    $expected_result = [
      [
        "type" => "engagement_association",
        "uuid" => "e2d8682b-1ace-e502-811e-82c88d7fb745",
        "data" => [
          "org_unit" => [
            "uuid" => "cb23626d-907c-650a-5a58-5640cb4f6ea9",],
            "validity" => ["from" => "$today","to" => null]
        ]
      ]
    ];
    $this->assertEquals($moveData, $expected_result);
  }

}
