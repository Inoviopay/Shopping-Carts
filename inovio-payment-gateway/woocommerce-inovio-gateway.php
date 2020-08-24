<?php
/**
 * Plugin Name: Inovio Payment Gateway WooCommerce Plugin
 * Description: Inovio payment gateway provide payment solutions.
 * Author: Inovio Payments
 * Version: 4.4.23
 * Author URI: https://inoviopay.com/
 * Plugin URI: https://www.inoviopay.com
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wporg
 * Domain Path: /languages
 */
if ( !defined( 'ABSPATH' ) ) { // To check absolute path
    exit;
}

// Use to load Woocommerce_Inovio_init after plugin load
add_action( 'plugins_loaded', 'Woocommerce_Inovio_init',11 );
require plugin_dir_path( __FILE__ ) . 'includes/installer/inovio-plugin-database-table.php';
// Create table for refund on activate inovio plugin
register_activation_hook( __FILE__, 'create_inovio_plugin_database_table' );
register_activation_hook( __FILE__, 'create_ach_inovio_plugin_database_table' );
require plugin_dir_path( __FILE__ ) . 'includes/common/class-common-inovio-payment.php';

/**
 * Use to load method after plugin loaded
 *
 * @return null
 */
function Woocommerce_Inovio_init() {
// Check class WP_Payment_Gateway exist or not
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

// Define Core Class Classes path
    define( 'INOVIO_PLUGIN_CORE_CLASS', plugin_dir_path( __FILE__ ) . 'includes/common/inovio-core/' );
    $class_files = scandir( INOVIO_PLUGIN_CORE_CLASS );
    foreach ( $class_files as $file ) {
        if ( preg_match( '/class-inovio?/', $file ) ) {
            include_once INOVIO_PLUGIN_CORE_CLASS . $file;
        }
    }
// hook to add link to inovio setting into checkout tab after activate inovio plugin
    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'plugin_action_links' );

    /**
     * Add links to plugins page for checkout settings
     *
     * @param  array $links
     * @return array
     */
    function plugin_action_links( $links = array() ) {
        $plugin_links = array (
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

// Inovio payment gateway
    include plugin_dir_path( __FILE__ ) . 'includes/inoviopay/woocommerce-inovio-gateway.php';
// Inovio ach payment gateway
    include plugin_dir_path( __FILE__ ) . 'includes/ach/class-woocommerce-ach-inovio-gateway.php';
}

// Hook to add custom checkout field: woocommerce_review_order_before_submit
    add_action( 'woocommerce_review_order_before_submit', 'my_custom_checkout_field' );
    /**
     * Add custom checkout field: woocommerce_review_order_before_submit
     *
     */
    function my_custom_checkout_field() {
        echo '<div id="my_custom_checkout_field">';
            woocommerce_form_field( 'my_field_term', array (
                'type'      => 'checkbox',
                'required'  => 'true',
                'default' => 1,
                'class'     => array ('input-checkbox'),
                'label'     => __( 'I have read and agree to the <a href="'.plugin_dir_url( __FILE__ ) . 'assets/pdf/TOS_ENG.pdf'.'">Terms of Service</a> and <a href="'.plugin_dir_url( __FILE__ ) . 'assets/pdf/TOS_ENG.pdf'.'">Privacy Policy</a>'),
            ),  WC()->checkout->get_value( 'my_field_term' ) );
            echo '</div>';
        global $wpdb;
        $cart = WC()->cart->get_cart();
        foreach( $cart as $cart_item ) {
            $product = wc_get_product( $cart_item['product_id'] );
            if( isset( $cart_item['variation_id'] ) ) {
                $variation_product = new WC_Product_Variation( $cart_item['variation_id'] );
                $regular_price = $variation_product->get_price();
            }
            $product_id = $product->get_id();
            $meta = get_post_meta( $product_id );
            if( isset( $meta['_subscription_period'] ) ) {
                $meta_length = $meta['_subscription_period'][0];
                $meta_interval = $meta['_subscription_period_interval'][0];
                $regular_price = $meta['_subscription_price'][0];
                $currency = get_woocommerce_currency_symbol();
                echo '<div id="my_custom_checkout_field">';
                    woocommerce_form_field( 'my_field_name', array (
                        'type'      => 'checkbox',
                        'required'  => 'true',
                        'default' => 1,
                        'class'     => array ('input-checkbox'),
                        'label'     => __('After '.$meta_interval.$meta_length.' , membership renews at '.$currency .$regular_price.' every '.$meta_interval.$meta_length.' untill cancelled'),
                    ),  WC()->checkout->get_value( 'my_field_name' ) );
                    echo '</div>';
            }
        }
    }

// Hook to save the custom checkout field in the order meta, when checkbox has been checked
    add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta', 10, 1 );
    /**
     * Save the custom checkout field in the order meta, when checkbox has been checked
     *
     * @param  int $order_id
     */
    function custom_checkout_field_update_order_meta( $order_id ) {
        if ( ! empty( sanitize_text_field( $_POST['my_field_name'] ) ) ) {
            update_post_meta( $order_id, 'my_field_name', sanitize_text_field( $_POST['my_field_name'] ) );
        }
        if ( ! empty( sanitize_text_field( $_POST['my_field_term'] ) ) ) {
            update_post_meta( $order_id, 'my_field_term', sanitize_text_field( $_POST['my_field_term'] ) );
        }
    }

// Hook to display the custom field result on the order edit page (backend) when checkbox has been checked
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_on_order_edit_pages', 10, 1 );
    /**
     * Display the custom field result on the order edit page (backend) when checkbox has been checked
     *
     * @param  array $order
     */
    function display_custom_field_on_order_edit_pages( $order ) {
        $my_field_name = get_post_meta( $order->get_id(), 'my_field_name', true );
        $my_field_term = get_post_meta( $order->get_id(), 'my_field_term', true );
        if( 1 == $my_field_name ) {
            echo '<p><strong>Product verification: </strong> <span style="color:red;">enabled</span></p>';
        }
        if( 1 == $my_field_term ) {
            echo '<p><strong>Terms of Service: </strong> <span style="color:red;">enabled</span></p>';
        }
    }
    