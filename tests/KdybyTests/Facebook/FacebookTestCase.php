<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace KdybyTests\Facebook;

use Kdyby;
use Nette;
use Tester;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class FacebookTestCase extends Tester\TestCase
{

	/**
	 * @var \Nette\DI\Container
	 */
	protected $container;

	/**
	 * @var \Kdyby\Facebook\Facebook
	 */
	protected $facebook;

	/**
	 * @var \Kdyby\Facebook\Configuration
	 */
	protected $config;

	/**
	 * @var \Kdyby\Facebook\SessionStorage
	 */
	protected $session;

	/**
	 * @var \Kdyby\Facebook\ApiClient|\Kdyby\Facebook\Api\CurlClient
	 */
	protected $apiClient;

	/**
	 * @var Nette\Utils\ArrayHash
	 */
	protected $testUser;

	/**
	 * @var Nette\Utils\ArrayHash
	 */
	protected $testUser2;



	/**
	 * @param string $fbConfig
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer($fbConfig = 'config.neon')
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Facebook\DI\FacebookExtension::register($config);
		$config->addConfig(__DIR__ . '/files/' . $fbConfig);
		$config->addConfig(__DIR__ . '/../nette-reset.neon');

		/** @var \Nette\DI\Container|\SystemContainer $dic */
		$dic = $config->createContainer();

		$dic->removeService('httpRequest');
		$dic->addService('httpRequest', new Nette\Http\Request(
				new Nette\Http\UrlScript('http://kdyby.org/'),
				NULL, NULL, NULL, NULL, NULL, 'GET')
		);

		$session = $dic->getByType('Nette\Http\Session');
		/** @var \Nette\Http\Session $session */
		$session->isStarted() && $session->destroy();

		$router = $dic->getService('router');
		$router[] = new Nette\Application\Routers\Route('unit-tests/<presenter>/<action>', 'Mock:default');

		$this->facebook = $dic->getByType('Kdyby\Facebook\Facebook');
		$this->config = $dic->getByType('Kdyby\Facebook\Configuration');
		$this->session = $dic->getByType('Kdyby\Facebook\SessionStorage');
		$this->apiClient = $dic->getByType('Kdyby\Facebook\ApiClient');
		$this->container = $dic;

		return $dic;
	}



	protected function prepareTestUsers()
	{
		if ($this->config === NULL || $this->facebook === NULL) {
			throw new \LogicException(sprintf('Before calling this method, init a container using %s::createContainer()', get_called_class()));
		}

		$testUser = dirname(TEMP_DIR) . sprintf('/test-user-%s.json', $this->config->graphVersion);
		if (!is_dir($dir = dirname($testUser))) {
			@mkdir($dir, 0777, TRUE);
		}

		$h = fopen($testUser, 'a+b');
		$stat = fstat($h);
		if (!$stat['size']) {
			flock($h, LOCK_EX);

			$stat = fstat($h);
			if (!$stat['size']) {
				$testUser = $this->facebook->api('/' . $this->config->appId . '/accounts/test-users', 'POST', array(
					'installed' => TRUE,
					'name' => 'Filip Test Procházka',
					'locale' => 'en_US',
					'permissions' => 'read_stream,user_photos',
				));

				$testUser2 = $this->facebook->api('/' . $this->config->appId . '/accounts/test-users', 'POST', array(
					'installed' => TRUE,
					'name' => 'Jan Test Procházka',
					'locale' => 'en_US',
					'permissions' => 'read_stream,user_photos',
				));

				fwrite($h, json_encode(array($testUser, $testUser2), defined('JSON_PRETTY_PRINT') ? constant('JSON_PRETTY_PRINT') : 0));
			}

			flock($h, LOCK_UN);
		}
		fseek($h, 0);

		list($user1, $user2) = json_decode(fread($h, 1000000));
		$this->testUser = Nette\Utils\ArrayHash::from($user1);
		$this->testUser2 = Nette\Utils\ArrayHash::from($user2);

		@fclose($h);
	}



	protected function createWithRequest($url = NULL, $post = NULL, $cookies = NULL, $headers = NULL, $method = 'GET')
	{
		$url = new Nette\Http\UrlScript($url ?: 'http://kdyby.org/');

		foreach ((array) $cookies as $key => $val) {
			$_COOKIE[$key] = $val;
		}

		$this->container->removeService('httpRequest');
		$this->container->addService('httpRequest', new Nette\Http\Request($url, NULL, $post, NULL, $cookies, $headers, $method));

		$router = $this->container->getService('router');
		unset($router[0]);
		$flags = $url->scheme === 'https' ? Nette\Application\Routers\Route::SECURED : 0;
		$router[] = new Nette\Application\Routers\Route('unit-tests/<presenter>/<action>', 'Mock:default', $flags);

		return new MockedFacebook(
			$this->config,
			$this->container->getByType('Kdyby\Facebook\SessionStorage'),
			$this->container->getByType('Kdyby\Facebook\ApiClient'),
			$this->container->getService('httpRequest'),
			new Nette\Http\Response()
		);
	}



	/**
	 * @param Kdyby\Facebook\Dialog $component
	 * @param string $name
	 * @return Kdyby\Facebook\Dialog
	 */
	protected function toPresenter(Kdyby\Facebook\Dialog $component, $name = 'facebook_dialog')
	{
		$presenter = $this->container->createInstance('KdybyTests\Facebook\PresenterMock');
		/** @var \KdybyTests\Facebook\PresenterMock $presenter */
		$this->container->callInjects($presenter);

		$query = $this->container->getService('httpRequest')->getQuery();
		$presenter->run(new Nette\Application\Request('Mock', 'GET', array('action' => 'default') + $query));

		$presenter->addComponent($component, $name);

		return $component;
	}

}
