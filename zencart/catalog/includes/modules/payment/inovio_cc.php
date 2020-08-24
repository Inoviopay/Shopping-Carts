<?php

/**
 * Inovio payment method class
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2016 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: Sunil Singh
 */

/**
 * Inovio Payment Module
 */class inovio_cc extends base
{

    public $code;
    public $title;
    public $description;
    public $enabled;
    public $_check;
    public $cc_card_number;
    public $cc_expiry_month;
    public $cc_expiry_year;
    public $cc_cvv_number;
    /*
     * Set order status processing
     */
    public $order_status = 2;

    public function __construct()
    {
        
        $this->signature = 'inovio|inovio_cc|2.0|2.3';
        $this->api_version = '2.0';

        $this->code = 'inovio_cc';
        $this->title = MODULE_PAYMENT_INOVIO_CC_TITLES;
        $this->public_title = empty(MODULE_PAYMENT_INOVIO_CC_TITLE) ? MODULE_PAYMENT_INOVIO_CC_TITLES : MODULE_PAYMENT_INOVIO_CC_TITLE;
        $this->description = MODULE_PAYMENT_INOVIO_CC_TEXT_DESCRIPTION;
        $this->enabled = defined('MODULE_PAYMENT_INOVIO_CC_STATUS') && (MODULE_PAYMENT_INOVIO_CC_STATUS == 'True') ? true : false;
        $this->inovio_refund_table = DB_PREFIX . "inovio_refunded";
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
     * @globla Object $order
     */
    public function prepare_request_data()
    {
        global $order;
        
        return $request = [
            "bill_addr" => $order->billing['street_address'].','.$order->billing['suburb'],
            "pmt_numb" => _sess_read("inovio_cc_number_nh-dns"),
            "pmt_key" => _sess_read("inovio_cc_ccv_nh-dns"),
            //"xtl_ip" => !empty(zen_get_ip_address()) ? zen_get_ip_address() : '127.0.0.1',
            "cust_fname" => $order->billing['firstname'],
            "pmt_expiry" => _sess_read("inovio_cc_expires_month") . _sess_read("inovio_cc_expires_year"),
            "cust_email" => $order->customer['email_address'],
            "bill_addr_zip" => $order->billing['postcode'],
            "bill_addr_city" => $order->billing['city'],
            "bill_addr_state" => zen_get_zone_code($order->billing['country_id'], $order->billing['zone_id'], $order->billing['state']),
            "request_currency" => $order->info['currency'],
            "bill_addr_country" => $order->billing['country']['iso_code_2'],

            "ship_addr_country" =>$order->delivery['country']['iso_code_2'] ,
            "ship_addr_city" => $order->delivery['city'],
            "ship_addr_state" => zen_get_zone_code(
                $order->delivery['country_id'],
                $order->delivery['zone_id'],
                $order->delivery['state']
            ),
            "ship_addr_zip" => $order->delivery['postcode'],
            "ship_addr" => $order->delivery['street_address'].','. $order->delivery['suburb'],

        ];
    }

    /**
     * Use to get advance fields
     *
     * @return array $advanceParams
     */
    public function get_advance_param()
    {
        
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
     * @global Object $order
     */
    public function get_product_data()
    {
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
     * @global Object $order
     */
    public function restrict_quantity()
    {

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
    public function get_gateway_information()
    {
        $requestParams = [
            'end_point'     => MODULE_PAYMENT_INOVIO_CC_API_ENDPOINT,
            'site_id'       => MODULE_PAYMENT_INOVIO_CC_SITE_ID,
            'req_username'  => MODULE_PAYMENT_INOVIO_CC_USERNAME,
            'req_password'  => MODULE_PAYMENT_INOVIO_CC_PASSWORD,
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
    public function selection()
    {

        global $order;

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => sprintf('%02d', $i));
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $expires_year[] = array('id' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)));
        }

        $selection = array(
            'id' => $this->code,
            'module' => MODULE_PAYMENT_INOVIO_CC_TITLE."<img src ='".DIR_WS_IMAGES."inovio_cards.png' >",
            'fields' => array(
                    array(
                        'field' => zen_draw_input_field(
                            '',
                            '',
                            ''
                                . " maxlength='4' readonly",
                            "hidden"
                        )
                    ),
                    array(
                        'title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_NUMBER,
                        'field' => zen_draw_input_field(
                            'inovio_cc_number_nh-dns',
                            '',
                            'id="inovio_cc_number_nh-dns"'
                                . ' autocomplete="off"  title="Credit card number" minlength="12" maxlength="16"'
                                . '',
                            "text",
                            false
                        )
                    ),
                    array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_EXPIRES,
                        'field' => zen_draw_pull_down_menu(
                            'inovio_cc_expires_month',
                            $expires_month
                        ) . '&nbsp;' .
                        zen_draw_pull_down_menu(
                            'inovio_cc_expires_year',
                            $expires_year
                        )
                    ),
                    array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_CCV,
                        'field' => zen_draw_input_field(
                            'inovio_cc_ccv_nh-dns',
                            '',
                            'id="inovio_cc_ccv_nh-dns"'
                                . "minlength='3' maxlength='4' autocomplete='off' title='CVV'",
                            "password",
                            false
                        )
                    )
            ));
        
        return $selection;
    }

    /**
     * Add server side validation on checkout page.
     *
     */
    public function pre_confirmation_check()
    {

        $this->credit_card_validation();

        $this->cc_card_number  = zen_output_string_protected($_POST['inovio_cc_number_nh-dns']);
        $this->cc_expiry_month = zen_output_string_protected($_POST['inovio_cc_expires_month']);
        $this->cc_expiry_year  = zen_output_string_protected($_POST['inovio_cc_expires_year']);
        $this->cc_cvv_number   = zen_output_string_protected($_POST['inovio_cc_ccv_nh-dns']);
    }

    /**
     * Use to check credit card expiration date.
     *
     * @param string $card_data
     * @return boolean
     */
    public function validate_expirydate($card_date = null)
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

    /**
     * Show credit card form on checkout page.
     *
     * @global Object $order
     * @return array $confirmation
     */
    public function confirmation()
    {

        $confirmation = array('fields' => array(
                    array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_NUMBER,
                        'field' => substr($this->cc_card_number, 0, 4) . str_repeat('X', (strlen($this->cc_card_number) - 8)) . substr($this->cc_card_number, -4)
                        ),
                    array('title' => MODULE_PAYMENT_INOVIO_CC_CREDIT_CARD_EXPIRES,
                        'field' => strftime('%B, %Y', mktime(0, 0, 0, $_POST['inovio_cc_expires_month'], 1, $_POST['inovio_cc_expires_year']))
                        )
                ));
        return $confirmation;
    }

    /**
     * Outputs the html form hidden elements sent as POST data to the payment gateway.
     *
     * @return boolean
     */
    public function process_button()
    {
        $process_button_string =
                _sess_write("inovio_cc_expires_year", $this->cc_expiry_year) ;
                _sess_write("inovio_cc_expires_month", $this->cc_expiry_month) ;
                _sess_write("inovio_cc_number_nh-dns", $this->cc_card_number) ;
                _sess_write("inovio_cc_ccv_nh-dns", $this->cc_cvv_number) ;
        
//                zen_draw_hidden_field('inovio_cc_expires_year', $this->cc_expiry_year) .
//                zen_draw_hidden_field('inovio_cc_expires_month', $this->cc_expiry_month) .
//                zen_draw_hidden_field('inovio_cc_number_nh-dns',$this->cc_card_number) .
//                zen_draw_hidden_field('inovio_cc_ccv_nh-dns', $this->cc_cvv_number) .
                zen_draw_hidden_field(zen_session_name(), zen_session_id());

        return $process_button_string;
    }

    /**
     * This is where you will implement any payment verification. This function can be quite complicated to implement
     *
     * @global Object $order
     * @global Object $messageStack
     */
    public function before_process()
    {
        global $order, $messageStack;

        _sess_read("inovio_cc_expires_year");
        _sess_read("inovio_cc_expires_month");
        _sess_read("inovio_cc_number_nh-dns");
        _sess_read("inovio_cc_ccv_nh-dns");
        
        try {
            // Restrict product's quantity
            if ($this->restrict_quantity() == false) {
                throw new Exception("For any single product quantity should not be greater than "
                . MODULE_PAYMENT_INOVIO_CC_PRODUCT_QUANTITY_RESTRICTION . ", please click"
                . "on <a href='".zen_href_link(FILENAME_SHOPPING_CART, '', 'SSL', true, false)."'>Cart</a>to update cart");
            }
            // Merchant authentication
            if ($this->merchant_authenticate() == false) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_ERROR_TITLE);
            }
            // Do auth and capture validation
            if ($this->auth_capture_process() != true) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_ERROR_TITLE);
            }
            // Set order status as processing
        } catch (Exception $ex) {
            $messageStack->add_session('checkout_payment', $ex->getMessage() . '<!-- [' . $this->code . '] -->', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
    }

    /**
     * It is used to implement any post proessing of the payment/order after the order has been finalised.
     *
     * @global Object $response
     * @global Object $order
     * @global Object $db
     * @global Object $currencies
     * @global int $insert_id
     */
    public function after_process()
    {

        global $response, $insert_id, $db, $order, $currencies;

        $order_total = $order->info['subtotal'];
        $prod_id = preg_replace("/[^0-9]/", "", $response[3]);

        // Insert data in inovio_refunded table
        $refund_data = array(
            "inovio_order_id" => $insert_id,
            "inovio_api_po_id" => $prod_id,
            "total_order_amount" => $order_total
        );
        zen_db_perform($this->inovio_refund_table, $refund_data);

        $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, customer_notified, date_added) values (:orderComments, :orderID, :orderStatus, -1, now() )";
        $currency_comment = '';
        if ($order->info['currency'] != $this->gateway_currency) {
            $currency_comment = ' (' . number_format($order->info['total'] * $currencies->get_value($this->gateway_currency), 2) . ' ' . $this->gateway_currency . ')';
        }
        $sql = $db->bindVars($sql, ':orderComments', 'Processed via inovio payment. : ' . $this->auth_code . ' TransID: ' . $prod_id, 'string');
        $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
        $sql = $db->bindVars($sql, ':orderStatus', $this->order_status, 'integer');
        $db->Execute($sql);
        return false;
    }

    /**
     * Validate credit card details before process payment.
     *
     * @global Object $messageStack
     */
    public function credit_card_validation()
    {
        global $messageStack;
        
        try {
            if (empty(zen_output_string_protected($_POST['inovio_cc_number_nh-dns']))) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_EMPTY_CREDIT_CARD);
            } elseif (!empty(zen_output_string_protected($_POST['inovio_cc_number_nh-dns'])) &&
                    strlen(zen_output_string_protected($_POST['inovio_cc_number_nh-dns'])) < 12 || strlen(zen_output_string_protected($_POST['inovio_cc_number_nh-dns'])) > 16) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_INVALID_CREDIT_CARD);
            } elseif (empty(zen_output_string_protected($_POST['inovio_cc_expires_month'])) || empty(zen_output_string_protected($_POST['inovio_cc_expires_year']))) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_EMPTY_EXPIRY_DATE);
            } elseif ($this->validate_expirydate(
                zen_output_string_protected($_POST['inovio_cc_expires_year'])
                            . zen_output_string_protected($_POST['inovio_cc_expires_month'])
            )== false) {
                        throw new Exception(MODULE_PAYMENT_INOVIO_CC_INVALID_EXPIRY_DATE);
            } elseif (empty(zen_output_string_protected($_POST['inovio_cc_ccv_nh-dns']))) {
                throw new Exception(MODULE_PAYMENT_INOVIO_CC_EMPTY_CVV);
            }
        } catch (Exception $ex) {
            $messageStack->add_session('checkout_payment', $ex->getMessage(), 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
        }
    }

    /**
     * Standard functionlity for osCommerce to see if the module is installed.
     *
     * @return boolean $this->_check
     * @global Object $db
     */
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("SELECT configuration_value from " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_INOVIO_CC_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    /**
     * This is where you define module's configurations (displayed in admin).
     *
     * @param array $parameter
     * @global Object $db
     * @global Object $messageStack
     */
    public function install($parameter = null)
    {
        global $db, $messageStack;
        
        if (defined('MODULE_PAYMENT_INOVIO_CC_STATUS')) {
            $messageStack->add_session('Inovio payment module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=inovio_cc', 'NONSSL'));
            return 'failed';
        }

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
                'set_function' => (isset($data['set_function']) ? $data['set_function'] : ''),
                'date_added' => 'now()'
            );
            
            zen_db_perform(TABLE_CONFIGURATION, $sql_data_array);
        }
        // Create zen_inovio_refund table on installation
        $this->create_inovio_table();
    }

    /**
     * Function is use to create table on activate inovio payment plugin
     *
     *  @global Object $db
     */
    public function create_inovio_table()
    {
        global $db;
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->inovio_refund_table . " (
                  id bigint(20) NOT NULL AUTO_INCREMENT,
                  inovio_order_id bigint(20) NOT NULL,
                  inovio_api_po_id varchar(256),
                  total_order_amount varchar(256),
                  PRIMARY KEY  (id)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
        $db->Execute($sql);
    }


    /**
     * Standard functionality to uninstall the module.
     *
     *  @global Object $db
     */
    public function remove()
    {
        global $db;
        
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " where configuration_key like '%INOVIO%'");
        $db->Execute("DROP TABLE IF EXISTS " . $this->inovio_refund_table);
    }

    /**
     * This array must include all the configuration setting keys defined in your install() function.
     *
     *  @global Object $db
     * @return array $keys
     */
    public function keys()
    {
        global $db;
        
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
     * @global Object $db
     * @return array $params
     */
    public function get_params()
    {
        global $db;
        
        $check_query = $db->Execute("select orders_status_id from " . TABLE_ORDERS_STATUS . " where orders_status_name = 'Inovio refund' limit 1");
        $check_row = $check_query->RecordCount();
        
        if ($check_row < 1) {
            $status_query = $db->Execute("select max(orders_status_id) as status_id from " . TABLE_ORDERS_STATUS);

            $status_id = $status_query->fields['status_id'] + 1;

            $languages = zen_get_languages();
            
            if ($languages > 0) {
                foreach ($languages as $lang) {
                    $db->Execute("insert into " . TABLE_ORDERS_STATUS . " (orders_status_id, language_id, orders_status_name) "
                            . " values ('" . $status_id . "', '" . $lang['id'] . "', 'Inovio Refund')");
                }
            }

            $flags_query = $db->Execute("describe " . TABLE_ORDERS_STATUS . " public_flag");

            if ($flags_query->RecordCount() == 1) {
                $db->Execute("update " . TABLE_ORDERS_STATUS . " set public_flag = 0 and downloads_flag = 0 where orders_status_id = '" . $status_id . "'");
            }
        }

        $params = array_merge($this->setadmin_setting_form(), $this->set_advance_param());

        return $params;
    }

    /**
     * Inovio Merchant setting form.
     * `
     * @return array
     */
    public function setadmin_setting_form()
    {
        
        return [
            'MODULE_PAYMENT_INOVIO_CC_STATUS' => array(
                'title' => 'Enable Inovio Payment Method',
                'value' => 'True',
                'set_function' => 'zen_cfg_select_option(array(\'True\', \'False\'), '
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
                'title' => 'Maximum quantity to purchase for single product',
                'value' => 99
            ),
            'MODULE_PAYMENT_INOVIO_CC_DEBUG' => array(
                'title' => 'Enable debug',
                'value' => 'NO',
                'set_function' => "zen_cfg_select_option(array(\'YES\', \'NO\'),"
            )
        ];
    }

    /**
     * Function is use to set advance fields.
     *
     * @param null
     * @return array $advanceParams
     */
    public function set_advance_param()
    {

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
    public function include_core_class()
    {
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
     * @return boolean
     */
    public function merchant_authenticate()
    {

        $parse_result = $this->set_apimethod_and_getresponse('authenticate', $this->get_gateway_information());
        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG === "YES") {
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
     * @return boolean
     */
    public function auth_capture_process()
    {
        global $response;

        $auth_cap_params = array_merge(
            $this->get_gateway_information(),
            $this->get_product_data(),
            $this->prepare_request_data()
        )
                            + $this->get_advance_param();

         // Check if log enable and implemented shipping parameters
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG === "YES") {
            $this->inovio_logger("Checking Shipping Information - " . print_r($auth_cap_params, true));
        }


        $parse_results = $this->set_apimethod_and_getresponse('authAndCapture', $auth_cap_params);
        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG === "YES") {
            $this->inovio_logger("Auth and capture response - " . print_r($parse_results, true));
        }
        // Check card length
        if (isset($parse_results->REF_FIELD) && 'pmt_numb' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception(MODULE_PAYMENT_INOVIO_CC_INVALID_CREDIT_CARD);
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_expiry' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception(MODULE_PAYMENT_INOVIO_CC_INVALID_EXPIRY_DATE);
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_key' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception(MODULE_PAYMENT_INOVIO_CC_INVALID_CVV);
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
    public function set_apimethod_and_getresponse($methodname = "", $requestparams = array())
    {

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
    public function inovio_logger($message = "")
    {

        if (!file_exists(DIR_FS_CATALOG . "logs")) {
            mkdir(DIR_FS_CATALOG . "logs", 0777, true);
        }
        error_log($message . "\n", 3, DIR_FS_CATALOG . "logs/" . date("d-m-Y") . "-inovio.log");
    }

    /**
     * Define client side javascript that will verify any input fields you use in the payment method
     *   selection page
     *
     * @return boolean
     */
    public function javascript_validation()
    {
         $this->add_custom_inoviojs();
    }

    /**
     * Funnction is use to refund amount using inovio payment gateway
     * @param float $order_id
     * @return boolean
     */
    public function inovio_refund($order_id = null)
    {
        global $db;
        $select_query = "SELECT inovio_api_po_id,total_order_amount FROM ";
        $select_query .= $this->inovio_refund_table . " WHERE inovio_order_id=";
        $select_query .= $order_id;
        $fetch_data = $db->Execute($select_query);
        
        $params = array_merge(
            $this->get_gateway_information(),
            array(
                    'request_ref_po_id' => $fetch_data->fields['inovio_api_po_id'],
                    'credit_on_fail' => 1,
                    'li_value_1' => $fetch_data->fields['total_order_amount']
                )
        );
        $parse_result = $this->set_apimethod_and_getresponse("ccreverse", $params);

        // Check if log enable
        if (MODULE_PAYMENT_INOVIO_CC_DEBUG === "YES") {
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
    public function add_custom_inoviojs()
    {
        $js = '<script type="text/javascript">
                $(document).ready(function() {
                    // On page load make credit card field empty
                    $("#inovio_cc_number_nh-dns, #inovio_cc_ccv_nh-dns").val("");
                    
                    // restrict enter numeric only
                    $(document).on("keypress", "#inovio_cc_number_nh-dns, #inovio_cc_ccv_nh-dns", enterNumeric);
                    // restrict right click
                    $(document).on("contextmenu", "#inovio_cc_number_nh-dns, #inovio_cc_ccv_nh-dns", restrict_right_click);
                    // restrict cust copy paste
                    $(document).bind("cut copy paste", "#inovio_cc_ccv_nh-dns, #inovio_cc_number_nh-dn", function(e) {
                      e.preventDefault();
                    });
                   
                    $(document).on("select", "#inovio_cc_ccv_nh-dns, #inovio_cc_number_nh-dns", function(){
                        $(this).val("");
                    });
                    $(document).on("change", "#inovio_cc_ccv_nh-dns", function(){
                        if ($(this).val().length > 4) {
                            $(this).val("");
                        }
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
