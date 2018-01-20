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

	const DEFAULT_SESSION_NS = 'TorCDN';
	
	/**
	 * @var String Session ID
	 */
	protected $id;

	/**
	 * @var String Session namespace
	 */
	protected $namespace;

	/**
	 * @var Array Session data store
	 */
	protected $SESSION = [];

	/**
	 * Construct the session, start if not started
	 * @param String Namespace
	 * @param String Session ID
	 */
	public function __construct($ns = null, $id = null) {
		$this->setNamespace($ns ? $ns : self::DEFAULT_SESSION_NS);
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
		$this->SESSION = &$_SESSION[$this->getNamespace()];
	}

	/**
	 * Persist the session before process ends
	 */
	public function __destruct() {
		$_SESSION[$this->getNamespace()] = $this->SESSION;
	}

	/**
	 * Retrieve a session value
	 * @param String Namespace
	 * @param Mixed Default value to return if namespace is not set
	 */
	public function get($name, $default = null) {
		return isset($this->SESSION[$name]) ? $this->SESSION[$name] : $default;
	}

	/**
	 * Set a session value
	 * @param String Namespace
	 * @param String Value
	 */
	public function set($name, $value) {
		return $this->SESSION[$name] = $value;
	}

	/**
	 * Check if a session value has been set
	 * @param String Namespace
	 */
	public function isset($name) {
		return  isset($this->SESSION[$name]);
	}

	/**
	 * Unset a session value
	 * @param String Namespace
	 */
	public function unset($name) {
		unset($this->SESSION[$name]);
	}

	/**
	 * Destroy the session
	 */
	public function destroy() {
		$this->SESSION = [];
	}

	/**
	 * Set a namespace to save session data to
	 * @param String $namespace
	 * @return void
	 */
	public function setNamespace($namespace) {
		$this->namespace = $namespace;
	}

	/**
	 * Get the session namespace
	 * @return String
	 */
	public function getNamespace() {
		return $this->namespace ?: self::DEFAULT_SESSION_NS;
	}
	
}