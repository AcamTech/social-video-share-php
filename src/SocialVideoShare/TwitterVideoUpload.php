<?php

namespace TorCDN\SocialVideoShare;

use Codebird\Codebird; // https://github.com/jublonet/codebird-php
use GuzzleHttp;
use TorCDN\Server\Session;
use TorCDN\Server\Request;
use TorCDN\SocialVideoShare\Exception;

/**
 * OAuth and Video Upload to Twitter API
 * TODO: Separate magic API methods to own class
 * TODO: Separate auth
 * TODO: Make idempotent
 */
class TwitterVideoUpload {

    /**
     * Codebird instance
     *
     * @var Codebird
     */
    protected $codeBird;

    const apiMethods = [
        'oauth_requestToken',
        'setToken',
        'oauth_authorize',
        'oauth_accessToken',
        'media_upload',
        'statuses_update'
    ];

    /**
     * Session Service
     * @var Session
     */
    protected $Session;

    /**
     * HTTP Request Service
     * @var Request
     */
    protected $Request;

    /**
     * Default namespace to save session vars to
     */
    const SESSION_DEFAULT_NS = 'twitter';

    /**
     * Functions that receives debugging
     * @var callbale Function
     */
    protected $debugHandler;

    /**
     * Create Instance with configuration
     * @param Array $config [key, secret, callback_url]
     * @param TorCDN\Session $ns Session service
     */
    public function __construct($config, Session $Session = null)
    {
        $this->config = $config;
        $this->setSession($Session ? $Session : new Session(self::SESSION_DEFAULT_NS));
        $this->Request = new Request();

        Codebird::setConsumerKey($config['key'], $config['secret']);
        $this->codeBird = Codebird::getInstance();
    }

    /**
     * Set the session service
     *
     * @param Session $Session
     * @return void
     */
    public function setSession(Session $Session) {
        $this->Session = $Session;
    }

    /**
     * Retrieve accessToken or perform user OAuth to get one
     * TODO: Make idempotent - remove $Request
     *
     * @return array Token 
     *              [
     *                  oauth_token, 
     *                  oauth_token_secret
     *              ]
     */
    public function getOAuthToken($returnUri = null) {
        $Session = $this->Session;
        $Request = $this->Request;

        if ($Request->isset('oauth_verifier') && $Session->isset('oauth_verify')) {
            $token = $this->verifyToken($Request->get('oauth_verifier'));
            if ($returnUri) {
                header('Location: ' . $returnUri);
                die;
            }
        }

        return [
            'oauth_token' => $Session->get('oauth_token'),
            'oauth_token_secret' => $Session->get('oauth_token_secret')
        ];
    }

    public function createAuthUrl()
    {
        $Session = $this->Session;
        // get the request token
        $reply = $this->oauth_requestToken([
            'oauth_callback' => $this->config['callback_url']
        ]);
        
        // store the token
        $this->setToken($reply->oauth_token, $reply->oauth_token_secret);

        $Session->set('verify_oauth_token', $reply->oauth_token);
        $Session->set('verify_oauth_token_secret', $reply->oauth_token_secret);
        $Session->set('oauth_verify', true);
        
        // get auth URL
        $auth_url = $this->oauth_authorize();

        return $auth_url;
    }

    protected function verifyToken($oauth_verifier)
    {
        $Session = $this->Session;
        $this->setToken(
            $Session->get('verify_oauth_token'), 
            $Session->get('verify_oauth_token_secret')
        );
        $Session->unset('oauth_verify');
        
        // get the access token
        $reply = $this->oauth_accessToken([
            'oauth_verifier' => $oauth_verifier
        ]);
        
        // store the token (which is different from the request token!)
        $Session->set('oauth_token', $reply->oauth_token);
        $Session->set('oauth_token_secret', $reply->oauth_token_secret);

        return $reply;
    }

    /**
     * Upload a video file on local disk
     *
     * @param String $file File path
     * @param Callable $progressCallback Function to receive progress updates
     * @return Object statuses_update API respose object
     */
    public function uploadVideoFromFile($file, callable $progressCallback = null) {
        $size_bytes = filesize($file);
        $stream     = fopen($file, 'rb');
        return $this->uploadVideoFromStream($stream, $size_bytes);
    }

    /**
     * Upload a video file from a remote HTTP(S) URL
     *
     * @param String $url Full URL
     * @param Int $size Optional Filesize. 
     *            If not supplied, it will be read from Content-Length header
     * @param Callable $progressCallback Function to receive progress updates
     * @return Object statuses_update API respose object
     */
    public function uploadVideoFromUrl($url, int $size = null, callable $progressCallback = null) {
        if (!$size) {
            $size = $this->getUrlContentLength($url);
        }
        $stream = fopen($url, 'rb');
        return $this->uploadVideoFromStream($stream, $size);
    }

