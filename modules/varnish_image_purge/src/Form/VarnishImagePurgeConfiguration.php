<?php

/**
 * @file
 */

namespace Drupal\varnish_image_purge\Form;

/**
 * @file
 * Contains \Drupal\varnish_image_purge\Form\VarnishImagePurgeConfiguration.
 */

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;


/**
 * Configure site information settings for this site.
 */
class VarnishImagePurgeConfiguration extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'varnish_image_purge_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['varnish_image_purge.configuration'];
  }


  /**
   *
   */
  public function loadNodeTypes() {
    $types = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    return $types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('varnish_image_purge.configuration');
    $types = $this->loadNodeTypes();

    if (empty($types)) {
      drupal_set_message($this->t('No content types were found'));
      return NULL;
    }

    $options = [];
    foreach ($types as $type) {
      $label = $type->label();
      $name = $type->id();
      $options["$name"] = $label;
    }

    $form['intro'] = [
      '#markup' => t('Configure which enity types that Varnish image purge should be used for, if none selected, all enitiy types will be used.'),
    ];

    $form['entity_types'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => $config->get('entity_types'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // @todo
    // Validations.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('varnish_image_purge.configuration');
    $config->set('entity_types', $form_state->getValue('entity_types'));
    $config->save();

    return parent::submitForm($form, $form_state);
  }

}
