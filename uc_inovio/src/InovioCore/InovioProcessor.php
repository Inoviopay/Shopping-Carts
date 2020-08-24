<?php
namespace Drupal\uc_inovio\InovioCore;
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
    public $serviceConfig;

    /**
     * Request parameters
     */
    public $requestParams = [];

    /**
     * API Request parameters
     */
    public $requestMethod = null;
    
    public function setServiceConfig($serviceConfig = null) {
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
        $this->requestMethod = $requestMethod;

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
             drupal_set_message("Invalid API request parameters.","error");
        }
        $this->requestParams = $postData;
        
        return $this;
    }

    /**
     * Identify and Invoking defined API method request
     * return exception otherwise.
     * 
     * @param object  $this->{$this->requestMethod}()  Inovio API response
     */
    public function getResponse()
    {
        if (empty($this->requestMethod) || !method_exists($this, $this->requestMethod)) {
             drupal_set_message("Invalid API request method.","error");
        }
        $this->requestParams += $this->serviceConfig->getConfig();

        return $this->{$this->requestMethod}();
    }

    /**
     * Verify the card detail and address detail of customer
     */
    private function authenticate()
    {
        $this->requestParams['request_action'] = 'TESTAUTH';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Check if Payment Service is available to process requests.
     */
    private function serviceAvailability()
    {
        $this->requestParams['request_action'] = 'TESTGW';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }
  
    /**
     * Confirm the availability of funds in the cardholder�s bank account.
     */
    private function authorization()
    {
        $this->requestParams['request_action'] = 'CCAUTHORIZE';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Capture of the authorized funds in a single request to the Payment Service.
     */
    private function authAndCapture()
    { 
        $this->requestParams['request_action'] = 'CCAUTHCAP';        
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }

    /**
     * Void a previous authorization.
     */ 
    private function ccReverse()
    {
        $this->requestParams['request_action'] = 'CCREVERSE';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }
        
    /**
     * Capture a previous authorization
     */ 
    private function ccCapture()
    {
        $this->requestParams['request_action'] = 'CCCAPTURE';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }
    
    /**
     * Refund a previously captured authorization
     */ 
    private function ccCredit()
    {
        $this->requestParams['request_action'] = 'CCCREDIT';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }
    
    /**
     * Check the status of a previous purchase 
     * useful if the connection timed out, which creates uncertainty about the purchase result.
     */ 
    private function ccStatus()
    {
        $this->requestParams['request_action'] = 'CCSTATUS';
        $response = $this->serviceConfig->executeCall($this->requestParams);

        return $this->serviceConfig->filterResponse($response, []);
    }
    
} ## end Processor Class
