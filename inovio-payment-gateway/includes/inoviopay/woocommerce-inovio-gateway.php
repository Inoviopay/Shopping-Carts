<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Woocommerce_Inovio_Gateway
 *
 * @author Inovio Payments
 */
include plugin_dir_path( __FILE__ ) . '../common/shortcodes/class-inovio-payment-shortcodes.php';
include plugin_dir_path( __FILE__ ) . 'methods/class-inovio-direct-method.php';

/**
 * Woocommerce_Inovio_Gateway Class
 */
class Woocommerce_Inovio_Gateway extends Inovio_Direct_Method {

    /**
     *  Default constructor to set default parameters and methods.
     */
    public function __construct() {
        // add js in Inovio gateway
        add_action( 'init', array( $this, 'inovio_direct_payment_script' ) );
        // add css in Inovio gateway
        add_action( 'init', array( $this, 'inovio_direct_payment_style' ) );
        parent::__construct();
       
        add_action( 'init', array( $this, 'inovio_session' ) );
    }

    /**
     * Use to load js file for Inovio Payment Gateway
     *
     * @access public
     */
    public function inovio_direct_payment_script() {
        wp_enqueue_script( 'inovio-type-detection' );
        wp_enqueue_script(
                'inovio-type-detection', plugins_url()."/".explode("/", plugin_basename( __file__ ))[0] . '/assets/js/inovio-script.js', array( 'jquery' )
        );
    }

    /**
     * Use to load js file for Inovio Payment Gateway
     *
     * @access public
     */
    public function inovio_direct_payment_style() {
        wp_enqueue_style( 'inovio-type-detection-css' );
        wp_enqueue_style(
                'inovio-type-detection-css', plugins_url()."/".explode("/", plugin_basename( __file__ ))[0] . '/assets/css/inovio-style.css'
        );
    }

    /**
     * use to add affiliate hash id in session
     */
    public function inovio_session() {
        if ( isset( $_GET['affiliates'] ) ) {

            if ( !is_admin() ) {
                WC()->session->set( 'affiliate_hash', sanitize_key( $_GET['affiliates'] ) );
            }
        }
    }

}

new Woocommerce_Inovio_Gateway();
