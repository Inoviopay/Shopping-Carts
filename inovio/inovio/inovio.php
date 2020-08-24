<?php

/**
 * @version $Id: inovio.php
 * @package VirtueMart
 * @subpackage payment
 * @author Inovio Team <sunils3@chetu.com>
 * @copyright Copyright (C) 2017 The Inovio team - All rights reserved.
 * @license VirtueMart is free software and Inovio payment gateway is custom payment gateway
 */
defined('_JEXEC') or die('Restricted access');

if (!class_exists('Creditcard')) {
    require_once JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'creditcard.php';
}

if (!class_exists('vmPSPlugin')) {
    require JPATH_VM_PLUGINS . DS . 'vmpsplugin.php';
}

/**
 * Class use to create Inovio Payment Gateway
 * @since 3.2
 */
class PlgVmpaymentInovio extends vmPSPlugin {

    /**
     * Credit card name
     * @var string
     */
    private $ccName = '';

    /**
     * Credit card type
     * @var string
     */
    private $ccType = '';

    /**
     * Credit card Number
     * @var integer
     */
    private $ccNumber = '';

    /**
     * Credit card CVV number
     * @var integer
     */
    private $ccCvv = '';

    /**
     * Set product Restriction val
     * @var integer
     */
    private $productRestriction = '';

    /**
     * Set product Id from config
     * @var integer
     */
    private $productId = '';

    /**
     * Credit card Expiry Month
     * @var integer
     */
    private $ccExpireMonth = '';

    /**
     * Credit card Expiry Year
     * @var integer
     */
    private $ccExpireYear = '';

    /**
     * Table Name
     * @var string
     */
    protected $tablename = 0;

    /**
     * Check Valid
     * @var boolean
     */
    private $ccValid = false;

    /**
     * Check error message
     * @var array
     */
    private $errormessage = array();

    /**
     * Check Inovio Params
     * @var array
     */
    protected $inovioParams = array(
        "version" => "3.1",
        "delim_char" => ",",
        "delim_data" => "true",
        "relay_response" => "FALSE",
        "encap_char" => "|",
    );

    /**
     * Check approved or not
     * @var boolean
     */
    public $approved;

    /**
     * Check Declined
     * @var boolean
     */
    public $declined;

    /**
     * Check error
     * @var string
     */
    public $error;

    /**
     * Check on hold
     * @var string
     */
    public $held;

    const APPROVED = 1;
    const DECLINED = 2;
    const ERROR = 3;
    const HELD = 4;
    const INOVIO_DEFAULT_PAYMENT_CURRENCY = "USD";

    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
     * This causes problems with cross-referencing necessary for the observer design pattern.
     * @param   object $subject The object to observe
     * @param   array $config An array that holds the plugin configuration
     * @since 1.5
     */
    public function __construct(&$subject, $config) {

        parent::__construct($subject, $config);
        $this->loggable = true;
        $this->tablepkey = 'id';
        $this->tableId = 'id';
        $this->tableFields = array_keys($this->getTableSQLFields());
        $varsToPush = $this->getVarsToPush();
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @return string
     */
    protected function getVmPluginCreateTableSQL() {
        return $this->createTableSQL('Payment Inovio Table');
    }

    /**
     * Fields to create the payment table
     * @return array SQL Fileds
     */
    public function getTableSQLFields() {

        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT null AUTO_INCREMENT',
            'transaction_id' => 'varchar(64)',
            'po_id' => 'varchar(64)',
            'transaction_status' => 'varchar(20)',
            'virtuemart_order_id' => 'int(20)',
            'response_obj' => 'text',
        );

        return $SQLfields;
    }

