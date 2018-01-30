<?php
/**
 * Implements FacebookHttpClientInterface so Facebook SDK can use Guzzle6 
 * @link https://github.com/facebook/php-graph-sdk
 * @link https://www.sammyk.me/how-to-inject-your-own-http-client-in-the-facebook-php-sdk-v5#writing-a-guzzle-6-http-client-implementation-from-scratch
 */
namespace TorCDN\SocialVideoShare;

use Facebook;
use GuzzleHttp;

class FacebookGuzzle6HttpClient implements Facebook\HttpClients\FacebookHttpClientInterface
{
    private $client;

    public function __construct(GuzzleHttp\Client $client)
    {
        $this->client = $client;
    }

    public function send($url, $method, $body, array $headers, $timeOut)
    {
        $request = new GuzzleHttp\Psr7\Request($method, $url, $headers, $body);
        try {
            $response = $this->client->send($request, ['timeout' => $timeOut, 'http_errors' => false]);
        } catch (GuzzleHttp\Exception\RequestException $e) {
            throw new Facebook\Exceptions\FacebookSDKException($e->getMessage(), $e->getCode());
        }
        $httpStatusCode = $response->getStatusCode();
        $responseHeaders = $response->getHeaders();

        foreach ($responseHeaders as $key => $values) {
            $responseHeaders[$key] = implode(', ', $values);
        }

        $responseBody = $response->getBody()->getContents();

        return new Facebook\Http\GraphRawResponse(
                        $responseHeaders,
                        $responseBody,
                        $httpStatusCode);
    }
}