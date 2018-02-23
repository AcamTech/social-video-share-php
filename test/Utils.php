<?php

namespace TorCDN\SocialVideoShare\Test;

use GuzzleHttp;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Test Utils
 * @author Gabe Lalasava <gabe@torcdn.com>
 */
class Utils 
{

    /**
     * Read the stream to string
     *
     * @param GuzzleHttp\Psr7\Stream $stream
     * @return string
     */
    static function streamReadAll(GuzzleHttp\Psr7\Stream $stream)
    {
        $body = '';
        while(!$stream->eof()) {
            $body .= $stream->read(1024*1024);
        }
        return $body;
    }

    /**
     * Retrieve JSON Object from Stream
     *
     * @param GuzzleHttp\Psr7\Stream $stream
     * @return object
     */
    static function getJsonFromStream(GuzzleHttp\Psr7\Stream $stream)
    {
        return json_decode(self::streamReadAll($stream));
    }

}
