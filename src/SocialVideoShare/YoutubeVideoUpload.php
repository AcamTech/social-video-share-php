<?php
/**
 * @author Gabe Lalasava <gabe@fijiwebdesign.com>
 * @copyright Copyright 2017, Gabirieli Lalasava
 * @see https://developers.google.com/youtube/v3/docs/videos/insert
 * 
 * Uploads video to youtube using url, file path or readable stream
 */

namespace TorCDN\SocialVideoShare; 

use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Video;
use Google_Http_MediaFileUpload;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use TorCDN\SocialVideoShare\Exception;

class YoutubeVideoUpload
{
    /**
     * @var callable Callback to handle progress of uploaded bytes
     */
    protected $progressHandler;

    /**
     * @var Psr\Log\LoggerInterface Logger instance
     */
    protected $logger;

    /**
     * Construct with Google Client and Youtube Service
     * @param {Google_Client} $client
     * @param {Google_Service_YouTube} $service Optional
     */
    public function __construct(Google_Client $client, Google_Service_YouTube $service = null)
    {
        $this->client = $client;
        $this->service = $service ? $service : new Google_Service_YouTube($client);
        $this->setMimeType('video/*');
        $this->setChunkSizeBytes(1 * 1024 * 1024); // 1Mb. Min is 262144 bytes

        $logger = new Logger(__CLASS__);
        //$logger->pushHandler(new StreamHandler('YoutubeVideoUpload.log', Logger::DEBUG));
        $this->setLogger($logger);
        $this->setProgresshandler(function($progress) use ($logger) {
            $logger->debug('Progress: ' . json_encode($progress));
        });
    }

    /**
     * Set the script execution time limit
     * @param Int $timeoutSecs
     */
    public function setTimeLimit(Int $timeoutSecs) {
        ini_set('max_execution_time', $timeoutSecs);
    }

    /**
     * Set the debug logger
     * @param Psr\Log\LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Set the progress callback handler
     * @param callable $progressHandler
     */
    public function setProgresshandler(callable $progressHandler) {
        $this->progressHandler = $progressHandler;
    }

    /**
     * Upload a video file from a Readable Stream or File Resouce / File Pointer
     * @param {Stream} $stream Readable Stream 
     *                 eg: fopen('/path/to/video.mp4', 'rb');
     *                 eg: fopen('https://example.com/video.mp4', 'r');
     * @param {Array|Google_Service_YouTube_Video} $properties Properties for video.insert 
    *   Example:
    *      [
    *           "snippet"=>
    *               [
    *               "categoryId"    =>"22",
    *               "description"   => "Test S3 to Youtube upload",
    *               "title"         => "Test S3 video upload @ " . date('r')
    *               ],
    *           "status" =>
    *               [
    *                   "privacyStatus" => "public"
    *               ]
    *       ]
    *   For full docs see: https://developers.google.com/youtube/v3/docs/videos/insert
     * @param {String} $part eg: 'snippet,status'
     * @param {array} $params 
     */
    public function uploadVideoStream($stream, $properties, $part, $params, $length = null)
    {
        $params = array_filter($params);
        $resource = $properties instanceof Google_Service_YouTube_Video
            ? $properties
            : new Google_Service_YouTube_Video($properties);
        $this->client->setDefer(true);
        $request = $this->service->videos->insert($part, $resource, $params);
        $this->client->setDefer(false);
        $response = $this->uploadMedia($request, $stream, $length, $this->mimeType);
        return $response;
    }

    /**
     * Upload a video file from a URL or local file path
     * @param {String} $file_path URL or local file path
     * @param {Array} $properties Properties for video.insert 
     *                see: uploadVideoStream() comments
     *                see: https://developers.google.com/youtube/v3/docs/videos/insert
     * @param {String} $part eg: 'snippet,status'
     * @param {array} $params 
     */
    public function uploadVideoFile($file_path, $properties, $part, $params, $length = null)
    {
        $stream = fopen($file_path, "rb");
        if (!$length) {
            try {
                $length = filesize($file_path);
            } catch(Exception $e) {
                throw new Exception('Could not get file content length.');
            }
        }
        return $this->uploadVideoStream($stream, $properties, $part, $params, $length);
    }
    
    /**
     * Performs the upload to Youtube in chunks
     * @param Object $request Videos.Insert Request
     * @param Resource $stream
     * @param Int $length
     * @param String $mimeType
     * @return Mixed Status
     */
    private function uploadMedia($request, $stream, $length = null, $mimeType = 'video/*')
    {
        if (!$length) {
            throw new InvalidParamException(
                'Length must be specified in uploadVideoFile() or uploadVideoStream()');
        }
        // Create a MediaFileUpload object for resumable uploads.
        // Parameters to MediaFileUpload are:
        // this->client, request, mimeType, data, resumable, chunksize.
        $this->media = new Google_Http_MediaFileUpload(
            $this->client,
            $request,
            $mimeType,
            null,
            true,
            $this->chunkSizeBytes
        );

        $this->media->setFileSize($length);

        $this->logger->debug(
            'Uploading file: ' . json_encode(['chunksize' => $this->chunkSizeBytes, 'length' => $length])
        );

        // Read the media file and upload it chunk by chunk.
        $status = false;
        $this->chunkCount = 0;
        $this->totalSize = 0;
        $buffer = '';

        while (!$status && !feof($stream)) {

            // fread non local files returns as soon as a packet is available
            // usually 8192 bytes. http://php.net/manual/en/function.fread.php
            // buffer packets to $this->chunkSizeBytes then upload
            $packet = fread($stream, $this->chunkSizeBytes);
            $buffer .= $packet;

            if (feof($stream) || strlen($buffer) >= $this->chunkSizeBytes) {

                while (strlen($buffer) >= $this->chunkSizeBytes) {
                    $chunk = substr($buffer, 0, $this->chunkSizeBytes);
                    $buffer = substr($buffer, $this->chunkSizeBytes);
                    $status = $this->uploadChunk($chunk);
                }
                
                if (feof($stream) && strlen($buffer)) {
                    $status = $this->uploadChunk($buffer);
                    $buffer = '';
                }

            }
        }

        fclose($stream);
        return $status;
    }

    protected function uploadChunk($chunk)
    {
        $chunkSize = strlen($chunk);
        $this->totalSize += $chunkSize;
        $this->chunkCount++;

        $this->progress([
            'chunkSize' => $chunkSize,
            'chunkCount' => $this->chunkCount,
            'totalSize' => $this->totalSize
        ]);

        $status = $this->media->nextChunk($chunk);

        return $status;
    }

    /**
     * Set the upload chunk size in bytes
     * Set a higher value for reliable connection as fewer chunks lead to faster uploads. 
     * Set a lower value for better recovery on less reliable connections.
     * @param {Int} $chunkSizeBytes Chunk size 
     *              eg: (1 * 1024 * 1024)
     */
    public function setChunkSizeBytes($chunkSizeBytes)
    {
        if ($chunkSizeBytes < 262144) {
            throw new InvalidParamException('ChunkSizeBytes must be more than 262144 bytes');
        }
        $this->chunkSizeBytes = $chunkSizeBytes;
    }

    /**
     * Set the MimeType of the file to upload
     * @param {String} $mimeType Default is 'video/*'
     *                 eg: 'video/mp4' 
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;
    }

    /**
     * Update progressHandler function with upload progress
     * @param Array $progress [ chunk: {size}, total: {size} ]
     */
    protected function progress($progress)
    {
        return call_user_func($this->progressHandler, $progress);
    }

}