<?php

/**
 * Inovio Custom payment method to extend opencart payment functionality.
 */
class ControllerExtensionPaymentInovioPay extends Controller {

    private $product_order_Id;

    /**
     * It used to send data on checkout page in view file.
     * 
     * @return string
     */
    public function index() {
        $this->load->language('extension/payment/inovio_pay');

        $data['months'] = array();

        for ($i = 1; $i <= 12; $i++) {
            $data['months'][] = array(
                'text' => strftime('%B', mktime(0, 0, 0, $i, 1, 2000)),
                'value' => sprintf('%02d', $i)
            );
        }

        $today = getdate();

        $data['year_expire'] = array();

        for ($i = $today['year']; $i < $today['year'] + 11; $i++) {
            $data['year_expire'][] = array(
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i)),
                'value' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
            );
        }

        return $this->load->view('extension/payment/inovio_pay', $data);
    }

    /**
     * It used to process payment and call inovio api request response.
     * 
     * @throws Exception
     */
    public function inovio_checkout() {

        try {
            $this->load->model('checkout/order');
            $this->load->model('account/order');

            $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $json = array();
            // Validate credit card inoformation
            $this->creditCardValidation();

            // Restrict product's quantity
            if ($this->restrictQuantity() === false) {
                throw new Exception("For any single product quantity should not be greater than "
                . $this->config->get("payment_inovio_pay_maximum_product_quantity") . ", please click"
                . " on " . "<a href='" . $this->url->link('checkout/cart') . "'>Update Cart</a>");
            }

            // Merchant authentication
            if ($this->merchantAuthenticate() === false) {
                throw new Exception($this->language->get('text_inovio_common_error_message'));
            }

            // Do auth and capture validation
            if ($this->authCaptureProcess() === false) {
                throw new Exception($this->language->get('text_inovio_common_error_message'));
            } else {
                // Redirect with success URL
                $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_inovio_pay_order_status_id'));
                $json['redirect'] = $this->url->link('checkout/success', '', true);

                // Add API order related data in custom table

                $this->addApiOrder($this->session->data['order_id'], $this->getProductOrderId());
            }
        } catch (Exception $ex) {
            $json['error'] = $ex->getMessage();
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * Validate credit card details before sending to payment.
     * 
     * @throws Exception
     */
    public function creditCardValidation() {

        $this->load->model('checkout/order');
        $this->load->language('extension/payment/inovio_pay');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $credit_card_number = str_replace(' ', '', $this->request->post['inovio_cc_number']);
        $credit_card_month = $this->request->post['inovio_cc_expire_date_month'];
        $credit_card_year = $this->request->post['inovio_cc_expire_date_year'];
        $credit_card_cvv = $this->request->post['inovio_input-cc-cvv2'];

        if (empty($credit_card_number)) {
            throw new Exception($this->language->get('text_inovio_error_creditcard'));
        } elseif (!empty($credit_card_number) &&
                strlen($credit_card_number) < 12 || strlen($credit_card_number) > 16) {
            throw new Exception($this->language->get('text_inovio_error_invalid_creditcard'));
        } elseif (empty($credit_card_month) || empty($credit_card_year)) {
            throw new Exception($this->language->get('text_inovio_error_expirydate'));
        } elseif ($this->validateExpirydate($credit_card_year . $credit_card_month) === false) {
            throw new Exception($this->language->get('text_inovio_error_expirydate'));
        } elseif (empty($credit_card_cvv)) {
            throw new Exception($this->language->get('text_inovio_error_cvv'));
        }
    }

    /**
     * Use to check credit card expiration date.
     * 
     * @param string $card_data
     * @return boolean
     */
    public function validateExpirydate($card_date = null) {
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
     * Use to set request data to call Inovio APImethod 
     * 
     * @return array $request
     */
    public function prepareRequestData() {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/inovio_pay');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        if (empty($order_info)) {
            throw new Exception($this->language->get('text_inovio_common_error_message'));
        }

        $request = [
            "bill_addr" => html_entity_decode($order_info['payment_address_1'], ENT_QUOTES, 'UTF-8') . ' '
            . html_entity_decode($order_info['payment_address_2'], ENT_QUOTES, 'UTF-8'),
            "pmt_numb" => str_replace(' ', '', $this->request->post['inovio_cc_number']),
            "pmt_key" => $this->request->post['inovio_input-cc-cvv2'],
            "xtl_ip" => ($this->request->server['REMOTE_ADDR'] == '::1') ? '127.0.0.1' : $this->request->server['REMOTE_ADDR'],
            "cust_fname" => html_entity_decode($order_info['payment_firstname'], ENT_QUOTES, 'UTF-8'),
            "cust_lname" => html_entity_decode($order_info['payment_lastname'], ENT_QUOTES, 'UTF-8'),
            "pmt_expiry" => $this->request->post['inovio_cc_expire_date_month'] . $this->request->post['inovio_cc_expire_date_year'],
            "cust_email" => $order_info['email'],
            "bill_addr_zip" => html_entity_decode($order_info['payment_postcode'], ENT_QUOTES, 'UTF-8'),
            "bill_addr_city" => html_entity_decode($order_info['payment_city'], ENT_QUOTES, 'UTF-8'),
            "bill_addr_state" => html_entity_decode($order_info['payment_zone_code'], ENT_QUOTES, 'UTF-8'),
            "request_currency" => $this->session->data['currency'],
            "bill_addr_country" => html_entity_decode($order_info['payment_iso_code_3'], ENT_QUOTES, 'UTF-8'),

            "ship_addr_country" =>html_entity_decode($order_info['shipping_iso_code_3'], ENT_QUOTES, 'UTF-8') ,
            "ship_addr_city" => html_entity_decode($order_info['shipping_city'], ENT_QUOTES, 'UTF-8'),
            "ship_addr_state" => html_entity_decode($order_info['shipping_zone_code'], ENT_QUOTES, 'UTF-8'),
            "ship_addr_zip" => html_entity_decode($order_info['shipping_postcode'], ENT_QUOTES, 'UTF-8'),
            "ship_addr" => html_entity_decode($order_info['shipping_address_1'], ENT_QUOTES, 'UTF-8') . ' '
            . html_entity_decode($order_info['shipping_address_2'], ENT_QUOTES, 'UTF-8'),

        ];

        return $request;
    }

    /**
     * Use to get advance fields.
     *   
     * @return array $advanceParams
     */
    public function getAdvanceParam() {
        $advance_params = [
            $this->config->get('payment_inovio_pay_advance_key_1') => $this->config->get('payment_inovio_pay_advance_value_1'),
            $this->config->get('payment_inovio_pay_advance_key_2') => $this->config->get('payment_inovio_pay_advance_value_2'),
            $this->config->get('payment_inovio_pay_advance_key_3') => $this->config->get('payment_inovio_pay_advance_value_3'),
            $this->config->get('payment_inovio_pay_advance_key_4') => $this->config->get('payment_inovio_pay_advance_value_4'),
            $this->config->get('payment_inovio_pay_advance_key_5') => $this->config->get('payment_inovio_pay_advance_value_5'),
            $this->config->get('payment_inovio_pay_advance_key_6') => $this->config->get('payment_inovio_pay_advance_value_6'),
            $this->config->get('payment_inovio_pay_advance_key_7') => $this->config->get('payment_inovio_pay_advance_value_7'),
            $this->config->get('payment_inovio_pay_advance_key_8') => $this->config->get('payment_inovio_pay_advance_value_8'),
            $this->config->get('payment_inovio_pay_advance_key_9') => $this->config->get('payment_inovio_pay_advance_value_9'),
            $this->config->get('payment_inovio_pay_advance_key_10') => $this->config->get('payment_inovio_pay_advance_value_10')
        ];

        return $advance_params;
    }

    /**
     * For single product's qunantity should not be greater than 99
     * 
     * @return boolean $returnstate
     */
    public function restrictQuantity() {

        $this->load->model('checkout/order');
        $this->load->language('extension/payment/inovio_pay');
        $ordered_products = $this->model_account_order->getOrderProducts($this->session->data['order_id']);
        $returnstate = false;

        if ($ordered_products > 0) {
            $returnstate = true;
            foreach ($ordered_products as $cart_item) {
                if ($cart_item['quantity'] > $this->config->get('payment_inovio_pay_maximum_product_quantity')) {
                    $returnstate = false;
                }
            }
        } else {
            throw new Exception($this->language->get('text_inovio_common_error_message'));
        }

        return $returnstate;
    }

    /**
     * Use to set Inovio initial requeired parameters
     * 
     * @return arrray $requestParams
     */
    public function getGatewayInformation() {

        $this->load->language('extension/payment/inovio_pay');

        $request_params = [
            'end_point' => $this->config->get('payment_inovio_pay_merchant_api_endpoint'),
            'site_id' => $this->config->get('payment_inovio_pay_merchant_id'),
            'req_username' => $this->config->get('payment_inovio_pay_merchant_username'),
            'req_password' => $this->config->get('payment_inovio_pay_merchant_password'),
            'request_response_format' => 'json'
        ];

        $finalRequestParams = [];
      
        foreach ($request_params as $reqKey => $reqParamVal) {
          if (empty($request_params[$reqKey])) {
              throw new Exception($this->language->get('text_inovio_common_error_message'));
              exit;   
          }

          $finalRequestParams[$reqKey] = trim($reqParamVal);
      }
    
      return $finalRequestParams;

    }

    /**
     * Use to get product Ids, price and quantity
     * 
     * @return array $final_array
     */
    public function getProductData() {
        $this->load->model('checkout/order');
        $this->load->language('extension/payment/inovio_pay');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        $final_array = [];
            
        $final_array['li_prod_id_1'] = $this->config->get('payment_inovio_pay_product_id');
        $final_array['li_count_1'] = 1;
        $final_array['li_value_1'] = $order_info["total"];
       
        return $final_array;
        
    }

    /**
     * Function is use to set method for core SDK and get response.
     * 
     * @param string $methodname
     * @param array $requestparams
     * @return string
     */
    public function setApimethodAndGetresponse($methodname = "", $requestparams = array()) {

        $this->includeCoreClass();
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
     * Include inovio payment method core SDK.
     * 
     * @param NULL
     */
    public function includeCoreClass() {

        try {
            $this->load->language('extension/payment/inovio_pay');
            // Include core SDK claass
            $file_folder = dirname(__FILE__) . '/inovio-cc/';
            $class_files = scandir($file_folder);

            if ($class_files > 0) {

                foreach ($class_files as $files) {

                    if (preg_match('/class-inovio?/', $files)) {
                        include_once( $file_folder . $files );
                    }
                }
            } else {
                throw new Exception($this->language->get('text_inovio_common_error_message'));
            }
        } catch (Exception $ex) {
            $this->response->redirect($this->url->link('extension/payment/failure', '', true));
        }
    }

    /**
     * Use to validate merchant information on checkout page.
     * 
     * @param null
     * @return true ? false
     */
    public function merchantAuthenticate() {

        $parse_result = $this->setApimethodAndGetresponse('authenticate', $this->getGatewayInformation());

        $this->inovioLogger("Merchant authentication response - " . print_r($parse_result, true));

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
    public function authCaptureProcess() {
        $this->load->language('extension/payment/inovio_pay');
        $auth_cap_params = array_merge(
                        $this->getGatewayInformation(), $this->getProductData(), $this->prepareRequestData()) + $this->getAdvanceParam(1);

        $parse_results = $this->setApimethodAndGetresponse('authAndCapture', $auth_cap_params);

        $this->inovioLogger("Auth and capture response - " . print_r($parse_results, true));

        // Check card length
        if (isset($parse_results->REF_FIELD) && 'pmt_numb' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception($this->language->get('text_inovio_error_invalid_creditcard'));
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_expiry' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception($this->language->get('text_inovio_error_expirydate'));
        }

        // Check card expiry date
        if (isset($parse_results->REF_FIELD) && 'pmt_key' == strtolower($parse_results->REF_FIELD)) {
            throw new Exception($this->language->get('text_inovio_error_invalidcvv'));
        }

        if (isset($parse_results->PO_ID)) {
            $this->setProdctOrderId($parse_results->PO_ID);
        }

        if (isset($parse_results->TRANS_STATUS_NAME) && 'APPROVED' == $parse_results->TRANS_STATUS_NAME) {

            return true;
        }

        return false;
    }

    /**
     * Use to set Product order Id 
     */
    public function setProdctOrderId($product_order_Id = null) {
        $this->product_order_Id = $product_order_Id;
    }

    /**
     * Use to get API product Id
     * 
     * @return int
     */
    public function getProductOrderId() {
        return $this->product_order_Id;
    }

    /**
     * Function is use to create custom logger for inovio payment method
     * 
     * @param string $message
     */
    public function inovioLogger($message = "") {

        if ($this->config->get('payment_inovio_pay_debug') == 1) {
            $log = new \Log('inovio.log');
            $log->write($message);
        }
    }

    /**
     * Use to add API order id and product order id
     * 
     * @param int $order_id
     * @param int $apiproduct_id
     */
    public function addApiOrder($order_id = null, $api_product_id = null) {

        $this->db->query("INSERT INTO `" . DB_PREFIX . "inovio_refund_data`"
                . "SET  `inovio_order_id` = $order_id,"
                . "`inovio_api_po_id` = $api_product_id");
    }
}
