<?php
/**
 * Class Inovio_Payment_Model_Pay
 * @package payment extension
 * @version Author: Chetu Team
 */

class Inovio_Payment_Model_Pay extends Mage_Payment_Model_Method_Cc {
    /**
     * Here are examples of flags that will determine functionality availability
     * of this module to be used by frontend and backend.
     *
     * @see all flags and their defaults in Mage_Payment_Model_Method_Abstract
     *
     */

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Can refund online?
     */
    protected $_canRefund = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund partial invoice online?
     */
    protected $_canRefundInvoicePartial = false;

    /**
     * Can void online?
     */
    protected $_canVoid = false;

    /**
     * Can use for muliple shipping online?
     */
    protected $_canUseForMultishipping = false;

    /**
     * Can save Cc online?
     */
    protected $_canSaveCc = false;

    /**
     * Can fetch the transaction information online?
     */
    protected $_canFetchTransactionInfo = false;

    /**
     * Can initialization nneded online?
     */
    protected $_isInitializeNeeded = false;

    /**
     * Set currency code for transaction.
     */
    protected $_allowCurrencyCode = array('USD');

    /**
     * unique internal payment method identifier
     */
    protected $_code = 'inovio_payment';

    /**
     * set form path for payment.
     */
    protected $_formBlockType = 'inovio_payment/form_pay';

