<?php

/* @package  Opencart payment extension
 * @author   Sunil Singh
 * @author   Chetu team
 * @access   public
 */

class ControllerExtensionPaymentInovioPay extends Controller
{

    private $error = array();

    /**
     * Show inovio merchant setting form in payment section.
     */
    public function index()
    {

        $this->load->language('extension/payment/inovio_pay');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->inovio_validate()) {
            $this->model_setting_setting->editSetting('payment_inovio_pay', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/inovio_pay', 'user_token=' . $this->session->data['user_token'], true)
        );
        // Use to render action into inovio merchant setting form.
        $data['action'] = $this->url->link('extension/payment/inovio_pay', 'user_token=' . $this->session->data['user_token'], true);
        // Use to render cancel ito inovio merchant setting form.
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data = array_merge(
            $this->merchant_info(),
            $this->merchant_set_data()
        );

        $this->load->model('localisation/order_status');
        // Load order status
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        // Load header
        $data['header'] = $this->load->controller('common/header');
        // Load left column
        $data['column_left'] = $this->load->controller('common/column_left');
        // Load footer
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/inovio_pay', $data));
    }

    /**
     *  It contains to create custom table to store refund related data.
     */
    public function install()
    {
        $this->load->model('extension/payment/inovio_pay');
        $this->model_extension_payment_inovio_pay->install();
    }

    /**
     *  It used to drop table on uninstall payment extension.
     */
    public function uninstall()
    {
        $this->load->model('extension/payment/inovio_pay');
        $this->model_extension_payment_inovio_pay->uninstall();
    }

    /**
     * Use to load javascript file for refund process
     * @return string
     */
    public function order()
    {

        $this->load->language('extension/payment/inovio_pay');

        $data['user_token'] = $this->session->data['user_token'];

        $data['order_id'] = $this->request->get['order_id'];
        $data['user_token'] = $this->session->data['user_token'];
        $data['comment_status'] = "Refund has been completed via inovio payment";
        return $this->load->view('extension/payment/inovio_pay_order_ajax', $data);
    }

    /**
     * Use to make refund functionality
     *
     * @param integer $orderId
     */
    public function refundProcess()
    {

        $this->load->model('extension/payment/inovio_pay');
        $product_order_id = $this->model_extension_payment_inovio_pay->getProductOrderId($_REQUEST['orderId']);

        ## refund process
        $params = array_merge(
            $this->model_extension_payment_inovio_pay->get_gateway_information(),
            array(
            'request_ref_po_id' => $product_order_id,
            'credit_on_fail' => 1,
                )
        );
        $refundResponse = $this->model_extension_payment_inovio_pay->set_apimethod_and_getresponse('CCREVERSE', $params);
        
        if ($refundResponse->TRANS_STATUS_NAME == 'APPROVED') {
            $this->inovio_logger("Refund response from API-".print_r($refundResponse, true));
            
            $this->model_extension_payment_inovio_pay->updateOrderStatusOnRefund(
                $_POST['orderId'],
                $_POST['order_status_id'],
                $_POST['comment_status']
            );
        }
    }

    /**
     * Set error message if any error occured during save merchant form data.
     *
     * @return $this->error
     */
    protected function inovio_validate()
    {

        if (!$this->user->hasPermission('modify', 'extension/payment/inovio_pay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_inovio_pay_merchant_id']) {
            $this->error['error_merchant_id'] = $this->language->get('error_merchant_id');
        }

        if (!$this->request->post['payment_inovio_pay_merchant_username']) {
            $this->error['error_merchant_username'] = $this->language->get('error_merchant_username');
        }

        if (!$this->request->post['payment_inovio_pay_merchant_password']) {
            $this->error['error_merchant_password'] = $this->language->get('error_merchant_password');
        }

        if (!$this->request->post['payment_inovio_pay_merchant_api_endpoint']) {
            $this->error['error_merchant_api_endpoint'] = $this->language->get('error_merchant_api_endpoint');
        }

        if (!$this->request->post['payment_inovio_pay_product_id']) {
            $this->error['error_product_id'] = $this->language->get('error_product_id');
        }

        if (!$this->request->post['payment_inovio_pay_maximum_product_quantity']) {
            $this->error['error_maximum_product_quantity'] = $this->language->get('error_maximum_product_quantity');
        }

        return !$this->error;
    }

