<?php

namespace TorCDN\SocialVideoShare;

use TorCDN\Server\Request;
use TorCDN\Server\Session;
use Google_Client;

class GoogleAuth {

	/**
	 * @var Google_Client
	 */
	protected $client;

	/**
	 * Create google client
	 * @param $params {client_id, client_secret, redirect_uri, [scope]}
	 */
	public function __construct($params, $client = null, $Session = null, $Request = null) {
		$this->Session = $Session ?: new Session();
		$this->Request = $Request ?: new Request();

		// google api client
		$client = $client ?: new Google_Client();
		$client->setApplicationName($params['app_name']);
		$client->setClientId($params['client_id']);
		$client->setClientSecret($params['client_secret']);
		$client->setRedirectUri($params['redirect_uri']);
		$client->setScopes($params['scope']);
		$client->setAccessType('offline');

		$this->client = $client;
	}

	/**
	 * Return the google Auth URL that you can redirect to or create a link for
	 * @param String $redirectUri
	 */
	public function createAuthUrl($redirectUri = null) {
		if ($redirectUri) {
			$this->client->setRedirectUri($redirectUri);
		}
		return $this->client->createAuthUrl();
	}

	/**
	 * Delete the session
	 */
	public function logout() {
		$this->Session->unset('google_access_token');
	}

	public function getAccessToken() {
		$Request = $this->Request;
		$Session = $this->Session;
		$client = $this->client;

		$accessToken = $Session->get('google_access_token');
		$oldScope = $Session->get('google_scope');
		$scope = $client->getScopes();

		if ($accessToken && $oldScope == $scope) {
			$client->setAccessToken($accessToken);
			if ($client->isAccessTokenExpired()) {
				$refreshToken = $client->getRefreshToken(); // TODO: why is this failing?
				if ($refreshToken) {
					$fetchSuccess = $client->fetchAccessTokenWithRefreshToken($refreshToken);
					if ($fetchSuccess) {
						$accessToken = $client->getAccessToken();
					}
				} else {
					$accessToken = [];
				}
			}
		} else {
			$accessToken = null;
			$authCode = $Request->get('code');
			if ($authCode) {
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				if ($accessToken) {
					$client->setAccessToken($accessToken);
				}
			}
		}
		$Session->set('google_access_token', $accessToken);
		$Session->set('google_scope', $scope);
		return $accessToken; 
	}


}
