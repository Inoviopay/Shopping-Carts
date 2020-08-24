<?php

/**
 * Inovio Payment method use make payment and extends with prestashop core module payment Module
 *  @author Chetu Developer
 *  @copyright  By Chetu Developer 2017-18
 */
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

require_once(_PS_ROOT_DIR_ . '/modules/ps_inoviopayment/component/inoviovalidate.php');

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_Inoviopayment extends PaymentModule
{

    protected $_html = '';
    protected $_postErrors = array();
    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    const FLAG_DISPLAY_PAYMENT_INVITE = 'INOVIO_PAYMENT_INVITE';

  /**
   * Set default value for Ps_Inoviopayment
   */
    public function __construct()
    {
      // Define paymentname with inoviopayment
        $this->name = 'ps_inoviopayment';
      // It will display in Payment tab in admin section
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
      // Prestashop version support 1.7
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Inovio Team';
        $this->controllers = array('validation');
      // Check compatibility
        $this->is_eu_compatible = 1;
      // Currency True
        $this->currencies = true;
      // Currency Mode checkbox
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;

      // Inherit Parent functionality from payment module
        parent::__construct();
      // Display Name
        $this->displayName = $this->l('Inovio Payment');
        $this->confirmUninstall = $this->trans('Are you sure about removing these details?', array(), 'Modules.Inoviopayment.Admin');
        $this->description = $this->l('Inovio Payment method is custom Payment method to make payment');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

  /**
   * Check necessary method on installation
   * @return boolean
   */
    public function install()
    {

        Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, true);
      // Create custom table
        $this->CreateInoviotable();

        $this->warning = null;
      // Register Hooks with prestashop
        if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn') ||
            !$this->registerHook('displayHeader') || !$this->registerHook('adminOrder')) {
            $this->warning = $this->l('Something went wrong please check your module');
            return false;
        }
        if (is_null($this->warning) && !function_exists('curl_init')) {
            $this->warning = $this->l('cURL is required to use this module. Please install the php extention cURL.');
        }
        return true;
    }

  /**
   * Fields to create the payment table
   * @return array SQL Fileds
   */
    public function CreateInoviotable()
    {
        $SQLfields = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "inovio_payment_details`(
            inovio_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            transaction_id int(11),
            po_id varchar(64),
            ps_order_id int(20)
        )";
        if (!Db::getInstance()->Execute($SQLfields)) {
            return false;
        }
    }

  /**
   *
   *
   * @param array $params
   * @return array
   */
    public function hookPaymentReturn($params)
    {
        if (!$this->active || !Configuration::get(self::FLAG_DISPLAY_PAYMENT_INVITE)) {
            return;
        }

        $state = $params['order']->getCurrentState();
        if (in_array(
            $state,
            array(
                Configuration::get('INOVIO_PAY_ORDERSTATE')
                    )
        )) {
            $inovioDetails = Tools::nl2br($this->details);
            if (!$inovioDetails) {
                $inovioDetails = '___________';
            }

            $inovioAddress = Tools::nl2br($this->address);
            if (!$inovioAddress) {
                $inovioAddress = '___________';
            }

            $this->smarty->assign(array(
            'shop_name' => $this->context->shop->name,
            'total' => Tools::displayPrice(
                $params['order']->getOrdersTotalPaid(),
                new Currency($params['order']->id_currency),
                false
            ),
            'inovioDetails' => $inovioDetails,
            'inovioAddress' => $inovioAddress,
            'status' => 'ok',
            'reference' => $params['order']->reference,
            'contact_url' => $this->context->link->getPageLink('contact', true)
            ));
        } else {
            $this->smarty->assign(
                array(
                  'status' => 'failed',
                  'contact_url' => $this->context->link->getPageLink('contact', true),
                )
            );
        }

        return $this->fetch('module:ps_inoviopayment/views/templates/hook/payment_return.tpl');
    }

  /**
   * Remove fields from database on uninstall module.
   * @return boolean
   */
    public function uninstall()
    {
        $delInovioTable = "DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "inovio_payment_details`";
        if (!Db::getInstance()->Execute($delInovioTable)) {
            return false;
        }
      // Remove Configure data on uinstall
        if ($this->inovioConfigEvents($this->inoviofieldsData(), 'removeConfig') ||
            !Configuration::deleteByName(self::FLAG_DISPLAY_PAYMENT_INVITE) ||
            !parent::uninstall()) {
            return false;
        }
        return true;
    }

  /**
   * Show inovio payme module's payment option and get cart parameters from checkout page.
   * @param  $params
   * @return array
   */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $payment_options = [
        $this->getEmbeddedPaymentOption(),
        ];
        return $payment_options;
    }

  /**
   * Check currency to make an order.
   * @param Object $cart
   * @return boolean
   */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

  /**
   * Show credit card form on checkout page.
   * @return PaymentOption
   */
    public function getEmbeddedPaymentOption()
    {

        $embeddedOption = new PaymentOption();

        $this->context->smarty->assign([
        'description' => Configuration::get('INOVIO_DESCRIPTION')
        ]);
        $cardsource = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/logo.png');

        $embeddedOption->setCallToActionText($this->l('Inovio Payment'))
            ->setForm($this->generateForm())
            ->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:ps_inoviopayment/views/templates/front/payment_infos.tpl'
                )
            )
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/cards.png'));

        return $embeddedOption;
    }

  /**
   * Set month and year for checkout page and merge with credit card form fields.
   * @return array set month and year for checkout page
   */
    protected function generateForm()
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = sprintf("%02d", $i);
        }
        $years = [];
        for ($i = 0; $i <= 10; $i++) {
            $years[] = date('Y', strtotime('+' . $i . ' years'));
        }
        $this->context->smarty->assign([
        'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true),
        'months' => $months,
        'years' => $years,
        'productvalidation' => Configuration::get('INOVIO_PRODUCT_QAUNTITY_RESTRICTION')
        ]);

        return $this->context->smarty->fetch('module:ps_inoviopayment/views/templates/front/payment_form.tpl');
    }

  /**
   * Validate merchant configuration form.
   */
    protected function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(self::FLAG_DISPLAY_PAYMENT_INVITE, Tools::getValue(self::FLAG_DISPLAY_PAYMENT_INVITE));

            if (!Tools::getValue('INOVIO_SITE_ID')) {
                $this->_postErrors[] = $this->trans('Site Id is required.', array(), 'Modules.Inoviopayment.Admin');
            }
            if (!Tools::getValue('INOVIO_DESCRIPTION')) {
                $this->_postErrors[] = $this->trans('Description is required.', array(), 'Modules.Inoviopayment.Admin');
            }
            if (!Tools::getValue('INOVIO_API_ENDPOINT')) {
                $this->_postErrors[] = $this->trans('API End Point is required.', array(), "Modules.Inoviopayment.Admin");
            }
            if (!Tools::getValue('INOVIO_API_USERNAME')) {
                $this->_postErrors[] = $this->trans('API Username is required.', array(), "Modules.Inoviopayment.Admin");
            }
            if (!Tools::getValue('INOVIO_API_PASSWORD')) {
                $this->_postErrors[] = $this->trans('API Password is required.', array(), "Modules.Inoviopayment.Admin");
            }
        }
    }

  /**
   * Update merchant form data for inovio configuration.
   */
    public function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
          // Update fields.
            $this->inovioConfigEvents($this->inoviofieldsData(), 'update');
        }
        $this->_html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Global'));
    }

  /**
   * It will work on event base update, getConfiguration data and remove Configuration data
   *
   * @param array $fields
   * @param string $events
   * @return array
   */
    public function inovioConfigEvents($fields = array(), $events = "")
    {
        if ($events == 'update') {
            return $this->updateInovioValue($fields);
        }
        if ($events == 'getConfigValues') {
            return $this->getConfigValues($fields);
        }
        if ($events == 'removeConfig') {
            return $this->removeConfigValue($fields);
        }
    }

  /**
   * Inovio Form data
   *
   * @return array
   */
    public function inoviofieldsData()
    {
        return array(
        'INOVIO_SITE_ID', 'INOVIO_DESCRIPTION', 'INOVIO_API_ENDPOINT', 'INOVIO_API_ENDPOINT', 'INOVIO_API_USERNAME',
        'INOVIO_API_PASSWORD', 'INOVIO_PRODUCT_ID','INOVIO_PRODUCT_QAUNTITY_RESTRICTION', 'INOVIO_LOG', 'INOVIO_ADVANCE_KEY_1',
        'INOVIO_ADVANCE_KEY_1', 'INOVIO_ADVANCE_VALUE_1','INOVIO_ADVANCE_KEY_2', 'INOVIO_ADVANCE_VALUE_2',
        'INOVIO_ADVANCE_KEY_3', 'INOVIO_ADVANCE_VALUE_3','INOVIO_ADVANCE_KEY_4', 'INOVIO_ADVANCE_VALUE_4',
        'INOVIO_ADVANCE_KEY_5', 'INOVIO_ADVANCE_VALUE_5','INOVIO_ADVANCE_KEY_6', 'INOVIO_ADVANCE_VALUE_6',
        'INOVIO_ADVANCE_KEY_7', 'INOVIO_ADVANCE_VALUE_7','INOVIO_ADVANCE_KEY_8', 'INOVIO_ADVANCE_VALUE_8',
        'INOVIO_ADVANCE_KEY_9', 'INOVIO_ADVANCE_VALUE_9','INOVIO_ADVANCE_KEY_10', 'INOVIO_ADVANCE_VALUE_10',
        );
    }

  /**
   *
   * update form fields value from configuration
   *
   * @param array $updatefields
   */
    public function updateInovioValue($updatefields = array())
    {
        foreach ($updatefields as $updateValues) {
            Configuration::updateValue($updateValues, Tools::getValue($updateValues));
        }
    }

  /**
   *
   * Get form fields value from configuration
   *
   * @param array $configValues
   */
    public function getConfigValues($configValues = array())
    {
        $configValue = [];
        foreach ($configValues as $getconfigValues) {
            $configValue[$getconfigValues] = Tools::getValue($getconfigValues, Configuration::get($getconfigValues));
        }
        return $configValue;
    }

  /**
   * Remove fields value from configuration
   *
   * @param array $removeConfigValues
   */
    public function removeConfigValue($removeConfigValues = array())
    {
        foreach ($removeConfigValues as $removeConfigval) {
            !Configuration::deleteByName($removeConfigval);
        }
    }

  /**
   * Set configuration for inovio payment extension.
   * @return string set configuration form in admin section
   */
    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->_postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->_postErrors as $err) {
                    $this->_html .= $this->displayError($err);
                }
            }
        } else {
            $this->_html .= '<br />';
        }
        $this->_html .= $this->merchantSettingForm();

        return $this->_html;
    }

  /**
   * Set inovio merchant setting form in admin.
   * @return string set merchant setting form
   */
    public function merchantSettingForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? : 0;
        $this->fields_form = array();
        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure='
            . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
        'fields_value' => $this->inovioConfigEvents($this->inoviofieldsData(), 'getConfigValues'),
        'languages' => $this->context->controller->getLanguages(),
        'id_language' => $this->context->language->id
        );

        return $helper->generateForm(array($this->basicformfields(), $this->advanceformFields()));
    }

  /**
   * Set merchant setting form fields.
   * @return array return basic merchant setting fields
   */
    public function basicformfields()
    {
        return array(
        'form' => array(
            'legend' => array(
                'title' => $this->trans('Merchant Information', array(), 'Modules.Inoviopayment.Admin'),
                'icon' => 'icon-envelope'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->trans('Site id', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_SITE_ID',
                    'required' => true,
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->trans('Description', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_DESCRIPTION',
                    'required' => true,
                    'desc' => $this->trans('Description will show on checkout page .', array(), 'Modules.Inoviopayment.Admin'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Api End Point', array(), 'Module.Inoviopayment.Admin'),
                    'name' => 'INOVIO_API_ENDPOINT',
                    'required' => true,
                    'desc' => $this->trans('Inovio API End Point URL.', array(), 'Modules.Inoviopayment.Admin'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Username', array(), 'Module.Inoviopayment.Admin'),
                    'name' => 'INOVIO_API_USERNAME',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Password', array(), 'Module.Inoviopayment.Admin'),
                    'name' => 'INOVIO_API_PASSWORD',
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans("Maximum qauntity to purchase for single product", array(), 'Module.Inoviopayment.Admin'),
                    'name' => 'INOVIO_PRODUCT_QAUNTITY_RESTRICTION',
                    'desc' => $this->trans('API restriction for qauntity to purchase any single product', array(), 'Modules.Inoviopayment.Admin'),
                    'required' => true,
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Set Product Id for all products', array(), 'Module.Inoviopayment.Admin'),
                    'name' => 'INOVIO_PRODUCT_ID',
                    'required' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Log Enable/Disable', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_LOG',
                    'is_bool' => true,
                    'hint' => $this->trans('Your country\'s legislation may require you to send the invitation to pay by email only. Disabling the option will hide the invitation on the confirmation page.', array(), 'Modules.Wirepayment.Admin'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => true,
                            'label' => $this->trans('Enabled', array(), 'Admin.Global'),
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => false,
                            'label' => $this->trans('Disabled', array(), 'Admin.Global'),
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Admin.Actions'),
            )
        ),
        );
    }

  /**
   * Advance form fields of merchant setting form.
   * @return array
   */
    public function advanceformFields()
    {
        return array(
        'form' => array(
            'legend' => array(
                'title' => $this->trans('Advance Parameters', array(), 'Modules.Inoviopayment.Admin'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 1', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_1',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 1', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_1',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 2', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_2',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 2', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_2',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 3', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_3',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 3', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_3',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 4', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_4',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 4', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_4',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 5', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_5',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 5', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_5',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 6', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_6',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 6', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_6',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 7', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_7',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API VALUE 7', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_7',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 8', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_8',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 8', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_8',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 9', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_9',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 9', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_9',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Key 10', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_KEY_10',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('API Value 10', array(), 'Modules.Inoviopayment.Admin'),
                    'name' => 'INOVIO_ADVANCE_VALUE_10',
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Admin.Actions'),
            )
        ),
        );
    }

  /**
   * Load CSS and JS into HTML Head-tag.
   */
    public function hookdisplayHeader()
    {
        if (!$this->active) {
            return;
        }
        if ($this->name == 'ps_inoviopayment') {
            $this->context->controller->addJS(__PS_BASE_URI__ . 'modules/ps_inoviopayment/js/inovio_checkout.js');
        }

        if ($this->name == 'ps_inoviopayment') {
            $error = !empty(Tools::getValue('inovioError')) ? Tools::getValue('inovioError') : '';
            $errors = !empty(Tools::getValue('inovioerrors')) ? Tools::getValue('inovioerrors') : '';
            if ($error) {
                $this->context->controller->addJS(__PS_BASE_URI__ . 'modules/ps_inoviopayment/js/inovio_error_checkout.js');
            }
            if ($errors) {
                $this->context->controller->addJS(__PS_BASE_URI__ . 'modules/ps_inoviopayment/js/inovio_quantity_restrict.js');
            }
        }
    }

  /*
   * @brief hook handle the request for standrd refund and products return.
   * @param array $params this is hold the detail of order from order_confirmation controler.
   */

    public function hookadminOrder($params)
    {
    
        $order_id = Tools::getValue('id_order');
        $statusQuery = "select id_order_state from `"._DB_PREFIX_."order_history`"
            . "where id_order='".$order_id."' order by id_order_state desc";
        $currentStatus = Db::getInstance()->getValue($statusQuery);
    
        if ($currentStatus == 7) { // if order want to reversed
            $order = new Order((int) $order_id);


      
            $total_paid_amount = $order->total_paid_real;
          /*echo "<pre>";
        print_r($_REQUEST);
          die('testing time');*/

            if ($this->processRefund($order->id_cart, $total_paid_amount) == true) {
                $order = new Order($order_id);
                $history = new OrderHistory();
                $history->id_order = (int) $order->id;
                // Set Order status=7
                $history->changeIdOrderState($currentStatus, (int) $order->id);
                $history->add(true);
            } else {
                $this->errors[] = $this->trans('unable to reverse order.', array(), 'Admin.Orderscustomers.Notification');
                return false;
            }
        }
    }

  /**
   * Process refund functionality if gateway supoorted refund.
   *
   * @param  int    $order_id
   * @param  float  $amount
   * @param  string $reason
   * @return bool     True or FALSE based on success, or a WP_Error object
   */
    public function processRefund($order_id, $amount = 0)
    {
        $inovioValidated = new InovioValidation();
        if (0.00 == $amount) {
            return false;
        }
      // Merge params
        $params = array_merge(
            $inovioValidated->setGatewayInformation(),
            array(
            'request_ref_po_id' => $this->getTransactionId($order_id),
            'credit_on_fail' => 1
            )
        );
  
        $reverserResonse = $inovioValidated->setApiMethodAndgetResponse($params, 'ccreverse');
        if (!empty($reverserResonse->TRANS_ID) && !empty($reverserResonse->PO_ID) && $reverserResonse->PO_ID == $this->getTransactionId($order_id)) {
          // Add log on refund
            if (Configuration::get('INOVIO_LOG') == 1) {
                PrestaShopLoggerCore::addLog("Successful refunded parametrs");
                PrestaShopLoggerCore::addLog(print_r($reverserResonse, true));
            }
            return true;
        } else {
            return false;
        }
    }

  /**
   * Get transaction id from inovio_payment_details table
   *
   * @param integer $order_id
   * @return array $getData
   */
    public function getTransactionId($order_id)
    {
        $custQuery = "select po_id from `" . _DB_PREFIX_ . "inovio_payment_details` where "
            . "ps_order_id=" . $order_id;
        $getData = Db::getInstance()->getRow($custQuery, false);
        return $getData['po_id'];
    }
}
