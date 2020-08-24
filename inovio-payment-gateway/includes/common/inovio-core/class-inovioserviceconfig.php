<?php
/**
 * Class InovioServiceConfig
 *
 * InovioServiceConfig loads the SDK configuration file and
 * hands out appropriate config params to other classes
 *
 * @package Inovio
 */
class InovioServiceConfig {

	/**
	 * API endpoint
	 */
	private $api_endpoint;

	/**
	 * Default Config parameters.
	 */
	private $requestparams = [
		'request_response_format' => 'json',
		'request_api_version'     => 3.6,
	];

	/**
	 * Required Configuration parameters.
	 */
	private $requiredparams = [ 'site_id', 'req_username', 'req_password', 'end_point' ];

	/**
	 * default Constructor
	 */
	public function __construct( $conf = array() ) {

		$this->curl = new InovioConnection();

		foreach ( $this->requiredparams as $param ) {
			if ( empty( $conf[ $param ] ) ) {
                            die("sdsd");
				throw new Exception( 'Error in payment processing request, please contact to your service provider.' );
			}
		}
		$this->api_endpoint = $conf['end_point'];

		if ( isset( $conf['ssl_cert_path'] ) ) { // check if SSL enable on server
			$this->curl->isSSLCert = true;
			$this->curl->set_sslcert_path( $conf['ssl_cert_path'] );
			$this->curl->set_ssl_certpasswd( $conf['ssl_cert_passphrase'] );

			unset( $conf['ssl_cert_path'], $conf['ssl_cert_passphrase'] );
		}
		$this->requestparams = $conf + $this->requestparams;
		unset( $this->requestparams['end_point'] ); // remove url from params list
	}

	/**
	 * Get request parameters
	 */
	public function get_config() {
		return $this->requestparams;
	}

	/**
	 * Convenience method for making POST requests.
	 *
	 * @param  array $postData    Array holds gateway request parameters.
	 * @return array  $response    Gateway response for both success or failure.
	 */
	public function execute_call( array $postData ) {
		$args = array(
			'body'        => $this->to_url_encode( $postData ),
			'httpversion' => '1.0',
			'headers'     => array(),
			'cookies'     => array(),
		);
		$response = wp_remote_post( $this->api_endpoint, $args );
		
		return $this->handle_response( $response );
	}

	/**
	 * [handle_response description]
	 *
	 * @param  [type] $response [description]
	 * @return [type]           [description]
	 */
	private function handle_response( $response ) {

		if ( $response['response']['code'] != 200 ) {

			throw new Exception( 'Error in payment processing request, please contact to your service provider.' );
		}
		
		return $response['body'];
	}

	/**
	 * Filter API response by removing unnecessary properties.
	 *
	 * @param  array $response     Inovio API response
	 * @param  array $extraParams  unnecessary property list
	 * @return array $response     filtered response
	 */
	public function filter_response( $response, $extraParams = array() ) {
		if ( ! empty( $extraParams ) ) {
			foreach ( $extraParams as $param ) {
				unset( $response[ $param ] );
			}
		}
		return $response;
	}

	/**
	 * To encode array into URL encoded string for API request.
	 *
	 * @param  array $requestData  request post data
	 * @return string request post data
	 */
	public function to_url_encode( array $requestData ) {
		$urlEncodedString = '';

		foreach ( $requestData as $key => $value ) {
			$urlEncodedString .= $key . '=' . $value . '&';
		}

		return rtrim( $urlEncodedString, '&' );
	}

} // end Class InovioService
