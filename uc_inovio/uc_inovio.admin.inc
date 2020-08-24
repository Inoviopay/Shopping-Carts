<?php

/**
 * @file
 * Show merchant setting form in admin panel
 */

function uc_inovio_admin_settings_form($form, &$form_state) {
  
    $form['uc_inovio_checkout_label'] = [
        '#type' => 'textfield',
        '#title' => t('Checkout Title'),
        '#description' => t('Customize the label of the final checkout button when the customer is about to pay.'),
        '#default_value' => variable_get('uc_inovio_checkout_label')
    ];
    $form['uc_inovio_api_endpoint'] = [
      '#type' => 'textfield',
      '#title' => t('API End Point'),
      '#description' => t('Inovio Gateway API URL.'),
      '#default' => t('Pay with your credit card via Inovio Billing Direct API.'),
      '#required' => TRUE,
      '#default_value' => variable_get('uc_inovio_api_endpoint')
    ];

    $form['uc_inovio_site_id'] = [
      '#type' => 'textfield',
      '#title' => t('Site Id'),
      '#description' => t('Enter Inovio Merchant Site Id'),
      '#required' => TRUE,
      '#default_value' => variable_get('uc_inovio_site_id')
    ];
    $form['uc_inovio_product_id'] = [
      '#type' => 'textfield',
      '#title' => t('Set Product Id for all products'),
      '#description' => t('Set Product Id for all products'),
      '#required' => TRUE,
      '#default_value' => variable_get('uc_inovio_product_id')
    ];

    $form['uc_inovio_req_username'] = [
      '#type' => 'textfield',
      '#title' => t('API Username'),
      '#description' => t('Enter Inovio Merchant Username'),
      '#required' => TRUE,
      '#default_value' => variable_get('uc_inovio_req_username')
    ];

    $form['uc_inovio_req_password'] = [
      '#type' => 'textfield',
      '#title' => t('API Password'),
      '#description' => t('Enter Inovio Merchant Password'),
      '#required' => TRUE,
      '#default_value' => variable_get('uc_inovio_req_password')
    ];

    $form['uc_inovio_product_quantity_restriction'] = [
      '#type' => 'textfield',
      '#title' => t('Maximum quantity to purchase for single product'),
      '#description' => t('API restriction for quantity to purchase any single product.'),
      '#default_value' => !empty(variable_get('uc_inovio_product_quantity_restriction'))
                           ? variable_get('uc_inovio_product_quantity_restriction') :99
    ];
    
    $form['advancetab'] = [
      '#title' => t('Advanced API Parameters'),
      '#type' => 'fieldset',
      '#description' => 'Advance API Key and Value',
      '#open' => TRUE,
    ];
    $form['advancetab']['uc_inovio_apikey1'] = [
      '#title' => t('API Key 1'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey1','')
    ];
    $form['advancetab']['uc_inovio_apivalue1'] = [
      '#title' => t('API Value 1'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue1','')
    ];

    $form['advancetab']['uc_inovio_apikey2'] = [
      '#title' => t('API Key 2'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey2','')
    ];

    $form['advancetab']['uc_inovio_apivalue2'] = [
      '#title' => t('API Value 2'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue2','')
    ];

    $form['advancetab']['uc_inovio_apikey3'] = [
      '#title' => t('API Key 3'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey3','')
    ];

    $form['advancetab']['uc_inovio_apivalue3'] = [
      '#title' => t('API Value 3'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue4','')
    ];

    $form['advancetab']['uc_inovio_apikey4'] = [
      '#title' => t('API Key 4'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey4','')
    ];

    $form['advancetab']['uc_inovio_apivalue4'] = [
      '#title' => t('API Value 4'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue4','')
    ];

    $form['advancetab']['uc_inovio_apikey5'] = [
      '#title' => t('API Key 5'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey5','')
    ];

    $form['advancetab']['uc_inovio_apivalue5'] = [
      '#title' => t('API Value 5'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue5','')
    ];

    $form['advancetab']['uc_inovio_apikey6'] = [
      '#title' => t('API Key 6'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey6','')
    ];

    $form['advancetab']['uc_inovio_apivalue6'] = [
      '#title' => t('API Value 6'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue6','')
    ];

    $form['advancetab']['uc_inovio_apikey7'] = [
      '#title' => t('API Key 7'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey7','')
    ];

    $form['advancetab']['uc_inovio_apivalue7'] = [
      '#title' => t('API Value 7'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue7','')
    ];

    $form['advancetab']['uc_inovio_apikey8'] = [
      '#title' => t('API Key 8'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey8','')
    ];

    $form['advancetab']['uc_inovio_apivalue8'] = [
      '#title' => t('API Value 8'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue8','')
    ];

    $form['advancetab']['uc_inovio_apikey9'] = [
      '#title' => t('API Key 9'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey9','')
    ];

    $form['advancetab']['uc_inovio_apivalue9'] = [
      '#title' => t('API Value 9'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue9','')
    ];

    $form['advancetab']['uc_inovio_apikey10'] = [
      '#title' => t('API Key 10'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apikey10','')
    ];

    $form['advancetab']['uc_inovio_apivalue10'] = [
      '#title' => t('API Value 10'),
      '#type' => 'textfield',
      '#default_value' => variable_get('uc_inovio_apivalue10','')
    ];

    $form['uc_inovio_debug'] = [
      '#type' => 'checkbox',
      '#title' => t('Debug'),
      '#description' => t('Log debug payment information to dblog when card is "charged" by this gateway.'),
      '#default_value' => variable_get('uc_inovio_debug')
    ];
    
    return $form;
}