<?php

namespace TorCDN;

/**
 * Simple Server Incoming Request wrapper
 * 
 * Example: page.php?foo=bar
 * $Request = new Request();
 * $Request->get('foo'); // bar
 */
class HttpServerIncomingClientRequest {

	/**
	 * @var {Array} HTTP Request Headers
	 */
	protected $headers = [];

	/**
	 * Construct and build headers
	 */
	public function __construct()
	{
		$this->headers = getallheaders();
	}

	/**
	 * Retrieve a GET value
	 * @param {String} Namespace
	 * @param {Mixed} Default value to return if namespace is not set
	 */
	public function get($name, $default = null)
	{
		return isset($_GET[$name]) ? $_GET[$name] : $default;
	}

	/**
	 * Retrieve a POST value
	 * @param {String} Namespace
	 * @param {Mixed} Default value to return if namespace is not set
	 */
	public function post($name, $default = null)
	{
		return isset($_POST[$name]) ? $_POST[$name] : $default;
	}

	/**
	 * Retrieve a REQUEST value
	 * @param {String} Namespace
	 * @param {Mixed} Default value to return if namespace is not set
	 */
	public function req($name, $default = null)
	{
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
	}

	/**
	 * Retrieve a HTTP Header value
	 * @param {String} Namespace
	 * @param {Mixed} Default value to return if namespace is not set
	 */
	public function header($name, $default = null)
	{
		return isset($this->header[$name]) ? $this->header[$name] : $default;
	}
}

// polyfill getallheaders()
if (!function_exists('getallheaders')) 
{ 
    function getallheaders() 
    { 
    	$headers = []; 
		foreach ($_SERVER as $name => $value) 
		{ 
			if (substr($name, 0, 5) == 'HTTP_') 
			{ 
			   $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value; 
			} 
			else if ($name == "CONTENT_TYPE")
			{ 
               $headers["Content-Type"] = $value; 
            } else if ($name == "CONTENT_LENGTH")
            { 
               $headers["Content-Length"] = $value; 
            }
		} 
		return $headers; 
    } 
}