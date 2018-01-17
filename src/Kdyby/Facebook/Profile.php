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
 * @property array $permissions
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
	private $fields = [
	    "address",
        "name",
        "age_range",
        "birthday",
        "about",
        "gender",
        "cover",
        "devices",
        "education",
        "first_name",
        "hometown",
        "inspirational_people",
        "interested_in",
        "languages",
        "last_name",
        "link",
        "locale",
        "location",
        "meeting_for",
        "middle_name",
        "name",
        "name_format",
        "profile_pic",
        "relationship_status",
        "religion",
        "short_name",
        "sports",
        "website",
        "work"
    ];



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
     * Get all details that you can with /me profile
	 * @param string $key
	 * @return ArrayHash|NULL
	 */
	public function getDetails()
	{
		if ($this->details === NULL) {
			try {
				$this->details = $this->facebook->api('/' . $this->profileId,NULL,[
				    "fields" => join(",",$this->fields)
                ]);

			} catch (FacebookApiException $e) {
				$this->details = [];
			}
		}

		return $this->details;
	}

    /**
     * @param array $keys
     * @return ArrayHash|NULL
     */
    public function getDetail($keys = ["name"])
    {
        if ($this->details === NULL) {
            try {
                $this->details = $this->facebook->api('/' . $this->profileId,NULL,[
                    "fields" => join(",",$keys)
                ]);

            } catch (FacebookApiException $e) {
                $this->details = [];
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
	public function getPictureUrl(array $params = [])
	{
		$params = array_merge($params, ['redirect' => false]);

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
	public function getPermissions(array $params = [])
	{
		$params = array_merge($params, ['access_token' => $this->facebook->getAccessToken()]);

		try {
			$response = $this->facebook->api("/{$this->profileId}/permissions", 'GET', $params);
			if ($response && !empty($response->data)) {
				$items = [];
				if (isset($response->data[0]['permission'])) {
					foreach ($response->data as $permissionsItem) {
						$items[$permissionsItem->permission] = $permissionsItem->status === 'granted';
					}

				} elseif (isset($response->data[0])) {
					$items = (array) $response->data[0];
				}

				return ArrayHash::from($items);
			}

		} catch (FacebookApiException $e) {
			return NULL;
		}
	}

}
