<?php

namespace TorCDN\SocialVideoShare;

use Facebook\Facebook;
use TorCDN\Server\Session;
use TorCDN\Server\Request;
use TorCDN\SocialVideoShare\Exception\NoAccessTokenException;
use TorCDN\SocialVideoShare\FacebookCurlVideoUpload;
use GuzzleHttp;

/**
 * OAuth and Video Upload to Facebook Graph API
 */
class FacebookVideoUpload
{

    /**
     * Facebook Access token from OAuth
     * @var string
     */
    protected $accessToken;

    /**
     * Facebook Graph SDK instance
     * @var Facebook\Facebook
     */
    protected $fb;

    /**
     * Permissions/scope required for video upload
     * @var array
     */
    protected $permissions = [
        'email', 'public_profile', 'publish_actions', 'user_videos'
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
    const SESSION_DEFAULT_NS = __CLASS__;

    /**
     * Functions that receives debugging
     * @var callable Function
     */
    protected $logger;

    /**
     * Create Instance with configuration
     * @param Array $config 
     *              [
     *                'app_id' => '{app-id}',
     *                'app_secret' => '{app-secret}',
     *                'default_graph_version' => 'v2.10',
     *                'default_access_token' => '{access-token}', // optional
     *              ]
     * @param TorCDN\Session $Session Session service
     */
    public function __construct($config, Session $Session = null)
    {
        $this->config = $config;
        $this->setSession($Session ? $Session : new Session(self::SESSION_DEFAULT_NS));
        $this->Request = new Request();

        $this->fb = new Facebook($config);
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
     * Set the fb accessToken
     *
     * @param string $accessToken
     * @return void
     */
    public function setAccessToken(string $accessToken) {
        $this->accessToken = $accessToken;
    }

    /**
     * Retrieve accessToken or perform user OAuth to get one
     *
     * @return string Token
     */
    public function getAccessToken()
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $helper = $this->fb->getRedirectLoginHelper();
        $accessToken = $helper->getAccessToken();

        if ($accessToken) {
            // The OAuth 2.0 client handler helps us manage access tokens
            $oAuth2Client = $this->fb->getOAuth2Client();
            
            // Get the access token metadata from /debug_token
            $tokenMetadata = $oAuth2Client->debugToken($accessToken);
            
            // Validation (these will throw FacebookSDKException's when they fail)
            $tokenMetadata->validateAppId($this->config['app_id']); 
            // If you know the user ID this access token belongs to, you can validate it here
            //$tokenMetadata->validateUserId('123');
            $tokenMetadata->validateExpiration();
            
            if (!$accessToken->isLongLived()) {
                $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
            }
        }
      
        return $accessToken ? $accessToken->getValue() : null;
    }

    /**
     * Create the authentication URL with $returnUrl and given $permissions
     *
     * @param string $returnUrl URL to handle OAuth
     * @param array $permissions (optional) Facebook permissions/scope
     * @return string
     */
    public function createAuthUrl($returnUrl, $permissions = null)
    {
        if (!$permissions) {
            $permissions = $this->permissions;
        }
        $helper = $this->fb->getRedirectLoginHelper();
        $loginUrl = $helper->getLoginUrl($returnUrl, $permissions);
      
        return $loginUrl;
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
     * @param array $meta Video meta data 
     *              [
     *                  title => {title}, 
     *                  description => {description}
     *              ]
     * @param String $url Full URL
     * @param Int $size Optional Filesize. 
     *            If not supplied, it will be read from Content-Length header
     * @param Callable $progressCallback Function to receive progress updates
     * @return Object statuses_update API respose object
     */
    public function uploadVideoFromUrl($meta, $url, int $size = null, callable $progressCallback = null) {
        if (!$size) {
            $size = $this->getUrlContentLength($url);
        }
        
        $fbCurlVideoUpload = new FacebookCurlVideoUpload($this->config);
        $fbCurlVideoUpload->setAccessToken($this->getAccessToken());

        if ($this->logger) {
            $fbCurlVideoUpload->setLogger($this->logger);
        }

        $reply = $fbCurlVideoUpload->transferToFacebookFromUrl(
            $meta['title'], $meta['description'], $url, $size
        );
        return $reply;
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

        if (!$accessToken = $this->getAccessToken()) {
            throw new NoAccessTokenException;
        }
        
        throw new Exception('Not implemented yet. Use uploadVideoFromUrl()');
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
     * Register a function that receives debugging
     *
     * @param Callable $fn Function that receives debugging
     * @return void
     */
    public function setLogger(callable $fn) {
        if (!is_callable($fn)) {
            throw new InvalidParamException('First parameter must be a callable function.');
        }
        $this->logger = $fn;
    }

    /**
     * Allow external debugging via $this->setLogger()
     * @return void
     */
    public function log() {
        if ($this->logger) {
            call_user_func_array($this->logger, func_get_args());
        }
    }

}