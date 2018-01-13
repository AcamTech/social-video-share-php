<?php

namespace TorCDN;

/**
 * Simple session wrapper
 * 
 * Example:
 * $Session = new Session();
 * $Session->set('foo', 'bar');
 * ... next page load ...
 * $Session->get('foo'); // bar
 */
class Session {
	
	/**
	 * @var {String} Session ID
	 */
	protected $id;

	/**
	 * Construct the session, start if not started
	 * @param {String} Session ID
	 */
	public function __construct($id = null) {
		if (session_status() != PHP_SESSION_ACTIVE) {
			if ($id) {
				session_id($id);
			}
			session_start();
		} else {
			if ($id) {
				session_commit();
				session_id($id);
				session_start();
			}
		}
	}

	/**
	 * Retrieve a session value
	 * @param {String} Namespace
	 * @param {Mixed} Default value to return if namespace is not set
	 */
	public function get($name, $default = null) {
		return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
	}

	/**
	 * Set a session value
	 * @param {String} Namespace
	 * @param {String} Value
	 */
	public function set($name, $value) {
		return $_SESSION[$name] = $value;
	}

	/**
	 * Check if a session value has been set
	 * @param {String} Namespace
	 */
	public function isset($name) {
		return  isset($_SESSION[$name]);
	}

	/**
	 * Unset a session value
	 * @param {String} Namespace
	 */
	public function unset($name) {
		unset($_SESSION[$name]);
	}

	/**
	 * Destroy the session
	 */
	public function destroy() {
		session_destroy();
	}
}