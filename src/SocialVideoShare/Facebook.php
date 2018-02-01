<?php

namespace TorCDN\SocialVideoShare;

use Facebook\FileUpload\FacebookResumableUploader;
use Facebook\FileUpload\FacebookTransferChunk;

/**
 * Allow access to useful functions in facebook SDK
 */
class Facebook extends \Facebook\Facebook
{
    /**
     * Attempts to upload a chunk of a file in $retryCountdown tries.
     *
     * @param FacebookResumableUploader $uploader
     * @param string $endpoint
     * @param FacebookTransferChunk $chunk
     * @param int $retryCountdown
     *
     * @return FacebookTransferChunk
     *
     * @throws FacebookSDKException
     */
    public function maxTriesTransfer(FacebookResumableUploader $uploader, $endpoint, FacebookTransferChunk $chunk, $retryCountdown)
    {
        $newChunk = $uploader->transfer($endpoint, $chunk, $retryCountdown < 1);

        if ($newChunk !== $chunk) {
            return $newChunk;
        }

        $retryCountdown--;

        // If transfer() returned the same chunk entity, the transfer failed but is resumable.
        return $this->maxTriesTransfer($uploader, $endpoint, $chunk, $retryCountdown);
    }
}