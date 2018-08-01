# Social Video Share

Share video from a CDN or File hosting to social networks. 

* Efficient   - videos streamed non-blocking through the API with configurable chunk size.
* Extensible  - add support for your own social media sites and/or video sources.
* Hosted      - Video is hosted on Social Network for best integration

Eg: Downloads a video from S3 and publishes to facebook feed

## Supported CDN

* Amazon S3       - Support S3 API for private video storage
* Any HTTP url    - eg: https://clips.vorwaerts-gmbh.de/big_buck_bunny.mp4 
* Local disk      - eg: /path/to/file.mp4

## Supported Social Networks

* Youtube   - Share local file, URL or S3
* Facebook  - Share local file, URL or S3
* Twitter   - Share local file, URL or S3
* Vimeo     - Share URL or S3 (Local current not working)

## Supported Social Networks without upload (link to video only)

* Instagram   - URL to video
* Linkedin    - URL to video
* Google+     - In progress* link to Youtube

# Installation

You can clone the project from Git or use composer.

## Clone the project via Git

```
git clone git@github.com:Seedess/social-video-share-php.git
cd social-video-share-php
composer install
```

## OR Install as a composer dependency in your project

Edit `composer.json` in your project add add these entries. 

```
"require": {
        "seedess/social-video-share-php": "dev-master"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "seedess/social-video-share-php",
                "version": "dev-master",
                "source": {
                    "url": "git@github.com:Seedess/social-video-share-php.git",
                    "type": "git",
                    "reference": "master"
                }
            }
        }
    ],
```

this adds the required dependency `seedess/social-video-share-php` 
which resolves to `git@github.com:Seedess/social-video-share-php.git`

Then update your dependencies.

```
composer update
```

## REST Server

A REST server is provided as a microservice - for generic use cases - that can authenticate with each social network and upload video to those networks. 

Start the REST server via: 

```
php -S localhost:8080
```

View the API base endpoint at: 

http://localhost:8080/restApi.php

You can view the different routes available in `restApi.php` and `routes/` directory. 
Documentation for the REST API will be added soon. 

## Examples

See: https://github.com/Seedess/social-video-share-php/blob/master/route/facebook.php
for an example implementation for Facebook. 

OR view the REST API currently in `restApi.php` and other routes in `/route` directory.

## Library Custom Usage

After installing via GIT or composer as outlined above you can use the library by autoloading. 

```
<?php
require_once __DIR__ . '/vendor/autoload.php';

```

A Sample configuration is included. 
You will need to set the values to your own client ids and secrets.

```
$config = require __DIR__ . '/config.php';
```

After including the config you can use it to Authenticate via a social network. 

## Social Network Authentication

Google (youtube): 

```
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\GoogleAuth;

$Session     = new Session('google');
$Client      = new Google_Client();
$GoogleAuth  = new GoogleAuth($config['google'], $Client, $Session);

$accessToken = $GoogleAuth->getAccessToken();

if (!$accessToken) {
    $authUrl = $GoogleAuth->createAuthUrl($redirectUrl);
    header('Location: ' . $authUrl);
} else {
    echo 'Your access token is ' . $accessToken;
}

```

Or for Facebook:

```
use TorCDN\Server\Session;
use TorCDN\SocialVideoShare\FacebookVideoUpload;

$Session = new Session('facebook');
$facebook = new FacebookVideoUpload($config['facebook'], $Session);
$accessToken = $Facebook->getAccessToken();

if (!$accessToken) {
    $authUrl = $Facebook->createAuthUrl();
    header('Location: ' . $authUrl);
} else {
    echo 'Your access token is ' . $accessToken;
}

```

Other examples can be found in the `routes/` directory.

## Video Upload to Social Networks

Video upload from Amazon S3 to Facebook

```
use TorCDN\Server\Session;
use TorCDN\Server\Request;
use TorCDN\SocialVideoShare\S3Stream;
use TorCDN\SocialVideoShare\FacebookVideoUpload;

$request = new Request();

$bucket       = $request->get('bucket');
$filename     = $request->get('filename');
$accessToken  = $request->get('accessToken');
$meta         = [
                    'title' => $request->get('title'),
                    'description' => $request->get('description')
                ];

if (!$url && !($bucket || $filename)) {
    throw new \Exception('Required query parameter: url or bucket and filename.', 400);
}

$Session = new Session('facebook');
$S3Stream = new S3Stream($config['s3']);
$Session = new Session('facebook');
$facebook = new FacebookVideoUpload($config['facebook'], $Session);

if (!$accessToken) {
    $accessToken = $Facebook->getAccessToken() || $Session->get('accessToken');
}

if (!$accessToken) {
    throw new \Exception('Required query parameter: accessToken.', 400);
}

$facebook->setAccessToken($accessToken);

$url = $S3Stream->getUrl($bucket, $filename);
$resp = $facebook->uploadVideoFromUrl($meta, $url, $size = null, $progressCallback = null);

echo '<pre>' . print_r($resp, true) . '</pre>';
```

More examples can be found in the implementations in the `routes/` directory. 

