<?php

/**
 * Class Inovio_Direct_Method
 * Use to extend WC_Payment_Gateway
 */
class Ach_Inovio_Method extends WC_Payment_Gateway {

    public static $inovio_direct_api_log = false;

    /**
     * Default constructor to set initial parameters and methods
     */
    public function __construct() {
        // Make unique name for inovio direct method
        $this->id = 'achinoviomethod';
        $this->common_class = new class_common_inovio_payment();
        // Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration)
        $this->has_fields = true;
        // Title of the payment method shown on the admin page.
        $this->method_title = __( 'ACH Inovio', $this->id );
        // Enter an URL to an image
        $this->icon = plugins_url()."/".explode("/", plugin_basename( __file__ ))[0] . '/assets/img/ach-check-logo.png';
        $this->method_description = 'Pay with ACH Inovio payment gateway';  
        $this->supports = array ('products',
                                'refunds',
                                'subscriptions',
                                'subscription_cancellation', 
                                'subscription_suspension', 
                                'subscription_reactivation',
                                'subscription_amount_changes',
                                'subscription_date_changes',
                                'multiple_subscriptions',
                            );
        // To set admin section form field
        $this->init_form_fields();

        // To set admin section
        $this->init_settings();
        // Get user define values from admin
        $this->enabled = $this->get_option( 'enabled' );
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->instructions = $this->get_option( 'instructions' );
        $this->api_endpoint = $this->get_option( 'apiEndPoint' );
        $this->site_id = $this->get_option( 'site_id' );
        $this->req_username = $this->get_option( 'req_username' );
        $this->req_password = $this->get_option( 'req_password' );
        $this->debug = $this->get_option( 'debug' );
        $this->debug = 'yes' == $this->get_option( 'debug', 'no' );
        $this->req_product_id = $this->get_option( 'req_product_id' );
        $this->routing_number_validate = $this->get_option( "routing_number_validate" );
        add_action( 'wp_enqueue_scripts', array( $this, 'inovio_ach_payment_script' ) );

        // Check WooCommerce version
        if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array ( &$this, 'process_admin_options' ) );
        } else {
            add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
        }

        // add js in Inovio gateway
    }

    /**
     * Use to load js file for Inovio ACH payment gateway
     *
     * @access public
     */
    public function inovio_ach_payment_script() {

        wp_enqueue_script(
                'ach-inovio-gateway-js', plugins_url()."/".explode("/", plugin_basename( __file__ ))[0] . '/assets/js/inovio-ach-script.js', array ( 'jquery' )
        );
        $achInovioPlugindir = plugins_url()."/".explode("/", plugin_basename( __file__ ))[0];
        wp_localize_script( 'ach-inovio-gateway-js', 'achInovioPlugindir', $achInovioPlugindir );
        wp_localize_script( 'ach-inovio-gateway-js', 'ach_ajax_scripts', array (
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'ach_validate_routing_url' => $this->routing_number_validate
         ) );
    }

    /**
     * It is use to update ACH inovio api pending to complete
     * 
     * @global type $wpdb
     * @param type $order_id
     * @param type $amount
     * @param type $reason
     * @return \WP_Error|boolean
     * @throws Exception
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        
        global $wpdb;
        try {
            // Get order related data
            $order = wc_get_order( $order_id );

            if ( !$order_id ) {
                throw new Exception( __( 'Invalid order ID.', 'woocommerce' ) );
            }

            $action = wc_clean( $_REQUEST['action'] );
            // Add partial refunded amount with inovio for total refund
            if ( !empty( $action ) && $action === 'woocommerce_refund_line_items' ) {
                $this->ach_insert_refunded_data( $order_id, $amount );
            }
            $order_status = wc_clean( $_REQUEST['order_status'] );
          
            if ( !empty( $order_status ) && $order_status === 'wc-refunded' ) {

                $amount = $order->get_total() - $this->ach_inovio_get_total_refunded( $order_id );
            }

            // Transaction_id will be only found in case of inovio payment method
            $transaction_id = get_post_meta( $order_id, '_achtransaction_id', true );
            $this->common_class->inovio_logger( "transaction_id-".$transaction_id, $this );
            $this->common_class->inovio_logger( "order_id-".$order_id, $this );

            if ( get_post_meta( $order_id, '_payment_method', true ) != 'achinoviomethod' || empty( $transaction_id ) ) {
                return;
            }
            
            // Merge params
            $params = array_merge(
                    $this->common_class->merchant_credential( $this ), array (
                'request_ref_po_id' => $transaction_id,
                'credit_on_fail' => 0,
                'li_value_1' => $amount,
                    )
            );

            $service_config = new InovioServiceConfig( $params );
            $processor = new InovioProcessor( $service_config );

            // Set method ccreverse
            $response = $processor->set_methodname( 'ach_credit' )->get_response();

            $parse_result = json_decode( $response );


            if ( !empty($parse_result->PO_ID ) && !empty( $parse_result->PO_ID ) && $parse_result->PO_ID == $transaction_id ) {
                $order->add_order_note( 'ACH Inovio Payment refund completed. Refund Transaction ID:-' . $parse_result->PO_ID );
            } else {

                $order->add_order_note( 'Respnse code:' . $parse_result->SERVICE_RESPONSE . " and Service API response-" . $parse_result->SERVICE_ADVICE );

                $this->common_class->inovio_logger( $response, $this );
            }
        } catch ( Exception $ex ) {

            $this->common_class->inovio_logger( $ex->getMessage(), $this );
            return;
        }
    }

    /**
     * Create form to configure merchant information
     * @global object $wpdb
     * @param type $order_id
     */
    public function ach_inovio_get_total_refunded( $order_id = null ) {

        $order = new WC_Order( $order_id );
        global $wpdb;
        $qry = "SELECT sum( ach_inovio_refunded_amount ) as  already_refunded_amount from {$wpdb->prefix}ach_inovio_refunded as t1 WHERE t1.ach_inovio_order_id=$order_id";
        $resultset = $wpdb->prepare( get_results( $qry, OBJECT ) );

        return $resultset[0]->already_refunded_amount;
    }

    /**
     * Add Partial refunded amount
     *
     * @global object $wpdb
     * @param  int $order_id
     * @param int $amount
     */
    public function ach_insert_refunded_data( $order_id = null, $amount = null ) {
        global $wpdb;
        $wpdb->insert(
                $wpdb->prefix . 'ach_inovio_refunded', array (
            'ach_inovio_order_id' => $order_id,
            'ach_inovio_refunded_amount' => $amount,
                ), array (
            '%s',
            '%f',
                )
        );
    }

    /**
     * Create form to configure merchant information
     */
    public function init_form_fields() {
        $direct_object = new inovio_payment_shortcodes();
        $this->form_fields = $direct_object->inovio_admin_setting_form();
    }

    /**
     * Use to create payment form on checkout page.
     *
     */
    public function payment_fields() {
        if ( !empty( $this->description ) ) {
            echo wpautop( wptexturize( $this->description ) );
        }
        echo do_shortcode( '[ach_inovio_checkoutform]' );
    }

    /**
     * Use to process payment
     *
     * @param int $order_id
     * @global object $woocommerce
     */
    public function process_payment( $order_id ) {

        global $woocommerce;

        $order = wc_get_order( $order_id );
        $routing_number = !empty( wc_clean( $_POST['ach_inovio_routing_number'] ) ) ? str_replace(array( ' ', '-' ), '', woocommerce_clean( $_POST['ach_inovio_routing_number'] ) ) : '';
        $account_number = !empty( wc_clean( $_POST['ach_inovio_account_number'] ) ) ? woocommerce_clean( $_POST['ach_inovio_account_number'] ) : '';
        try {
            if ( empty( $account_number ) ) {
                throw new Exception( __( 'Please enter account number', $this->id ) );
            } elseif ( empty( $routing_number ) ) {
                throw new Exception( __ ( 'Please enter routing number', $this->id ) );
            }
            // Restrict product's quantity
            if ( $this->common_class->restrict_quantity( $this ) == false ) {

                global $woocommerce;
                $cart_url = $woocommerce->cart->get_cart_url();
                throw new Exception(
                __(
                        "For any single product's quantity should not be greater than " .
                        $this->get_option( 'inovio_product_quantity_restriction' ) .
                        ".<a href='$cart_url'> Back to cart page.</a>", $this->id
                )
                );
            }

            // merchant authentication
            if ( $this->common_class->merchant_authorization( $this ) == false ) {
                throw new Exception( __( 'Please contact to service provider', $this->id ) );
            } else {
                $sanitize_post = wc_clean( $_POST );
                $order_param = $this->common_class->get_order_params( $order_id, $sanitize_post );

                // Combine array parameters to call auth_and_capture
                $params = array_merge( $this->common_class->merchant_credential( $this ), $order_param, $this->common_class->get_product_ids( $order, $this )
                );

                $status = 'WC-' . $order_id . '-' . time();

                $service_config = new InovioServiceConfig( $params );
                $processor = new InovioProcessor( $service_config );

                // Set method auth and capture
                $response = $processor->set_methodname( 'ach_auth_and_capture' )->get_response();

                $parse_result = json_decode( $response );
                // check card length
                if ( !empty($parse_result->API_ADVICE ) || !empty($parse_result->API_RESPONSE ) && $parse_result->API_RESPONSE===111 ) {
                    throw new Exception( __( 'Invalid account number.', $this->id ) );
                }

                // check card length
                if ( !empty($parse_result->REF_FIELD ) || !empty( $parse_result->API_ADVICE ) || !empty( $parse_result->SERVICE_ADVICE ) ) {
                    throw new Exception( __( 'Something went wrong, please contact support', $this->id ) );
                } elseif (
                        isset($parse_result->TRANS_STATUS_NAME) &&
                        'PENDING' == $parse_result->TRANS_STATUS_NAME &&
                        empty( $parse_result->API_ADVICE ) &&
                        empty( $parse_result->SERVICE_ADVICE )
                ) {
                    // Add thank you message after complete payment
                    $thankyou_msg = 'Transaction has been completed successfully.';

                    // Add order note
                    $order->add_order_note( 'payment has been completed by ACH inovio payment gateway and Transaction Id:' . $parse_result->PO_ID );
                    
                    // Payment complete add PO_ID as transaction id in post_meta table
                    add_post_meta( $order->id, '_achtransaction_id', $parse_result->PO_ID,true );
                    $order->update_status( 'processing' );            
                   

                    if ( $this->debug == 'yes' ) {
                        // Add log
                        $this->common_class->inovio_logger( 'Payment Complete', $this );
                        $this->common_class->inovio_logger( print_r( $response, true ), $this );
                    }

                    // Reduce stock
                    $order->reduce_order_stock();

                    // Add notice thank you page
                    wc_add_notice( $thankyou_msg, 'success' );

                    // Add token Id in as note
                    $order->add_order_note( 'Token ID:-' . $parse_result->PO_ID );
                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    if ( !is_admin() ) {
                        WC()->session->set( 'affiliate_hash', '' );
                    }

                    // Return thank you page redirect
                    return array (
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order ),
                    );
                } elseif ( !empty( $parse_result->API_ADVICE ) || empty( $parse_result->SERVICE_ADVICE ) ) {

                    $status = 'ERROR';

                    // Add note
                    $order->add_order_note( sprintf( __( 'TransactionID %s', $this->id ), $parse_result->PO_ID ) );

                    add_post_meta( $order_id, '_achtransaction_id', $parse_result->PO_ID, true );

                    // Payment failed
                    $order->update_status( 'failed', sprintf( __( 'ACH payment failed. Payment was rejected due to an error%s', $this->id ) ) );

                    // Remove cart
                    $woocommerce->cart->empty_cart();

                    if ( $this->debug == 'yes' ) {
                        // Add log
                        $this->common_class->inovio_logger( 'Transaction Failed', $this );
                        $this->common_class->inovio_logger( $response, $this );
                    }

                    wc_add_notice( sprintf( __( 'Payment failed%s ', $this->id ) ), 'error' );

                    throw new Exception(
                    __(
                            'Something went wrong, please contact to your '
                            . 'service provider.', $this->id
                    )
                    );
                }
            }
        } catch (Exception $ex) { // Add log
            $this->common_class->inovio_logger( $ex->getMessage(), $this );
            $this->common_class->inovio_logger( print_r( $parse_result, true ), $this );
            
            wc_add_notice( $ex->getMessage(), 'error' );
        }
    }

}

// end class Inovio_Direct_Method

/**
 * Use to load Custom Gateway extention into WooCommerce
 *
 * @param array $method
 * @return array $method
 */
function add_ach_inovio_class( $method ) {
    $method[] = 'Ach_Inovio_Method';

    return $method;
}

// add Inovio Payment Gateway using hooks woocommerce_payment_gateways
add_filter( 'woocommerce_payment_gateways', 'add_ach_inovio_class' );