    /**
     * Validate merchant form fields before insert into setting table.
     *
     * @return array $merchantinfo
     */
    protected function merchant_info()
    {

        $merchantinfo = [];
        $merchantinfo['error_merchant_id'] = isset($this->error['error_merchant_id']) ? $this->error['error_merchant_id'] : "";
        $merchantinfo['error_merchant_username'] = isset($this->error['error_merchant_username']) ? $this->error['error_merchant_username'] : "";
        $merchantinfo['error_merchant_password'] = isset($this->error['error_merchant_password']) ? $this->error['error_merchant_password'] : "";
        $merchantinfo['error_merchant_api_endpoint'] = isset($this->error['error_merchant_api_endpoint']) ? $this->error['error_merchant_api_endpoint'] : "";
        $merchantinfo['error_product_id'] = isset($this->error['error_product_id']) ? $this->error['error_product_id'] : "";

         $merchantinfo['error_maximum_product_quantity'] = isset($this->error['error_maximum_product_quantity']) ?
         $this->error['error_maximum_product_quantity'] : "";

        return $merchantinfo;
    }

    /**
     * Check merchant information into merchant form fields.
     *
     * @return array $merchantset_info
     */
    protected function merchant_set_data()
    {
        $merchantset_info = [];
        $merchantset_info['payment_inovio_pay_merchant_id'] = !empty(
            $this->request->post['payment_inovio_pay_merchant_id']
        ) ?
                $this->request->post['payment_inovio_pay_merchant_id'] :
                $this->config->get('payment_inovio_pay_merchant_id');

        $merchantset_info['payment_inovio_pay_merchant_username'] = !empty(
            $this->request->post['payment_inovio_pay_merchant_username']
        ) ?
                $this->request->post['payment_inovio_pay_merchant_username'] :
                $this->config->get('payment_inovio_pay_merchant_username');

        $merchantset_info['payment_inovio_pay_merchant_password'] = !empty(
            $this->request->post['payment_inovio_pay_merchant_password']
        ) ?
                $this->request->post['payment_inovio_pay_merchant_password'] :
                $this->config->get('payment_inovio_pay_merchant_password');

        $merchantset_info['payment_inovio_pay_merchant_api_endpoint'] = !empty(
            $this->request->post['payment_inovio_pay_merchant_api_endpoint']
        ) ?
                $this->request->post['payment_inovio_pay_merchant_api_endpoint'] :
                $this->config->get('payment_inovio_pay_merchant_api_endpoint');

        $merchantset_info['payment_inovio_pay_product_id'] = !empty(
            $this->request->post['payment_inovio_pay_product_id']
        ) ?
                $this->request->post['payment_inovio_pay_product_id'] :
                $this->config->get('payment_inovio_pay_product_id');

        $merchantset_info['payment_inovio_pay_maximum_product_quantity'] = !empty(
            $this->request->post['payment_inovio_pay_maximum_product_quantity']
        ) ?
                $this->request->post['payment_inovio_pay_maximum_product_quantity'] :
                $this->config->get('payment_inovio_pay_maximum_product_quantity');

        $merchantset_info['payment_inovio_pay_advance_key_1'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_1']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_1'] :
                $this->config->get('payment_inovio_pay_advance_key_1');

        $merchantset_info['payment_inovio_pay_advance_value_1'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_1']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_1'] :
                $this->config->get('payment_inovio_pay_advance_value_1');

        $merchantset_info['payment_inovio_pay_advance_key_2'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_2']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_2'] :
                $this->config->get('payment_inovio_pay_advance_key_2');

        $merchantset_info['payment_inovio_pay_advance_value_2'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_2']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_2'] :
                $this->config->get('payment_inovio_pay_advance_value_2');


        $merchantset_info['payment_inovio_pay_advance_key_3'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_3']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_3'] :
                $this->config->get('payment_inovio_pay_advance_key_3');

        $merchantset_info['payment_inovio_pay_advance_value_3'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_3']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_3'] :
                $this->config->get('payment_inovio_pay_advance_value_3');

        $merchantset_info['payment_inovio_pay_advance_key_4'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_4']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_4'] :
                $this->config->get('payment_inovio_pay_advance_key_4');

        $merchantset_info['payment_inovio_pay_advance_value_4'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_4']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_4'] :
                $this->config->get('payment_inovio_pay_advance_value_4');

        $merchantset_info['payment_inovio_pay_advance_key_5'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_5']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_5'] :
                $this->config->get('payment_inovio_pay_advance_key_5');

        $merchantset_info['payment_inovio_pay_advance_value_5'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_5']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_5'] :
                $this->config->get('payment_inovio_pay_advance_value_5');

        $merchantset_info['payment_inovio_pay_advance_key_6'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_6']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_6'] :
                $this->config->get('payment_inovio_pay_advance_key_6');

        $merchantset_info['payment_inovio_pay_advance_value_6'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_6']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_6'] :
                $this->config->get('payment_inovio_pay_advance_value_6');

        $merchantset_info['payment_inovio_pay_advance_key_7'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_7']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_7'] :
                $this->config->get('payment_inovio_pay_advance_key_7');

        $merchantset_info['payment_inovio_pay_advance_value_7'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_7']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_7'] :
                $this->config->get('payment_inovio_pay_advance_value_7');

        $merchantset_info['payment_inovio_pay_advance_key_8'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_8']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_8'] :
                $this->config->get('payment_inovio_pay_advance_key_8');

        $merchantset_info['payment_inovio_pay_advance_value_8'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_8']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_8'] :
                $this->config->get('payment_inovio_pay_advance_value_8');

        $merchantset_info['payment_inovio_pay_advance_key_9'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_9']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_9'] :
                $this->config->get('payment_inovio_pay_advance_key_9');

        $merchantset_info['payment_inovio_pay_advance_value_9'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_9']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_9'] :
                $this->config->get('payment_inovio_pay_advance_value_9');

        $merchantset_info['payment_inovio_pay_advance_key_10'] = !empty(
            $this->request->post['payment_inovio_pay_advance_key_10']
        ) ?
                $this->request->post['payment_inovio_pay_advance_key_10'] :
                $this->config->get('payment_inovio_pay_advance_key_10');

        $merchantset_info['payment_inovio_pay_advance_value_10'] = !empty(
            $this->request->post['payment_inovio_pay_advance_value_10']
        ) ?
                $this->request->post['payment_inovio_pay_advance_value_10'] :
                $this->config->get('payment_inovio_pay_advance_value_10');

        $merchantset_info['payment_inovio_pay_order_status_id'] = !empty(
            $this->request->post['payment_inovio_pay_order_status_id']
        ) ?
                $this->request->post['payment_inovio_pay_order_status_id'] :
                $this->config->get('payment_inovio_pay_order_status_id');

        $merchantset_info['payment_inovio_pay_status'] = !empty(
            $this->request->post['payment_inovio_pay_status']
        ) ?
                $this->request->post['payment_inovio_pay_status'] :
                $this->config->get('payment_inovio_pay_status');

        $merchantset_info['payment_inovio_pay_sort_order'] = !empty(
            $this->request->post['payment_inovio_pay_sort_order']
        ) ?
                $this->request->post['payment_inovio_pay_sort_order'] :
                $this->config->get('payment_inovio_pay_sort_order');

        $merchantset_info['payment_inovio_pay_debug'] = !empty(
            $this->request->post['payment_inovio_pay_debug']
        ) ?
                $this->request->post['payment_inovio_pay_debug'] :
                $this->config->get('payment_inovio_pay_debug');

        return $merchantset_info;
    }

    /**
     * Function is use to create custom logger for inovio payment method
     *
     * @param string $message
     */
    public function inovio_logger($message = "")
    {

        if ($this->config->get('payment_inovio_pay_debug') == 1) {
            $log = new Log('inovio.log');
            $log->write($message);
        }
    }
}
