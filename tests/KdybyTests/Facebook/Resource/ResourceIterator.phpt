<?php

/**
 * Test: Kdyby\Facebook\Resource\ResourceIterator.
 *
 * @testCase KdybyTests\Facebook\Resource\ResourceIteratorTest
 * @author Martin Štekl <martin.stekl@gmail.com>
 * @package Kdyby\Facebook\Resource
 */

namespace KdybyTests\Facebook\Resource;

use Kdyby\Facebook\Resource\ResourceLoader;
use KdybyTests;
use Tester\Assert;

require_once __DIR__ . '/ResourceTestCase.php';

/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
class ResourceIteratorTest extends ResourceTestCase
{

	/**
	 * @var \Kdyby\Facebook\Resource\ResourceIterator
	 */
	private $iterator;



	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function setUp()
	{
		parent::setUp();
		$this->iterator = $this->loader->getIterator();
	}



	/**
	 * This methods should not be tested separately.
	 */
	public function testValidAndNext()
	{
		$iterator = $this->iterator;

		$i = 0;
		while ($i < $this->user->postCount) {
			Assert::true($iterator->valid());
			$iterator->next();
			$i++;
		}
		Assert::false($iterator->valid());
		$iterator->next(); // This does not throw any error if iterator is not valid
	}



	public function testCurrent()
	{
		$iterator = $this->iterator;

		while ($iterator->valid()) {
			Assert::notEqual(NULL, $iterator->current());
			$iterator->next();
		}
		Assert::equal(NULL, $iterator->current());
	}



	public function testRewind()
	{
		$iterator = $this->iterator;

		Assert::true($iterator->valid());
		while ($iterator->valid()) {
			Assert::notEqual(NULL, $iterator->current());
			$iterator->next();
		}
		Assert::equal(NULL, $iterator->current());

		$iterator->rewind();

		Assert::true($iterator->valid());
		while ($iterator->valid()) {
			Assert::notEqual(NULL, $iterator->current());
			$iterator->next();
		}
		Assert::equal(NULL, $iterator->current());
	}



	public function testKey()
	{
		$iterator = $this->iterator;

		$i = 0;
		while ($iterator->valid()) {
			Assert::equal((string) $i, (string) $iterator->key()); // Iterator can return number in string
			$iterator->next();
			$i++;
		}
		Assert::equal(NULL, $iterator->key());
	}

}

KdybyTests\run(new ResourceIteratorTest());
