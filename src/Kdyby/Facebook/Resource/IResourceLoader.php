<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\Resource;

/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
interface IResourceLoader
{

	/**
	 * Returns collections of data from data source at one page.
	 *
	 * @return array|\Traversable
	 */
	public function getNextPage();



	/**
	 * Resets loader to first data source.
	 *
	 * @return \Kdyby\Facebook\Resource\IResourceLoader
	 */
	public function reset();

}
