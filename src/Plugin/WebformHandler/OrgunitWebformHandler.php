<?php

namespace Drupal\os2forms_egir\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\os2forms_egir\GIRUtils;

/**
 * Webform submission handler for loading org units.
 *
 * @WebformHandler(
 *   id = "org_unit",
 *   label = @Translation("Load Organization Unit"),
 *   category = @Translation("Load GIR entity"),
 *   description = @Translation("Load GIR data into form fields."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *   tokens = TRUE,
 * )
 */
class OrgunitWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function alterForm(
    array &$form,
    FormStateInterface $form_state,
    WebformSubmissionInterface $webform_submission
  ) {

    $values = $webform_submission->getData();

    if (!array_key_exists('organizational_unit', $values)) {
      return;
    }
    $org_unit_id = $values['organizational_unit'];

    $uuid = GIRUtils::getTermData($org_unit_id, 'field_uuid');

    if (!$uuid) {
      // No GIR UUID available.
      return;
    }

    if ($form['#webform_id'] == 'move_many_externals') {
      $externals = GIRUtils::getExternals($uuid);
      GIRUtils::formsLog()->notice('Externals: ' . json_encode($externals));

      if ($externals) {
        $external_options = [];
        foreach ($externals as $username => $e) {
          // Get ID from username.
          $user = user_load_by_name($username);
          $external_options[$user->id()] = $username;
        }
        $form['elements']['move_externals']['origin_and_destination_units']['externals']['#options'] = $external_options;
      }
    }
  }

  /**
   * Function to be called after submitting the webform.
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $values = $webform_submission->getData();

    $org_unit_id = $values['organizational_unit'];
    $org_unit_uuid = GIRUtils::getTermData($org_unit_id, 'field_uuid');

    if (!$org_unit_uuid) {
      GIRUtils::formsLog()->notice("No UUID found for org unit: $org_unit_id");
      return;
    }

    if (!empty($values['name'])) {
      // Already filled, don't overwrite existing changes.
      return;
    }

    // Now get all the right data from MO.
    $ou_path = '/service/ou/' . $org_unit_uuid . '/';
    $ou_json = GIRUtils::getJsonFromApi($ou_path);

    // Fill out the form.
    $webform_submission->setElementData('name', $ou_json['name']);

    // Parse owner.
    $owner_path = $ou_path . 'details/owner';
    $owner_json = GIRUtils::getJsonFromApi($owner_path);

    // There is only one potential owner, and details/owner returns a list.
    $owner_data = reset($owner_json);
    if ($owner_data) {
      $owner_uuid = $owner_data["owner"]["uuid"];
      $owner_id = GIRUtils::getUserByGirUuid($owner_uuid);
      // Insert owner into form.
      $webform_submission->setElementData('owner', $owner_id);
    }

    if ($form['#webform_id'] == 'move_many_externals') {
      $webform_submission->setElementData('origin_unit', $ou_json['name']);
    }
  }

}
