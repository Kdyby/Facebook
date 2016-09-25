<?php

/**
 * Test: Kdyby\Facebook\Facebook.
 *
 * @testCase KdybyTests\Facebook\Facebook_v2_1Test
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Facebook
 */

namespace KdybyTests\Facebook;

use Kdyby;
use Kdyby\Facebook\FacebookApiException;
use KdybyTests;
use Nette;
use Nette\Application\Routers\Route;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class Facebook_v2_1Test extends FacebookTestCase
{

	protected function setUp()
	{
		$this->createContainer('config.kdyby.neon');
		$this->config->graphVersion = 'v2.1';
		$this->prepareTestUsers();
	}



	public function testGetLoginURL()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		$loginUrl = new Nette\Http\UrlScript($login->getUrl());

		Assert::same('https', $loginUrl->scheme);
		Assert::same('www.facebook.com', $loginUrl->host);
		Assert::same('/v2.1/dialog/oauth', $loginUrl->path);

		parse_str($loginUrl->query, $query);
		Assert::same($facebook->config->appId, $query['client_id']);
		Assert::same('http://kdyby.org/unit-tests/?do=facebook_dialog-response', $query['redirect_uri']);

		// we don't know what the state is, but we know it's an md5 and should be 32 characters long.
		Assert::same(32, strlen($query['state']));
	}



	public function testGetCodeWithValidCSRFState()
	{
		$this->session->state = md5(uniqid(mt_rand(), TRUE)); // $facebook->getSession()->establishCSRFTokenState();
		$code = md5(uniqid(mt_rand(), TRUE));

		$facebook = $this->createWithRequest(NULL, array(
			'code' => $code,
			'state' => $this->session->state
		));

		Assert::same($code, $facebook->publicGetCode());
	}



	public function testGetCodeWithInvalidCSRFState()
	{
		$this->session->state = md5(uniqid(mt_rand(), TRUE)); // $facebook->getSession()->establishCSRFTokenState();
		$code = md5(uniqid(mt_rand(), TRUE));

		$facebook = $this->createWithRequest(NULL, array(
			'code' => $code,
			'state' => $this->session->state . 'forgery!!!'
		));

		Assert::false($facebook->publicGetCode());
	}



	public function testGetCodeWithMissingCSRFState()
	{
		$code = md5(uniqid(mt_rand(), TRUE));

		$facebook = $this->createWithRequest(NULL, array(
			'code' => $code,
		));

		// intentionally don't set CSRF token at all
		Assert::false($facebook->publicGetCode());
	}



	public function testGetUserFromSignedRequest()
	{
		$facebook = $this->createWithRequest(NULL, array(
			'signed_request' => $this->kValidSignedRequest(),
		));

		Assert::same($this->testUser->id, $facebook->getUser());
	}



	public function testGetUserDetails()
	{
		/** @var Kdyby\Facebook\Facebook $facebook */
		$facebook = $this->container->getByType('Kdyby\Facebook\Facebook');
		$facebook->setAccessToken($this->testUser->access_token);

		Assert::same($this->testUser->id, $facebook->getUser());

		$profile = $facebook->getProfile();
		Assert::same('Filip Test Procházka', $profile->details['name']);

		Assert::true($profile->permissions['user_friends']);
		Assert::true($profile->permissions['public_profile']);
	}



	public function testSignedRequestRewrite()
	{
		$facebook = $this->createWithRequest(NULL, array(
			'signed_request' => $this->kValidSignedRequest($this->testUser->id, 'Hello sweetie'),
		));

		Assert::same($this->testUser->id, $facebook->getUser());
		Assert::same('Hello sweetie', $facebook->getAccessToken());

		$facebook->uncache();
		$facebook->setHttpRequest(NULL, array(
			'signed_request' => $this->kValidSignedRequest($this->testUser2->id, 'spoilers')
		));

		Assert::equal($this->testUser2->id, $facebook->getUser());

		$facebook->setHttpRequest(NULL, array('signed_request' => NULL));
		$facebook->uncacheSignedRequest();

		Assert::notEqual('Hello sweetie', $facebook->getAccessToken());
	}



	public function testGetSignedRequestFromCookie()
	{
		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getSignedRequestCookieName() => $this->kValidSignedRequest()
		));

		Assert::notSame(NULL, $facebook->getSignedRequest());
		Assert::same($this->testUser->id, $facebook->getUser());
	}



	public function testGetSignedRequestWithIncorrectSignature()
	{
		$bogusSignature = Kdyby\Facebook\SignedRequest::encode(array(
			'algorithm' => 'HMAC-SHA256',
		), 'bogus');

		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getSignedRequestCookieName() => $bogusSignature
		));

		Assert::null($facebook->getSignedRequest());
	}



	public function testAPIForLoggedOutUsers()
	{
		$facebook = $this->createWithRequest();

		$response = $facebook->api('/4');
		Assert::same('Mark Zuckerberg', $response['name']);
	}



	public function testAPIWithBogusAccessToken()
	{
		$facebook = $this->createWithRequest();

		// if we don't set an access token and there's no way to
		// get one, then the FQL query below works beautifully, handing
		// over Zuck's public data.  But if you specify a bogus access
		// token as I have right here, then the FQL query should fail.
		// We could return just Zuck's public data, but that wouldn't
		// advertise the issue that the access token is at worst broken
		// and at best expired.
		$facebook->setAccessToken('this-is-not-really-an-access-token');

		try {
			$facebook->api('/4');

			Assert::fail('Should not get here.');

		} catch (FacebookApiException $e) {
			Assert::same(190, $e->getCode());
		}
	}



	public function testGraphAPIWithBogusAccessToken()
	{
		$facebook = $this->createWithRequest();
		$facebook->setAccessToken('this-is-not-really-an-access-token');

		try {
			$facebook->api('/me');
			Assert::fail('Should not get here.');

		} catch (FacebookApiException $e) {
			// means the server got the access token and didn't like it
			Assert::same('OAuthException: 190: Invalid OAuth access token.', (string) $e);
		}
	}



	public function testCurlFailure()
	{
		$facebook = $this->createWithRequest();

		if (!defined('CURLOPT_TIMEOUT_MS')) {
			// can't test it if we don't have millisecond timeouts
			return;
		}

		$exception = NULL;
		try {
			// we dont expect facebook will ever return in 1ms

			$this->apiClient->curlOptions[CURLOPT_TIMEOUT_MS] = 50;
			$facebook->api('/' . $this->testUser->id);

		} catch (FacebookApiException $e) {
			$exception = $e;
		}

		unset($this->apiClient->curlOptions[CURLOPT_TIMEOUT_MS]);
		if (!$exception) {
			Assert::fail('no exception was thrown on timeout.');
		}

		$code = $exception->getCode();
		if ($code != CURLE_OPERATION_TIMEOUTED && $code != CURLE_COULDNT_CONNECT) {
			Assert::fail("Expected curl CURLE_OPERATION_TIMEOUTED or CURLE_COULDNT_CONNECT but got: $code");
		}

		Assert::same('CurlException', $exception->getType());
	}



	public function testGraphAPIWithOnlyParams()
	{
		$facebook = $this->createWithRequest();

		$response = $facebook->api('/' . $this->testUser->id);
		Assert::true(isset($response['id'])); // User ID should be public.
		Assert::true(isset($response['name'])); // User's name should be public.
		Assert::true(isset($response['first_name'])); // User's first name should be public.
		Assert::true(isset($response['last_name'])); // User's last name should be public.
		Assert::false(isset($response['work'])); // User's work history should only be available with a valid access token.
		Assert::false(isset($response['education'])); // User's education history should only be available with a valid access token.
		Assert::false(isset($response['verified'])); // User's verification status should only be available with a valid access token.
	}



	public function testBundledCACert()
	{
		$facebook = $this->createWithRequest();

		// use the bundled cert from the start
		$this->apiClient->curlOptions[CURLOPT_CAINFO] = \Composer\CaBundle\CaBundle::getBundledCaBundlePath();
		$response = $facebook->api('/' . $this->testUser2->id);
		Assert::same((string) $this->testUser2->id, $response['id']); // should get expected id.
	}



	public function testValidCodeToToken()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedCode = $code = 'code1';
		$facebook->forcedAccessTokenFromCode_Map[json_encode(array($code))] = $access_token = 'at1';

		Assert::same($access_token, $facebook->getAccessToken());
	}



	public function testSignedRequestWithoutAuthClearsDataInAvailData()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedSignedRequest = array('foo' => 1);
		$sessionStorage = $facebook->mockSessionStorage();

		Assert::false($sessionStorage->clearCalled);
		Assert::same(0, $facebook->getUser());
		Assert::true($sessionStorage->clearCalled);
	}



	public function testFailedToGetUserFromAccessTokenClearsData()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedAccessToken = 'at1';
		$sessionStorage = $facebook->mockSessionStorage();

		Assert::false($sessionStorage->clearCalled);
		Assert::false($facebook->getUserFromAccessTokenCalled);
		Assert::same(0, $facebook->getUser());
		Assert::true($sessionStorage->clearCalled);
		Assert::true($facebook->getUserFromAccessTokenCalled);
	}



	public function testAppsecretProofNoParams()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();

		$token = $facebook->getAccessToken();
		$proof = $facebook->config->getAppSecretProof($token);

		$facebook->api('/' . $this->testUser->id);
		Assert::same($proof, $apiClient->calls[0][1]['appsecret_proof']);
	}



	public function testAppsecretProofWithParams()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();

		$proof = 'foo';
		$facebook->api('/' . $this->testUser->id, array('appsecret_proof' => $proof));

		Assert::same($proof, $apiClient->calls[0][1]['appsecret_proof']);
	}



	private function kValidSignedRequest($id = NULL, $oauth_token = NULL)
	{
		return Kdyby\Facebook\SignedRequest::encode(array(
			'user_id' => $id ?: $this->testUser->id,
			'oauth_token' => $oauth_token
		), $this->config->appSecret);
	}

}

KdybyTests\run(new Facebook_v2_1Test());
