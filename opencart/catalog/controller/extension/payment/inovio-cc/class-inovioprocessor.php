<?php

/**
 * Class InovioProcessor
 *
 * InovioProcessor process the API Call.
 *
 * @package Inovio
 */
class InovioProcessor
{

    /**
     * InovioServiceConfig object
     */
    private $serviceConfig;

    /**
     * Request parameters
     */
    private $request_params = [];

    /**
     * API Request parameters
     */
    private $request_method = null;

    /* public function __construct(InovioServiceConfig $serviceConfig)
      {
      $this->serviceConfig = $serviceConfig;
      } */

    public function setServiceConfig($serviceConfig = null)
    {
        $this->serviceConfig = $serviceConfig;
    }

    /**
     * Set API request method.
     *
     * @param  string  $requestMethod SDK/API method name
     * @return object  $this
     */
    public function setMethodName($requestMethod = null)
    {
        $this->request_method = $requestMethod;

        return $this;
    }

    /**
     * Set and Check API request parameters.
     *
     * @param  array   $postData  Array holds gateway request parameters.
     * @return object  $this
     */
    public function setParams($postData = array())
    {
        if (empty($postData) || !is_array($postData)) {
            throw new Exception("Invalid API request parameters.");
        }
        $this->request_params = $postData;

        return $this;
    }

    /**
     * Identify and Invoking defined API method request
     * return exception otherwise.
     *
     * @param object  $this->{$this->request_method}()  Inovio API response
     */
    public function getResponse()
    {
        if (empty($this->request_method) || !method_exists($this, $this->request_method)) {
            throw new Exception("Invalid API request method.");
        }
        $this->request_params += $this->serviceConfig->getConfig();


        return $this->{$this->request_method}();
    }

    /**
     * Verify the card detail and address detail of customer
     */
    private function authenticate()
    {
        $this->request_params['request_action'] = 'TESTAUTH';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Check if Payment Service is available to process requests.
     */
    private function serviceAvailability()
    {
        $this->request_params['request_action'] = 'TESTGW';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Confirm the availability of funds in the cardholder�s bank account.
     */
    private function authorization()
    {
        $this->request_params['request_action'] = 'CCAUTHORIZE';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Capture of the authorized funds in a single request to the Payment Service.
     */
    private function authAndCapture()
    {
        $this->request_params['request_action'] = 'CCAUTHCAP';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Void a previous authorization.
     */
    private function ccReverse()
    {
        $this->request_params['request_action'] = 'CCREVERSE';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Capture a previous authorization
     */
    private function ccCapture()
    {
        $this->request_params['request_action'] = 'CCCAPTURE';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Refund a previously captured authorization
     */
    private function ccCredit()
    {
        $this->request_params['request_action'] = 'CCCREDIT';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Check the status of a previous purchase
     * useful if the connection timed out, which creates uncertainty about the purchase result.
     */
    private function ccStatus()
    {
        $this->request_params['request_action'] = 'CCSTATUS';
        $response = $this->serviceConfig->executeCall($this->request_params);

        return $this->serviceConfig->filterResponse($response, []);
    }
}

## end Processor Class
