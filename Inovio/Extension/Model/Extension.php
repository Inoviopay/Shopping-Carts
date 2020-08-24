<?php
namespace Inovio\Extension\Model;

use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Framework\DataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Quote\Api\Data\PaymentMethodInterface;
use Inovio\Extension\Model\Chargetypes;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

/**
 * Pay In Store payment method model
 *
 * @package     Inovio_Extension
 * @author      Chetu India Team
 */
class Extension extends \Magento\Payment\Model\Method\Cc
{

    const CODE = 'inovio_extension';

    protected $_code                        = self::CODE;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canUseCheckout              = true;
    protected $_canFetchTransactionInfo     = true;
    protected $_isGateway                   = true;
    protected $_canUseInternal              = true;
    protected $_canVoid                     = false;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_debugReplacePrivateDataKeys = ['number', 'exp_month', 'exp_year', 'cvc'];
    protected $_Config;
    protected $_Processor;
    protected $_Connection;
    protected $_remote;
    protected $_checkoutSession;


    /**
     * Request instance
     *
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $_request;

    
    //protected $_helper;
    protected $_logger;
    protected $_logs;
    protected $_storeManager;

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Payment\Model\Method\Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Psr\Log\LoggerInterface $logs,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remote,
        \Magento\Checkout\Model\Session $checkoutSession,
        Config $config,
        \Inovio\Extension\Model\Core\ServiceConfig $serviceConfig, // API service config object
        \Inovio\Extension\Model\Core\Processor $processor, // API Processor config object
        \Inovio\Extension\Model\Core\Connection $connection, //Create Inovio Connection
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $moduleList,
            $localeDate,
            null,
            null,
            $data
        );

        $this->_remote           = $remote;
        $this->_moduleList       = $moduleList;
        $this->_localeDate       = $localeDate;
        $this->_paymentData      = $paymentData;
        $this->_scopeConfig      = $scopeConfig;
        $this->_logger           = $logger;
        $this->_logs             = $logs;
        $this->config            = $config;
        $this->_checkoutSession  = $checkoutSession;
        $this->_Config           = $serviceConfig;
        $this->_Processor        = $processor;
        $this->_Connection       = $connection;
        $this->_storeManager     = $storeManager;
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     *
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ? : []);
        }

        /** @var DataObject $info */
        $info = $this->getInfoInstance();
        $info->addData(
            [
                'cc_type' => $additionalData->getCcType(),
                'cc_owner' => $additionalData->getCcOwner(),
                'cc_last_4' => substr($additionalData->getCcNumber(), -4),
                'cc_number' => $additionalData->getCcNumber(),
                'cc_cid' => $additionalData->getCcCid(),
                'cc_exp_month' => $additionalData->getCcExpMonth(),
                'cc_exp_year' => $additionalData->getCcExpYear(),
                'cc_ss_issue' => $additionalData->getCcSsIssue(),
                'cc_ss_start_month' => $additionalData->getCcSsStartMonth(),
                'cc_ss_start_year' => $additionalData->getCcSsStartYear()
            ]
        );

        $adddata      = $data->getAdditionalData();
        $infoInstance = $this->getInfoInstance();

