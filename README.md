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
* Linkedin  - Share local file, URL or S3
* Vimeo     - Share URL or S3 (Local current not working)

## Supported Social Networks without upload (link to video only)

* Instagram   - URL to video
* Linkedin    - URL to video
* Google+     - In progress* link to Youtube
