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
  public function alterForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    $values = $webform_submission->getData();
    $utils = new GIRUtils();

    if (!array_key_exists('external_employee', $values)) {
      return;
    }
    $employee_id = $values['external_employee'];

    $uuid = $utils->getUserData($employee_id, 'field_uuid');

    if (!$uuid) {
      return;
    }

    if ($form['#webform_id'] === 'move_external') {
      // Special handling of this form.
      $engagement = $utils->getEngagement($uuid);
      $org_units = [];
      $org_unit_options = [];

      if ($engagement) {
        $engagement_associations = $utils->getEngagementAssociations($engagement['uuid']);

        foreach ($engagement_associations as $ea) {
          if ($ea['engagement_association_type']['user_key'] === 'External') {
            // This is an org unit where the external is working.
            $org_unit_name = $ea['org_unit']['name'];
            $organizational_unit_id = $utils->getTermIdByName($org_unit_name);
            $org_units[] = $organizational_unit_id;
            $org_unit_options[$organizational_unit_id] = $org_unit_name;
          }
        }
      }
      $form['elements']['move_external']['old_organizational_unit']['#options'] = $org_unit_options;
      // If user has no engagement associations of type "External", we disable
      // the field -there will be nothing to move.
      if (!$org_unit_options) {
        $form['elements']['move_external']['old_organizational_unit']['#disabled'] = TRUE;
      }
    }

  }

  /**
   * Collect data for proper display in form.
   *
   * This function will be called when user has just entered the employee's
   *  initials, before any changes or editing are made.
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    $values = $webform_submission->getData();
    $config = new EGIRConfig();
    $utils = new GIRUtils();

    $employee_id = $values['external_employee'];

    $uuid = $utils->getUserData($employee_id, 'field_uuid');

    if (!$uuid) {
      // Not linked to any employee in GIR.
      return;
    }
    if (!empty($values['first_name']) or !empty($values['last_name'])) {
      // This form has already been filled, don't overwrite submission.
      return;
    }

    // Now get all the right data from MO.
    $employee_path = '/service/e/' . $uuid . '/';

    $employee_json = $utils->getJsonFromApi($employee_path);

    if (!$employee_json) {
      return;
    }

    // Date for retrieving valid details.
    $today = date('Y-m-d');
    // Get details link and extract addresses etc.
    $details_path = $employee_path . 'details/';

    $details_json = $utils->getJsonFromApi($details_path);

    if (!$details_json) {
      return;
    }

    // Array for extra UUID information.
    $extra_uuids = [];

    // Get email and phone from address details.
    $email_address = '';
    $telephone_number = '';
    if ($details_json['address']) {
      $address_path = "{$details_path}address?at={$today}";
      $address_json = $utils->getJsonFromApi($address_path);

      foreach ($address_json as $address) {
        if ($address['address_type']['name'] === 'Mobile') {
          // $mobile_number = $address['value'];
        }
        elseif ($address['address_type']['uuid'] === $config->extPhoneType) {
          $telephone_number = $address['value'];
          $extra_uuids['phone_addr_uuid'] = $address['uuid'];
        }
        elseif ($address['address_type']['uuid'] === $config->extEmailType) {
          $email_address = $address['value'];
          $extra_uuids['email_addr_uuid'] = $address['uuid'];
        }
      }
    }

    $cost_center_id = '';
    $organizational_unit_id = '';
    $consultant_type_id = '';
    $engagement = $utils->getEngagement($uuid);
    $org_units = [];

    if ($engagement) {
      $extra_uuids['engagement_uuid'] = $engagement['uuid'];

      $consultancy_name = $engagement['org_unit']['name'];
      $consultancy_id = $utils->getTermIdByName($consultancy_name);
      $consultant_type_name = $engagement['engagement_type']['name'];
      $consultant_type_id = $utils->getTermIdByName($consultant_type_name);

      // Now for the engagement associations.
      $engagement_associations = $utils->getEngagementAssociations($engagement['uuid']);

      foreach ($engagement_associations as $ea) {
        if ($ea['engagement_association_type']['user_key'] === 'Legal Company') {
          // This is the placement in the legal organization.
        }
        elseif (
          $ea['engagement_association_type']['user_key'] === 'Cost Center'
        ) {
          // This is the cost center.
          $cost_center_name = $ea['org_unit']['name'];
          $cost_center_id = $utils->getTermIdByName($cost_center_name);
          $extra_uuids['cost_center_ea_uuid'] = $ea['uuid'];
        }
        elseif (
          $ea['engagement_association_type']['user_key'] === 'External'
        ) {
          // This is an org unit where the external is working.
          $org_unit_name = $ea['org_unit']['name'];
          $organizational_unit_id = $utils->getTermIdByName($org_unit_name);
          $org_units[] = $organizational_unit_id;
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
