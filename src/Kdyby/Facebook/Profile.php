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
use Nette\Utils\ArrayHash;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property ArrayHash $details
 * @property string $pictureUrl
 */
class Profile extends Nette\Object
{

	/**
	 * @var Facebook
	 */
	private $facebook;

	/**
	 * @var string
	 */
	private $profileId;

	/**
	 * @var ArrayHash
	 */
	private $details;



	/**
	 * @param Facebook $facebook
	 * @param string $profileId
	 */
	public function __construct(Facebook $facebook, $profileId)
	{
		$this->facebook = $facebook;
		$this->profileId = $profileId;
	}



	/**
	 * @return string
	 */
	public function getId()
	{
		if ($this->profileId === 'me') {
			return $this->facebook->getUser();
		}

		return $this->profileId;
	}



	/**
	 * @param string $key
	 * @return ArrayHash|NULL
	 */
	public function getDetails($key = NULL)
	{
		if ($this->details === NULL) {
			try {
				$this->details = $this->facebook->api('/' . $this->profileId);

			} catch (FacebookApiException $e) {
				$this->details = array();
			}
		}

		if ($key !== NULL) {
			return isset($this->details[$key]) ? $this->details[$key] : NULL;
		}

		return $this->details;
	}



	/**
	 * @return Profile|NULL
	 */
	public function getSignificantOther()
	{
		if (!$other = $this->getDetails('significant_other')) {
			return NULL;
		}

		return $this->facebook->getProfile($other['id']);
	}



	/**
	 * @param array $params
	 * @return null
	 */
	public function getPictureUrl(array $params = array())
	{
		$params = array_merge($params, array('redirect' => false));

		try {
			return $this->facebook->api("/{$this->profileId}/picture", NULL, $params)->data->url;

		} catch (FacebookApiException $e) {
			return NULL;
		}
	}



	/**
	 * @param array $params
	 * @return NULL|ArrayHash
	 */
	public function getPermissions(array $params = array())
	{
		$params = array_merge($params, array('access_token' => $this->facebook->getAccessToken()));

		try {
			$response = $this->facebook->api("/{$this->profileId}/permissions", 'GET', $params);
			if ($response && !empty($response->data)) {
				$items = array();
				foreach ($response->data as $permissionsItem) {
					if ($permissionsItem->status === 'granted') {
						$items[$permissionsItem->permission] = 1;
					}
				}
				return ArrayHash::from($items);
			}

		} catch (FacebookApiException $e) {
			return NULL;
		}
	}

}