        return $this;
    }

    /**
     * Send capture request to gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return string $transactionId
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if (!$this->canCapture()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('The capture action is not available.'));
        }

        if ($amount <= 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for authorization.'));
        }
        try {
            $restrict_product = $this->config->getPaymentConfigData('extension_product_quantity_restriction');
            $checkout_url = $this->_storeManager->getStore()->getBaseUrl() . 'checkout/cart/';
            $cartButton = "<a href='$checkout_url'>Back to cart page</a>";

            if ($this->_merchantAuthenticate() === false) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Somethig went wrong, please contact to your service provider.'));
            } else {
                // Restrict Product quantity should not be greater than as set from admin for single product
                if ($this->restrictQuantity() === false) {
                    $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Quantity checking');
                    throw new \Magento\Framework\Exception\LocalizedException(__(
                        "For single product quantity should not be greater than $restrict_product "
                            . ", please click on cart and update product's quantity"
                    ));
                }
              
                // For auth and capture process.
                $finalParams = array_merge(
                    $this->_setGatewayInformation(),
                    $this->_prepareRequestData($payment),
                            $this->getProductIds()
                    )
                + $this->getAdvaceparam();
                // echo "<pre>";
                // print_r($finalParams);
                // echo "</pre>";
                    
                $parseResult = $this->_setApiMethodAndgetResponse('authAndCapture', $finalParams);
                $custid = $parseResult->CUST_ID;
                $order = $payment->getOrder();
                $order->setcust_id($custid)->save();
                
                

                if (isset($parseResult->TRANS_STATUS_NAME) && 'APPROVED' == $parseResult->TRANS_STATUS_NAME) {
                    $payment->setTransactionId($parseResult->PO_ID);
                    $payment->setAdditionalInformation('transaction_id', $parseResult->PO_ID);
                    $payment->setIsTransactionClosed(0);
                    
                    if ($this->config->getPaymentConfigData('debug_log') == 1) {
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Response after auth and capture method');
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                    }
                }
                // Check card length
                elseif (isset($parseResult->REF_FIELD) && 'pmt_numb' == strtolower($parseResult->REF_FIELD)) {
                    if ($this->config->getPaymentConfigData('debug_log') == 1) {
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Auth and capture response for credit card number');
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(__('Somethig went wrong, please contact to your service provider.'));
                }
                // Check card expiry date
                elseif (isset($parseResult->REF_FIELD) && 'pmt_expiry' == strtolower($parseResult->REF_FIELD)) {
                    $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Auth and capture response for expiry date');
                    $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));

                    throw new \Magento\Framework\Exception\LocalizedException(__('Somethig went wrong, please contact to your service provider.'));
                }
                // Check card expiry key
                elseif (isset($parseResult->REF_FIELD) && 'pmt_key' == strtolower($parseResult->REF_FIELD)) {
                    if ($this->config->getPaymentConfigData('debug_log')==1) {
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Auth and capture response for CVV number');
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                    }
                    throw new \Magento\Framework\Exception\LocalizedException(__('Somethig went wrong, please contact to your service provider.'));
                }
                // Check API Advice, Service Advice and Transaction status
                elseif (isset($parseResult->SERVICE_RESPONSE) && $parseResult->SERVICE_RESPONSE == 500 && !empty($parseResult->SERVICE_ADVICE)) {
                    if ($this->config->getPaymentConfigData('debug_log') == 1) {
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Auth and capture response for status response');
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                    }

                    throw new \Magento\Framework\Exception\LocalizedException(__('Somethig went wrong, please contact to your service provider.'));
                } else {
                    if ($this->config->getPaymentConfigData('debug_log') == 1) {
                        $this->_logs->log(
                            \Psr\Log\LogLevel::INFO,
                            'Something went wrong, please contact to your service provider'
                        );
                        $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                    }
                    
                    throw new \Magento\Framework\Exception\LocalizedException(__('Something went wrong,please contact to your service provider.'));
                }
            }
        } catch (Exception $ex) {
            throw new LocalizedException(__('There was an error capturing the transaction: %1.', $ex->getMessage()));
        }

        return $this;
    }

    /**
     * Refund the amount through gateway
     *
     * @param \Magento\Framework\DataObject|\Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $transactionId
     * @throws \Exception
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        try {
            if ($amount <= 0) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid amount for refund.'));
            }

            if (!$payment->getParentTransactionId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid transaction ID.'));
            }
            $transactionId = $payment->getAdditionalInformation('transaction_id');

            $params = array_merge(
                $this->_setGatewayInformation(),
                array(
                    'request_ref_po_id' => $transactionId,
                    'credit_on_fail' => 1,
                    'li_value_1'  => $amount
                )
            );

            $parseResult = $this->_setApiMethodAndgetResponse('ccreverse', $params);

            if (isset($parseResult->TRANS_STATUS_NAME) && $parseResult->TRANS_STATUS_NAME == "APPROVED") {
                $payment
                        ->setTransactionId($transactionId . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
                        ->setParentTransactionId($transactionId)
                        ->setIsTransactionClosed(1)
                        ->setShouldCloseParentTransaction(1);
                
                if ($this->config->getPaymentConfigData('debug_log') == 1) {
                    $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Refund Response');
                    $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
                }
            }

            return $transactionId;
        } catch (\Exception $e) {
            $this->_logs->log(
                \Psr\Log\LogLevel::INFO,
                "There was an error refunding the transaction:" . $e->getMessage()
            );
            throw new LocalizedException(__('There was an error refunding the transaction: %1.', $e->getMessage()));
        }
    }
    
    /**
     * Cancel payment abstract method
     *
     * @param \Magento\Framework\DataObject|InfoInterface $payment
     * @return $this
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this;
    }

    /**
     * Use to set request data to call Inovio APImethod
     * @param $payment
     * @return array $request
     */
    public function _prepareRequestData($payment = null)
    {
        // create object of payment to get all order related data
        $order   = $payment->getOrder();
        $orderid =  $order->getOrderId();
        //echo $orderid;
        // $custid = $order->getCustomerId();
        $billing = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();

        return [
            //"xtl_order_id" => $order->getOrderId(),
            "bill_addr" => $billing->getStreetLine(1) . ' ' . $billing->getStreetLine(2),
            "pmt_numb" => $payment->getCcNumber(),
            "pmt_key" => $payment->getCcCid(),
            "xtl_ip" => $this->_remote->getRemoteAddress(),
            "xtl_ip" => "127.0.0",
            "cust_fname" => $billing->getName(),
            "pmt_expiry" => strlen($payment->getCcExpMonth()) < 2 ? ('0' . $payment->getCcExpMonth() . $payment->getCcExpYear()) : $payment->getCcExpMonth() . $payment->getCcExpYear(),
            "cust_email" => $order->getCustomerEmail(),
            "bill_addr_zip" => $billing->getPostcode(),
            "bill_addr_city" => $billing->getCity(),
            "bill_addr_state" => $billing->getRegion(),
            "request_currency" => $order->getBaseCurrencyCode(),
            "bill_addr_country" => $billing->getCountryId(),
            "ship_addr_country" => $shipping->getCountryId(),
            "ship_addr_city" => $shipping->getCity(),
            "ship_addr_state" => $shipping->getRegion(),
            "ship_addr_zip" => $shipping->getPostcode(),
            "ship_addr" =>$shipping->getStreetLine(1) . ', '.$shipping->getStreetLine(2),

        ];


    }

    /**
     * Use to validate mercahnt information
     *
     * @param null
     * @return true ? false
     */
    public function _merchantAuthenticate()
    {
        $parseResult = $this->_setApiMethodAndgetResponse('authenticate', $this->_setGatewayInformation());
         
        if ($this->config->getPaymentConfigData('debug_log') == 1) {
            $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Merchant authorized response');
            $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
        }

        if ($parseResult->SERVICE_RESPONSE != 100) {
            if ($this->config->getPaymentConfigData('debug_log') == 1) {
                $this->_logs->log(\Psr\Log\LogLevel::INFO, 'Merchant Authentication Failed');
                $this->_logs->log(\Psr\Log\LogLevel::INFO, print_r($parseResult, true));
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
    public function _setGatewayInformation()
    {
        $requestParams = [
            'end_point' => $this->config->getPaymentConfigData('api_url'),
            'site_id' => $this->config->getPaymentConfigData('site_id'),
            'req_username' => $this->config->getPaymentConfigData('api_username'),
            'req_password' => $this->config->getPaymentConfigData('api_password'),
            'request_response_format' => 'json'
        ];

        $finalRequestParams = [];
      
        foreach ($requestParams as $reqKey => $reqParamVal) {
            if (empty($requestParams[$reqKey])) {
                 throw new \Magento\Framework\Exception\LocalizedException(__('Something went wrong,please contact to your service provider.'));
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
    public function getAdvaceparam()
    {
        return $advanceParams = [
            $this->config->getPaymentConfigData('apikey1') => $this->config->getPaymentConfigData('apivalue1'),
            $this->config->getPaymentConfigData('apikey2') => $this->config->getPaymentConfigData('apivalue2'),
            $this->config->getPaymentConfigData('apikey3') => $this->config->getPaymentConfigData('apivalue3'),
            $this->config->getPaymentConfigData('apikey4') => $this->config->getPaymentConfigData('apivalue4'),
            $this->config->getPaymentConfigData('apikey5') => $this->config->getPaymentConfigData('apivalue5'),
            $this->config->getPaymentConfigData('apikey6') => $this->config->getPaymentConfigData('apivalue6'),
            $this->config->getPaymentConfigData('apikey7') => $this->config->getPaymentConfigData('apivalue7'),
            $this->config->getPaymentConfigData('apikey8') => $this->config->getPaymentConfigData('apivalue8'),
            $this->config->getPaymentConfigData('apikey9') => $this->config->getPaymentConfigData('apivalue9'),
            $this->config->getPaymentConfigData('apikey10') => $this->config->getPaymentConfigData('apivalue10')
        ];
    }

    /**
     * Use to get product Ids, price and quantity
     *
     * @return array $finalArray
     */
    public function getProductIds()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $cartObj = $objectManager->get('\Magento\Checkout\Model\Cart');

        $subTotal = $cartObj->getQuote()->getSubtotal(); //Current Cart Subtotal

        $grandTotal = $cartObj->getQuote()->getGrandTotal(); //Cart Grand total

        $finalArray = [];
        
            // static because client has told that use only 41241 id
            $finalArray['li_prod_id_1'] = $this->config->getPaymentConfigData('extension_required_product_id');
            $finalArray['li_count_1']   = 1;
            $finalArray['li_value_1']   = $grandTotal;
        

        return $finalArray;
    }

   

    /**
     * For single product's qunantity should not be greater than 99
     *
     */
    public function restrictQuantity()
    {
        $returnstate = true;

        foreach ($this->_checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            if ($item->getQty() > $this->config->getPaymentConfigData('extension_product_quantity_restriction')) {
                $returnstate =  false;
            }
        }

        return $returnstate;
    }

    /**
     * Set method for coder SDK and get response
     * $requestParams
     * @return string $parseResult
     */
    public function _setApiMethodAndgetResponse($methodName = null, $requestParams = array())
    {
        $this->_Config->serviceConfig($requestParams, $this->_Connection);
        $this->_Processor->setServiceConfig($this->_Config);
        $response    = $this->_Processor->setMethodName($methodName)->getResponse();

        return $parseResult = json_decode($response);
    }
}
