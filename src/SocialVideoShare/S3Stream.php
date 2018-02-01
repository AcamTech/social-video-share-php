<?php

/**
 * @author Gabe Lalasava <gabe@fijiwebdesign.com>
 * @copyright Copyright 2017, Gabirieli Lalasava
 * 
 * Based on https://github.com/jmathai/s3-bucket-stream-zip-php
 * @author Jaisen Mathai <jaisen@jmathai.com>
 * @copyright Copyright 2015, Jaisen Mathai
 *
 * This library streams (in memory) the contents from an Amazon S3 file
 *
 * Example usage can be found in the examples folder.
 */
namespace TorCDN\SocialVideoShare;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use GuzzleHttp;
use TorCDN\SocialVideoShare\Exception;

class S3Stream
{

  /**
   * @var array
   *
   * {
   *   key: your_aws_key,
   *   secret: your_aws_secret
   * }
   */
  private $auth   = array();

  /**
   * @var object
   */
  public $s3Client;

  /**
   * @var resource Stream File pointer
   */
  private $stream;

  /**
   * @var callable Logger
   */
  private $logger;

  /**
   * Create a new S3 Stream object with AWS S3 Credentials
   *
   * @param Array $config - AWS config parameters
   *    [
   *        'credentials' => [
   *            'key'     =>  '',
   *             'secret' => ''
   *        ],
   *        'region'      => ''
   *    ]
   */
  public function __construct(array $config = [])
  {

    if(!isset($config['credentials']['key']) || !isset($config['credentials']['secret']))
      throw new InvalidParamException('Requires $config[credentials] with key and secret');

    $this->config = array_merge([
        'version'           => 'latest',
        //'signature_version' => 'v4',
        'region'            => 'us-west-2',
        'credentials'       => [
            'key' => '',
            'secret' => ''
        ],
        'handler' => function (CommandInterface $cmd, RequestInterface $r) {
            $url = $cmd['PresignedUrl'];
            echo 'commands';
            var_dump($cmd);
            return new Result;
        }
    ], $config);
    
    $this->s3Client = S3Client::factory($this->config);

    $this->s3Client->registerStreamWrapper();

    $this->setLogger(function() {});
  }

  /**
   * Ensure we close the stream asap
   */
  public function __destruct() {
    if (is_resource($this->stream)) {
        $this->close();
    }
  }

  /**
   * Set the Logger function
   * @param callable $logger
   */
  public function setLogger(callable $logger) {
    $this->logger = $logger;
  }

  /**
   * Stream a file
   * 
   * Example: Video Streaming
   *    header('Content-Type: video/mp4');
   *    $s3Client->stream('bucket-name', 'path/video.mp4', function($chunk) {
   *        echo $chunk;
   *        @ob_flush(); // push to client
   *    });
   *
   * @param String $bucket - Name of bucket
   * @param String $filename  - Name for the file
   * @param Callable $cb - Callback function that receives streamed file chunks
   * 
  */
  public function stream(string $bucket, string $filename, callable $cb)
  {
    if ($stream = fopen('s3://' . $bucket . '/' . $filename, 'r')) {
        // if we have a callback, read stream to callback
        if (is_callable($cb)) {
            while (!feof($stream)) {
                // Read 1024 bytes from the stream
                call_user_func($cb, fread($stream, 1024));
            }
            // close stream
            fclose($stream);
        } else {
            throw new InvalidParamException('$cb is not callable function or method.');
        }
        throw new S3Exception('Could not open stream to file.');
    }
    
    $this->stream = $stream;
  }

  /**
   * Get a PHP Stream resource to the file
   *
   * @param String $bucket - Name of bucket
   * @param String $filename  - Name for the file
   * @return Resource Stream
   * 
  */
  public function getStream(string $bucket, string $filename) {
    $this->stream = fopen('s3://' . $bucket . '/' . $filename, 'r');
    return $this->stream;
  }

  /**
   * Get a URL to the file
   *
   * @param String $bucket - Name of bucket
   * @param String $filename  - Name for the file
   * 
  */
  public function getUrl(string $bucket, string $filename, $expiration = '+10 minutes') {
    $request = $this->getRequest($bucket, $filename, $expiration);
    $signedUrl = $request->getUri()->__toString();
    return $signedUrl;
  }

  /**
   * Get a Guzzle Request for the file
   *
   * @param String $bucket - Name of bucket
   * @param String $filename  - Name for the file
   * 
   * @return GuzzleHttp\PSR-7\Request Request
   * 
  */
  public function getRequest(string $bucket, string $filename, $expiration = '+10 minutes') {
    // We need to use a command to get a request for the S3 object
    //  and then we can get the presigned URL.
    $command = $this->s3Client->getCommand('GetObject', [
        'Bucket' => $bucket,
        'Key' => $filename
    ]);
    $request = $this->s3Client ->createPresignedRequest($command, $expiration);
    return $request;
  }

  /**
   * Get the HTTP Headers of a file. 
   * The body of the file is not downloaded. Just the headers.
   *
   * @param String $bucket - Name of bucket
   * @param String $filename  - Name for the file
   * 
  */
  public function getHeaders(string $bucket, string $filename) {
    $request = $this->getRequest($bucket, $filename); // PSR-7 HTTP GET Request
    $headers = $this->getUrlHeaders($request->getUri());
    
    return $headers;
  }

  /**
   * Get the HTTP Headers of any HTTP url, including those not on S3
   * The body of the url is not downloaded. Just the headers.
   *
   * @param String $url
   * 
  */
  public function getUrlHeaders(string $url) {
    $headers = [];
    $client = new GuzzleHttp\Client();
    // We need to make a HTTP GET request then abort it 
    // because S3 private files do not support HEADER requests 
    $exception = new Exception('No exception thrown by HTTP request');
    $response = null;
    try {
      $response = $client->request(
        'GET',
        $url,
        [
          'on_headers' => function (GuzzleHttp\Psr7\Response $response) use (&$headers, $client) {
            $headers = $response->getHeaders();
            throw $exception = new \Exception('Closing connection because we have headers.');
          },
          'stream' => true,
          'timeout' => 1, /* sometime on_headers is not triggered */
          'progress' => function(
              $downloadTotal,
              $downloadedBytes,
              $uploadTotal,
              $uploadedBytes
            ) {
              if ($downloadedBytes > 1000 * 1000) {
                throw $exception = new \Exception('Closing connection because 1Mb already downloaded');
              }
            }
        ]
      );

      if (!$headers) {
        $headers = $response->getHeaders();
      }

    } catch(\Exception $e) {
      if (!($exception instanceof GetUrlHeadersException)) {
        $exception = $e;
      }
    }

    $this->log('getUrlHeaders Exception', $exception->getMessage(), $headers, $response);
    
    return $headers;
  }

  /**
   * Close the stream file pointer
   */
  public function close() {
    fclose($this->stream);
  }

  /**
   * Logging Utility
   */
  protected function log() {
    call_user_func_array($this->logger, func_get_args());
  }
}