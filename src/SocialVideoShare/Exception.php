<?php
/**
 * Social Video Share Exceptions
 * @author Gabe Lalasava <gabe@fijiwebdesign.com>
 * @copyright Copyright 2017, Gabirieli Lalasava
 */

 namespace TorCDN\SocialVideoShare;

class Exception extends \Exception {}

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
class TwitterApiException extends Exception {}

/**
 * Failed to retrieve URL HTTP headers
 */
class GetUrlHeadersException extends Exception {}