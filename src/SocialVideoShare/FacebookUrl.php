<?php

namespace TorCDN\SocialVideoShare;

use Facebook\FileUpload\FacebookFile;
use Facebook\Exceptions\FacebookSDKException;

/**
 * Class FacebookFile
 *
 * @package Facebook
 */
class FacebookUrl extends FacebookFile
{

  /**
   * Creates a new FacebookFile entity.
   *
   * @param string $url
   * @param int $maxLength
   * @param int $offset
   *
   * @throws FacebookSDKException
   */
  public function __construct($url, $maxLength = -1, $offset = -1)
  {
      parent::__construct($url, $maxLength, $offset);
  }

  /**
   * Return the size of the file.
   *
   * @return int
   */
  public function getSize()
  {
      $headers = get_headers($this->path, 1);
      $headers = array_change_key_case($headers);
      $fileSize = trim($headers['content-length'], '"');
      return $fileSize;
  }

}