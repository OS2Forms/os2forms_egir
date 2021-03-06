<?php

namespace Drupal\os2forms_egir;

use GuzzleHttp\Client;

use GuzzleHttp\Exception\BadResponseException;

/**
 * Utilities for GIR communication & EGIR form data.
 */
class GIRUtils {

  /**
   * The http service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Keycloak/OpenID auth on/off.
   *
   * @var bool
   */
  protected $useAuth;

  /**
   * Constructor.
   */
  public function __construct(Client $httpClient = NULL, $useAuth = TRUE) {
    if (!$httpClient) {
      $httpClient = \Drupal::httpClient();
    }
    $this->httpClient = $httpClient;
    $this->useAuth = $useAuth;
  }

  /**
   * Get logger.
   */
  public function formsLog() {
    return \Drupal::logger('os2forms_egir');
  }

  /**
   * Get user data by Drupal ID and field name.
   */
  public function getUserData($user_id, $field_name) {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load($user_id);
    return $user->getTypedData()->get($field_name)->value;
  }

  /**
   * Get taxonomy term data by Drupal ID and field name.
   */
  public function getTermData($term_id, $field_name) {
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term_id);
    return $term->getTypedData()->get($field_name)->value;
  }

  /**
   * Get term ID by name.
   */
  public function getTermIdByName($name) {

    $properties = [];
    $properties['name'] = $name;
    $terms = \Drupal::entityTypeManager()->getStorage(
      'taxonomy_term'
    )->loadByProperties($properties);
    $term = reset($terms);
    return $term->id();
  }

  /**
   * Get Drupal user ID by MO UUID.
   */
  public function getUserByGirUuid($mo_uuid) {
    $user_store = \Drupal::entityTypeManager()->getStorage('user');
    $user_array = $user_store->loadByProperties(['field_uuid' => $mo_uuid]);
    if ($user_array) {
      return reset($user_array)->id();
    }
    else {
      return NULL;
    }
  }

  /**
   * Get JSON from specified GIR API path.
   */
  public function getJsonFromApi($path) {
    $config = new EGIRConfig();
    $mo_url = $config->girUrl;
    $url = $mo_url . $path;
    $auth_token = $this->getOpenIdToken();

    // Authenticate.
    $headers = [
      'Authorization' => 'Bearer ' . $auth_token,
      'Accept' => 'application/json',
    ];

    try {
      $response = $this->httpClient->request(
        'GET',
        $url,
        ['headers' => $headers]
      );
    }
    catch (BadResponseException $e) {
      $response = $e->getResponse();
    }

    if ($response->getStatusCode() === 200) {
      return json_decode($response->getBody(), TRUE);
    }
    else {
      $this->formsLog()->notice('Call to URL' . $url . 'failed:' . $response->getBody());
      return "";
    }
  }

  /**
   * Post data to the relevant path.
   */
  public function postJsonToApi($path, $data) {
    // Full API path.
    $config = new EGIRConfig();
    $url = $config->girUrl . $path;
    // Authentication headers.
    $access_token = $this->getOpenIdToken();
    $headers = [
      'Authorization' => 'Bearer ' . $access_token,
      'Accept' => 'application/json',
      'content-type' => 'application/json',
    ];

    try {
      $response = $this->httpClient->request(
        'POST',
        $url,
        ['body' => $data, 'headers' => $headers]
      );
    }
    catch (BadResponseException $e) {
      $response = $e->getResponse();
    }
    return $response;
  }

  /**
   * Get OpenID authentication token from Keycloak.
   */
  public function getOpenIdToken() {
    if (!$this->useAuth) {
      return '';
    }
    $keycloak_configuration = \Drupal::config('openid_connect.settings.keycloak');

    $keycloak_settings = $keycloak_configuration->get('settings');
    $keycloak_base = $keycloak_settings['keycloak_base'];
    $keycloak_realm = $keycloak_settings['keycloak_realm'];
    $client_id = $keycloak_settings['client_id'];
    $client_secret = $keycloak_settings['client_secret'];

    $token_url = $keycloak_base . '/realms/' . $keycloak_realm . '/protocol/openid-connect/token';

    $payload['grant_type'] = 'client_credentials';
    $payload['client_id'] = $client_id;
    $payload['client_secret'] = $client_secret;

    // $json = json_encode($payload);
    $response = $this->httpClient->request(
      'POST',
      $token_url,
      ['form_params' => $payload]
    );
    $status_code = $response->getStatusCode();

    if ($status_code === 200) {
      $body = json_decode($response->getBody(), TRUE);
      $access_token = $body['access_token'];

      return $access_token;
    }
    else {
      return '';
    }
  }

  /**
   * Get all employments with engagements in the specified organisation unit.
   */
  public function getExternals($org_unit_uuid) {
    $ea_path = "/api/v1/engagement_association?validity=present&org_unit={$org_unit_uuid}";
    $engagement_associations = $this->getJsonFromApi($ea_path);

    if (!$engagement_associations) {
      return [];
    }

    $externals = [];
    foreach ($engagement_associations as $ea) {
      if ($ea['engagement_association_type']['user_key'] === 'External') {
        $externals[] = $ea['engagement']['person'];
      }
    }

    return $externals;
  }

  /**
   * Get the engagement (singular) for the given employee.
   */
  public function getEngagement($employee_uuid) {
    $employee_path = "/service/e/{$employee_uuid}/";
    $details_path = $employee_path . 'details/';
    $details_json = $this->getJsonFromApi($details_path);

    // Get org unit for current engagement from engagement details.
    // Date for retrieving valid details.
    $today = date('Y-m-d');
    if ($details_json['engagement']) {
      $engagement_path = "{$details_path}engagement?at={$today}";
      $engagement_json = $this->getJsonFromApi($engagement_path);
      // @todo Later, handle multiple engagements.
      $engagement = reset($engagement_json);
    }
    return $engagement;
  }

  /**
   * Get the engagement associations for the given engagement.
   */
  public function getEngagementAssociations($engagement_uuid) {
    $today = date('Y-m-d');
    $ea_path = (
      "/api/v1/engagement_association?engagement={$engagement_uuid}&at={$today}"
    );

    $ea_json = $this->getJsonFromApi($ea_path);

    if (!$ea_json) {
      return [];
    }
    else {
      return $ea_json;
    }
  }

  /**
   * Get move payloads for engagement associations from one org unit to another.
   */
  public function getMoveData($engagement_uuid, $old_ou_uuid, $new_ou_uuid) {
    $config = new EGIRConfig();
    $today = date('Y-m-d');
    // Array storing edit data payload.
    $move_data = [];
    $engagement_associations = $this->getEngagementAssociations($engagement_uuid);
    foreach ($engagement_associations as $ea) {
      $ea_type_uuid = $ea['engagement_association_type']['uuid'];
      $ea_org_unit_uuid = $ea['org_unit']['uuid'];
      if ($ea_type_uuid === $config->externalEA && $ea_org_unit_uuid === $old_ou_uuid) {
        $move_data[] = [
          'type' => 'engagement_association',
          'uuid' => $ea['uuid'],
          'data' => [
            'org_unit' => ['uuid' => $new_ou_uuid],
            'validity' => ['from' => $today, 'to' => NULL],
          ],
        ];
      }
    }

    return $move_data;
  }

}
