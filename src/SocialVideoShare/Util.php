<?php

namespace TorCDN\SocialVideoShareApi;

/**
 * Helper functions
 */
class Util
{

  /**
   * Generate a nonce
   *
   * @param integer $length
   * @return string
   */
  public static function generateRandomSecureToken($length = 40)
  {
    $chars = [];
    $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
    $codeAlphabet.= "0123456789";
    $max = strlen($codeAlphabet);

    for ($i=0; $i < $length; $i++) {
        $chars[] = $codeAlphabet[random_int(0, $max-1)];
    }

    return implode('', $chars);
  }
  
}

