<?php
/**
 * Class InovioConnection
 *
 * InovioConnection assemble the Curl/API call request and
 * parsing the returned response
 *
 * @package Inovio\Connection
 */
class InovioConnection {

	/**
	 * curl instance
	 */
	private $ch = null;

	/**
	 * header information
	 */
	private $headers = array ();

	/**
	 * Contains all request Options [CURLOPT]
	 */
	private $curloptions = array ();

	/**
	 * Curl response
	 */
	private $response = [];

	/**
	 * SSL enabled flag
	 */
	public $issslcert = false;

	/**
	 * Default Constructor
	 */
	public function __construct() {
		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception( 'Curl module is not available on this system' );
		}
	}

	/**
	 * Initiate curl request and validate & set request URL.
	 *
	 * @param [string] $url  Target URL
	 * @return Object  $this
	 */
	public function set_requesturl( $url = null ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new Exception( 'Invalid API endpoint URL - ' . $url );
		}
		$this->ch = curl_init( $url );

		return $this;
	}

	/**
	 * Some default options for curl
	 * These are typically overridden by user defined config
	 */
	private function use_default_curloptions() {
		$this->curloptions[ CURLOPT_FOLLOWLOCATION ] = 0;
		$this->curloptions[ CURLOPT_RETURNTRANSFER ] = 1;

		if ( ! $this->issslcert ) {
			$this->curloptions[ CURLOPT_SSL_VERIFYHOST ] = 0;
			$this->curloptions[ CURLOPT_SSL_VERIFYPEER ] = 0;
		}
	}

	/**
	 * Set request http method.
	 *
	 * @param  string $method  Http method
	 * @return Object  $this
	 */
	public function set_http_method( $method = 'get' ) {

		$method = strtolower( (string) $method );

		if ( ! in_array( $method, [ 'post', 'get', 'put', 'delete', 'head', 'options', 'connect' ] ) ) {
			throw new Exception( 'Invalid HTTP method - ' . $method );
		}
		$this->curloptions[ CURLOPT_CUSTOMREQUEST ] = $method;

		return $this;
	}

	/**
	 * Set request data to be send to request URL.
	 *
	 * @param  mixed $post_data  Request data to be send to request URL.
	 * @return $this
	 */
	public function set_postfields( $post_data = null ) {
		$this->curloptions[ CURLOPT_POSTFIELDS ] = $post_data;

		return $this;
	}

	/**
	 * Set ssl cert path for certificate based client authentication
	 *
	 * @param string $cert_path   SSL certificate path
	 */
	public function set_sslcert_path( $cert_path ) {
		if ( empty( $cert_path ) ) {
			throw new Exception( 'Please provide a valid SSL cert path' );
		}
		$this->curloptions[ CURLOPT_SSLCERT ] = realpath( $cert_path );

		return $this;
	}

	/**
	 * Set ssl cert pass_phrase for certificate based client authentication
	 *
	 * @param null $pass_phrase
	 */
	public function set_ssl_certpasswd( $pass_phrase = null ) {
		if ( isset( $pass_phrase ) && trim( $pass_phrase ) !== '' ) {
			$this->curloptions[ CURLOPT_SSLCERTPASSWD ] = $pass_phrase;
		}

		return $this;
	}

	/**
	 * @purpose : This is used to close curl request and reset all properties.
	 */
	private function close() {
		curl_close( $this->ch );

		$this->ch          = null;
		$this->curloptions = array();
		$this->headers     = array();
	}

} // end InovioConnection class
