<?php

/**
 * Test: Kdyby\Facebook\Configuration format methods.
 *
 * @testCase KdybyTests\Facebook\ConfigurationTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Facebook
 */

namespace KdybyTests\Facebook;

use Kdyby\Facebook\Configuration;
use KdybyTests;
use Tester\TestCase;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ConfigurationTest extends TestCase
{

	/**
	 * @var Configuration
	 */
	private $config;



	protected function setUp()
	{
		$this->config = new Configuration('123456', '*something-really-secret*');
	}



	public function testReadingConfiguration()
	{
		Assert::equal('fbsr_123456', $this->config->getSignedRequestCookieName());
		Assert::equal('fbm_123456', $this->config->getMetadataCookieName());
		Assert::equal('123456|*something-really-secret*', $this->config->getApplicationAccessToken());
	}



	public function testAppSecretProof() 
	{
		Assert::equal('e87be160723b0a7d7b3558cad3c3e869c27f164b71119deace137fb592aa1f19', $this->config->getAppSecretProof("token"));
	}



	public function testCreateUrl()
	{
		Assert::equal('https://api.facebook.com/me?feed=me', (string) $this->config->createUrl('api', 'me', array('feed' => 'me')));
		Assert::equal('https://api.facebook.com/restserver.php', (string) $this->config->getApiUrl('api'));
	}



	public function testPassingEntireGraphUrl()
	{
		Assert::equal(
			'https://graph.facebook.com/me/albums?limit=25&after=MTAxNTExOTQ1MjAwNzI5NDE=',
			(string) $this->config->createUrl('graph', 'https://graph.facebook.com/me/albums?limit=25&after=MTAxNTExOTQ1MjAwNzI5NDE=')
		);
	}

}

KdybyTests\run(new ConfigurationTest());
