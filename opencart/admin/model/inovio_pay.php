<?php

/**
 * Class used to extend core model of opencart for payment.
 */
class ModelExtensionPaymentInovioPay extends Model
{

    /**
     *  It contains to create custom table to store refund related data.
     */
    public function install()
    {
        $this->db->query("
                CREATE TABLE IF NOT EXISTS  `" . DB_PREFIX . "inovio_refund_data` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `inovio_order_id` bigint(20) NOT NULL,
                `inovio_api_po_id` varchar(256) DEFAULT NULL,
                PRIMARY KEY (id)
              ) ENGINE=MyISAM DEFAULT CHARSET=latin1;
            ");
    }

    /**
     *  It used to drop table on uninstall payment extension.
     */
    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "inovio_refund_data`;");
    }

    /**
     * Get product order id for refund functionality.
     *
     * @param int $orderId
     * @return int
     */
    public function getProductOrderId($orderId = null)
    {
        $orderData = $this->db->query("SELECT inovio_api_po_id FROM `" . DB_PREFIX . "inovio_refund_data` WHERE inovio_order_id=$orderId");

        return $orderData->row['inovio_api_po_id'];
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
     * Include inovio payment method core SDK.
     *
     * @param NULL
     */
    public function include_core_class()
    {

        try {
            $this->load->language('extension/payment/inovio_pay');
            // Include core SDK claass
            $file_folder = dirname(__FILE__) . '/inovio-cc/';
            $class_files = scandir($file_folder);

            if ($class_files > 0) {
                foreach ($class_files as $files) {
                    if (preg_match('/class-inovio?/', $files)) {
                        include_once($file_folder . $files);
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
     * Use to set Inovio initial requeired parameters
     *
     * @return arrray $requestParams
     */
    public function get_gateway_information()
    {

        $this->load->language('extension/payment/inovio_pay');

        $requestParams = [
            'end_point' => $this->config->get('payment_inovio_pay_merchant_api_endpoint'),
            'site_id' => $this->config->get('payment_inovio_pay_merchant_id'),
            'req_username' => $this->config->get('payment_inovio_pay_merchant_username'),
            'req_password' => $this->config->get('payment_inovio_pay_merchant_password'),
            'request_response_format' => 'json'
        ];

        if (in_array("", $requestParams)) {
            throw new Exception($this->language->get('text_inovio_common_error_message'));
        }

        return $requestParams;
    }

    /**
     * Update order status for refund process
     *
     * @param int $order_id
     * @param int $order_status_id
     * @param string $comment
     */
    public function updateOrderStatusOnRefund($order_id = null, $order_status_id = null, $comment = null)
    {
        $this->db->query("UPDATE `" . DB_PREFIX . "order_history` SET `order_status_id` = '"
                . (int) $order_status_id . "', `notify` ="
                . " '0', `comment` = '" . $this->db->escape($comment)
                . "', `date_added` = NOW() WHERE `order_id` = '" . (int) $order_id . "' AND `order_status_id` =".$order_status_id);
    }
}
