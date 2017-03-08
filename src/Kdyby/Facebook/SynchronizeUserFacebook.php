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

/**
 * @author Milan Matějček <milan.matejcek@gmail.com>
 */
class SynchronizeUserFacebook extends Nette\Object
{
	/** @var Nette\Security\IUserStorage */
	private $userStorage;

	/** @var SessionStorage */
	private $sessionStorage;

	/** @var Facebook */
	private $facebook;


	public function __construct(Nette\Security\IUserStorage $userStorage, SessionStorage $sessionStorage, Facebook $facebook)
	{
		$this->userStorage = $userStorage;
		$this->sessionStorage = $sessionStorage;
		$this->facebook = $facebook;
		$this->syncFacebookSession();
	}


	/**
	 * Synchronize user state with facebook.
	 */
	public function syncFacebookSession()
	{
		if ($this->facebook->getUser() && $this->isUserChangeState()) {
			$this->facebook->destroySession();
		}
	}


	/** @return bool */
	private function isUserChangeState()
	{
		if (!$this->userStorage->isAuthenticated()) {
			return $this->sessionStorage->checkLogout();
		}
		$this->sessionStorage->login();
		return FALSE;
	}

}
