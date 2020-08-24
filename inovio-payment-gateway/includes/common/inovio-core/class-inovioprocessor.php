<?php

/**
 * Class InovioProcessor
 *
 * InovioProcessor process the API Call.
 *
 * @package Inovio
 */
class InovioProcessor {

    /**
     * InovioServiceConfig object
     */
    private $service_config;

    /**
     * Request parameters
     */
    private $requestparams = [];

    /**
     * API Request parameters
     */
    private $request_method = null;

    /**
     * default Constructor
     */
    public function __construct( InovioServiceConfig $service_config ) {
        $this->service_config = $service_config;
    }

    /**
     * Set API request method.
     *
     * @param  string $request_method SDK/API method name
     * @return object  $this
     */
    public function set_methodname( $request_method = null ) {
        $this->request_method = $request_method;

        return $this;
    }

    /**
     * Set and Check API request parameters.
     *
     * @param  array $postData  Array holds gateway request parameters.
     * @return object  $this
     */
    public function set_params( $postData = array() ) {
        if ( empty( $postData) || !is_array($postData ) ) {
            throw new Exception( 'Invalid API request parameters.' );
        }
        $this->requestparams = $postData;

        return $this;
    }

    /**
     * Identify and Invoking defined API method request
     * return exception otherwise.
     *
     * @param object $this->{$this->request_method}()  Inovio API response
     */
    public function get_response() {
        if ( empty( $this->request_method ) || !method_exists( $this, $this->request_method ) ) {
            throw new Exception( 'Invalid API request method.' );
        }
        $this->requestparams += $this->service_config->get_config();

        return $this->{$this->request_method}();
    }

    /**
     * Verify the card detail and address detail of customer
     */
    private function authenticate() {
        $this->requestparams['request_action'] = 'TESTAUTH';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Check if Payment Service is available to process requests.
     */
    private function service_availability() {
        $this->requestparams['request_action'] = 'TESTGW';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Confirm the availability of funds in the cardholder�s bank account.
     */
    private function authorization() {
        $this->requestparams['request_action'] = 'CCAUTHORIZE';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Capture of the authorized funds in a single request to the Payment Service.
     */
    private function auth_and_capture() {
        $this->requestparams['request_action'] = 'CCAUTHCAP';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Cancelation of subscription to the Payment Service.
     */
    private function cancel_subscription() {
        $this->requestparams['request_action'] = 'SUB_CANCEL';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Updation of subscription to the Payment Service.
     */
    private function update_subscription() {
        $this->requestparams['request_action'] = 'SUB_UPDATE';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * ACHAUTHCAP .
     */
    private function ach_auth_and_capture() {
        $this->requestparams['request_action'] = 'ACHAUTHCAP';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * ACHCREDIT .
     */
    private function ach_credit() {
        $this->requestparams['request_action'] = 'ACHCREDIT';
        $response = $this->service_config->execute_call( $this->requestparams);

        return $this->service_config->filter_response( $response, [] );
    }
    
     /**
     * ACHAUTHCAP .
     */
    private function ach_reverse() {
        $this->requestparams['request_action'] = 'ACHREVERSE';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Void a previous authorization.
     */
    private function ccreverse() {
        $this->requestparams['request_action'] = 'CCREVERSE';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Capture a previous authorization
     */
    private function capture() {
        $this->requestparams['request_action'] = 'CCCAPTURE';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Refund a previously captured authorization
     */
    private function cc_credit() {
        $this->requestparams['request_action'] = 'CCCREDIT';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

    /**
     * Check the status of a previous purchase
     * useful if the connection timed out, which creates uncertainty about the purchase result.
     */
    private function cc_status() {
        $this->requestparams['request_action'] = 'CCSTATUS';
        $response = $this->service_config->execute_call( $this->requestparams );

        return $this->service_config->filter_response( $response, [] );
    }

}

// end Processor Class
