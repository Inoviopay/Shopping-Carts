<?php

/**
 * Class InovioServiceConfig
 *
 * InovioServiceConfig loads the SDK configuration file and
 * hands out appropriate config params to other classes
 *
 * @package Inovio
 */
class InovioServiceConfig
{

	/**
	 * API endpoint
	 */
	private $apiEndpoint;

	/**
	 * Default Config parameters.
	 */
	private $_requestParams = [
	  'request_response_format' => 'json',
	  'request_api_version' => 3.6
	];

	/**
	 * Required Configuration parameters.
	 */
	private $_requiredParams = ['site_id', 'req_username', 'req_password', 'end_point'];

	/**
	 * default Constructor
	 */
	public function __construct($conf = array())
	{
		$this->curl = new InovioConnection();

		foreach ($this->_requiredParams as $param)
		{
			if (empty($conf[$param]))
			{
				throw new Exception("$param is required.");
			}
		}
		$this->apiEndpoint = $conf['end_point'];

		if (isset($conf['ssl_cert_path']))
		{ // check if SSL enable on server
			$this->curl->isSSLCert = true;
			$this->curl->setSSLCertPath($conf['ssl_cert_path']);
			$this->curl->setSSLCertPasswd($conf['ssl_cert_passphrase']);

			unset($conf['ssl_cert_path'], $conf['ssl_cert_passphrase']);
		}
		$this->_requestParams = $conf + $this->_requestParams;
		unset($this->_requestParams['end_point']); //remove url from params list
	}

	/**
	 * Get request parameters
	 */
	public function getConfig()
	{
		return $this->_requestParams;
	}

	/**
	 * Convenience method for making POST requests.
	 * @param  array  $postData    Array holds gateway request parameters.
	 * @return array  $response    Gateway response for both success or failure.
	 */
	public function executeCall(array $postData)
	{
		$response = $this->curl
		    ->SetRequesturl($this->apiEndpoint)
		    ->SetPostFields($this->toUrlEncode($postData))
		    ->execute();
		return $this->handleResponse($response);
	}

	/**
	 * [handleResponse description]
	 * @param  [type] $response [description]
	 * @return [type]           [description]
	 */
	private function handleResponse($response)
	{

		if (!empty($response['curl_error']) ||
		    $response['curl_errno'] > 0 ||
		    $response['curl_getinfo']['http_code'] != 200
		)
		{
			$this->createLog([
			  'curl response' => $response['curl_response'],
			  'curl error' => $response['curl_error'],
			  'curl error no.' => $response['curl_errno'],
			  'http code' => $response['curl_getinfo']['http_code']
			    ], 'error');

			throw new Exception("Error Processing Request, please see log file.");
		}
		return $response['curl_response'];
	}

	/**
	 * Filter API response by removing unnecessary properties.
	 * @param  array $response     Inovio API response
	 * @param  array $extraParams  unnecessary property list
	 * @return array $response     filtered response
	 */
	public function filterResponse($response, $extraParams = array())
	{
		if (!empty($extraParams))
		{
			foreach ($extraParams as $param)
			{
				unset($response[$param]);
			}
		}
		return $response;
	}

	/**
	 * To encode array into URL encoded string for API request.
	 * @param  array  $requestData  request post data
	 * @return string request post data
	 */
	public function toUrlEncode(array $requestData)
	{
		$urlEncodedString = '';

		foreach ($requestData as $key => $value)
		{
			$urlEncodedString .= $key . '=' . $value . '&';
		}

		return rtrim($urlEncodedString, '&');
	}

	/**
	 *
	 */
	public function createLog($message, $type = 'debug')
	{
		$configPath = dirname(__FILE__) . "/InovioLogger/config.xml";
		Logger::configure(dirname(__FILE__) . "/InovioLogger/config.xml");

		$log = Logger::getLogger(null);
		$log->{$type}($message);
	}

}

## end Class InovioService
