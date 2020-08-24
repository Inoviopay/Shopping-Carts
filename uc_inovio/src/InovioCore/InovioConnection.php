<?php
namespace Drupal\uc_inovio\InovioCore;
/**
 * Class InovioConnection
 *
 * InovioConnection assemble the Curl/API call request and
 * parsing the returned response
 *
 * @package Inovio\Connection
 */
class InovioConnection
{
    /**
     * curl instance
     */
    private $ch = null;

    /**
     * header information
     */
    private $headers = array();

    /**
     * Contains all request Options [CURLOPT]
     */
    private $curlOptions = array();

    /**
     * Curl response
     */
    private $response = [];

    /**
     * SSL enabled flag
     */
    public $isSSLCert = false;
    protected $_logs;

   

    public function InovioConnection()
    {
        if (!function_exists("curl_init")) {
             drupal_set_message("Curl module is not available on this system","error");
        }
    }

    /**
     * Initiate curl request and validate & set request URL.
     *       
     * @param [string] $url  Target URL
     * @return Object  $this
     */
    public function SetRequestUrl($url = null)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
             drupal_set_message('Invalid API endpoint URL - ' . $url,"error");
        }
        $this->ch = curl_init($url);
    
        return $this;
    }
    
    /**
     * Some default options for curl
     * These are typically overridden by user defined config
     */
    private function useDefaultCurlOptions()
    {
        $this->curlOptions[CURLOPT_FOLLOWLOCATION] = 0;
        $this->curlOptions[CURLOPT_RETURNTRANSFER] = 1;

        if (!$this->isSSLCert) {
            $this->curlOptions[CURLOPT_SSL_VERIFYHOST] = 0;
            $this->curlOptions[CURLOPT_SSL_VERIFYPEER] = 0;
        }
    }

    /**
     * Set request http method.
     * 
     * @param  string  $method  Http method
     * @return Object  $this
     */
    public function SetHttpMethod($method = 'get')
    {
        $method = strtolower((string) $method);

        if (!in_array($method, ['post', 'get', 'put', "delete", 'head', 'options', 'connect'])) {

             drupal_set_message('Invalid HTTP method - '. $method,"error");
        }
        $this->curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        
        return $this;
    }

    /**
     * Set request data to be send to request URL.
     * 
     * @param  mixed $post_data  Request data to be send to request URL.
     * @return $this
     */
    public function SetPostFields($post_data = null) {
        $this->curlOptions[CURLOPT_POSTFIELDS] = $post_data;        
        
        return $this;
    }   

    /**
     * Set ssl cert path for certificate based client authentication
     *
     * @param string  $certPath   SSL certificate path
     */
    public function setSSLCertPath($certPath)
    {
        if (empty($certPath)) {
          
             drupal_set_message("Please provide a valid SSL cert path","error");
        }
        $this->curlOptions[CURLOPT_SSLCERT] = realpath($certPath);

        return $this;
    }

    /**
     * Set ssl cert passPhrase for certificate based client authentication
     *
     * @param null    $passPhrase  
     */
    public function setSSLCertPasswd($passPhrase = null)
    {
        if (isset($passPhrase) && trim($passPhrase) != "") {
            $this->curlOptions[CURLOPT_SSLCERTPASSWD] = $passPhrase;
        }

        return $this;
    }

    /**
     * Execute/send request.
     * 
     * @param none
     * @return bool|mixed
     */
    public function execute() {
        $this->useDefaultCurlOptions();

        curl_setopt_array($this->ch, $this->curlOptions);
        
        ## curl execute
        $this->response['curl_response'] = curl_exec($this->ch);
        $this->response['curl_errno'] = curl_errno($this->ch);
        $this->response['curl_error'] = curl_error($this->ch);
        $this->response['curl_getinfo'] = curl_getinfo($this->ch);

        $this->close();

        return $this->response;
    }

    /**
     * @purpose : This is used to close curl request and reset all properties.
     */
    private function Close() {
        curl_close($this->ch);

        $this->ch = null;
        $this->curlOptions = array();
        $this->headers = array();
    }

} ## end InovioConnection class
