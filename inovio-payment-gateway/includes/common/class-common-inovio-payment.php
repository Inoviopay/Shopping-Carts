<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class_common_inovio_payment
 *
 * @author Inovio Payments
 */
class class_common_inovio_payment {

    /**
     * Use to get advance fields
     *
     * @return array
     */
    public function get_advaceparam($options = Object) {
        $advance_params = [];
        for ( $i=1; $i <= 10 ; $i++ ) {
            $advance_params[$options->get_option( 'APIkey'.$i )] = $options->get_option( 'APIvalue'.$i );
        }
        return $advance_params;
    }
    
    /**
     * For single product's quantity should not be greater than 99
     */
    public function restrict_quantity( $options = Object ) {
        $cart = WC()->cart->cart_contents;
        $returnstate = true;
        foreach ( $cart as $cart_item  ) {
            if ( $cart_item['quantity'] > $options->get_option( 'inovio_product_quantity_restriction' ) ) {
                $returnstate = false;
            }
        }
        return $returnstate;
    }

    /**
     * Use to get product Ids, price and quantity
     *
     * @return array()
     */
    public function get_product_ids( $order = array(), $options = Object ) {
        $final_array = [];
        $final_array['li_prod_id_1'] = $options->req_product_id;
        $final_array['li_count_1'] = 1;
		$final_array['li_value_1'] = ( $order->get_total() ) ? $order->get_total() : 0;

        return $final_array;
    }

    /**
     * Use to authorize merchant
     *
     * @return boolean
     */
    public function merchant_authorization( $options ) {
        $service_config = new InovioServiceConfig( $this->merchant_credential( $options ) );
        $processor = new InovioProcessor( $service_config );
        // authorize user
        $response = $processor->set_methodname( 'authenticate' )->get_response();
        // decode json data into object
        $parse_result = json_decode( $response );
        // Getting Service response
        if ( 100 != $parse_result->SERVICE_RESPONSE ) {
            if ( $options->debug == 'yes' ) :
                $this->inovio_logger( 'Authentication Failed' );
                $this->inovio_logger( $response );
            endif;

            return false;
        } else {
            return true;
        }
    }

    /**
     * use to set merchant related Parameters
     *
     * @return array $requestParams
     */
    public function merchant_credential( $options ) {
        $request_params = [
            'end_point' => $options->api_endpoint,
            'site_id' => $options->site_id,
            'req_username' => $options->req_username,
            'req_password' => $options->req_password,
        ];
        $final_request_params = [];
        foreach ( $request_params as $reqKey => $reqParamVal ) {
            if ( empty( $request_params[$reqKey] ) ) {
                throw new Exception(
                __(
                        'Something went wrong, please contact to your service provider'
                )
                );
            }
            $final_request_params[$reqKey] = trim($reqParamVal);
        }
        return $final_request_params;
    }

    /**
     * use to check credit card expiration date
     * 
     * @param  int $card_date
     */
    public function validate_expirydate( $card_date = null ) {
        $today = date( 'Ym' );
        $today_dt = new DateTime( $today );
        $expire_dt = new DateTime( $card_date );

        if ( $expire_dt < $today_dt ) {
            return false;
        }
            return true;
    }

    /**
     * Inovio Logging method
     *
     * @param  string $message
     */
    public function inovio_logger( $message, $options ) {
        if ( isset( $options->debug ) && 'yes' == $options->debug ) {
            $log = new WC_Logger();
            $log->add( 'Inovio_payment', $message );
        }
    }

    /**
    * 
    * process subscription parameters
    *
    * @param  int $order_id
    */

    public function get_order_params_subscription( $order_id ) {
        $order = new WC_Order( $order_id );
        $parent_order_id = WC_Subscriptions_Renewal_Order::get_parent_order_id( $order_id );
        update_post_meta( $order->get_id(), '_inovio_gateway_scheduled_subscription_custid', get_post_meta( $parent_order_id, 'CUST_ID', true ) );
        $params=  [
        'CUST_ID' => get_post_meta( $parent_order_id, 'CUST_ID', true ) == "" ? "" : get_post_meta( $parent_order_id, 'CUST_ID', true ),
        'PMT_L4' => get_post_meta( $parent_order_id, 'PMT_L4', true ) == "" ? "" : get_post_meta( $parent_order_id, 'PMT_L4', true ),
        'REQUEST_REBILL' => 1,
        'request_action' => 'CCAUTHCAP',
        'request_currency' => get_woocommerce_currency(),
        'XTL_ORDER_ID' => $order_id,
        ];                                                        
        return $params;           
    }

    /**
    * 
    * Process payment parameters.
    *
    * @param  int $order_id
    * @param  int $expiry_date
    * @param  array $post_data
    */

    public function get_order_params( $order_id, $post_data, $expiry_date = "" ) {
        $order = new WC_Order( $order_id );
        $pmt_key_or_routing_number = [];
        $pmt_number = "";
        if ( !empty( $post_data["ach_inovio_routing_number"] ) && strlen( $post_data["ach_inovio_routing_number"] ) > 3 && $post_data["payment_method"] == "achinoviomethod" ) {
            $pmt_key_or_routing_number = ["bank_identifier" => wc_clean( $post_data["ach_inovio_routing_number"] )];
            $pmt_number = $post_data["ach_inovio_account_number"];
        } elseif ( !empty( $post_data["inoviodirectmethod_gate_card_cvv"] ) && strlen( $post_data["inoviodirectmethod_gate_card_cvv"]) <= 4 && $post_data["payment_method"] == "inoviodirectmethod" ) {
            $pmt_key_or_routing_number = ["pmt_key" => wc_clean( $post_data["inoviodirectmethod_gate_card_cvv"] )];
            $pmt_number = $post_data["inoviodirectmethod_gate_card_numbers"];
        }
        $params=  [
            'XTL_ORDER_ID' => $order_id,
            'bill_addr' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'pmt_numb' => $pmt_number,
            'xtl_ip' => get_post_meta( $order_id, '_customer_ip_address', true ) == "::1" ? "127.0.0" : get_post_meta( $order_id, '_customer_ip_address', true ),
            'cust_fname' => $order->get_billing_first_name(),
            'cust_lname' => $order->get_billing_last_name(),
            'pmt_expiry' => $expiry_date,
            'cust_email' => $order->get_billing_email(),
            'bill_addr' => $order->get_billing_address_1() . ', ' . $order->get_billing_address_2(),
            'bill_addr_zip' => $order->get_billing_postcode(),
            'bill_addr_city' => $order->get_billing_city(),
            'bill_addr_state' => $order->get_billing_state(),
            'request_currency' => get_woocommerce_currency(),
            'bill_addr_country' => $order->get_billing_country(),
            'ship_addr_country' => $order->get_shipping_country(),
            'ship_addr_city' => $order->get_shipping_city(),
            'ship_addr_state' => $order->get_shipping_state(),
            'ship_addr_zip' => $order->get_shipping_postcode(),
            'ship_addr' => $order->get_shipping_address_1() . ', ' . $order->get_shipping_address_2(),
                ] + $pmt_key_or_routing_number;
        if( $post_data["payment_method"] == "achinoviomethod" ){
            unset( $params["pmt_expiry"] );
        }
        return $params;
    }
}
