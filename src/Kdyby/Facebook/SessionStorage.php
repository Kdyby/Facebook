<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook;

use Nette;
use Tracy\Debugger;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property string $state A CSRF state variable to assist in the defense against CSRF attacks.
 * @property string $code
 * @property string $access_token
 * @property string $user_id
 */
class SessionStorage
{
    use Nette\SmartObject;

	const FBSS_COOKIE_NAME = 'fbss';

	// We can set this to a high number because the main session
	// expiration will trump this.
	const FBSS_COOKIE_EXPIRE = 31556926; // 1 year

	/**
	 * @var \Nette\Http\SessionSection
	 */
	protected $session;

	/**
	 * @var array
	 */
	protected static $supportedKeys = ['state', 'code', 'access_token', 'user_id'];



	/**
	 * @param \Nette\Http\Session $session
	 * @param Configuration $config
	 */
	public function __construct(Nette\Http\Session $session, Configuration $config)
	{
		$this->session = $session->getSection('Facebook/' . $config->getApplicationAccessToken());
	}



	/**
	 * Lays down a CSRF state token for this process.
	 *
	 * @return void
	 */
	public function establishCSRFTokenState()
	{
		if (!$this->state) {
			$this->state = md5(uniqid(mt_rand(), TRUE));
		}
	}



	/**
	 * Stores the given ($key, $value) pair, so that future calls to
	 * getPersistentData($key) return $value. This call may be in another request.
	 *
	 * Provides the implementations of the inherited abstract
	 * methods.  The implementation uses PHP sessions to maintain
	 * a store for authorization codes, user ids, CSRF states, and
	 * access tokens.
	 */
	public function set($key, $value)
	{
		if (!in_array($key, self::$supportedKeys)) {
			return;
		}

		$this->session->$key = $value;
	}



	/**
	 * Get the data for $key, persisted by BaseFacebook::setPersistentData()
	 *
	 * @param string $key The key of the data to retrieve
	 * @param mixed $default The default value to return if $key is not found
	 *
	 * @return mixed
	 */
	public function get($key, $default = FALSE)
	{
		if (!in_array($key, self::$supportedKeys)) {
			return FALSE;
		}

		return isset($this->session->$key) ? $this->session->$key : $default;
	}



	/**
	 * Clear the data with $key from the persistent storage
	 *
	 * @param string $key
	 * @return void
	 */
	public function clear($key)
	{
		if (!in_array($key, self::$supportedKeys)) {
			return;
		}

		unset($this->session->$key);
	}



	/**
	 * Clear all data from the persistent storage
	 *
	 * @return void
	 */
	public function clearAll()
	{
		$this->session->remove();
	}



	/**
	 * @param string $name
	 * @return mixed
	 */
	public function &__get($name)
	{
		$value = $this->get($name);
		return $value;
	}



	/**
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		$this->set($name, $value);
	}



	/**
	 * @param string $name
	 * @return bool
	 */
	public function __isset($name)
	{
		if (!in_array($name, self::$supportedKeys)) {
			return FALSE;
		}

		return isset($this->session->$name);
	}



	/**
	 * @param string $name
	 */
	public function __unset($name)
	{
		$this->clear($name);
	}

}
