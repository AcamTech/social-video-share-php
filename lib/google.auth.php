<?php

namespace TorCDN;

require_once('vendor/autoload.php');
require_once('config.php');
require_once('Session.class.php');
require_once('Request.class.php');

use TorCDN\HttpServerIncomingClientRequest as Request;
use TorCDN\Session;
use Google_Client;

class GoogleAuth {

	/**
	 * Create google client
	 * @param $params {client_id, client_secret, redirect_uri, [scope]}
	 */
	public function __construct($params, $client = null, $Session = null, $Request = null) {
		$this->Session = new Session();
		$this->Request = new Request();

		// google api client
		$client = $client ? $client : new Google_Client();
		//$client->setApplicationName($google_app_name);
		$client->setClientId($params['client_id']);
		$client->setClientSecret($params['client_secret']);
		$client->setRedirectUri($params['redirect_uri']);
		$client->setScopes($params['scope']);
		$client->setAccessType('offline');

		$this->client = $client;
	}

	/**
	 * Return the google Auth URL that you can redirect to or create a link for
	 */
	public function createAuthUrl() {
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
