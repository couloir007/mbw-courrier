<?php

namespace Drupal\mbw_payment\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure mbw_payment settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mbw_payment_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mbw_payment.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['store_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Store ID'),
      '#default_value' => $this->config('mbw_payment.settings')->get('store_id'),
      '#required' => TRUE,
    ];
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $this->config('mbw_payment.settings')->get('api_key'),
      '#required' => TRUE,
    ];
    $form['checkout_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Checkout ID'),
      '#default_value' => $this->config('mbw_payment.settings')->get('checkout_id'),
      '#required' => TRUE,
    ];
    $form['environment'] = [
      '#type' => 'select',
      '#title' => $this->t('Environment'),
      '#default_value' => $this->config('mbw_payment.settings')->get('environment'),
      '#options' => [
        'qa' => 'QA',
        'prod' => 'Production',
      ],
      '#required' => TRUE,
    ];
    $form['request_endpoint_qa'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Endpoint (QA)'),
      '#default_value' => $this->config('mbw_payment.settings')->get('request_endpoint_qa'),
      '#required' => TRUE,
    ];
    $form['request_endpoint_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Request Endpoint (Prod)'),
      '#default_value' => $this->config('mbw_payment.settings')->get('request_endpoint_prod'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    if ($form_state->getValue('store_id') != 'example') {
//      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
//    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mbw_payment.settings')
      ->set('store_id', $form_state->getValue('store_id'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('checkout_id', $form_state->getValue('checkout_id'))
      ->set('environment', $form_state->getValue('environment'))
      ->set('request_endpoint_qa', $form_state->getValue('request_endpoint_qa'))
      ->set('request_endpoint_prod', $form_state->getValue('request_endpoint_prod'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
