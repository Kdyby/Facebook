<?php

/**
 * Test: Kdyby\Facebook\Resource\ResourceLoader.
 *
 * @testCase KdybyTests\Facebook\Resource\ResourceLoaderTest
 * @author Martin Štekl <martin.stekl@gmail.com>
 * @package Kdyby\Facebook\Resource
 */

namespace KdybyTests\Facebook\Resource;

use Iterator;
use Kdyby\Facebook\Resource\ResourceIterator;
use Kdyby\Facebook\Resource\ResourceLoader;
use KdybyTests;
use Nette\ArrayHash;
use Tester\Assert;

require_once __DIR__ . '/ResourceTestCase.php';

/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
class ResourceLoaderTest extends ResourceTestCase
{

	public function testAddField()
	{
		$loader = $this->loader;
		Assert::same(array(), $loader->getFields());

		$loader->addField("message");
		Assert::same(array("message"), $loader->getFields());

		$loader->addField("from");
		Assert::same(array("message", "from"), $loader->getFields());
	}



	public function testSetFields()
	{
		$loader = $this->loader;
		Assert::same(array(), $loader->getFields());

		$loader->setFields(array("message"));
		Assert::same(array("message"), $loader->getFields());

		$loader->setFields(array("from"));
		Assert::same(array("from"), $loader->getFields());

		$loader->setFields();
		Assert::same(array(), $loader->getFields());
	}



	public function testSetLimit()
	{
		$loader = $this->loader;

		$loader->setLimit();
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit(10);
		Assert::equal(10, $loader->getLimit());

		$loader->setLimit("10");
		Assert::equal(10, $loader->getLimit());

		$loader->setLimit(10.07);
		Assert::equal(10, $loader->getLimit());

		$loader->setLimit("10.07");
		Assert::equal(10, $loader->getLimit());

		$loader->setLimit(10.57);
		Assert::equal(11, $loader->getLimit());

		$loader->setLimit("10.57");
		Assert::equal(11, $loader->getLimit());

		$loader->setLimit(0);
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit("0");
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit(-10);
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit("-10");
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit("");
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit("test");
		Assert::equal(NULL, $loader->getLimit());

		$loader->setLimit(new \stdClass());
		Assert::equal(NULL, $loader->getLimit());
	}



	public function testGetNextPage()
	{
		$loader = $this->loader;
		$loader->setFields(array("id", "message"));

		$expected = ArrayHash::from(array(
			"0" => array(
				"id" => "100006500974824_1378194239073929",
				"message" => "Testovací status 5",
				"created_time" => "2013-08-04T20:11:16+0000",
			),
			"1" => array(
				"id" => "100006500974824_1378194192407267",
				"message" => "Testovací status 4",
				"created_time" => "2013-08-04T20:11:12+0000",
			),
			"2" => array(
				"id" => "100006500974824_1378194159073937",
				"message" => "Testovací status 3",
				"created_time" => "2013-08-04T20:11:09+0000",
			),
		));
		Assert::equal($expected, $loader->getNextPage());

		$expected = ArrayHash::from(array(
			"0" => array(
				"id" => "100006500974824_1378194142407272",
				"message" => "Testovací status 2",
				"created_time" => "2013-08-04T20:11:06+0000",
			),
			"1" => array(
				"id" => "100006500974824_1378194129073940",
				"message" => "Testovací status 1",
				"created_time" => "2013-08-04T20:11:03+0000",
			),
		));
		Assert::equal($expected, $loader->getNextPage());

		$expected = ArrayHash::from(array());
		Assert::equal($expected, $loader->getNextPage());
	}



	public function testReset()
	{
		$loader = $this->loader;
		$loader->setFields(array("id", "message"));
		$expected = ArrayHash::from(array(
			"0" => array(
				"id" => "100006500974824_1378194239073929",
				"message" => "Testovací status 5",
				"created_time" => "2013-08-04T20:11:16+0000",
			),
			"1" => array(
				"id" => "100006500974824_1378194192407267",
				"message" => "Testovací status 4",
				"created_time" => "2013-08-04T20:11:12+0000",
			),
			"2" => array(
				"id" => "100006500974824_1378194159073937",
				"message" => "Testovací status 3",
				"created_time" => "2013-08-04T20:11:09+0000",
			),
		));
		Assert::equal($expected, $loader->getNextPage());

		$expected2 = ArrayHash::from(array(
			"0" => array(
				"id" => "100006500974824_1378194142407272",
				"message" => "Testovací status 2",
				"created_time" => "2013-08-04T20:11:06+0000",
			),
			"1" => array(
				"id" => "100006500974824_1378194129073940",
				"message" => "Testovací status 1",
				"created_time" => "2013-08-04T20:11:03+0000",
			),
		));
		Assert::equal($expected2, $loader->getNextPage());

		$loader->reset();
		Assert::equal($expected, $loader->getNextPage());
	}



	public function testGetIterator()
	{
		$loader = $this->loader;
		Assert::true($loader->getIterator() instanceof Iterator);
		Assert::true($loader->getIterator() instanceof ResourceIterator);
	}

}

KdybyTests\run(new ResourceLoaderTest());