    //protected $_infoBlockType = 'inovio_payment/info_pay';
    /**
     * 
     * @param Varien_Object $payment
     * @param type $amount
     * @return of type Inovio_payment_Model_Pay class $this object.
     */
    public function capture(Varien_Object $payment, $amount) {

        try {
            $restrict_product = Mage::getStoreConfig('payment/inovio_payment/extension_product_quantity_restriction');
            $checkout_url = Mage::helper('checkout/url')->getCheckoutUrl();
            $cartButton = "<a href='$checkout_url'>Back to cart page</a>";


            // Restrict Product quantity should not be greater than as set from admin for single product
            if ($this->restrictQuantity() === false) {
                Mage::throwException(Mage::helper('inovio_payment')->__(
                                "For single product quantity should not be greater than $restrict_product "
                                . ", please click on cart icon and update product's quantity"
                ));
            }
            if ($this->merchantAuthenticate() === false) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
            }
            // For auth and capture process.
            $finalParams = array_merge(
                            $this->setGatewayInformation(), $this->prepareRequestData($payment, $amount),
                                $this->getProductIds()
                            )+ $this->getAdvaceparam();
            
            $parseResult = $this->setApiMethodAndgetResponse('authAndCapture', $finalParams);

            // Add log
            $this->inovioLog("Auth and capture response");
            $this->inovioLog($parseResult);

            if (isset($parseResult->TRANS_STATUS_NAME) && 'APPROVED' == $parseResult->TRANS_STATUS_NAME) {
                $payment->setTransactionId($parseResult->PO_ID);
                $payment->setIsTransactionClosed(1);
                $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array('transaction_id' => $parseResult->PO_ID));
            }
            // Check card length
            else if (isset($parseResult->REF_FIELD) && 'pmt_numb' == strtolower($parseResult->REF_FIELD)) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
            }
            // Check card expiry date
            else if (isset($parseResult->REF_FIELD) && 'pmt_expiry' == strtolower($parseResult->REF_FIELD)) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
            }
            // Check card expiry key
            else if (isset($parseResult->REF_FIELD) && 'pmt_key' == strtolower($parseResult->REF_FIELD)) {

                Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
            }
            // Check API Advice, Service Advice and Transaction status 
            else if (isset($parseResult->SERVICE_RESPONSE) && $parseResult->SERVICE_RESPONSE == 500 && !empty($parseResult->SERVICE_ADVICE)) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
            } else {
                Mage::throwException(Mage::helper('inovio_payment')->__('Something went wrong,please contact to your service provider.'));
            }
        } catch (Exception $ex) {
            $errorMsg = $this->_getHelper('inovio_payment')->__('There was an error capturing the transaction .' . $ex->getMessage());
            Mage::throwException($errorMsg);
            //Mage::getSingleton('core/session')->addNotice($message);
        }
        return $this;
    }

    /**
     * Use to set request data to call Inovio APImethod 
     * @param $payment
     * @return array $request
     */
    public function prepareRequestData(Varien_Object $payment, $amount) {
        // create object of payment to get all order related data
        $order = $payment->getOrder();

        $billingaddress = $order->getBillingAddress();
        $shippingaddress = $order->getShippingAddress();

        return $request = [
            "bill_addr" => $billingaddress->getData('street'),
            "pmt_numb" => $payment->getCcNumber(),
            "pmt_key" => $payment->getCcCid(),
            "xtl_ip" => (!empty(Mage::helper('core/http')->getRemoteAddr()) 
                        && Mage::helper('core/http')->getRemoteAddr() == '::1')
                        ? '127.0.0.1' : Mage::helper('core/http')->getRemoteAddr(),
            "cust_fname" => $billingaddress->getName(),
            "pmt_expiry" => (strlen($payment->getCcExpMonth()) < 2) ? ('0' . $payment->getCcExpMonth() . $payment->getCcExpYear()) : $payment->getCcExpMonth() . $payment->getCcExpYear(),
            "cust_email" => $order->getCustomerEmail(),
            "bill_addr_zip" => $billingaddress->getData('postcode'),
            "bill_addr_city" => $billingaddress->getData('city'),
            "bill_addr_state" => $billingaddress->getRegionCode(),
            "request_currency" => $order->getBaseCurrencyCode(),
            "bill_addr_country" => $billingaddress->getData('country_id'),
            "ship_addr_country" => $shippingaddress->getData('country_id'),
            "ship_addr_city" => $shippingaddress->getData('city'),
            "ship_addr_state" => $shippingaddress->getRegionCode(),
            "ship_addr_zip" => $shippingaddress->getData('postcode'),
            "ship_addr" =>$shippingaddress->getData('street'),

        ];
    }

    /**
     * Use to validate mercahnt information
     * 
     * @param null
     * @return true ? false
     */
    public function merchantAuthenticate() {
        $parseResult = $this->setApiMethodAndgetResponse('authenticate', $this->setGatewayInformation());

        // Add log
        $this->inovioLog("Merchant authorized response");
        $this->inovioLog($parseResult);

        if ($parseResult->SERVICE_RESPONSE != 100) {
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
    public function setGatewayInformation() {
        $requestParams = [
            'end_point' => Mage::getStoreConfig('payment/inovio_payment/api_url'),
            'site_id' => Mage::getStoreConfig('payment/inovio_payment/site_id'),
            'req_username' => Mage::getStoreConfig('payment/inovio_payment/api_username'),
            'req_password' => Mage::getStoreConfig('payment/inovio_payment/api_password'),
            'request_response_format' => 'json'
        ];

        $finalRequestParams = [];
      
        foreach ($requestParams as $reqKey => $reqParamVal) {
          if (empty($requestParams[$reqKey])) {
              Mage::throwException(Mage::helper('inovio_payment')->__('Somethig went wrong, please contact to your service provider.'));
              exit;   
          }

          $finalRequestParams[$reqKey] = trim($reqParamVal);
      }
    
      return $finalRequestParams;
    }

    /**
     * Use to get advance fields
     * 
     * @param null
     * @return array $advanceParams
     */
    public function getAdvaceparam() {
        return $advanceParams = [
            Mage::getStoreConfig('payment/inovio_payment/apikey1') => Mage::getStoreConfig('payment/inovio_payment/apivalue1'),
            Mage::getStoreConfig('payment/inovio_payment/apikey2') => Mage::getStoreConfig('payment/inovio_payment/apivalue2'),
            Mage::getStoreConfig('payment/inovio_payment/apikey3') => Mage::getStoreConfig('payment/inovio_payment/apivalue3'),
            Mage::getStoreConfig('payment/inovio_payment/apikey4') => Mage::getStoreConfig('payment/inovio_payment/apivalue4'),
            Mage::getStoreConfig('payment/inovio_payment/apikey5') => Mage::getStoreConfig('payment/inovio_payment/apivalue5'),
            Mage::getStoreConfig('payment/inovio_payment/apikey6') => Mage::getStoreConfig('payment/inovio_payment/apivalue6'),
            Mage::getStoreConfig('payment/inovio_payment/apikey7') => Mage::getStoreConfig('payment/inovio_payment/apivalue7'),
            Mage::getStoreConfig('payment/inovio_payment/apikey8') => Mage::getStoreConfig('payment/inovio_payment/apivalue8'),
            Mage::getStoreConfig('payment/inovio_payment/apikey9') => Mage::getStoreConfig('payment/inovio_payment/apivalue9'),
            Mage::getStoreConfig('payment/inovio_payment/apikey10') => Mage::getStoreConfig('payment/inovio_payment/apivalue10')
        ];
    }
    

    /**
     * Use to get product Ids, price and quantity
     * 
     * @return array $finalArray
     */
    public function getProductIds() {

        
        $finalArray = [];
        $quote = Mage::getModel('checkout/session')->getQuote();
        $quoteData= $quote->getData();
        $grandTotal=$quoteData['grand_total'];

        $finalArray['li_prod_id_1'] = Mage::getStoreConfig('payment/inovio_payment/extension_required_product_id');
        $finalArray['li_count_1'] = 1;
        $finalArray['li_value_1'] = $grandTotal; 
            
        return $finalArray;
    }

    /**
     * For single product's qunantity should not be greater than 99
     * 
     */
    public function restrictQuantity() {
        $returnstate = true;
        $cart = Mage::getModel('checkout/cart')->getQuote();

        foreach ($cart->getAllItems() as $item) {

            if ($item->getQty() > Mage::getStoreConfig('payment/inovio_payment/extension_product_quantity_restriction')) {
                $returnstate = false;
            }
        }

        return $returnstate;
    }

    /**
     * Set method for coder SDK and get response
     * $requestParams
     * @return string $parseResult 
     */
    public function setApiMethodAndgetResponse($methodName = null, $requestParams = array()) {
        // Include core classes
        $this->include_core_class();

        $configservices = new InovioServiceConfig;
        // Create connection for Inovio Processor.
        $processors = new InovioProcessor();
        // Create connection for InovioConnections.
        $connections = new InovioConnection();
        $configservices->serviceConfig($requestParams, $connections);
        $processors->setServiceConfig($configservices);
        $response = $processors->setMethodName($methodName)->getResponse();

        return json_decode($response);
    }

    /**
     * Include inovio payment method core SDK.
     * 
     * @param NULL
     */
    public function include_core_class() {
        // Include core SDK claass
        $file_folder = dirname(__FILE__) . '/inovio-cc/';
        $class_files = scandir($file_folder);
        $checkout_url = Mage::helper('checkout/url')->getCheckoutUrl();
        try {

            if ($class_files > 0) {

                foreach ($class_files as $files) {

                    if (preg_match('/class-inovio?/', $files)) {
                        include_once( $file_folder . $files );
                    }
                }
            } else {
                throw new Exception("Please contact to your service provider.");
            }
        } catch (Exception $ex) {
            $errorMsg = $this->_getHelper()->__($ex->getMessage());
            Mage::throwException($errorMsg);
        }
    }

    /**
     * 
     * @param Varien_Object $payment
     * @param float $amount
     * @return of Object Velocity_CreditCard_Model_Payment class $this object.
     */
    public function refund(Varien_Object $payment, $amount) {
        try {
            if ($amount <= 0) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Invalid amount for refund.'));
            }

            if (!$payment->getParentTransactionId()) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Invalid transaction ID.'));
            }
            $transactionId = $payment->getTransactionAdditionalInfo('transaction_id');
            // get additional information
            $collection = Mage::getModel('sales/order_payment_transaction')
                    ->getCollection()
                    ->addAttributeToFilter('order_id', array('eq' => $payment->getOrder()->getEntityId()))
                    ->addAttributeToFilter('txn_type', array('eq' => 'capture'))
                    ->addPaymentIdFilter($payment->getId());

            $addionalInfo = [];
            foreach ($collection as $transaction) {
                $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);
            }
            $addionalInfo['trans_id'] = $transaction->getAdditionalInformation(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS);

            $params = array_merge(
                    $this->setGatewayInformation(), array(
                        'request_ref_po_id' => $addionalInfo['trans_id']['transaction_id'],
                        'credit_on_fail' => 1,
                        'li_value_1' => $amount
                    )
            );
            $this->inovioLog('before Refund Response');
            $this->inovioLog($params);

            $parseResult = $this->setApiMethodAndgetResponse('ccreverse', $params);
            // Add log
            $this->inovioLog('Refund Response');
            $this->inovioLog($parseResult);

            if (isset($parseResult->TRANS_STATUS_NAME) && $parseResult->TRANS_STATUS_NAME == "APPROVED") {
                $payment->setTransactionId($parseResult->PO_ID);
                $payment->setIsTransactionClosed(1);
                $payment->setShouldCloseParentTransaction(1);

                return $this;
            } else if (!empty($parseResult->SERVICE_ADVICE) && $parseResult->SERVICE_RESPONSE === 516) {
                Mage::throwException(Mage::helper('inovio_payment')->__('Order already refunded'));
            } else {
                Mage::throwException(Mage::helper('inovio_payment')->__('Transaction Id does not exist'));
            }
        } catch (Exception $e) {
            $errorMsg = $this->_getHelper()->__('Error Processing the request- ' . $e->getMessage());
            Mage::throwException($errorMsg);
        }
    }

    /**
     * Use to create custom logger
     * 
     * @param String $message
     */
    protected function inovioLog($message = "") {
        $message = is_array($message) ? print_r($message, true) : $message;
        if (Mage::getStoreConfig('payment/inovio_payment/activelog') == 1) {
            Mage::log($message, null, $this->getCode() . '.log', true);
        }
    }

}