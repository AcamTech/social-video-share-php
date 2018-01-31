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
git clone git@bitbucket.org:torcdn/social-video-share-php.git
cd social-video-share-php
composer install
```

## OR Install as a composer dependency in your project

Edit `composer.json` in your project add add these entries. 

```
"require": {
        "torcdn/social-video-share-php": "dev-master"
    },
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "torcdn/social-video-share-php",
                "version": "dev-master",
                "source": {
                    "url": "git@bitbucket.org:torcdn/social-video-share-php.git",
                    "type": "git",
                    "reference": "master"
                }
            }
        }
    ],
```

this adds the required dependency `torcdn/social-video-share-php` 
which resolves to `git@bitbucket.org:torcdn/social-video-share-php.git`

Then update your dependencies.

```
composer update
```

## Example

See: https://bitbucket.org/torcdn/social-video-share-api 
for an example implementation

OR view the REST API currently in `restApi.php` and routes in `/route`
Note: These will be removed in future and moved to `torcdn\social-video-share-api`