<?php

/*
  $Id$
  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com
  Copyright (c) 2014 osCommerce
  Released under the GNU General Public License
 */

/**
 * Class inovio_cc
 * Use to create inovio payment method
 */
class inovio_cc {

    public $code, $title, $description, $enabled, $_check;

    public function __construct() {
        global $HTTP_GET_VARS, $PHP_SELF, $order;

        $this->signature = 'inovio|inovio_cc|2.0|2.3';
        $this->api_version = '2.0';

        $this->code = 'inovio_cc';
        $this->title = MODULE_PAYMENT_INOVIO_CC_TITLES;
        $this->public_title = empty(MODULE_PAYMENT_INOVIO_CC_TITLE) ? MODULE_PAYMENT_INOVIO_CC_TITLES : MODULE_PAYMENT_INOVIO_CC_TITLE;
        $this->description = MODULE_PAYMENT_INOVIO_CC_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_INOVIO_CC_SORT_ORDER') ? MODULE_PAYMENT_INOVIO_CC_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_INOVIO_CC_STATUS') && (MODULE_PAYMENT_INOVIO_CC_STATUS == 'True') ? true : false;
        $this->inovio_refund_table = "inovio_refunded";
        // Check module is enabled or not
        if (defined('MODULE_PAYMENT_INOVIO_CC_STATUS')) {
            $this->title;
        }
        // Check curl enable or not
        if (!function_exists('curl_init')) {
            $this->description = '<div class="secWarning">' . MODULE_PAYMENT_INOVIO_CC_ERROR_ADMIN_CURL . '</div>' . $this->description;
            $this->enabled = false;
        }
    }

    /**
     * Use to set request data to call Inovio APImethod
     *
     * @return array $request
     */
    public function prepare_request_data() {
        global $HTTP_POST_VARS, $order;

        return $request = [
            "bill_addr" => $order->billing['street_address'],
            "pmt_numb" => $HTTP_POST_VARS['inovio_cc_number_nh-dns'],
            "pmt_key" => $HTTP_POST_VARS['inovio_cc_ccv_nh-dns'],
            "xtl_ip" => !empty(tep_get_ip_address()) ? tep_get_ip_address() : '127.0.0.1',
            "cust_fname" => $order->billing['firstname'],
            "pmt_expiry" => $HTTP_POST_VARS['inovio_cc_expires_month'] . $HTTP_POST_VARS['inovio_cc_expires_year'],
            "cust_email" => $order->customer['email_address'],
            "bill_addr_zip" => $order->billing['postcode'],
            "bill_addr_city" => $order->billing['city'],
            "bill_addr_state" => tep_get_zone_code(
                    $order->billing['country_id'], $order->billing['zone_id'], $order->billing['state']
            ),
            "request_currency" => $order->info['currency'],
            "bill_addr_country" => $order->billing['country']['iso_code_2'],
            "ship_addr_country" => $order->delivery['country']['iso_code_2'],
            "ship_addr_city" => $order->delivery['city'],
            "ship_addr_state" => tep_get_zone_code(
                    $order->delivery['country_id'], $order->delivery['zone_id'], $order->delivery['state']
            ),
            "ship_addr_zip" => $order->delivery['postcode'],
            "ship_addr" => $order->delivery['street_address'],
        ];
    }

    /**
     * Use to get advance fields
     *
     * @return array $advanceParams
     */
    public function get_advance_param() {
        return $advanceParams = [
            INOVIO_ADVANCE_KEY_1 => INOVIO_ADVANCE_VALUE_1,
            INOVIO_ADVANCE_KEY_2 => INOVIO_ADVANCE_VALUE_2,
            INOVIO_ADVANCE_KEY_3 => INOVIO_ADVANCE_VALUE_3,
            INOVIO_ADVANCE_KEY_4 => INOVIO_ADVANCE_VALUE_4,
            INOVIO_ADVANCE_KEY_5 => INOVIO_ADVANCE_VALUE_5,
            INOVIO_ADVANCE_KEY_6 => INOVIO_ADVANCE_VALUE_6,
            INOVIO_ADVANCE_KEY_7 => INOVIO_ADVANCE_VALUE_7,
            INOVIO_ADVANCE_KEY_8 => INOVIO_ADVANCE_VALUE_8,
            INOVIO_ADVANCE_KEY_9 => INOVIO_ADVANCE_VALUE_9,
            INOVIO_ADVANCE_KEY_10 => INOVIO_ADVANCE_VALUE_10
        ];
    }

    /**
     * Use to get product Ids, price and quantity
     *
     * @return array $final_array
     */
    public function get_product_data() {
        global $order;

        $final_array = [];

        $final_array['li_prod_id_1'] = MODULE_PAYMENT_INOVIO_CC_PRODUCT_ID; // static because client has told that use only 41241 id
        $final_array['li_count_1'] = 1;
        $final_array['li_value_1'] = $order->info['total'];

        return $final_array;
    }

    /**
     * For single product's qunantity should not be greater than 99
     *
     * @return boolean $returnstate
     */
    public function restrict_quantity() {

        global $order;

        $returnstate = false;
        if ($order->products > 0) {
            $returnstate = true;
            foreach ($order->products as $cart_item) {
                if ($cart_item['qty'] > MODULE_PAYMENT_INOVIO_CC_PRODUCT_QUANTITY_RESTRICTION) {
                    $returnstate = false;
                }
            }
        }

        return $returnstate;
    }

    /**
     * Use to set Inovio initial requeired parameters
     *
     * @return arrray $requestParams
     */
    public function get_gateway_information() {


        $requestParams = [
            'end_point' => MODULE_PAYMENT_INOVIO_CC_API_ENDPOINT,
            'site_id' => MODULE_PAYMENT_INOVIO_CC_SITE_ID,
            'req_username' => MODULE_PAYMENT_INOVIO_CC_USERNAME,
            'req_password' => MODULE_PAYMENT_INOVIO_CC_PASSWORD,
            'request_response_format' => 'json'
        ];
        $finalRequestParams = [];

        foreach ($requestParams as $reqKey => $reqParamVal) {
            if (empty($requestParams[$reqKey])) {
                throw new Exception("Something went wrong, please contact to your service provider");
                exit;
            }

            $finalRequestParams[$reqKey] = trim($reqParamVal);
        }

        return $finalRequestParams;
    }

    /**
     * This function outputs the payment method title/text and if required, the input fields.
     *
     * @return array
     */
    public function selection() {
        return array(
            'id' => $this->code,
            'module' => $this->public_title . "<img src ='" . DIR_WS_IMAGES . "inovio_cards.png' >"
        );
    }

    /**
     * Use this function implement any checks of any conditions after payment method has been selected.
     *
     * @return boolean
     */
    public function pre_confirmation_check() {
        return false;
    }

    /**
     * Show credit card form on checkout page.
     *
     * @global Object $order
     * @return array $confirmation
     */
    public function confirmation() {

        global $order;

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => sprintf('%02d', $i));
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $expires_year[] = array('id' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        $confirmation = array('fields' => array(
                array(
                    'field' => tep_draw_input_field(
                            '', '', ''
                            . " maxlength='4' readonly", "hidden"
                    )
                ),
                array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_NUMBER,
                    'field' => tep_draw_input_field(
                            'inovio_cc_number_nh-dns', '', 'id="inovio_cc_number_nh-dns"'
                            . ' autocomplete="off" minlength="12" maxlength="16"'
                            . ' required', "text"
                    )
                ),
                array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_EXPIRES,
                    'field' => tep_draw_pull_down_menu(
                            'inovio_cc_expires_month', $expires_month
                    ) . '&nbsp;' .
                    tep_draw_pull_down_menu(
                            'inovio_cc_expires_year', $expires_year
                    )
                ),
                array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_CCV,
                    'field' => tep_draw_input_field(
                            'inovio_cc_ccv_nh-dns', '', 'id="inovio_cc_ccv_nh-dns"'
                            . "minlength='3' maxlength='4' required", "password"
                    )
                )
        ));
        // Add inovio custom js for checkout page.
        $this->add_custom_inoviojs();

        return $confirmation;
    }

    /**
     * Outputs the html form hidden elements sent as POST data to the payment gateway.
     *
     * @return boolean
     */
    public function process_button() {
        return false;
    }

    /**
     * This is where you will implement any payment verification. This function can be quite complicated to implement
     */
    public function before_process() {
        global $order;
        // Do merchant authentication
        try {
            // Do credit card validation
            $this->credit_card_validation();

            // Restrict product's quantity
            if ($this->restrict_quantity() == false) {
                throw new Exception("For any single product quantity should not be greater than "
                . MODULE_PAYMENT_INOVIO_CC_PRODUCT_QUANTITY_RESTRICTION . ", please click"
                . " on cart contents to update cart");
            }
            // Merchant authentication
            if ($this->merchant_authenticate() == false) {
                throw new Exception('Something went wrong, Please contact to your service provider');
            }
            // Do auth and capture validation
            if ($this->auth_capture_process() != true) {
                throw new Exception('Something went wrong, Please contact to tour service provider');
            }
            // Set order status processing
            $order->info['order_status'] = 2;
        } catch (Exception $ex) {
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . $ex->getMessage(), 'SSL'));
        }
    }

    /**
     * It is used to implement any post proessing of the payment/order after the order has been finalised.
     *
     * @global type $response
     * @global Object $order
     * @global int $insert_id
     */
    public function after_process() {

        global $response, $order, $insert_id, $cart;

        $order_total = $order->info['subtotal'];
        $prod_id = preg_replace("/[^0-9]/", "", $response[3]);

        // Insert data in inovio_refunded table
        $refund_data = array(
            "inovio_order_id" => $insert_id,
            "inovio_api_po_id" => $prod_id,
            "total_order_amount" => $order_total
        );
        tep_db_perform($this->inovio_refund_table, $refund_data);

        $sql_data_array = array('orders_id' => $insert_id,
            'orders_status_id' => 2,
            'date_added' => 'now()',
            'customer_notified' => '0',
            'comments' => implode("\n", $response)
        );

        tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

        $cart->reset(true);
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL'));
    }

    /**
     * Validate credit card details before sending to payment
     *
     * @global $HTTP_POST_VARS
     */
    public function credit_card_validation() {
        global $HTTP_POST_VARS;

        if (empty($HTTP_POST_VARS['inovio_cc_number_nh-dns'])) {
            throw new Exception('cc_number');
        } elseif (!empty($HTTP_POST_VARS['inovio_cc_number_nh-dns']) &&
                strlen($HTTP_POST_VARS['inovio_cc_number_nh-dns']) < 12 || strlen($HTTP_POST_VARS['inovio_cc_number_nh-dns']) > 16) {
            throw new Exception('invalid_credit_card');
        } elseif (empty($HTTP_POST_VARS['inovio_cc_expires_month']) || empty($HTTP_POST_VARS['inovio_cc_expires_year'])) {
            throw new Exception('empty_expiry_date');
        } elseif ($this->validate_expirydate($HTTP_POST_VARS['inovio_cc_expires_year'] . $HTTP_POST_VARS['inovio_cc_expires_month']) == false) {
            throw new Exception('invalid_expiry_date');
        } elseif (empty($HTTP_POST_VARS['inovio_cc_ccv_nh-dns'])) {
            throw new Exception('empty_cvv_number');
        }
    }

    /**
     * Use to check credit card expiration date
     *
     * @param string $card_data
     * @return boolean
     */
    public function validate_expirydate($card_date = null) {

        $today = date('Ym');
        $today_dt = new DateTime($today);
        $expire_dt = new DateTime($card_date);

        if ($expire_dt < $today_dt) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Standard functionlity for osCommerce to see if the module is installed.
     *
     * @return boolean $this->_check
     */
    public function check() {

        if (!isset($this->_check)) {
            $check_query = tep_db_query("SELECT configuration_value from " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_INOVIO_CC_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    /**
     * This is where you define module's configurations (displayed in admin).
     *
     * @param array $parameter
     */
    public function install($parameter = null) {
        $params = $this->get_params();

        if (isset($parameter)) {
            if (isset($params[$parameter])) {
                $params = array($parameter => $params[$parameter]);
            } else {
                $params = array();
            }
        }

        foreach ($params as $key => $data) {
            $sql_data_array = array(
                'configuration_title' => $data['title'],
                'configuration_key' => $key,
                'configuration_value' => (isset($data['value']) ? $data['value'] : ''),
                'configuration_description' => $data['desc'],
                'configuration_group_id' => '6',
                'sort_order' => '0',
                'date_added' => 'now()'
            );

            if (isset($data['set_func'])) {
                $sql_data_array['set_function'] = $data['set_func'];
            }

            if (isset($data['use_func'])) {
                $sql_data_array['set_function'] = $data['use_func'];
            }
            tep_db_perform(TABLE_CONFIGURATION, $sql_data_array);
        }
        $this->create_inovio_table();
    }

    /**
     * Function is use to create table on activate inovio payment plugin
     *
     */
    public function create_inovio_table() {

        $sql = "CREATE TABLE IF NOT EXISTS " . $this->inovio_refund_table . " (
                  id bigint(20) NOT NULL AUTO_INCREMENT,
                  inovio_order_id bigint(20) NOT NULL,
                  inovio_api_po_id varchar(256),
                  total_order_amount varchar(256),
                  PRIMARY KEY  (id)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        tep_db_query($sql);
    }

    /**
     * Get error message
     *
     * @global $HTTP_GET_VARS
     */
    public function get_error() {
        global $HTTP_GET_VARS;

        return $this->set_error_message($HTTP_GET_VARS['error']);
    }

    /**
     * Set error message for checkout page.
     *
     * @param type $error_message
     * @return type
     */
    public function set_error_message($error_message = array()) {

        switch ($error_message) {
            case 'cc_number':
                $error_message = MODULE_PAYMENT_INOVIO_CC_EMPTY_CREDIT_CARD;
                break;
            case 'invalid_credit_card':
                $error_message = MODULE_PAYMENT_INOVIO_CC_INVALID_CREDIT_CARD;
                break;
            case 'empty_expiry_date':
                $error_message = MODULE_PAYMENT_INOVIO_CC_EMPTY_EXPIRY_DATE;
                break;
            case 'invalid_expiry_date':
                $error_message = MODULE_PAYMENT_INOVIO_CC_INVALID_EXPIRY_DATE;
                break;
            case 'empty_cvv_number':
                $error_message = MODULE_PAYMENT_INOVIO_CC_EMPTY_CVV;
                break;
        }

        $error = array('title' => MODULE_PAYMENT_INOVIO_CC_ERROR_TITLE,
            'error' => $error_message);

        return $error;
    }

    /**
     * Standard functionality to uninstall the module.
     *
     */
    public function remove() {
        tep_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("DROP TABLE IF EXISTS " . $this->inovio_refund_table);
    }

    /**
     * This array must include all the configuration setting keys defined in your install() function.
     *
     * @return array $keys
     */
    public function keys() {
        $keys = array_keys($this->get_params());

        if ($this->check()) {
            foreach ($keys as $key) {
                if (!defined($key)) {
                    $this->install($key);
                }
            }
        }

        return $keys;
    }

    /**
     * Set the configuration value from admin section.
     *
     * @return array $params
     */
    public function get_params() {
        $check_query = tep_db_query("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Inovio refund' limit 1");
        $check_row = tep_db_num_rows($check_query);
        if ($check_row < 1) {
            $status_query = tep_db_query("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);
            $status = tep_db_fetch_array($status_query);

            $status_id = $status['status_id'] + 1;

            $languages = tep_get_languages();
            if ($languages > 0) {
                foreach ($languages as $lang) {
                    tep_db_query("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) "
                            . " values ('" . $status_id . "', '" . $lang['id'] . "', 'Inovio Refund')");
                }
            }

            $flags_query = tep_db_query("describe " . TABLE_ORDERS_STATUS . " public_flag");

            if (tep_db_num_rows($flags_query) == 1) {
                tep_db_query("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
            }
        } else {
            $check = tep_db_fetch_array($check_query);
        }

        $params = array_merge($this->setadmin_setting_form(), $this->set_advance_param());

        return $params;
    }

    /**
     * Inovio Merchant setting form.
     *
     * @return array
     */
    public function setadmin_setting_form() {
        return [
            'MODULE_PAYMENT_INOVIO_CC_STATUS' => array(
                'title' => 'Enable Inovio Payment Method',
                'value' => 'True',
                'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
            ),
            'MODULE_PAYMENT_INOVIO_CC_TITLE' => array(
                'title' => 'Inovio payment Gateway'
            ),
            'MODULE_PAYMENT_INOVIO_CC_SITE_ID' => array(
                'title' => 'Merchant site ID'
            ),
            'MODULE_PAYMENT_INOVIO_CC_USERNAME' => array(
                'title' => 'Merchant username'
            ),
            'MODULE_PAYMENT_INOVIO_CC_PASSWORD' => array(
                'title' => 'Merchant Password'
            ),
            'MODULE_PAYMENT_INOVIO_CC_API_ENDPOINT' => array(
                'title' => 'API endpoint'
            ),
            'MODULE_PAYMENT_INOVIO_CC_PRODUCT_ID' => array(
                'title' => 'Set product Id to purchase product'
            ),
            'MODULE_PAYMENT_INOVIO_CC_PRODUCT_QUANTITY_RESTRICTION' => array(
                'title' => 'Maximum qauntity to purchase for single product',
                'value' => 99
            ),
            'MODULE_PAYMENT_INOVIO_CC_SORT_ORDER' => array(
                'title' => 'Sort order of display.',
                'value' => '0'
            ),
            'MODULE_PAYMENT_INOVIO_CC_DEBUG' => array(
                'title' => 'Enable debug',
                'value' => 'No',
                'set_func' => 'tep_cfg_select_option(array(\'Yes\', \'No\'), '
            )
        ];
    }

    /**
     * Function is use to set advance fields.
     *
     * @param null
     * @return array $advanceParams
     */
    public function set_advance_param() {

        return $advanceParams = [
            'INOVIO_ADVANCE_KEY_1' => ['title' => 'Inovio advance key 1'],
            'INOVIO_ADVANCE_VALUE_1' => ['title' => 'Inovio advance value 1'],
            'INOVIO_ADVANCE_KEY_2' => ['title' => 'Inovio payment key 2'],
            'INOVIO_ADVANCE_VALUE_2' => ['title' => 'Inovio payment value 2'],
            'INOVIO_ADVANCE_KEY_3' => ['title' => 'Inovio payment key 3'],
            'INOVIO_ADVANCE_VALUE_3' => ['title' => 'Inovio payment value 3'],
            'INOVIO_ADVANCE_KEY_4' => ['title' => 'Inovio payment key 4'],
            'INOVIO_ADVANCE_VALUE_4' => ['title' => 'Inovio payment value 4'],
            'INOVIO_ADVANCE_KEY_5' => ['title' => 'Inovio payment key 5'],
            'INOVIO_ADVANCE_VALUE_5' => ['title' => 'Inovio payment value 5'],
            'INOVIO_ADVANCE_KEY_6' => ['title' => 'Inovio payment key 6'],
            'INOVIO_ADVANCE_VALUE_6' => ['title' => 'Inovio payment value 6'],
            'INOVIO_ADVANCE_KEY_7' => ['title' => 'Inovio payment key 7'],
            'INOVIO_ADVANCE_VALUE_7' => ['title' => 'Inovio payment value 7'],
            'INOVIO_ADVANCE_KEY_8' => ['title' => 'Inovio payment key 8'],
            'INOVIO_ADVANCE_VALUE_8' => ['title' => 'Inovio payment value 8'],
            'INOVIO_ADVANCE_KEY_9' => ['title' => 'Inovio payment key 9'],
            'INOVIO_ADVANCE_VALUE_9' => ['title' => 'Inovio payment value 9'],
            'INOVIO_ADVANCE_KEY_10' => ['title' => 'Inovio payment key 10'],
            'INOVIO_ADVANCE_VALUE_10' => ['title' => 'Inovio payment value 10']
        ];
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
        try {
            if ($class_files > 0) {
                foreach ($class_files as $files) {
                    if (preg_match('/class-inovio?/', $files)) {
                        include_once($file_folder . $files);
                    }
                }
            } else {
                throw new Exception("Please contact to your service provider.");
            }
        } catch (Exception $ex) {
            $this->inovio_logger($ex->getMessage());
            tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . $ex->getMessage(), 'SSL'));
        }
    }

    /**
     * Use to validate merchant information on checkout page.
     *
     * @param null
     * @return true ? false
     */
    public function merchant_authenticate() {

        $parse_result = $this->set_apimethod_and_getresponse('authenticate', $this->get_gateway_information());
        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG == "Yes") {
            $this->inovio_logger("Merchant authentication response - " . print_r($parse_result, true));
        }

        if ($parse_result->SERVICE_RESPONSE != 100) {
            return false;
        }

        return true;
    }

    /**
     * Function is use to call inovio api method authAndCapture and get response.
     *
     * @param null
     * @return true ? false
     */
    public function auth_capture_process() {
        global $response;

        $auth_cap_params = array_merge(
                        $this->get_gateway_information(), $this->get_product_data(), $this->prepare_request_data()
                ) + $this->get_advance_param();

        $parse_results = $this->set_apimethod_and_getresponse('authAndCapture', $auth_cap_params);
        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG == "Yes") {
            $this->inovio_logger("Auth and capture response - " . print_r($parse_results, true));
        }
        // Check card length
        if (isset($parse_results->REF_FIELD) && 'pmt_numb' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception('Invalid Credit Card number');
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_expiry' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception('Invalid Card Expiry date');
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_key' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception('CVV number is not valid');
        }
        if (isset($parse_results->TRANS_STATUS_NAME) && 'APPROVED' == $parse_results->TRANS_STATUS_NAME) {
            // Set data for order history table
            $response = [
                "Transaction has been completed using inovio payment method", "-------------------------", "Transaction Id-" . $parse_results->TRANS_ID,
                'Product Id-' . $parse_results->PO_ID, "Token Id-" . $parse_results->PMT_ID
            ];

            return true;
        }

        return false;
    }

    /**
     * Function is use to set method for core SDK and get response.
     *
     * @param string $methodname
     * @param array $requestparams
     * @return string
     */
    public function set_apimethod_and_getresponse($methodname = "", $requestparams = array()) {

        $this->include_core_class();
        // Create connection for InovioService.
        $configservices = new InovioServiceConfig;
        // Create connection for Inovio Processor.
        $processors = new InovioProcessor();
        // Create connection for InovioConnections.
        $connections = new InovioConnection();
        $configservices->serviceConfig($requestparams, $connections);
        $processors->setServiceConfig($configservices);
        $response = $processors->setMethodName($methodname)->getResponse();
        return json_decode($response);
    }

    /**
     * Function is use to create custom logger for inovio payment method
     *
     * @param string $message
     */
    public function inovio_logger($message = "") {

        if (!file_exists(DIR_FS_CATALOG . "logs")) {
            mkdir(DIR_FS_CATALOG . "logs", 0777, true);
        }
        error_log($message . "\n", 3, DIR_FS_CATALOG . "logs/" . date("d-m-Y") . "inovio.log");
    }

    /**
     * Define client side javascript that will verify any input fields you use in the payment method
     *   selection page
     *
     * @return boolean
     */
    public function javascript_validation() {

        return false;
    }

    /**
     * Funnction is use to refund amount using inovio payment gateway
     * @param float $amount
     * @return $transactionId
     */
    public function inovio_refund($order_id = null) {
        $select_query = "SELECT inovio_api_po_id,total_order_amount FROM ";
        $select_query .= $this->inovio_refund_table . " WHERE inovio_order_id=";
        $select_query .= $order_id;
        $fetch_data = tep_db_query($select_query);
        $get_data = tep_db_fetch_array($fetch_data);

        $params = array_merge(
                $this->get_gateway_information(), array(
            'request_ref_po_id' => $get_data['inovio_api_po_id'],
            'credit_on_fail' => 1,
            'li_value_1' => $get_data['total_order_amount']
                )
        );
        $parse_result = $this->set_apimethod_and_getresponse("ccreverse", $params);

        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG == "Yes") {
            $this->inovio_logger("Refund response -" . print_r($parse_result, true));
        }

        if (isset($parse_result->TRANS_STATUS_NAME) && $parse_result->TRANS_STATUS_NAME == "APPROVED") {
            return true;
        }

        return false;
    }

    /**
     * Inovio Customjs for inovio checkout form validation
     *
     */
    public function add_custom_inoviojs() {
        $js = '<script type="text/javascript">'
                . '$(document).ready(function() {
                    
                    $("#bodyWrapper form").attr("autocomplete", "off");
                    $("#inovio_cc_ccv_nh-dns").attr("autocomplete", "off");
                    $(document).on("keypress", "#inovio_cc_number_nh-dns", enterNumeric);
                    $(document).on("keypress", "#inovio_cc_ccv_nh-dns", enterNumeric);
                    $(document).on("keypress", "#inovio_cc_ccv_nh-dns", enterNumeric);
                    $(document).on("contextmenu", "#inovio_cc_ccv_nh-dns", restrict_right_click);
                    $(document).on("contextmenu", "#inovio_cc_number_nh-dns", restrict_right_click);
                 
                    $(document).bind("cut copy paste", "#inovio_cc_number_nh-dn", function(e) {
                      e.preventDefault();
                    });
                    
                    $(document).bind("cut copy paste", "#inovio_cc_ccv_nh-dns", function(e) {
                      e.preventDefault();
                    });
                   
                    $(document).on("select", "#inovio_cc_ccv_nh-dns", function(){
                        $(this).val("");
                    });
               });
                var enterNumeric = function(e) {
              return (e.which != 8 && e.which != 0 && (e.which < 48 || e.which > 57)) ? false : true;
        }
                var restrict_right_click = function(e){
                        return false;
                }';
        echo $js .= '</script>';
    }

}