    /**
     * This shows the plugin for choosing in the payment list of the checkout process.
     * @param   VirtueMartCart   $cart Cart Params
     * @param   int $selected   Check selected Payment method
     * @param   array $htmlIn   Html Collection
     * @return   boolean Return   boolean
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected = 0, &$htmlIn) {
        $this->loadScript();

        if ($this->getPluginMethods($cart->vendorId) === 0) {
            if (empty($this->_name)) {
                $app = JFactory::getApplication();
                $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                return false;
            } else {
                return false;
            }
        }

        $html = array();
        $methodName = $this->_psType . '_name';

        VmConfig::loadJLang('com_virtuemart', true);
        vmJsApi::jCreditCard();
        $htmla = '';
        $html = array();

        foreach ($this->methods as $this->currentMethod) {
            if ($this->checkConditions($cart, $this->currentMethod, $cart->cartPrices)) {
                $cartPrices = $cart->cartPrices;
                $methodSalesPrice = $this->setCartPrices($cart, $cartPrices, $this->currentMethod);
                $this->currentMethod->$methodName = $this->renderPluginName($this->currentMethod);
                $html = $this->getPluginHtml($this->currentMethod, $selected, $methodSalesPrice);
                if ($selected == $this->currentMethod->virtuemart_paymentmethod_id) {
                    $this->getInovioFromSession();
                } else {
                    $this->ccType = '';
                    $this->ccNumber = '';
                    $this->ccCvv = '';
                    $this->ccExpireMonth = '';
                    $this->ccExpireYear = '';
                }

                if (empty($this->currentMethod->creditcards)) {
                    $this->currentMethod->creditcards = self::getCreditCards();
                } elseif (!is_array($this->currentMethod->creditcards)) {
                    $this->currentMethod->creditcards = (array) $this->currentMethod->creditcards;
                }

                $creditCards = $this->currentMethod->creditcards;
                $creditCardList = '';

                if ($creditCards) {
                    $creditCardList = ($this->_renderCreditCardList($creditCards, $this->ccType, $this->currentMethod->virtuemart_paymentmethod_id, false));
                }

                $cvvImages = $this->displayCVVImages($this->currentMethod);
                $html .= '<br /><span class="vmpayment_cardinfo">' . vmText::_('VMPAYMENT_INOVIO_COMPLETE_FORM') . $sandboxMsg . '
            <table border="0" cellspacing="0" cellpadding="2" width="100%">
            <tr valign="top">
                <td nowrap width="10%" align="right">
                        <label for="creditcardtype">' . vmText::_('VMPAYMENT_INOVIO_CCTYPE') . '</label>
                </td>
                <td>' . $creditCardList .
                        '</td>
            </tr>
            <tr valign="top">
                <td nowrap width="10%" align="right">
                        <label for="cc_type">' . vmText::_('VMPAYMENT_INOVIO_CCNUM') . '</label>
                </td>
                <td>
                        <script type="text/javascript">
                        //<![CDATA[  
                          function checkInovio(id, el)
                           {
                             ccError=razCCerror(id);
                                CheckCreditCardNumber(el.value, id);
                                if (!ccError) {
                                el.value=\'\';}
                           }
                        //]]> 
                        </script>
                <input type="text" class="inputbox" maxlength="19" id="cc_number_' . $this->currentMethod->virtuemart_paymentmethod_id . '" name="cc_number_' . $this->currentMethod->virtuemart_paymentmethod_id . '" value="' . $this->ccNumber . '"    autocomplete="off"   onchange="javascript:checkInovio(' . $this->currentMethod->virtuemart_paymentmethod_id . ', this);"  />
                <div id="cc_cardnumber_errormsg_' . $this->currentMethod->virtuemart_paymentmethod_id . '"></div>
            </td>
            </tr>
            <tr valign="top">
                <td nowrap width="10%" align="right">
                        <label for="cc_cvv">' . vmText::_('VMPAYMENT_INOVIO_CVV2') . '</label>
                </td>
                <td>
                    <input type="password" class="inputbox" id="cc_cvv_' . $this->currentMethod->virtuemart_paymentmethod_id . '" name="cc_cvv_' . $this->currentMethod->virtuemart_paymentmethod_id . '" maxlength="4" size="5" value="' . $this->ccCvv . '" autocomplete="off" />

                <span class="hasTip" title="' . vmText::_('VMPAYMENT_INOVIO_WHATISCVV') . '::' . vmText::sprintf("VMPAYMENT_INOVIO_WHATISCVV_TOOLTIP", $cvvImages) . ' ">' .
                        vmText::_('VMPAYMENT_INOVIO_WHATISCVV') . '
                </span></td>
            </tr>
            <tr>
                <td nowrap width="10%" align="right">' . vmText::_('VMPAYMENT_INOVIO_EXDATE') . '</td>
                <td> ';
                $html .= shopfunctions::listMonths('cc_expire_month_' . $this->currentMethod->virtuemart_paymentmethod_id, $this->ccExpireMonth);
                $html .= " / ";
                $html .= '
                        <script type="text/javascript">
                        //<![CDATA[  
                          function changeDate(id, el)
                           {
                             var month = document.getElementById(\'cc_expire_month_\'+id); if(!CreditCardisExpiryDate(month.value,el.value, id))
                                 {el.value=\'\';
                                 month.value=\'\';}
                           }
                        //]]> 
                        </script>';

                $html .= shopfunctions::listYears('cc_expire_year_' . $this->currentMethod->virtuemart_paymentmethod_id, $this->ccExpireYear, null, null, " onchange=\"javascript:changeDate(" . $this->currentMethod->virtuemart_paymentmethod_id . ", this);\" ");
                $html .= '<div id="cc_expiredate_errormsg_' . $this->currentMethod->virtuemart_paymentmethod_id . '"></div>';
                $html .= '</td>  </tr>      </table></span>';
                $htmla = array();
                $htmla[] = $html;
            }
        }

        $htmlIn[] = $htmla;

        return true;
    }

    /**
     *  Credit card list
     * @return array Return Cart type
     */
    public static function getCreditCards() {
        return array(
            'Visa',
            'MasterCard',
            'AmericanExpress',
            'Discover',
        );
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     * @param   Object $cart Cart object
     * @param   String $method Payment method
     * @param   integer $cartPrices cart price
     * @return   boolean return true or false
     */
    protected function checkConditions($cart, $method, $cartPrices) {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cartPrices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amountCond = ($amount >= $method->min_amount && $amount <= $method->max_amount || ( $method->min_amount <= $amount && ( $method->max_amount == 0)));

        if (!$amountCond) {
            return false;
        }

        $countries = array();

        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
        }

        // Probably did not gave his BT:ST address

        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }

        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return true;
        }

        return false;
    }

    /**
     * Set inovio data into session
     * @return NULL Nothing return
     */
    public function setInovioIntoSession() {
        if (!class_exists('vmCrypt')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php';
        }

        $session = JFactory::getSession();
        $sessionInovio = new stdClass;

        // Card information
        $sessionInovio->ccType = $this->ccType;
        $sessionInovio->ccNumber = vmCrypt::encrypt($this->ccNumber);
        $sessionInovio->ccCvv = vmCrypt::encrypt($this->ccCvv);
        $sessionInovio->ccExpireMonth = $this->ccExpireMonth;
        $sessionInovio->ccExpireYear = $this->ccExpireYear;
        $sessionInovio->ccValid = $this->ccValid;
        $session->set('inovio', json_encode($sessionInovio), 'vm');
    }

    /**
     * Get information from session
     * @return null No Description
     */
    public function getInovioFromSession() {
        if (!class_exists('vmCrypt')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php';
        }

        $session = JFactory::getSession();
        $sessionInovio = $session->get('inovio', 0, 'vm');

        if (!empty($sessionInovio)) {
            $inovioData = (object) json_decode($sessionInovio, true);
            $this->ccType = $inovioData->cc_type;
            $this->ccNumber = vmCrypt::decrypt($inovioData->ccNumber);
            $this->ccCvv = vmCrypt::decrypt($inovioData->ccCvv);
            $this->ccExpireMonth = $inovioData->ccExpireMonth;
            $this->ccExpireYear = $inovioData->ccExpireYear;
            $this->ccValid = $inovioData->ccValid;
        }
    }

    /**
     * This is for checking the input data of the payment method within the checkout
     * @author Inovio Team
     * @param object $cart: get the cart related information from cart
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            // Another method was selected, do nothing
            return null;
        }

        if (!($this->currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return false;
        }

        $this->getInovioFromSession();

        return $this->validateInovioCreditcardData(true);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     * @param   integer $jplugin_id Get the plugin id name
     * @return string tablename
     */
    function plgVmOnStoreInstallPaymentPluginTable($jpluginId) {
        return parent::onStoreInstallPluginTable($jpluginId);
    }

    /**
     * This is for adding the input data of the payment method to the cart, after selecting
     * @author Inovio Team
     * @param   VirtueMartCart $cart get the cart information from cart page
     * @param   string $msg set message
     * @return   null if payment not selected; true if card infos are correct; string containing the errors id cc is not valid
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg) {

        if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
            // Another method was selected, do nothing
            return null;
        }

        if (!($this->currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            return false;
        }

        $this->ccType = vRequest::getVar('cc_type_' . $cart->virtuemart_paymentmethod_id, '');
        $this->ccName = vRequest::getVar('cc_name_' . $cart->virtuemart_paymentmethod_id, '');
        $this->ccNumber = str_replace(" ", "", vRequest::getVar('cc_number_' . $cart->virtuemart_paymentmethod_id, ''));
        $this->ccCvv = vRequest::getVar('cc_cvv_' . $cart->virtuemart_paymentmethod_id, '');
        $this->ccExpireMonth = vRequest::getVar('cc_expire_month_' . $cart->virtuemart_paymentmethod_id, '');
        $this->ccExpireYear = vRequest::getVar('cc_expire_year_' . $cart->virtuemart_paymentmethod_id, '');

        if (!$this->validateInovioCreditcardData(true)) {
            // Returns string containing errors
            return false;
        }

        $this->setInovioIntoSession();

        return true;
    }

    /**
     * Select product calculate price
     * @param   VirtueMartCart   $cart
     * @param   array   $cartPrices cart price
     * @param   String   $paymentName payment method name
     * @return   boolean true|false
     */
    public function plgVmOnSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cartPrices, &$paymentName) {

        if (!($this->currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
            // Another method was selected, do nothing
            return null;
        }

        if (!$this->selectedThisElement($this->currentMethod->payment_element)) {
            return false;
        }

        $this->getInovioFromSession();
        $cartPrices['payment_tax_id'] = 0;
        $cartPrices['payment_value'] = 0;

        if (!$this->checkConditions($cart, $this->currentMethod, $cartPrices)) {
            return false;
        }

        $paymentName = $this->renderPluginName($this->currentMethod);

        $this->setCartPrices($cart, $cartPrices, $this->currentMethod);

        return true;
    }

    /**
     * Render Plugin Name
     * @param   string $plugin plugin name
     * @return   string $pluginName plugin name
     */
    protected function renderPluginName($plugin) {

        $return = '';
        $pluginName = $this->_psType . '_name';
        $pluginDesc = $this->_psType . '_desc';
        $description = '';

        if (!empty($plugin->$pluginDesc)) {
            $description = '<span class="' . $this->_type . '_description">' . $plugin->$pluginDesc . '</span>';
        }

        $this->getInovioFromSession();
        $extrainfo = $this->getExtraPluginNameInfo();
        $pluginName = $return . '<span class="' . $this->_type . '_name">' . $plugin->$pluginName . '</span>' . $description;

        return $pluginName;
    }

    /**
     * Display stored payment data for an order
     * @param   integer $virtuemartOrderId Order id
     * @param   integer $virtuemartPaymentId Payment id
     * @return string
     */
    public function plgVmOnShowOrderBEPayment($virtuemartOrderId, $virtuemartPaymentId) {
        if (!($this->currentMethod = $this->selectedThisByMethodId($virtuemartPaymentId))) {
            // Another method was selected, do nothing
            return null;
        }

        if (!($paymentTable = $this->getDataByOrderId($virtuemartOrderId))) {
            return null;
        }

        $user = JFactory::getUser();
        VmConfig::loadJLang('com_virtuemart');

        $db = JFactory::getDBO();
        $q1 = 'SELECT * FROM `#__virtuemart_orders` where virtuemart_order_id = ' . $virtuemartOrderId;
        $db->setQuery($q1);
        $ship = $db->loadObjectList();
        $shipment = $ship[0]->order_shipment;
        $shipmenttax = $ship[0]->order_shipment_tax;
        $total_ship_amount = $shipment + $shipmenttax;
        $obcurr = CurrencyDisplay::getInstance();

        $resObj = unserialize($paymentTable->response_obj);
        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlHeaderBE();
        $html .= $this->getHtmlRowBE('COM_VIRTUEMART_PAYMENT_NAME', self::getPaymentName($virtuemartPaymentId));
        $html .= $this->getHtmlRowBE('INOVIO_PAYMENT_ORDER_TOTAL', $resObj->TRANS_VALUE_SETTLED . " " . self::INOVIO_DEFAULT_PAYMENT_CURRENCY);
        $html .= $this->getHtmlRowBE('INOVIO_COST_PER_TRANSACTION', $resObj->TRANS_VALUE_SETTLED);
        $html .= $this->getHtmlRowBE('VMPAYMENT_INOVIO_CASH_BACK_AMOUNT', $resObj->TRANS_VALUE_SETTLED);
        $code = "inovio_response_";

        foreach ($paymentTable as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $html .= $this->getHtmlRowBE($key, $value);
            }
        }

        $html .= '</table>' . "\n";

        return $html;
    }

    /**
     * Re implementation of vmPaymentPlugin::plgVmOnConfirmedOrder()
     * $cart cart object
     * Credit Cards Test Numbers
     * Visa Test Account           4007000000027
     * Amex Test Account           370000000000002
     * Master Card Test Account    6011000000000012
     * Discover Test Account       5424000000000015
     * @author Inovio Team
     * @param   object $cart card data from cart
     * @param   object $order order data from checkout page
     */
    public function plgVmConfirmedOrder(VirtueMartCart $cart, $order) {

        if (!($this->currentMethod = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            // Another method was selected, do nothing
            return null;
        }

        if (!$this->selectedThisElement($this->currentMethod->payment_element)) {
            return false;
        }

        $this->setInConfirmOrder($cart);
        $usrBT = $order['details']['BT'];
        $usrST = ((isset($order['details']['ST'])) ? $order['details']['ST'] : '');
        $session = JFactory::getSession();
        $returnContext = $session->getId();

        $paymentCurrencyId = shopFunctions::getCurrencyIDByName(self::INOVIO_DEFAULT_PAYMENT_CURRENCY);
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $paymentCurrencyId);
        $cd = CurrencyDisplay::getInstance($cart->pricesCurrency);

        $inovioMethodId = $usrBT->virtuemart_paymentmethod_id;
        $getAuthParams = $this->getParamsByMethodId($inovioMethodId, "merchantinfo");

        // Prepare data that should be stored in the database
        $dbValues = [];
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $dbValues['payment_method_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['return_context'] = $returnContext;
        $dbValues['payment_name'] = parent::renderPluginName($this->_currentMethod);
        $dbValues['cost_per_transaction'] = $this->_currentMethod->cost_per_transaction;
        $dbValues['cost_percent_total'] = $this->_currentMethod->cost_percent_total;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['payment_currency'] = $paymentCurrencyId;

        $this->debugLog("before store", "plgVmConfirmedOrder", 'debug');
        //$this->storePSPluginInternalData($dbValues);
        // Set error message
        try {
            $parseResult = $this->setApiMethodAndgetResponse('authenticate', $getAuthParams);

            // Check merchatnt response with 100
            if (isset($parseResult->SERVICE_RESPONSE) && $parseResult->SERVICE_RESPONSE != 100) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($parseResult, true), "Authenticate response", true);
                }

                throw new Exception("Something went wrong, please contact to service provider");
            }

            $getAuthAdvanceParams = $this->getParamsByMethodId($inovioMethodId);
            // Restrict product qauntity
            if ($this->restrictQuantity($cart) == false) {

                $redirectoCart = JRoute::_('index.php?option=com_virtuemart&view=cart');

                throw new Exception("For any single product's quantity should not be greater than " .
                $this->getProductRestrictVal() . " <a href='$redirectoCart' class='btn btn-danger' role='button'>Back to cart page.</a>");
            }
            $getProductParams = $this->getproductParams($usrBT);
            $cartParams = $this->cartParams($usrBT);

            //  Bind final params
            $finalAuthParams = array_merge($getAuthAdvanceParams, $getProductParams, $cartParams);




            if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                $this->logInfo(print_r($finalAuthParams, true), "To check shipping parameters", true);
            }


            //  Auth and Capture response with authAndCapture
            $responseAuthCapture = $this->setApiMethodAndgetResponse('authAndCapture', $finalAuthParams);

            // Check card length
            if (isset($responseAuthCapture->REF_FIELD) && 'pmt_numb' == strtolower($responseAuthCapture->REF_FIELD)) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($responseAuthCapture, true), "Auth and capture response for credit card number", true);
                }
                throw new Exception('Error Invalid Credit Card Length');
            }

            // Check card expiry date
            if (isset($responseAuthCapture->REF_FIELD) && 'pmt_expiry' == strtolower($responseAuthCapture->REF_FIELD)) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($responseAuthCapture, true), "Auth and capture response for expiry date", true);
                }
                throw new Exception('Error Invalid Card Expiry date');
            }

            // Check card expiry date
            if (isset($responseAuthCapture->REF_FIELD) && 'pmt_key' == strtolower($responseAuthCapture->REF_FIELD)) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($responseAuthCapture, true), "Auth and capture response for CVV number", true);
                }
                throw new Exception('Error CVV number not valid');
            }
            if (isset($responseAuthCapture->API_ADVICE)) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($responseAuthCapture, true), "Auth and capture response for API advice number", true);
                }
                throw new Exception('Something went wrong, please contact to service provider');
            }

            // Check API Advice, Service Advice and Transaction status 
            if (isset($responseAuthCapture->SERVICE_RESPONSE) && $responseAuthCapture->SERVICE_RESPONSE == 500 && !empty($responseAuthCapture->SERVICE_ADVICE)) {
                if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
                    $this->logInfo(print_r($responseAuthCapture, true), "Auth and capture response for status response, API advice, Service Advice and Transaction", true);
                }

                throw new Exception('Something went wrong, please contact to your service provider');
            }
        } catch (Exception $ex) {
            $mainframe = JFactory::getApplication();
            $mainframe->enqueueMessage($ex->getMessage(), 'error');
            $new_status = $this->_currentMethod->payment_declined_status;
            $this->handlePaymentCancel($order['details']['BT']->virtuemart_order_id, $html);

            // Will not process the order
            return false;
        }

        // Set order status approved if there is no exception
        $newStatus = $this->_currentMethod->payment_approved_status;
        $responseFields = [];

        // Save the authandcap response into 'virtuemart_payment_plg_inovio' custom table.
        $responseFields['transaction_id'] = $responseAuthCapture->TRANS_ID;
        $responseFields['po_id'] = $responseAuthCapture->PO_ID;
        $responseFields['transaction_status'] = $responseAuthCapture->TRANS_STATUS_NAME;
        $responseFields['virtuemart_order_id'] = $order['details']['BT']->virtuemart_order_id;
        $responseFields['response_obj'] = serialize($responseAuthCapture);
        $this->storePSPluginInternalData($responseFields);

        $html = '<table class="adminlist table">' . "\n";
        $html .= $this->getHtmlRow('INOVIO_PAYMENT_NAME', $this->_vmpCtable->payment_name);
        $html .= $this->getHtmlRow('VMPAYMENT_INOVIO_ORDER_NUMBER', $order['details']['BT']->order_number);
        $html .= $this->getHtmlRow('VMPAYMENT_INOVIO_AMOUNT', $responseAuthCapture->TRANS_VALUE_SETTLED);
        // Tocken code
        $html .= $this->getHtmlRow('VMPAYMENT_INOVIO_APPROVAL_CODE', $responseAuthCapture->PMT_ID);

        if ($responseAuthCapture->TRANS_ID) {
            $html .= $this->getHtmlRow('INOVIO_RESPONSE_TRANSACTION_ID', $responseAuthCapture->TRANS_ID);
        }

        $html .= '</table>' . "\n";
        $this->debugLog(vmText::_('VMPAYMENT_INOVIO_ORDER_NUMBER') . " " . $order['details']['BT']->order_number . ' payment approved', '_handleResponse', 'debug');

        $comment = 'ApprovalCode: ' . $responseAuthCapture->PMT_ID . '<br>Transaction_Id: ' . $responseAuthCapture->TRANS_ID .
                '<br>PO_Id: ' . $responseAuthCapture->PO_ID;
        $this->clearInovioSession();
        $newStatus = 'U';

        $modelOrder = VmModel::getModel('orders');
        $order['order_status'] = $newStatus;
        $order['customer_notified'] = 1;
        $order['comments'] = $comment;
        $modelOrder->updateStatusForOneOrder($order['details']['BT']->virtuemart_order_id, $order, true);

        // We delete the old stuff
        $cart->emptyCart();
        vRequest::setVar('html', $html);
        if ($this->getParamsByMethodId($inovioMethodId, "debuglog")) {
            $this->logInfo(print_r($responseAuthCapture, true), "Successfull Auth and capture response", true);
        }
    }

    /**
     * Handle on payment Cancel
     * @param type $virtuemartOrderId virtueMart oder id
     * @param type $html string
     */
    public function handlePaymentCancel($virtuemartOrderId, $html) {

        if (!class_exists('VirtueMartModelOrders')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php';
        }

        $modelOrder = VmModel::getModel('orders');
        $mainframe = JFactory::getApplication();
        vmWarn($html);
        $mainframe->enqueueMessage($html);
        $mainframe->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', false), vmText::_('COM_VIRTUEMART_CART_ORDERDONE_DATA_NOT_VALID'));
    }

    /**
     * Return the Payment Name of a given paymentID
     * @author   Inovio Team
     * @access   private
     * @param   int $id Payment ID
     * @return   string $pid 
     */
    static private function getPaymentName($id) {

        if (empty($id)) {
            return '';
        }

        $id = (int) $id;
        $db = JFactory::getDBO();

        $q = 'SELECT payment_name FROM `#__virtuemart_paymentmethods_en_gb` WHERE virtuemart_paymentmethod_id = ' . (int) $id;
        $db->setQuery($q);
        $pid = $db->loadResult();
        return $pid;
    }

    /**
     * Plugin Currency
     * @param   string $virtuemart_paymentmethod_id
     * @param   int $paymentCurrencyId
     * @return   boolean
     */
    public function plgVmgetPaymentCurrency($virtuemartPaymentmethodId, &$paymentCurrencyId) {

        if (!($this->currentMethod = $this->getVmPluginMethod($virtuemartPaymentmethodId))) {
            // Another method was selected, do nothing
            return null;
        }

        if (!$this->selectedThisElement($this->currentMethod->payment_element)) {
            return false;
        }

        $this->currentMethod->payment_currency = self::INOVIO_DEFAULT_PAYMENT_CURRENCY;

        if (!class_exists('VirtueMartModelVendor')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'vendor.php';
        }

        // VirtueMartModelVendor::getLoggedVendor();
        $vendorId = 1;
        $db = JFactory::getDBO();

        $q = 'SELECT   `virtuemart_currency_id` FROM `#__virtuemart_currencies` WHERE `currency_code_3`= "' . self::INOVIO_DEFAULT_PAYMENT_CURRENCY . '"';
        $db->setQuery($q);
        $paymentCurrencyId = $db->loadResult();
    }

    /**
     * Clear session data
     * @return null
     */
    public function clearInovioSession() {
        $session = JFactory::getSession();
        $session->clear('inovio', 'vm');
    }

    /**
     * RenderPluginName
     * Get the name of the payment method
     * @author Inovio Team
     * @param   $payment
     * @return   string Payment method name
     */
    public function getExtraPluginNameInfo() {

        $creditCardInfos = '';
        if ($this->validateInovioCreditcardData(false)) {
            $cc_number = "**** **** **** " . substr($this->ccNumber, -4);
            $creditCardInfos .= '<br /><span class="vmpayment_cardinfo">' . vmText::_('VMPAYMENT_INOVIO_CCTYPE') . $this->ccType . '<br />';
            $creditCardInfos .= vmText::_('VMPAYMENT_INOVIO_CCNUM') . $cc_number . '<br />';
            $creditCardInfos .= vmText::_('VMPAYMENT_INOVIO_CVV2') . '****' . '<br />';
            $creditCardInfos .= vmText::_('VMPAYMENT_INOVIO_EXDATE') . $this->ccExpireMonth . '/' . $this->ccExpireYear;
            $creditCardInfos .= "</span>";
        }

        return $creditCardInfos;
    }

    /**
     * Creates a Drop Down list of available Creditcards
     * @param   string $creditCards   credit card
     * @param   string $selectedCcType   select credit card
     * @param   string $paymentmethodId   payment meethod id
     * @param   string $multiple select   multiple
     * @param   string $attrs   select attribute
     * @return   string   string
     */
    public function _renderCreditCardList($creditCards, $selectedCcType, $paymentmethodId, $multiple = false, $attrs = '') {

        $idA = $id = 'cc_type_' . $paymentmethodId;
        if (!is_array($creditCards)) {
            $creditCards = (array) $creditCards;
        }

        foreach ($creditCards as $creditCard) {
            $options[] = JHTML::_('select.option', $creditCard, vmText::_('VMPAYMENT_INOVIO_' . strtoupper($creditCard)));
        }

        if ($multiple) {
            $attrs = 'multiple="multiple"';
            $idA .= '[]';
        }

        return JHTML::_('select.genericlist', $options, $idA, $attrs, 'value', 'text', $selected_cc_type);
    }

    /**
     * Validate Credit Card Number
     * @staticvar boolean $force
     * @param   boolean $enqueueMessage
     * @return   booleanValidate Credit Card Number
     */
    public function validateInovioCreditcardData($enqueueMessage = true) {
        static $force = true;

        if (empty($this->ccNumber) && empty($this->ccCvv)) {
            return false;
        }

        $html = '';
        $this->ccValid = !empty($this->ccNumber) && !empty($this->ccCvv) and ! empty($this->ccExpireMonth) and ! empty($this->ccExpireYear);

        if (!empty($this->ccNumber) and ! Creditcard::validate_credit_card_number($this->ccType, $this->ccNumber)) {
            $this->errormessage[] = 'VMPAYMENT_INOVIO_CARD_NUMBER_INVALID';
            $this->ccValid = false;
        }

        if (!Creditcard::validate_credit_card_cvv($this->ccType, $this->ccCvv)) {
            $this->errormessage[] = 'VMPAYMENT_INOVIO_CARD_CVV_INVALID';
            $this->ccValid = false;
        }

        if (!Creditcard::validate_credit_card_date($this->ccType, $this->ccExpireMonth, $this->ccExpireYear)) {
            $this->errormessage[] = 'VMPAYMENT_INOVIO_CARD_EXPIRATION_DATE_INVALID';
            $this->ccValid = false;
        }

        if (!$this->ccValid) {
            // $html.= "<ul>";
            foreach ($this->errormessage as $msg) {
                //$html .= "<li>" . vmText::_($msg) . "</li>";
                $html .= vmText::_($msg) . "<br/>";
            }

            // $html.= "</ul>";
        }

        if (!$this->ccValid && $enqueueMessage && $force) {
            $app = JFactory::getApplication();
            $app->enqueueMessage($html);
            $force = false;
        }

        return $this->ccValid;
    }

    /**
     * Display CVV images
     * @param   string $method
     * @return   string
     */
    public function displayCVVImages($method) {

        $cvvImages = $method->cvv_images;
        $img = '';

        if ($cvvImages) {
            $img = $this->displayLogos($cvvImages);
            $img = str_replace('"', "'", $img);
        }

        return $img;
    }

    /**
     * plgVmOnCheckAutomaticSelectedPayment
     * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
     * The plugin must check first if it is the correct type
     * @author Inovio Team
     * @param   VirtueMartCart cart: the cart object
     * @param   string $paymentCounter
     * @param   array $cart_prices cart price
     * @return   null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
     *
     */
    public function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cartPrices = array(), &$paymentCounter) {

        $return = $this->onCheckAutomaticSelected($cart, $cartPrices);
        if (isset($return)) {
            return 0;
        } else {
            return null;
        }
    }

    /**
     * This method is fired when showing the order details in the front end.
     * It displays the method-specific data.
     * @param   integer $virtuemartOrderId The order ID
     * @param   integer $virtuemartPaymentmethodId payment Id
     * @param   string $paymentName method name
     * @return   mixed Null for methods that aren't active, text (HTML) otherwise
     * @author   Inovio Team
     */
    protected function plgVmOnShowOrderFEPayment($virtuemartOrderId, $virtuemartPaymentmethodId, &$paymentName) {

        $this->onShowOrderFE($virtuemartOrderId, $virtuemartPaymentmethodId, $paymentName);

        return true;
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     * @param   integer $orderNumber The order ID
     * @param   integer $methodId  method used for this order
     * @return   mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author   Inovio Team
     */
    public function plgVmOnShowOrderPrintPayment($orderNumber, $methodId) {

        return parent::onShowOrderPrint($orderNumber, $methodId);
    }

    /**
     *  Set plugin Payment parameters
     * @param type $data
     * @return array
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * Set Table name for Inovio Parmeters
     * @param string $name
     * @param integer $id
     * @param string $table
     * @return string
     */
    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table) {

        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Use to set request data to call Inovio APImethod 
     * @param   $cart: cart parameters
     * @return   array $request
     */
    public function cartParams($cart = null) {
        if (!class_exists('ShopFunctions')) {
            require VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php';
        }

        if (!class_exists('vmCrypt')) {
            require JPATH_VM_ADMINISTRATOR . DS . 'helpers' . DS . 'vmcrypt.php';
        }

        $session = JFactory::getSession();
        $sessionInovio = $session->get('inovio', 0, 'vm');
        $inovioData = (object) json_decode($sessionInovio, true);
        $request = [
            "bill_addr" => trim($cart->address_1 . ', ' . $cart->address_2),
            "pmt_numb" => trim(vmCrypt::decrypt($inovioData->ccNumber)),
            "pmt_key" => vmCrypt::decrypt($inovioData->ccCvv),
            "xtl_ip" => trim($cart->ip_address) == "xx" ? "127.0.0.1" : $cart->ip_address,
            "cust_fname" => $cart->first_name,
            "cust_lname" => $cart->last_name,
            "pmt_expiry" => $inovioData->ccExpireMonth . $inovioData->ccExpireYear,
            "cust_email" => $cart->email,
            "bill_addr_zip" => $cart->zip,
            "bill_addr_city" => $cart->city,
            "bill_addr_state" => shopFunctions::getStateByID($cart->virtuemart_state_id, "state_2_code"),
            "request_currency" => shopFunctions::getCurrencyByID($cart->order_currency, "currency_code_3"),
            "bill_addr_country" => shopFunctions::getCountryByID($cart->virtuemart_country_id, "country_2_code"),
            "ship_addr_zip" => $cart->shipping_postcode,
            "ship_addr" => $cart->address_1 . ',' . $cart->address_2,
            'ship_addr_city' => $cart->city,
            'ship_addr_zip' => $cart->zip,
            'ship_addr_state' => ShopFunctions::getStateByID($cart->virtuemart_state_id, "state_2_code"),
            'ship_addr_country' => ShopFunctions::getCountryByID($cart->virtuemart_country_id, "country_2_code"),
        ];

        return $request;
    }

    /**
     * For single product's qunantity should not be greater than 99
     * 
     */
    public function restrictQuantity($cart = null) {

        $returnstate = true;
        foreach ($cart->products as $pkey => $prow) {
            if ($prow->quantity > $this->getProductRestrictVal()) {
                $returnstate = false;
            }
        }
        return $returnstate;
    }

    /**
     * Use to get product Ids, price and quantity
     * @param object $cart: Using cart get the order related information
     * @return   array $finalArray
     */
    public function getproductParams($usrBT = array()) {

        $finalArray = array();

        $finalArray['li_prod_id_1'] = $this->getProductId(); // static because client has told that use only 41241 id
        $finalArray['li_count_1'] = 1;
        $finalArray['li_value_1'] = $usrBT->order_total;

        return $finalArray;
    }

    /**
     * Set method for core SDK and get response
     * @param   string $methodName to set methodname for core SDK
     * @param   array $requestParams to set request parameters for core SDK
     * @return   string $parseResult get result from core SDK
     */
    public function setApiMethodAndgetResponse($methodName = "", $requestParams = array()) {
        // Include inovio core SDK
        include 'bootstrap.php';

        $serviceConfig = new InovioServiceConfig($requestParams);
        $processor = new InovioProcessor($serviceConfig);
        $response = $processor->setMethodName($methodName)->getResponse();

        return $parseResult = json_decode($response);
    }

    /**
     * User to get inovio request params using $virtuemart_paymentmethod_id
     * @param   int $VirtuemartPaymentmethodId
     * @param   string $accessData
     * @return   array
     */
    public function getParamsByMethodId($VirtuemartPaymentmethodId = null, $accessData = "") {
        $query = "SELECT payment_params FROM `#__virtuemart_paymentmethods` WHERE  virtuemart_paymentmethod_id = '"
                . $VirtuemartPaymentmethodId . "'";
        $db = JFactory::getDBO();
        $db->setQuery($query);
        $params = $db->loadResult();
        $paymentParams = explode("|", $params);
        $paymentParamss = [];

        foreach ($paymentParams as $paymentParam) {
            $param = explode('=', $paymentParam);
            $paymentParamss[$param[0]] = substr($param[1], 1, -1);
        }

        // To set restrict product value
        $this->setProductRestrictVal($paymentParamss);

        // To set product Id from merhcant admin form
        $this->setProductId($paymentParamss);

        // Access data only for merchant information
        if ($accessData == "merchantinfo") {
            return $this->setGatewayInformation($paymentParamss);
        }

        // Check debug enable or not
        if ($accessData == "debuglog") {
            return $paymentParamss['inoviodebug'];
        }

        // Bind data when need merchant info and advance params
        return $this->setGatewayInformation($paymentParamss) + $this->getAdvaceparam($paymentParamss);
    }

    /**
     * Use to set restriction quantity
     * 
     * @param array $paymentParamss
     */
    public function setProductRestrictVal($paymentParam = array()) {
        $this->productRestriction = $paymentParam['inovio_product_quantity_restriction'];
        
    }

    /**
     * Use to get restriction quantity
     * @return numeric
     */
    public function getProductRestrictVal() {
        return $this->productRestriction;
        
    }

    /**
     * Set product id from configuration
     * 
     * @param array $paymentParamss
     */
    public function setProductId($paymentParam = array()) {
        $this->productId = $paymentParam['inovio_product_id'];
    }

    /**
     * Get product id from configuration
     * 
     * @param array $paymentParamss
     */
    public function getProductId() {
        return $this->productId;
    }

    /**
     * Use to get advance fields
     * @param   array $payment_paramss
     * @return   array
     */
    public function getAdvaceparam($paymentParamss = array()) {
        return
                array(
                    $paymentParamss['APIkey1'] => $paymentParamss['APIvalue1'],
                    $paymentParamss['APIkey2'] => $paymentParamss['APIvalue2'],
                    $paymentParamss['APIkey3'] => $paymentParamss['APIvalue3'],
                    $paymentParamss['APIkey4'] => $paymentParamss['APIvalue4'],
                    $paymentParamss['APIkey5'] => $paymentParamss['APIvalue5'],
                    $paymentParamss['APIkey6'] => $paymentParamss['APIvalue6'],
                    $paymentParamss['APIkey7'] => $paymentParamss['APIvalue7'],
                    $paymentParamss['APIkey8'] => $paymentParamss['APIvalue8'],
                    $paymentParamss['APIkey9'] => $paymentParamss['APIvalue9'],
                    $paymentParamss['APIkey10'] => $paymentParamss['APIvalue10'],
        );
    }

    /**
     * Use to set Inovio initial required parameters
     * @param   array $payment_paramss
     * @return   array
     */
    public function setGatewayInformation($paymentParamss = array()) {
        $finalRequestParams = [];
        $requestParams = array(
            'end_point' => str_replace("\/", "/", $paymentParamss['api_url']),
            'site_id' => $paymentParamss['site_id'],
            'req_username' => $paymentParamss['username'],
            'req_password' => $paymentParamss['password'],
            'request_response_format' => 'json',
        );

        foreach ($requestParams as $reqKey => $reqParamVal) {
            if (!isset($requestParams[$reqKey])) {
                throw new Exception("Something went wrong, please contact to your service provider");
                exit;
            }

            $finalRequestParams[$reqKey] = trim($reqParamVal);
        }

        return $finalRequestParams;
    }

    /**
     * Add custom js for checkout page
     * @returrn NULL nothing to return
     */
    public function loadScript() {
        define('VMINOVIOPLUGINWEBROOT', 'plugins/vmpayment/inovio');
        $assetsPath = JURI::root() . VMINOVIOPLUGINWEBROOT . '/inovio/assets/js/inovio_custom.js';
        $document = JFactory::getDocument();
        $document->addScript($assetsPath);
    }

    /**
     * Order status changed
     * @param   Object   $order
     * @param   string   $old_order_status
     * @return   boolean|null return boolean
     */
    public function plgVmOnUpdateOrderPayment(&$order, $oldOrderStatus) {
        // Load the method
        if (!($this->_currentMethod = $this->getVmPluginMethod($order->virtuemart_paymentmethod_id))) {
            // Another method was selected, do nothing
            return null;
        }

        if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
            // Another method was selected, do nothing
            return null;
        }

        // Check order status confirm by user
        if (isset($oldOrderStatus) && $oldOrderStatus == 'U') {

            $db = JFactory::getDBO();

            $query = 'SELECT transaction_id,po_id from `#__virtuemart_payment_plg_inovio` WHERE virtuemart_order_id=' .
                    $order->virtuemart_order_id;
            $db->setQuery($query);

            //  Get data from inovio custom table
            $resultset = $db->loadObjectList();
            $refundParams = array_merge(
                    $this->getParamsByMethodId($order->virtuemart_paymentmethod_id, "merchantinfo"), array(
                'request_ref_po_id' => $resultset[0]->po_id,
                'credit_on_fail' => 1,
                'li_value_1' => $order->order_total
                )
            );
            $parseResult = $this->setApiMethodAndgetResponse('ccreverse', $refundParams);

            if (isset($resultset[0]->po_id) && !empty($parseResult->TRANS_ID) && !empty($parseResult->PO_ID) &&
                    $parseResult->PO_ID == $resultset[0]->po_id) {

                if (!class_exists('ShopFunctions')) {
                    require VMPATH_ADMIN . DS . 'helpers' . DS . 'shopfunctions.php';
                }

                // Update history table after refunded
                $queryH = $db->getQuery(true);
                $comment = 'Refund Transaction_Id: ' . $parseResult->TRANS_ID . '<br> '
                        . 'Order Total: ' . round($order->order_total, 2) . ' ' . shopFunctions::getCurrencyByID($order->order_currency, "currency_code_3") .
                        '<br>Refunded Amount:' . round($order->order_total, 2) . ' ' . shopFunctions::getCurrencyByID($order->order_currency, "currency_code_3");
                // For current datetime
                $date = JFactory::getDate();
                $columns = array('virtuemart_order_id', 'order_status_code', 'customer_notified', 'comments', 'published', 'created_on', 'created_by', 'modified_on', 'modified_by');
                $values = array($order->virtuemart_order_id, $db->quote('R'), 0, $db->quote($comment), 1, $db->quote($date->format(JDate::$format)), (int) $order->virtuemart_user_id, $db->quote($date->format(JDate::$format)), (int) $order->virtuemart_user_id);
                $queryH
                        ->insert($db->quoteName('#__virtuemart_order_histories'))
                        ->columns($db->quoteName($columns))
                        ->values(implode(',', $values));
                $db->setQuery($queryH);
                $db->execute();

                // Update order status
                $queryU = $db->getQuery(true);
                $fields = array(
                    $db->quoteName('order_status') . ' = ' . $db->quote('R')
                );
                $conditions = array(
                    $db->quoteName('virtuemart_order_id') . ' = ' . $order->virtuemart_order_id
                );
                $queryU->update($db->quoteName('#__virtuemart_orders'))->set($fields)->where($conditions);
                $db->setQuery($queryU);
                $db->execute();
                if ($this->getParamsByMethodId($order->virtuemart_paymentmethod_id, "debuglog")) {
                    $this->logInfo(print_r($parseResult, true), "Successful Reverse Response", true);
                }
            } else {
                if ($this->getParamsByMethodId($order->virtuemart_paymentmethod_id, "debuglog")) {
                    $this->logInfo(print_r($parseResult, true), "Error Reverse Response", true);
                }
            }
        }

        return true;
    }

}
