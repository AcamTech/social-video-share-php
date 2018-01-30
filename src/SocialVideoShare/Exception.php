<?php
/**
 * Social Video Share Exceptions
 * @author Gabe Lalasava <gabe@fijiwebdesign.com>
 * @copyright Copyright 2017, Gabirieli Lalasava
 * 
 * @todo move all exceptions to Exception/ directory
 */

namespace TorCDN\SocialVideoShare;

class Exception extends \Exception {

  protected $code = 0;

  protected $message = 'Uncaught TorCDN\SocialVideoShare\Exception thrown';

}

  /**
 * Invalid parameter passed to method
 */
class InvalidParamException extends Exception {}

/**
 * Invalid method call
 */
class InvalidMethodException extends Exception {}

/**
 * Error returned from Twitter API
 */
class TwitterApiException extends Exception
{

  public function __construct($message, $code = 500, $method, $apiRequest = null, $apiResponse = null)
  {
    parent::__construct($message, $code);
    $this->method      = $method;
    $this->apiRequest  = $apiRequest;
    $this->apiResponse = $apiResponse;
  }

  public function toJson()
  {
    return [
      'error'   => $this->getMessage(),
      'code'    => $this->getCode(),
      'method'  => $this->method,
      'path'    => str_replace('_', '/', $this->method),
      'request' => $this->apiRequest,
      'response' => $this->apiResponse
    ];
  }
}

/**
 * Failed to retrieve URL HTTP headers
 */
class GetUrlHeadersException extends Exception {}