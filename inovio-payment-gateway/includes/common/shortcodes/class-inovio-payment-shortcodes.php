<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of class-inovio-payment-shortcodes
 *
 * @author Inovio Payments
 */
class inovio_payment_shortcodes {

// Load hooks into constructor
    public function __construct() {
        add_shortcode( 'direct_checkoutform', array( $this, 'inoviodirect_checkout_form' ) );
        add_shortcode( 'ach_inovio_checkoutform', array( $this, 'ach_inovio_checkout_form' ) );
    }

    /**
     * Use to show Inovio Gateway setting in checkout section
     *
     * @return array
     */
    public function inovio_admin_setting_form( $form_type = "" ) {
        $description = $form_type == "inoviodirect" ? "Pay with credit card Inovio payment gateway":"Pay with ACH inovio payment gateway";

        $form = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable' ),
                'type' => 'checkbox',
                'label' => __( 'Enable gateway' ),
                'default' => 'yes',
            ),
            'title' => array(
                'title' => __( 'Title' ),
                'type' => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.' ),
                'default'=>__( $description ),
                'custom_attributes' => array(
                    'required' => __( 'required' ),
                ),
            ),
            'description' => array(
                'title' => __( 'Description' ),
                'type' => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.' ),
                'default' => __( $description ),
            ),
            'apiEndPoint' => array(
                'title' => __( 'API End Point' ),
                'type' => 'text',
                'description' => __( 'Inovio Gateway API URL.' ),
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
            'site_id' => array (
                'title' => __( 'Site Id' ),
                'label' => __( ' ' ),
                'type' => 'text',
                'description' => 'API site id',
                'desc_tip' => true,
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
            'req_username' => array (
                'title' => __( 'API Username', 'woocommerce' ),
                'type' => 'text',
                'description' => __( 'Get your API credentials from Inovio.' ),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
            'req_password' => array (
                'title' => __( 'API Password' ),
                'type' => 'password',
                'description' => __( 'Get your API credentials from Inovio.' ),
                'default' => '',
                'desc_tip' => true,
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
            'req_product_id' => array (
                'title' => __( 'Set product Id to purchase product' ),
                'type' => 'text',
                'description' => __( 'Set product Id to purchase product' ),
                'desc_tip' => true,
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
            'inovio_product_quantity_restriction' => array (
                'title' => __( 'Maximum quantity to purchase for single product' ),
                'type' => 'number',
                'description' => __( 'API restriction for quantity to purchase any single product.' ),
                'desc_tip' => true,
                'default' => __( '99' ),
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                    'min' => __( '0' ),
                ),
            ),
            'debug' => array (
                'title' => __( 'Debug Log' ),
                'type' => 'checkbox',
                'label' => __( 'Enable logging' ),
                'default' => 'no',
                'description' => sprintf( __( 'Log , inside <code>uploads/wc-logs/inovio_direct_extention-%s.txt</code>', 'wc_paymentwing' ), sanitize_file_name( wp_hash( 'wing' ) ) ),
            ),
        );

        $form = $form_type == "inoviodirect" ? $form + $this->inovio_advance_form() : $form + array (
            'routing_number_validate' => array (
                'title' => __( 'Routing number validate URL' ),
                'type' => 'text',
                'description' => __( 'Routing number validate URL.' ),
                'default' => 'https://www.routingnumbers.info/api/data.json?rn=',
                'custom_attributes' => array (
                    'required' => __( 'required' ),
                ),
            ),
        );

        return $form;
    }

    public function inovio_advance_form() {

        return array (
            'advancetab' => array (
                'title' => __('Advanced API Parameters'),
                'type' => 'title',
                'description' => '',
            ),

            'APIkey1' => array (
                'title' => __('API Key 1'),
                'type' => 'text',
            ),
            'APIvalue1' => array (
                'title' => __('API Value 1'),
                'type' => 'text',
            ),
            'APIkey2' => array (
                'title' => __('API Key 2'),
                'type' => 'text',
            ),
            'APIvalue2' => array (
                'title' => __('API Value 2'),
                'type' => 'text',
            ),
            'APIkey3' => array (
                'title' => __('API Key 3'),
                'type' => 'text',
            ),
            'APIvalue3' => array (
                'title' => __('API Value 3'),
                'type' => 'text',
            ),
            'APIkey4' => array (
                'title' => __('API Key 4'),
                'type' => 'text',
            ),
            'APIvalue4' => array (
                'title' => __('API Value 4'),
                'type' => 'text',
            ),
            'APIkey5' => array (
                'title' => __('API Key 5'),
                'type' => 'text',
            ),
            'APIvalue5' => array (
                'title' => __('API Value 5'),
                'type' => 'text',
            ),
            'APIkey6' => array (
                'title' => __('API Key 6'),
                'type' => 'text',
            ),
            'APIvalue6' => array (
                'title' => __('API Value 6'),
                'type' => 'text',
            ),
            'APIkey7' => array (
                'title' => __('API Key 7'),
                'type' => 'text',
            ),
            'APIvalue7' => array (
                'title' => __('API Value 7'),
                'type' => 'text',
            ),
            'APIkey8' => array (
                'title' => __('API Key 8'),
                'type' => 'text',
            ),
            'APIvalue8' => array (
                'title' => __('API Value 8'),
                'type' => 'text',
            ),
            'APIkey9' => array (
                'title' => __('API Key 9'),
                'type' => 'text',
            ),
            'APIvalue9' => array (
                'title' => __('API Value 9'),
                'type' => 'text',
            ),
            'APIkey10' => array (
                'title' => __('API Key 10'),
                'type' => 'text',
            ),
            'APIvalue10' => array (
                'title' => __('API Value 10'),
                'type' => 'text',
            )
        );
    }

    /**
     * Use to show form on checkout page
     *
     * @return string $html
     */
    public function inoviodirect_checkout_form() {
        $today = date( 'Y' );
        $start = date( 'Y' );
        $html = '<fieldset class="inoviodirectmethod_gate_form">
                <p class="form-row form-row-wide validate-required inoviodirectmethod_gate_card_number_wrap">
                    <label for="inoviodirectmethod_gate_card_numbers">Card number</label>
                    <input class="input-text" name="inoviodirectmethod_gate_card_numbers" title="Please enter valid card no" id="inoviodirectmethod_gate_card_numbers"  pattern="^0[1-16]|[1-16]\d$" maxlength="16" size="16" type="text" required>
                    <span id="inoviodirectmethod_gate_card_type_image"></span>
                </p>
                <p class="form-row form-row-first validate-required">
                  <label for="inoviodirectmethod_gate_card_expiration">Expiry date</label>
                <select id="cc-exp-month" class="txt" name="exp_month">
                    <option value="01">Jan</option>
                    <option value="02">Feb</option>
                    <option value="03">Mar</option>
                    <option value="04">Apr</option>
                    <option value="05">May</option>
                    <option value="06">Jun</option>
                    <option value="07">Jul</option>
                    <option value="08">Aug</option>
                    <option value="09">Sep</option>
                    <option value="10">Oct</option>
                    <option value="11">Nov</option>
                    <option value="12">Dec</option>
                </select>
                <select id="cc-exp-year" class="txt" name="exp_year">';

        for ( $start; $start <= $today + 10; $start++ ) {
            $html .= "<option value='" . $start . "'>$start</option>";
        }
        $html .= '</select>
                </p>
                <p class="form-row form-row-last validate-required">
                    <label for="inoviodirectmethod_gate_card_csc">Card security code</label>
                    <input type="password" class="input-text" id="inoviodirectmethod_gate_card_cvv" title="Please enter valid card security no"
                        name="inoviodirectmethod_gate_card_cvv" maxlength="4" size="4" pattern="[0-9]+" required
                    />
                </p>
                <div class="clear"></div>
            </fieldset>';
        return $html;
    }

    /**
     * Use to show form on checkout page
     *
     * @return string $html
     */
    public function ach_inovio_checkout_form() {
        $html = '<fieldset class="inoviodirectmethod_gate_form">
                <p class="form-row form-row-wide validate-required routing_number">
                    <label for="ach_inovio_routing_number">Routing number</label>
                    <input class="input-text" pattern="^0[1-9]|[1-9]\d$" name="ach_inovio_routing_number" title="Please enter valid routing number"
                    id="ach_inovio_routing_number"   maxlength="9" size="9" type="text" required />
                    <span id="ach_routing_number_message"></span>
                </p>
                <p class="form-row validate-required ">
                    <label for="ach_inovio_account_number">Account number</label>
                    <input class="input-text" pattern="^0[1-18]|[1-18]\d$" name="ach_inovio_account_number" title="Please enter valid card no" id="ach_inovio_account_number"
                        maxlength="18" size="18" type="text" required />
                </p>
                <p class="form-row validate-required ">
                    <label for="ach_inovio_confirm_account_number">Confirm account number</label>
                    <input class="input-text" pattern="^0[1-18]|[1-18]\d$" name="ach_inovio_confirm_account_number" title="Please enter valid card no" id="ach_inovio_confirm_account_number"
                        maxlength="18" size="18" type="text" required />
                         <span id="account_matched_message"></span>
                </p>
                <div class="clear"></div>
            </fieldset>';
        return $html;
    }
}
