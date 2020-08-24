<?php

/*
 * 2007-2015 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author PrestaShop SA <contact@prestashop.com>
 *  @copyright  2007-2015 PrestaShop SA
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

/**
 * @since 1.5.0
 */
require_once(_PS_ROOT_DIR_ . '/modules/ps_inoviopayment/component/inoviovalidate.php');

class Ps_InoviopaymentValidationModuleFrontController extends ModuleFrontController
{

  /**
   * For single product's qunantity should not be greater than 99
   */
    public function restrictQuantity()
    {

        $cart = $this->context->cart;
        $returnstate = true;
        foreach ($cart->getProducts() as $cart_item) {
            if ($cart_item['cart_quantity'] > Configuration::get('INOVIO_PRODUCT_QAUNTITY_RESTRICTION')) {
                $returnstate = false;
            }
        }
        return $returnstate;
    }

  /**
   * @see FrontController::postProcess()
   */
    public function postProcess()
    {
        $inovioValidated = new InovioValidation();

        if ($inovioValidated->merchantAuthenticate() === false) {
            Tools::redirect("index.php?controller=order&step=3&inovioError=" . rand());
        } else {
            if ($this->restrictQuantity() == false) {
                Tools::redirect("index.php?controller=order&step=3&inovioerrors=" . rand());
            }
          // Auth and capture parameters
            $finalParams = array_merge(
                $inovioValidated->setGatewayInformation(),
                $this->prepareRequestData(),
                $this->getProductData()
            ) + $this->getAdvaceparam();

            $responseAuthCapture = $inovioValidated->setApiMethodAndgetResponse($finalParams, 'authAndCapture');

            if (isset($responseAuthCapture->TRANS_STATUS_NAME) && 'APPROVED' == $responseAuthCapture->TRANS_STATUS_NAME) {
                if (Configuration::get('INOVIO_LOG') == 1) {
                    PrestaShopLoggerCore::addLog("Successfull auth and capture Params");
                    PrestaShopLoggerCore::addLog(print_r($responseAuthCapture, true));
                }
                  $this->context->smarty->assign([
                'params' => $_REQUEST,
                  ]);
                  $cart = $this->context->cart;
                  $customer = new Customer($cart->id_customer);
                  $currency = $this->context->currency;
                  $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
                if (!Validate::isLoadedObject($customer)) {
                      Tools::redirect('index.php?controller=order&step=1');
                }
                    $currency = $this->context->currency;
                    $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
                    $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, null, null, (int) $currency->id, false, $customer->secure_key);

                    // Insert into custom inovio table
                    $insertData = array(
                      'transaction_id' => $responseAuthCapture->TRANS_ID,
                      'po_id' => $responseAuthCapture->PO_ID,
                      'ps_order_id' => $cart->id,
                  );
                  Db::getInstance()->insert('inovio_payment_details', $insertData);
                  Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
            }
          // Check card length
            elseif (Configuration::get('INOVIO_LOG') == 1) {
                PrestaShopLogger::addLog('Auth and capture response for credit card number');
                PrestaShopLogger::addLog(print_r($responseAuthCapture, true));
            }
            Tools::redirect("index.php?controller=order&step=3&inovioError=" . rand());
        }
    }

  /**
   * Get Iso code of state
   *
   * @param int $state_id
   * @return string
   */
    public function getisoState($state_id)
    {
        $qry = 'SELECT `iso_code`
    FROM `' . _DB_PREFIX_ . 'state`
    WHERE id_state = ' . $state_id;
        return Db::getInstance()->getValue($qry);
    }

  /**
   * Use to set request data to call Inovio APImethod
   * @param $payment
   * @return array $request
   */
    public function prepareRequestData()
    {

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $currency = new CurrencyCore($this->context->cart->id_currency); // state related information
        $address = new Address($cart->id_address_delivery);
        $invoice = new Address($cart->id_address_invoice);

   

        $getISO = StateCore::getStates($this->context->language->iso_code);
        $request = [
        "bill_addr" => $address->address1 . ' ' . $address->address2,
        "pmt_numb" => Tools::getValue('inovio-card-number'),
        "pmt_key" => Tools::getValue('inovio-card-cvc'),
        //"xtl_ip" => '127.0.0', // For Local environment
        "xtl_ip" => Tools::getRemoteAddr(),
        "cust_fname" => $address->firstname,
        "pmt_expiry" => Tools::getValue('card-expiry-month') . Tools::getValue('card-expiry-year'),
        "cust_email" => $customer->email,
        "bill_addr_zip" => $address->postcode,
        "bill_addr_city" => $address->city,
        "bill_addr_state" => $this->getisoState($address->id_state),
        "request_currency" => $currency->iso_code,
        "bill_addr_country" => Country::getIsoById($this->context->country->id),

         "ship_addr_country" => Country::getIsoById($this->context->country->id) ,
        "ship_addr_city" => $invoice->city,
        "ship_addr_state" => $this->getisoState($invoice->id_state),
        "ship_addr_zip" => $invoice->postcode,
        "ship_addr" => $invoice->address1 . ' ' . $invoice->address2,

        ];

        return $request;
    }

    public function hookDisplayTop()
    {
        $controller = $this->context->controller;

        if ($controller->php_self != 'order' && $controller->php_self != 'order-opc') {
            return false;
        }
      /*
      You can do custom logic here if you want to display message only
      on some conditions or only on specific step of the checkout
       */
        $controller->errors[] = $this->l('Some message');

        return false;
    }

  /**
   * Use to get product Ids, price and quantity
   *
   * @return array()
   */
    public function getProductData()
    {
       $cart = $this->context->cart;
        $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
        $final_array = [];

          $final_array['li_prod_id_1'] = Configuration::get('INOVIO_PRODUCT_ID');
          $final_array['li_count_1'] = 1;
          $final_array['li_value_1'] = $total;

          return $final_array;

    }

  /**
   * Use to get advance fields
   *
   * @param null
   * @return array $advanceParams
   */
    public function getAdvaceparam()
    {
        return $advanceParams = [
        Configuration::get('INOVIO_ADVANCE_KEY_1') => Configuration::get('INOVIO_ADVANCE_VALUE_1'),
        Configuration::get('INOVIO_ADVANCE_KEY_2') => Configuration::get('INOVIO_ADVANCE_VALUE_2'),
        Configuration::get('INOVIO_ADVANCE_KEY_3') => Configuration::get('INOVIO_ADVANCE_VALUE_3'),
        Configuration::get('INOVIO_ADVANCE_KEY_4') => Configuration::get('INOVIO_ADVANCE_VALUE_4'),
        Configuration::get('INOVIO_ADVANCE_KEY_5') => Configuration::get('INOVIO_ADVANCE_VALUE_5'),
        Configuration::get('INOVIO_ADVANCE_KEY_6') => Configuration::get('INOVIO_ADVANCE_VALUE_6'),
        Configuration::get('INOVIO_ADVANCE_KEY_7') => Configuration::get('INOVIO_ADVANCE_VALUE_7'),
        Configuration::get('INOVIO_ADVANCE_KEY_8') => Configuration::get('INOVIO_ADVANCE_VALUE_8'),
        Configuration::get('INOVIO_ADVANCE_KEY_9') => Configuration::get('INOVIO_ADVANCE_VALUE_9'),
        Configuration::get('INOVIO_ADVANCE_KEY_10') => Configuration::get('INOVIO_ADVANCE_VALUE_10')
        ];
    }
}
