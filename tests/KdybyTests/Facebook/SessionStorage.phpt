<?php

/**
 * Test: Kdyby\Facebook\Facebook.
 *
 * @testCase KdybyTests\Facebook\SessionStorageTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Facebook
 */

namespace KdybyTests\Facebook;

use Kdyby;
use KdybyTests;
use Nette;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SessionStorageTest extends KdybyTests\Facebook\FacebookTestCase
{

	/**
	 * @var \Nette\Http\SessionSection
	 */
	private $rawSection;



	protected function setUp()
	{
		$this->createContainer();
		$this->rawSection = $this->container->getByType('Nette\Http\Session')
			->getSection('Facebook/' . $this->config->getApplicationAccessToken());
	}



	public function testSessionBackedFacebook()
	{
		$this->session->state = $val = 'foo';
		Assert::same($val, $this->rawSection['state']);
		Assert::same($val, $this->session->state);
	}

	public function testSessionBackedFacebookIgnoresUnsupportedKey()
	{
		$key = '--invalid--';
		$this->session->{$key} = $val = 'foo';

		Assert::false(isset($this->rawSection[$key]));
		Assert::false($this->session->{$key});
	}



	public function testClearSessionBackedFacebook()
	{
		$this->session->state = $val = 'foo';
		Assert::same($val, $this->rawSection['state']);
		Assert::same($val, $this->session->state);

		$this->session->clearAll();
		Assert::false(isset($this->rawSection['state']));
		Assert::false($this->session->state);
	}



	public function testSessionBackedFacebookIgnoresUnsupportedKeyInClear()
	{
		$this->rawSection[$key = '--invalid--'] = $val = 'foo';

		$this->session->clear($key);
		Assert::true(isset($this->rawSection[$key]));
		Assert::false($this->session->{$key});
	}



	public function testClearAllSessionBackedFacebook()
	{
		$this->session->state = $val = 'foo';
		Assert::same($val, $this->rawSection['state']);
		Assert::same($val, $this->session->state);

		$this->session->clearAll();

		Assert::false(isset($this->rawSection['state']));
		Assert::false($this->session->state);
	}



	public function testLoginLogout()
	{
		Assert::false($this->session->checkLogout());
		$this->session->login();
		Assert::true($this->session->checkLogout());
		Assert::false($this->session->checkLogout());
	}

}

KdybyTests\run(new SessionStorageTest());
