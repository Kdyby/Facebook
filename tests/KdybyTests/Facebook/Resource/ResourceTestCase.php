<?php

namespace KdybyTests\Facebook\Resource;

use Kdyby\Facebook\Resource\ResourceLoader;
use Kdyby\Facebook\SignedRequest;
use KdybyTests;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/TestUser.php';

/**
 * @author Martin Štekl <martin.stekl@gmail.com>
 */
abstract class ResourceTestCase extends KdybyTests\FacebookTestCase
{

	const TEST_APP_ACCESS_TOKEN = "494469227315105|rjCGOc1ntRu2-B2J0QaKZohrU7c";

	/**
	 * @var \Kdyby\Facebook\Resource\ResourceLoader
	 */
	protected $loader;

	/**
	 * @var \KdybyTests\Facebook\Resource\TestUser
	 */
	protected $user;



	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function setUp()
	{
		parent::setUp();
		$this->createContainer("config.kdyby.neon");
		$facebook = $this->createWithRequest();

		$facebook->setAccessToken(self::TEST_APP_ACCESS_TOKEN);

		$this->user = new TestUser();

		$this->loader = new ResourceLoader($facebook, "/" . $this->user->id . "/posts");
		$this->loader->setLimit(3);
	}

}
