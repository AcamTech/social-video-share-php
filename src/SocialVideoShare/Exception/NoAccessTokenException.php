<?php

namespace TorCDN\SocialVideoShare\Exception;

use TorCDN\SocialVideoShare\Exception;
/**
 * No Access token
 */
class NoAccessTokenException extends Exception
{
  protected $message = 'setAccessToken() must be called before making API requests.';
}