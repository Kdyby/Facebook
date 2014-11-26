<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\Resource;

use IteratorAggregate;
use Kdyby\Facebook\Facebook;
use Nette\Object;
use Nette\Utils\ArrayHash;
use Traversable;



/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
class ResourceLoader extends Object implements IteratorAggregate, IResourceLoader
{

	/**
	 * @var \Kdyby\Facebook\Facebook
	 */
	private $facebook;

	/**
	 * @var string|array
	 */
	private $pathOrParams;

	/**
	 * @var string|NULL
	 */
	private $method = NULL;

	/**
	 * @var array
	 */
	private $params = array();
	
	private $accessToken;

	/**
	 * @var ArrayHash|NULL
	 */
	private $lastResult = NULL;
	private $totalLoaded = 0;



	/**
	 * Creates new list of Facebook objects.
	 *
	 * @param \Kdyby\Facebook\Facebook $facebook
	 * @param string|array $pathOrParams
	 * @param string|NULL $method
	 * @param array $params
	 */
	public function __construct(Facebook $facebook, $pathOrParams, $method = NULL, array $params = array(), $accessToken = NULL)
	{
		$this->facebook = $facebook;
		$this->pathOrParams = $pathOrParams;
		$this->method = $method;
		$this->params = $params;
		$this->accessToken = $accessToken;
	}



	/**
	 * Sets list of fields which will be selected.
	 *
	 * @param string[] $fields
	 * @return \Kdyby\Facebook\Resource\ResourceLoader
	 */
	public function setFields(array $fields = array())
	{
		if (empty($this->params["fields"])) {
			$this->params["fields"] = array();
		}
		$this->params["fields"] = $fields;
		if (empty($this->params["fields"])) {
			unset($this->params["fields"]);
		}

		return $this;
	}



	/**
	 * Adds field to list of fields which will be selected.
	 *
	 * @param string $field
	 * @return \Kdyby\Facebook\Resource\ResourceLoader
	 */
	public function addField($field)
	{
		if (empty($this->params["fields"])) {
			$this->params["fields"] = array();
		}
		if (!in_array($field, $this->params["fields"])) {
			$this->params["fields"][] = $field;
		}

		return $this;
	}



	/**
	 * @return string[]
	 */
	public function getFields()
	{
		if (empty($this->params["fields"])) {
			$this->params["fields"] = array();
		}

		return $this->params["fields"];
	}



	/**
	 * @param int|NULL $limit
	 * @return \Kdyby\Facebook\Resource\ResourceLoader
	 */
	public function setLimit($limit = NULL)
	{
		if (is_numeric($limit) && $limit > 0) {
			$this->params["limit"] = intval(round($limit));
		} elseif (!empty($this->params["limit"])) {
			unset($this->params["limit"]);
		}

		return $this;
	}



	/**
	 * @return int|NULL
	 */
	public function getLimit()
	{
		return !empty($this->params["limit"]) ? $this->params["limit"] : NULL;
	}



	/**
	 * Loads first data source.
	 */
	private function load()
	{
		if ($this->lastResult === NULL) {
			$this->lastResult = $this->callApi($this->pathOrParams, $this->method, $this->params);
		} elseif ($this->hasNextPage()) {
			$this->lastResult = $this->callApi($this->getNextPath());
		}else
			$this->lastResult = NULL;
		
		if($this->lastResult !== NULL)
			$this->totalLoaded += count($this->lastResult->data);
	}
	
	private function callApi($path, $method, $params){
		if($this->accessToken){
			$curToken = $this->facebook->getAccessToken();
			$this->facebook->setAccessToken($this->accessToken);	
		}
		$ret = $this->facebook->api($path, $method, $params);
		if(isset($curToken))
			$this->facebook->setAccessToken($curToken);
		return $ret;
	}

	/**
	 * Checks if list has next page.
	 *
	 * @return bool
	 */
	private function hasNextPage()
	{
		if($this->totalLoaded >= $this->getLimit())
			return false;
		return !empty($this->lastResult->paging->next);
	}



	/**
	 * Parses path of next resource page from current data.
	 *
	 * @return string
	 */
	private function getNextPath()
	{
		return $this->lastResult->paging->next;
	}



	/**
	 * Returns collections of data from data source at one page.
	 *
	 * @return ArrayHash
	 */
	public function getNextPage()
	{
		$this->load();
		return $this->lastResult ? $this->lastResult->data : ArrayHash::from(array());
	}



	/**
	 * Resets loader to first data source.
	 *
	 * @return \Kdyby\Facebook\Resource\IResourceLoader
	 */
	public function reset()
	{
		$this->lastResult = NULL;

		return $this;
	}



	/**
	 * Retrieve an external iterator.
	 *
	 * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
	 * @return Traversable An instance of an object implementing <b>Iterator</b> or <b>Traversable</b>
	 */
	public function getIterator()
	{
		return new ResourceIterator($this);
	}

}
