<?php

namespace Drupal\mbw_shipping\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure mbw_shipping settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mbw_shipping_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['mbw_shipping.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['tax_rate'] = [
      '#type' => 'number',
      '#title' => $this->t('Tax Rate'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('tax_rate'),
      '#description' => 'Tax percent to add to total.',
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
    ];

    $form['tax_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tax Label'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('tax_label'),
      '#description' => 'Descriptor for payment form/receipt.',
    ];

    $form['fuel_surcharge'] = [
      '#type' => 'number',
      '#title' => $this->t('Fuel Surcharge'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('fuel_surcharge'),
      '#description' => 'Current addition to cost in %.',
      '#min' => 0,
      '#max' => 100,
      '#field_suffix' => '%',
    ];

    $form['max_order_weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum Order Weight'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('max_order_weight'),
      '#description' => 'Maximum total order weight in lbs.',
      '#min' => 0,
      '#max' => 10000,
      '#field_suffix' => 'lbs',
    ];

    $form['rates'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Rates'),
    ];

    $form['rates']['tier_1'] = [
      '#type' => 'number',
      '#title' => $this->t('0-10 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_1'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_2'] = [
      '#type' => 'number',
      '#title' => $this->t('11-25 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_2'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_3'] = [
      '#type' => 'number',
      '#title' => $this->t('26-50 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_3'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_4'] = [
      '#type' => 'number',
      '#title' => $this->t('51-75 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_4'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_5'] = [
      '#type' => 'number',
      '#title' => $this->t('76-100 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_5'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_6'] = [
      '#type' => 'number',
      '#title' => $this->t('101-125 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_6'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_7'] = [
      '#type' => 'number',
      '#title' => $this->t('126-150 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_7'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_8'] = [
      '#type' => 'number',
      '#title' => $this->t('151-175 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_8'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_9'] = [
      '#type' => 'number',
      '#title' => $this->t('176-200 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_9'),
      '#description' => '',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_10'] = [
      '#type' => 'number',
      '#title' => $this->t('201-500 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_10'),
      '#description' => 'Rate per lb',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_11'] = [
      '#type' => 'number',
      '#title' => $this->t('501-1000 lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_11'),
      '#description' => 'Rate per lb',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['rates']['tier_12'] = [
      '#type' => 'number',
      '#title' => $this->t('1001+ lbs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('rates_tier_12'),
      '#description' => 'Rate per lb',
      '#min' => 0,
      '#step' => '0.01',
    ];

    $form['account_ids'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Account IDs'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('account_ids'),
      '#description' => 'Comma separated list of valid Account IDs, without spaces',
    ];

    $form['excluded_postal_codes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Excluded Postal Codes'),
      '#default_value' => $this->config('mbw_shipping.settings')->get('excluded_postal_codes'),
      '#description' => 'Comma separated list of postal codes to exclude, without spaces',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
//    if ($form_state->getValue('example') != 'example') {
//      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
//    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('mbw_shipping.settings')
      ->set('tax_rate', $form_state->getValue('tax_rate'))
      ->set('tax_label', $form_state->getValue('tax_label'))
      ->set('fuel_surcharge', $form_state->getValue('fuel_surcharge'))
      ->set('max_order_weight', $form_state->getValue('max_order_weight'))
      ->set('rates_tier_1', $form_state->getValue('tier_1'))
      ->set('rates_tier_2', $form_state->getValue('tier_2'))
      ->set('rates_tier_3', $form_state->getValue('tier_3'))
      ->set('rates_tier_4', $form_state->getValue('tier_4'))
      ->set('rates_tier_5', $form_state->getValue('tier_5'))
      ->set('rates_tier_6', $form_state->getValue('tier_6'))
      ->set('rates_tier_7', $form_state->getValue('tier_7'))
      ->set('rates_tier_8', $form_state->getValue('tier_8'))
      ->set('rates_tier_9', $form_state->getValue('tier_9'))
      ->set('rates_tier_10', $form_state->getValue('tier_10'))
      ->set('rates_tier_11', $form_state->getValue('tier_11'))
      ->set('rates_tier_12', $form_state->getValue('tier_12'))
      ->set('account_ids', $form_state->getValue('account_ids'))
      ->set('excluded_postal_codes', $form_state->getValue('excluded_postal_codes'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
