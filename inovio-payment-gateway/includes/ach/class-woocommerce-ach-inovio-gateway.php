<?php

/**
 * It is use to create Ach payment method 
 */
include plugin_dir_path( __FILE__ ) . 'methods/class-ach-inovio-method.php';

class Woocommerce_Ach_Inovio_Gateway extends Ach_Inovio_Method {

    public function __construct() {
        parent::__construct();
    }

}

new Woocommerce_Ach_Inovio_Gateway();
