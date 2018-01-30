<?php
namespace TorCDN\SocialVideoShare;

use Facebook\Facebook;
use TorCDN\SocialVideoShare\Exception\NoAccessTokenException;

/**
 * Upload video to Facebook API from URL using CURL
 */
class FacebookCurlVideoUpload
{

    /**
     * Facebook Graph API URL for video upload
     */
    static $facebookAPIbaseURL = 'https://graph-video.facebook.com/v2.3/';

    /**
     * @var array $facebookConfig Facebook configuration
     */
    protected $facebookConfig = [
        'app_id' => '',
        'app_secret' => '',
        'default_graph_version' => 'v2.4',
        'default_access_token' => null
    ];

    /**
     * @var string $token Users's Facebook access token from OAuth
     */
    protected $token;

    /**
     * @var string $userId Users's Facebook id
     */
    protected $userId;

    /**
     * @var Facebook $fb Facebook Graph SDK instance 
     */
    protected $fb;

    /**
     * @var callable $logger Logging function
     */
    protected $logger;

    /**
     * Create
     *
     * @param array $facebookConfig Facebook Config Array
     * [
     *     'app_id' => '248990911853204',
     *     'app_secret' => 'd6f8aa2d88bfe629a32a2264dcc22872',
     *     'default_graph_version' => 'v2.4',
     *     'default_access_token' => '{access-token}', // optional
     * ]
     */
    public function __construct($facebookConfig)
    {
        $this->facebookConfig = $facebookConfig;
        $this->fb = new Facebook($facebookConfig);
        if (isset($facebookConfig['default_access_token'])) {
            $this->setAccessToken($facebookConfig['default_access_token']);
        }
    }

    /**
     * Set Facebook API Access token
     *
     * @param string $token Facebook Access Token
     * @return void
     */
    public function setAccesstoken($token)
    {
        $this->token = $token;
    }

    /**
     * Upload a video to Facebook from URL
     *
     * @param string $title
     * @param string $description
     * @param string $url
     * @return void
     */
    public function transferToFacebookFromUrl($title, $description, $url, $videoFileSize = null)
    {

        if (!$this->token) {
            throw new NoAccessTokenException;
        }

        if (!$videoFileSize) {
            $headers = get_headers($url, 1);
            $headers = array_change_key_case($headers);
            $videoFileSize = trim($headers['content-length'], '"'); 
        }
        
        $this->log('Video file size: ' . $videoFileSize);
        $h = array();
        $p = array(
            'upload_phase'  => 'start',
            'file_size'     => $videoFileSize
        );
        $r = $this->request('videos',$p);
        
        $upload_session_id = $r['upload_session_id'];
        $video_id = $r['video_id'];
        $start_offset = $r['start_offset'];
        $end_offset = $r['end_offset'];
        $s = array('start'=>'','chunk'=>'');
        $handle = fopen($url, "rb");
        while($start_offset != $end_offset) {
            $s2 = $this->getChunk($handle, $url, $end_offset - $start_offset, $s);
            $p = array(
                'upload_phase'=>'transfer',
                'upload_session_id'=> $upload_session_id,
                'start_offset' => $start_offset,
                'video_file_chunk' => $s2['chunk']
            );

            // retry sending each chunk with $maxRetries limit
            $success = false;
            $maxRetries = 3;
            while($maxRetries > 0 && !$success) {
                try {
                    $r = $this->request('videos', $p);
                } catch(\Exception $e) {
                    $maxRetries--;
                    continue;
                }
                $success = true;
            }
            
            $r = $this->request('videos', $p);
            $start_offset = $r['start_offset'];
            $end_offset = $r['end_offset'];
            $s = $s2;
        }

        $p = array(
            'upload_phase'=>'finish',
            'upload_session_id'=>$upload_session_id,
            'title'=>$title,
            'description'=>$description
        );
        $r = $this->request('videos', $p);
        
        return $r;
    }

    /**
     * Get the User id from facebook. setAccess() must be called first.
     * @return string userId
     */
    public function getUserId()
    {
        if (!$this->userId) {
            $response = $this->fb->get('/me', $this->token); 
            $userNode = $response->getGraphUser();
            $userId = $userNode->getProperty('id');
            $this->log('User ID: ' . $userId);
            $this->userId = $userId;
        }
        return $this->userId;
    }

    /**
    * Make a Cogi API call
    * @param string $call The API call to make (eg '/api/account/login')
    * @param array $parameters Any variable parameters, of the form 'name' => 'value'
    * @param array $headers Any HTTP headers, of the form 'name' => 'value'
    * @param bool $json_decode Should the system automatically decode JSON? Defaults to true
    */
    protected function request($call, $parameters = [], $headers = [], $json_decode = true, $use_post = true)
    {
        $this->log('Facebook API request', $call, $parameters);

        $url = self::$facebookAPIbaseURL;

        if (!$this->token) {
            throw new NoAccessTokenException;
        }

        $url .= $this->getUserId() . '/';

        if (empty($parameters['access_token']) && !empty($this->token)) {
            $parameters['access_token'] = $this->token;
        }

        $paramstring = '';
        if (!empty($parameters)) {
            foreach($parameters as $name => $parameter) {
                if (!empty($paramstring)) $paramstring .= '&';
                $paramstring .= urlencode($name) . '=' . urlencode($parameter);
            }
        }
        
        //$this->log("COGI URL: " . $url . $call . '?'.$paramstring);

        if ($use_post) {
            $ch = curl_init($url . $call);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $paramstring);
        } else {
            $paramstring = '?' . $paramstring;
            $ch = curl_init($url . $call . $paramstring);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (!empty($headers)) {
            $headerarray= array();
            foreach($headers as $name => $header) {
                $headerarray[] = $name . ': ' . $header;
            }
            if (!empty($headerarray)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headerarray);
        }

        if ($response = curl_exec($ch)) {
            if ($json_decode)
                $return = (array) json_decode($response);
            else
                $return = $response;
        } else {
            $return = false;
        }

        curl_close($ch);

        $this->log('Facebook API response', $call, $return);

        if (isset($return['error'])) {
            $e = new \Exception('Facebook API Error', 500);
            $this->log('Facebook API Error trace', $e->getTrace());
            throw $e;
        }

        return $return;

    }

    /**
     * Reads a chunk for $chunkSize from the stream $handle
     * @param resource $handle
     * @param string $url
     * @param int $chunkSize
     * @param string $s
     */
    protected function getChunk($handle, $url, $chunkSize, $s)
    {
        $bufferSize = 8192;
        $currentChunk = $s['start'];
        $size = strlen($currentChunk);

        while($size < $chunkSize) {
            $chunkBit = fread($handle, $bufferSize);
            $size += strlen($chunkBit);
            
            $currentChunk .= $chunkBit;
            if(feof($handle)) {
                break;
            }
        }

        if (feof($handle)) {
            return array('start'=>'','chunk'=>$currentChunk);
        } else {
            $start = substr($currentChunk,$chunkSize);
            if ($start === false) {
                $start = '';
            }
            return array('start'=>$start,'chunk'=>substr($currentChunk,0,$chunkSize));
        }
    }

    /**
     * Set a callable function to receive logs
     * @param callable $logger
     */
    public function setLogger(callable $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Logging utility
     */
    protected function log()
    {
        if ($this->logger) {
            call_user_func_array($this->logger, func_get_args());
        }
    }
}