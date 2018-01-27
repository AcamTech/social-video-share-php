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

class YoutubeVideoUpload
{

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
    }

    public function setTimeLimit(Int $timeoutSecs) {
        ini_set('max_execution_time', $timeoutSecs);
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
            } catch(Exception $e) { /* ignore for urls */ }
        }
        return $this->uploadVideoStream($stream, $properties, $part, $params, $length);
    }
    
    // TODO: get filesize from stream? 
    private function uploadMedia($request, $stream, $length = null, $mimeType = 'video/*')
    {
        if (!$length) {
            throw new InvalidParameterException(
                'Length must be specified in uploadVideoFile() or uploadVideoStream()');
        }
        // Create a MediaFileUpload object for resumable uploads.
        // Parameters to MediaFileUpload are:
        // this->client, request, mimeType, data, resumable, chunksize.
        $media = new Google_Http_MediaFileUpload(
            $this->client,
            $request,
            $mimeType,
            null,
            true,
            $this->chunkSizeBytes
        );

        $media->setFileSize($length);

        var_dump(['chunksize' => $this->chunkSizeBytes, 'content-length' => $length]);

        // Read the media file and upload it chunk by chunk.
        $status = false;
        $chunk = '';
        while (!$status && !feof($stream)) {
            $chunk .= fread($stream, $this->chunkSizeBytes);
            $len = strlen($chunk);

            if ($len < 262144 || $len < $this->chunkSizeBytes) {
                continue; // 262144 is min chunk size or upload fails
            }
            echo 'Read from upload file stream bytes: ' . strlen($chunk) . "\n";
            $status = $media->nextChunk($chunk);
            $chunk = '';
        }

        if (strlen($chunk) > 0) {
            $media->nextChunk($chunk); // last chunk size can be less than 262144
        }

        fclose($stream);
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
            throw new InvalidParameterException('ChunkSizeBytes must be more than 262144 bytes');
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

}

class InvalidParameterException extends \Exception {}