    /**
     * Upload a video given it's stream resource (file pointer/handle)
     *
     * @param Resource $stream Stream resource (file pointer/handle)
     * @param Int $size_bytes Size of video file in bytes. 
     *            Required because content length cannot be predeterminted from a stream.
     * @param Callable $progressCallback Function to receive progress updates
     * @return Object statuses_update API respose object
     */
    public function uploadVideoFromStream($stream, int $size_bytes, callable $progressCallback = null) {
        
        if (!is_resource($stream)) {
            throw new InvalidParamException('Parameter $stream must be a valid stream resource.');
        }
        if ($progressCallback && !is_callable($progressCallback)) {
            throw new InvalidParamException('Parameter $progressCallback must be a callable.');
        }
        
        // INIT the upload
        
        $reply = $this->media_upload([
            'command'     => 'INIT',
            'media_type'  => 'video/mp4',
            'total_bytes' => $size_bytes
        ], $progressCallback);
        
        $media_id = $reply->media_id_string;
        
        // APPEND data to the upload
        
        $segment_id = 0;

        $chunk = '';
        $chunkSizeBytes = 1048576; // 1MB per chunk
        
        while (! feof($stream)) {

            // fread non local files returns as soon as a packet is available
            // usually 8192 bytes. http://php.net/manual/en/function.fread.php
            // buffer packets to $chunkSizeBytes then upload
            $packet = fread($stream, $chunkSizeBytes); 

            $chunk .= $packet;

            // TODO: Send exact $chunkSizeBytes length chunks?
            if (feof($stream) || strlen($chunk) >= $chunkSizeBytes) {
                $reply = $this->media_upload([
                    'command'       => 'APPEND',
                    'media_id'      => $media_id,
                    'segment_index' => $segment_id,
                    'media'         => $chunk
                ], $progressCallback);
                $chunk = '';
                $segment_id++;
            }
            
        }
        
        fclose($stream);
        
        // FINALIZE the upload
        
        $reply = $this->media_upload([
            'command'       => 'FINALIZE',
            'media_id'      => $media_id
        ], $progressCallback);
        
        if ($reply->httpstatus < 200 || $reply->httpstatus > 299) {
            throw new TwitterApiException('Invalid HTTP Status code.');
        }
        
        // if you have a field `processing_info` in the reply,
        // use the STATUS command to check if the video has finished processing.
        
        // Now use the media_id in a Tweet
        $reply = $this->statuses_update([
            'status'    => 'Twitter now accepts video uploads.',
            'media_ids' => $media_id
        ]);

        return $reply;
    }

    /**
     * Retrieve the Length of the content by making a GET reqeust for the Content-Length header
     *
     * @param String $url
     * @throws Exception 
     * @return Int
     */
    protected function getUrlContentLength($url) {
        $headers = [];
        $client = new GuzzleHttp\Client();
        // We need to make a HTTP GET request then abort it 
        // because some URLs do not support HEADER requests 
        try {
        $client->request(
            'GET',
            $url,
            [
            'on_headers' => function (GuzzleHttp\Psr7\Response $response) use (&$headers, $client) {
                $headers = $response->getHeaders();
                throw new \Exception('Closing connection.');
            },
            'stream' => true
            ]
        );
        } catch(\Exception $e) { /* ignore */ }

        if (!isset($headers['Content-Length'][0])) {
            throw new \Exception('Could not retrieve Content-Length from URL.');
        }
        
        return (int) $headers['Content-Length'][0];
    }

    /**
     * Register a function that receives debugging of API request/response
     *
     * @param Callable $fn Function that receives API request/responses
     *                 $msg is passed to this function as first parameter
     * @return void
     */
    public function registerDebugHandler(callable $fn) {
        if (!is_callable($fn)) {
            throw new InvalidParamException('First parameter must be a callable function.');
        }
        $this->debugHandler = $fn;
    }

    /**
     * Allow external debugging via $this->registerDebugHandler
     * @param String $method API method called
     * @param Array $request API request arguments
     * @param Object $response API response object
     * @return void
     */
    public function debug($method, $request, $response = null) {
        $args = func_get_args();
        if ($this->debugHandler) {
            call_user_func_array($this->debugHandler, $args);
        }
    }

    /**
     * Maps methods to API calls automatically
     *
     * @param String $method
     * @param Array $arguments
     * @return Mixed
     */
    public $count = 0;
    public function __call($method, $arguments) {
        if (!in_array($method, self::apiMethods)) {
            throw new InvalidMethodException(
                sprintf('Called method %s that does not exist in API. ', htmlentities($method))
            );
        } 

        $reply = call_user_func_array([$this->codeBird, $method], $arguments);
        $this->debug($method, $arguments, $reply);

        // special case, media_upload progress handler
        if ($method == 'media_upload' && isset($arguments[1])) {
            $progressCallback = $arguments[1];
            call_user_func($progressCallback, $arguments[0]);
        }

        if (isset($reply->errors) || isset($reply->error)) {
            throw new TwitterApiException($method . ' API call returned an error.');
        }

        return $reply;
    }
}