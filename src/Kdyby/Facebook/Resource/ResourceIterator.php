<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\Resource;

use Iterator;
use IteratorIterator;
use Nette\Object;
use Nette\Utils\Strings;



/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
class ResourceIterator extends Object implements Iterator
{

	/**
	 * @var \Kdyby\Facebook\Resource\IResourceLoader
	 */
	private $resourceLoader;

	/**
	 * @var \Iterator|NULL
	 */
	private $pageIterator = NULL;

	/**
	 * @var int
	 */
	private $counter = 0;



	public function __construct(IResourceLoader $resourceLoader)
	{
		$this->resourceLoader = $resourceLoader;
	}



	/**
	 * Return the current element.
	 *
	 * @return \Nette\ArrayHash|NULL
	 */
	public function current()
	{
		$this->load();

		return $this->pageIterator->current();
	}



	/**
	 * Move forward to next element.
	 */
	public function next()
	{
		$this->load();

		$this->pageIterator->next();
		$this->counter++;
	}



	/**
	 * Return the key of the current element.
	 *
	 * @return mixed
	 */
	public function key()
	{
		$this->load();

		return $this->counter;
	}



	/**
	 * Checks if current position is valid.
	 *
	 * @return bool
	 */
	public function valid()
	{
		$this->load();

		return $this->pageIterator->valid();
	}



	/**
	 * Rewind the Iterator to the first element.
	 */
	public function rewind()
	{
		$this->resourceLoader->reset();
		$this->counter = 0;
	}



	/**
	 * Loads one page of resource into iterator.
	 */
	private function load()
	{
		if ($this->pageIterator === NULL || !$this->pageIterator->valid()) {
			$this->pageIterator = new IteratorIterator($this->resourceLoader->getNextPage());
			$this->pageIterator->rewind();
			if (!$this->pageIterator->valid()) {
				$this->counter = NULL;
			}
		}
	}

}
