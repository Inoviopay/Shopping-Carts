<?php

/**
 * @file
 * Inovio admin setting page
 */
namespace Drupal\uc_inovio\Plugin\Ubercart\PaymentMethod;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\uc_inovio\CreditCardPaymentMethodBase;
use Drupal\uc_order\OrderInterface;

/**
 * Defines the Inovio payment method.
 *
 * @UbercartPaymentMethod(
 *   id = "inovio",
 *   name = @Translation("Inovio"),
 * )
 */
class Inovio extends CreditCardPaymentMethodBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration();
  }

  /**
   * Build inovio merchant configuration form. 
   * 
   * @param array $form
   *   Configuration form setting
   * 
   * @param FormStateInterface $form_state
   *   Get form related information
   * 
   * @return \Drupal\uc_inovio\Plugin\Ubercart\PaymentMethod\type
   * 
   * @return array
   *   $form setting array
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['api_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('API End Point'),
      '#description' => t('Inovio Gateway API URL.'),
      '#default' => t('Pay with your credit card via Inovio Billing Direct API.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['api_endpoint'],
    );

    $form['site_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Site Id'),
      '#description' => t('Enter Inovio Merchant Site Id'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['site_id'],
    );

    $form['req_username'] = array(
      '#type' => 'textfield',
      '#title' => t('API Username'),
      '#description' => t('Enter Inovio Merchant Username'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['req_username'],
    );

    $form['req_password'] = array(
      '#type' => 'textfield',
      '#title' => t('API Password'),
      '#description' => t('Enter Inovio Merchant Password'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['req_password'],
    );
    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#description' => t('This controls the description which the user sees during checkout.'),
      '#default' => t('Pay with your credit card via Inovio Billing Direct API.'),
      '#default_value' => $this->configuration['description'],
    );
    $form['inovio_product_quantity_restriction'] = array(
      '#type' => 'textfield',
      '#title' => t('Maximum qauntity to purchase for single product'),
      '#description' => t('API restriction for qauntity to purchase any single product.'),
      '#default_value' => !empty($this->configuration['inovio_product_quantity_restriction'])
                           ? $this->configuration['inovio_product_quantity_restriction'] :99,
    );
    $form['inovio_product_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Set Product Id for all products'),
      '#description' => t('Set Product Id for all products'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['inovio_product_id'],
    );

    $form['advancetab'] = array(
      '#title' => t('Advanced API Parameters'),
      '#type' => 'details',
      '#description' => 'Advance API Key and Value',
      '#open' => TRUE,
    );
    $form['advancetab']['APIkey1'] = array(
      '#title' => t('API Key 1'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey1'],
    );
    $form['advancetab']['APIvalue1'] = array(
      '#title' => t('API Value 1'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue1'],
    );
    $form['advancetab']['APIkey2'] = array(
      '#title' => t('API Key 2'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey2'],
    );
    $form['advancetab']['APIvalue2'] = array(
      '#title' => t('API Value 2'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue2'],
    );
    $form['advancetab']['APIkey3'] = array(
      '#title' => t('API Key 3'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey3'],
    );
    $form['advancetab']['APIvalue3'] = array(
      '#title' => t('API Value 3'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue4'],
    );
    $form['advancetab']['APIkey4'] = array(
      '#title' => t('API Key 4'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey4'],
    );
    $form['advancetab']['APIvalue4'] = array(
      '#title' => t('API Value 4'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue4'],
    );
    $form['advancetab']['APIkey5'] = array(
      '#title' => t('API Key 5'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey5'],
    );
    $form['advancetab']['APIvalue5'] = array(
      '#title' => t('API Value 5'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue5'],
    );
    $form['advancetab']['APIkey6'] = array(
      '#title' => t('API Key 6'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey6'],
    );
    $form['advancetab']['APIvalue6'] = array(
      '#title' => t('API Value 6'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue6'],
    );
    $form['advancetab']['APIkey7'] = array(
      '#title' => t('API Key 7'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey7'],
    );
    $form['advancetab']['APIvalue7'] = array(
      '#title' => t('API Value 7'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue7'],
    );
    $form['advancetab']['APIkey8'] = array(
      '#title' => t('API Key 8'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey8'],
    );
    $form['advancetab']['APIvalue8'] = array(
      '#title' => t('API Value 8'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue8'],
    );
    $form['advancetab']['APIkey9'] = array(
      '#title' => t('API Key 9'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey9'],
    );
    $form['advancetab']['APIvalue9'] = array(
      '#title' => t('API Value 9'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue9'],
    );

    $form['advancetab']['APIkey10'] = array(
      '#title' => t('API Key 10'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIkey10'],
    );
    $form['advancetab']['APIvalue10'] = array(
      '#title' => t('API Value 10'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['advancetab']['APIvalue10'],
    );
    $form['debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#description' => $this->t('Log debug payment information to dblog when card is "charged" by this gateway.'),
      '#default_value' => $this->configuration['debug'],
    );

    return $form;
  }

  /**
   * Save inovio merhchant information into database.
   * 
   * @param array $form
   *   Configuration form setting
   *
   *    * @param FormStateInterface $form_state
   *   Get form related information
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {

    $this->configuration['api_endpoint'] = $form_state->getValue('api_endpoint');
    $this->configuration['site_id'] = $form_state->getValue('site_id');
    $this->configuration['req_username'] = $form_state->getValue('req_username');
    $this->configuration['req_password'] = $form_state->getValue('req_password');
    $this->configuration['inovio_product_id'] = $form_state->getValue('inovio_product_id');
    $this->configuration['description'] = $form_state->getValue('description');
    $this->configuration['inovio_product_quantity_restriction'] = $form_state->getValue('inovio_product_quantity_restriction');

    $this->configuration['debug'] = $form_state->getValue('debug');
    $this->configuration['advancetab']['APIkey1'] = $form_state->getValue(['settings', 'advancetab', 'APIkey1']);
    $this->configuration['advancetab']['APIvalue1'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue1']);
    $this->configuration['advancetab']['APIkey2'] = $form_state->getValue(['settings', 'advancetab', 'APIkey2']);
    $this->configuration['advancetab']['APIvalue1'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue2']);
    $this->configuration['advancetab']['APIkey3'] = $form_state->getValue(['settings', 'advancetab', 'APIkey3']);
    $this->configuration['advancetab']['APIvalue3'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue3']);
    $this->configuration['advancetab']['APIkey4'] = $form_state->getValue(['settings', 'advancetab', 'APIkey4']);
    $this->configuration['advancetab']['APIvalue4'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue4']);
    $this->configuration['advancetab']['APIkey5'] = $form_state->getValue(['settings', 'advancetab', 'APIkey5']);
    $this->configuration['advancetab']['APIvalue5'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue5']);
    $this->configuration['advancetab']['APIkey6'] = $form_state->getValue(['settings', 'advancetab', 'APIkey6']);
    $this->configuration['advancetab']['APIvalue6'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue6']);
    $this->configuration['advancetab']['APIkey7'] = $form_state->getValue(['settings', 'advancetab', 'APIkey7']);
    $this->configuration['advancetab']['APIvalue7'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue7']);
    $this->configuration['advancetab']['APIkey8'] = $form_state->getValue(['settings', 'advancetab', 'APIkey8']);
    $this->configuration['advancetab']['APIvalue8'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue8']);
    $this->configuration['advancetab']['APIkey9'] = $form_state->getValue(['settings', 'advancetab', 'APIkey9']);
    $this->configuration['advancetab']['APIvalue9'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue9']);
    $this->configuration['advancetab']['APIkey10'] = $form_state->getValue(['settings', 'advancetab', 'APIkey10']);
    $this->configuration['advancetab']['APIvalue10'] = $form_state->getValue(['settings', 'advancetab', 'APIvalue10']);
  }

}
