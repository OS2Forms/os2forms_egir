<?php

namespace Drupal\os2forms_egir\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\os2forms_egir\EGIRConfig;
use Drupal\os2forms_egir\GIRUtils;

/**
 * Webform submission handler for loading employees.
 *
 * @WebformHandler(
 *   id = "employee",
 *   label = @Translation("Load Employee"),
 *   category = @Translation("Load GIR entity"),
 *   description = @Translation("Load GIR data into form fields."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class EmployeeWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */

  /**
   * Collect data for proper display in form.
   *
   * This function will be called when user has just entered the employee's
   *  initials, before any changes or editing are made.
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    \Drupal::logger('os2forms_egir')->notice(
      'FORM: ' . json_encode($form)
    );
    \Drupal::logger('os2forms_egir')->notice(
      'FORM STATE: ' . json_encode($form_state)
    );
    \Drupal::logger('os2forms_egir')->notice(
      'SUBMISSION: ' . json_encode($webform_submission)
    );
    $values = $webform_submission->getData();
    $config = new EGIRConfig();
    $employee_id = $values['external_employee'];

    $uuid = GIRUtils::getUserData($employee_id, 'field_uuid');

    if (!$uuid) {
      return;
    }

    // Now get all the right data from MO.
    $employee_path = '/service/e/' . $uuid . '/';

    $employee_json = GIRUtils::getJsonFromApi($employee_path);

    if ($employee_json == "") {
      return;
    }

    // Date for retrieving valid details.
    $today = date("Y-m-d");
    // Get details link and extract addresses etc.
    $details_path = $employee_path . 'details/';

    $details_json = GIRUtils::getJsonFromApi($details_path);

    if ($details_json == "") {
      return;
    }

    // Array for extra UUID information.
    $extra_uuids = [];

    // Get email and phone from address details.
    $email_address = "";
    $email_addr_uuid = "";
    // $mobile_number = "";
    $telephone_number = "";
    if ($details_json['address']) {
      $address_path = "{$details_path}address?at={$today}";
      $address_json = GIRUtils::getJsonFromApi($address_path);

      foreach ($address_json as $address) {
        if ($address['address_type']['name'] == 'Mobile') {
          // $mobile_number = $address['value'];
        }
        elseif ($address['address_type']['uuid'] == $config->extPhoneType) {
          $telephone_number = $address['value'];
          $extra_uuids['phone_addr_uuid'] = $address['uuid'];
        }
        elseif ($address['address_type']['uuid'] == $config->extEmailType) {
          $email_address = $address['value'];
          $extra_uuids['email_addr_uuid'] = $address['uuid'];
        }

      }
    }

    $cost_center_id = "";
    $organizational_unit_id = "";
    $consultant_type_id = "";
    // $start_date = "";
    // $end_date = "";
    $engagement = [];
    $engagement_uuid = "";
    $org_units = [];

    // Get org unit for current engagement from engagement details.
    if ($details_json['engagement']) {
      $engagement_path = "{$details_path}engagement?at={$today}";
      $engagement_json = GIRUtils::getJsonFromApi($engagement_path);
      // @todo Later, handle multiple engagements.
      $engagement = reset($engagement_json);
    }

    if ($engagement) {
      $consultancy_name = $engagement['org_unit']['name'];

      $consultancy_id = GIRUtils::getTermIdByName($consultancy_name);

      $consultant_type_name = $engagement['engagement_type']['name'];
      $consultant_type_id = GIRUtils::getTermIdByName($consultant_type_name);

      // $start_date = $engagement['validity']['from'];
      // $end_date = $engagement['validity']['to'];
      // Now for the engagement associations.
      // This only makes sense if there is an engagement.
      $engagement_uuid = $engagement['uuid'];
      $extra_uuids['engagement_uuid'] = $engagement_uuid;
      $ea_path = (
        '/api/v1/engagement_association' . '?engagement=' . $engagement_uuid .
        '&at=' . $today
      );
      $ea_json = GIRUtils::getJsonFromApi($ea_path);

      if ($ea_json) {
        // There might not be any.
        foreach ($ea_json as $ea) {
          if ($ea['engagement_association_type']['user_key'] == "Legal Company") {
            // This is the placement in the legal organization.
          }
          elseif (
            $ea['engagement_association_type']['user_key'] == "Cost Center"
          ) {
            // This is the cost center.
            $cost_center_name = $ea['org_unit']['name'];
            $cost_center_id = GIRUtils::getTermIdByName($cost_center_name);
            $extra_uuids['cost_center_ea_uuid'] = $ea['uuid'];
          }
          elseif (
            $ea['engagement_association_type']['user_key'] == "External"
          ) {
            // This is the org unit where the external is working.
            $org_unit_name = $ea['org_unit']['name'];
            $organizational_unit_id = GIRUtils::getTermIdByName($org_unit_name);
            $org_units[] = $organizational_unit_id;
          }
        }
      }
    }

    // Fill out the form.
    $webform_submission->setElementData('first_name', $employee_json['givenname']);
    $webform_submission->setElementData('last_name', $employee_json['surname']);
    $webform_submission->setElementData('telephone_number', $telephone_number);
    $webform_submission->setElementData('email_address', $email_address);
    if ($engagement) {
      $webform_submission->setElementData('consultancy', $consultancy_id);
    }
    if ($cost_center_id) {
      $webform_submission->setElementData('cost_center', $cost_center_id);
    }
    if ($consultant_type_id) {
      $webform_submission->setElementData('consultant_type', $consultant_type_id);
    }
    if ($org_units) {
      $webform_submission->setElementData('organizational_unit', $org_units);
    }
    $webform_submission->setElementData('extra_uuids', json_encode($extra_uuids));
  }

}
