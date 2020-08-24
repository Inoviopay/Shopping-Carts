<?php

/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 *  @author    Tanseer Ahmed
 *  @copyright 2015-2020 Chetu India
 *  @license   LICENSE.txt
 */
class InovioValidation
{

    public function __construct()
    {
    }

  /**
   * Set method for coder SDK and get response
   *
   * @param $methodName, $requestParams
   * @return string $parseResult
   */
    public function setApiMethodAndgetResponse($requestParams = array(), $methodName = null)
    {
        require_once(_PS_ROOT_DIR_ . '/modules/ps_inoviopayment/inoviocore-sdk/InovioConnection.php');
        require_once(_PS_ROOT_DIR_ . '/modules/ps_inoviopayment/inoviocore-sdk/InovioProcessor.php');
        require_once(_PS_ROOT_DIR_ . '/modules/ps_inoviopayment/inoviocore-sdk/InovioServiceConfig.php');

        $connection = new InovioConnection();
        $inovioConfig = new InovioServiceConfig($requestParams, $connection);
        $processor = new InovioProcessor($inovioConfig);
        $response = $processor->setMethodName($methodName)->getResponse();
        return $parseResult = json_decode($response);
    }

  /**
   * Use to validate mercahnt information
   *
   * @param null
   * @return true ? false
   */
    public function merchantAuthenticate()
    {
        $parseResult = $this->setApiMethodAndgetResponse($this->setGatewayInformation(), 'authenticate');
        if ($parseResult->SERVICE_RESPONSE != 100) {
            if (Configuration::get('INOVIO_PRODUCT_ID')==1) {
                PrestaShopLogger::addLog('Merchant Authentication Failed');
                PrestaShopLogger::addLog(print_r($parseResult, true));
            }

            return false;
        } else {
            return true;
        }
    }

  /**
   * Use to set Inovio initial requeired parameters
   *
   * @param null
   * @return $requestParams
   */
    public function setGatewayInformation()
    {
        return $requestParams = [
        'end_point' => Configuration::get('INOVIO_API_ENDPOINT'),
        'site_id' => Configuration::get('INOVIO_SITE_ID'),
        'req_username' => Configuration::get('INOVIO_API_USERNAME'),
        'req_password' => Configuration::get('INOVIO_API_PASSWORD'),
        'request_response_format' => 'json'
        ];
    }

  /**
   * use to check credit card expiration date
   */
    public function validateExpirydate($card_date = null)
    {
        $today = date('Ym');
        $today_dt = new DateTime($today);
        $expire_dt = new DateTime($card_date);

        if ($expire_dt < $today_dt) {
            return false;
        } else {
            return true;
        }
    }
}
