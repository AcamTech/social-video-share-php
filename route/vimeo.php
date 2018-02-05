<?php
/**
 * Rest API for Upload and Share video to Vimeo from S3
 */

// TODO: Use InvalidParameterException insted of \Exception(msg, 400)

use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\VimeoAuth;
use Vimeo\Vimeo;
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\RestApi;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use TorCDN\SocialVideoShare\Util;

/**
 * REST API endpoint for Vimeo Authentication
 * @method /vimeo/auth
 * @param String appUrl   (Optional) Your app URL to return to after Authentication.
 *                        Note: /vimeo/auth handles OAuth. appUrl is redirected to after authentication.
 */
$api->get('/vimeo/auth', function (RestApi $api, Request $request) use ($config) {
  $vimeo = $config['vimeo'];

  $Session = new Session('vimeo');
  $Vimeo = new Vimeo($vimeo['client_id'], $vimeo['client_secret']);

  $appUrl       = $request->get('appUrl');
  $code         = $request->get('code');
  $tokenResp    = $Session->get('tokenResp');
  $redirectUri  = isset($vimeo['redirectUri']) ? $vimeo['redirectUri'] : strtok($request->getUri(), '?');

  if (!$appUrl) {
    $appUrl = strtok($request->getUri(), '?');
  }

  if ($code) {
    $state = $Session->get($request->get('state'));
    $Session->set($request->get('state'), null);
    if (!$state) {
      throw new \Exception('Invalid access token state received.', 400);
    }
    $tokenResp = $Vimeo->accessToken($code, $redirectUri);

    if (isset($tokenResp['body']['error'])) {
      return $api->json($tokenResp);
    }
    $Session->set('tokenResp', $tokenResp);

    return $api->redirect($state['appUrl']);
  }

  $authUrl = '';
  if (!$tokenResp) {
    $state = Util::generateRandomSecureToken();
    $Session->set($state, ['appUrl' => $appUrl]);
    $authUrl = $Vimeo->buildAuthorizationEndpoint($redirectUri, $vimeo['scopes'], $state);
  }

  $resp = [
    'authUrl' => $authUrl,
    'accessToken' => $tokenResp ? $tokenResp['body']['access_token'] : null,
    'response' => $tokenResp
  ];

  return $api->json($resp);
});

/**
 * REST API endpoint for Vimeo video upload and share
 * @method /vimeo/share
 * @param String accessToken  (Optional) Access Token from /vimeo/auth
 * 
 * If Uploading from S3
 * @param String bucket       S3 bucket
 * @param String filename     S3 File path
 * 
 * If Uploading from URL
 * @param String url          If not using S3 specificy a public video URL
 * 
 * @param String title        Video title
 * @param String description  Video description
 */
$api->get('/vimeo/share', function (RestApi $api, Request $request) use ($config) {
  $vimeo = $config['vimeo'];
  // execution time limit not exceeding execTimeLimitMax
  $execTimeLimitMax = 60 * 15; // 15 mins
  $execTimeLimit = min($request->get('execTimeLimit'), $execTimeLimitMax);

  $Session = new Session('vimeo');

  $bucket       = $request->get('bucket');
  $filename     = $request->get('filename');
  $url          = $request->get('url');
  $accessToken  = $request->get('accessToken');
  $title        = $request->get('title');
  $description  = $request->get('description');

  if (!$accessToken) {
    $tokenResp = $Session->get('tokenResp');
    $accessToken = isset($tokenResp['body']['access_token']) 
      ? $tokenResp['body']['access_token'] : null;
  }

  if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.', 400);
  }
  if (!$accessToken) {
    throw new \Exception('Required query parameter: accessToken.', 400);
  }

  $S3Stream = new S3Stream($config['s3']);
  $Vimeo = new Vimeo($vimeo['client_id'], $vimeo['client_secret']);

  $Vimeo->setToken($accessToken);

  if (!$url) {
    $url = $S3Stream->getUrl($bucket, $filename);
  }

  $params = [
    'type'        => 'pull', 
    'link'        => $url, 
    'name'        => $title, 
    'description' => $description
  ];
  $resp = $Vimeo->request('/me/videos', $params, 'POST');

  return $api->json($resp);

});

/**
 * REST API endpoint for Vimeo logout
 * @method /vimeo/logout
 */
$api->get('/vimeo/logout', function (RestApi $api, Request $request) use ($config) {
  $Session = new Session('vimeo');
  $Session->destroy();
  return $api->json(['status' => 'Log out ok']);

});
