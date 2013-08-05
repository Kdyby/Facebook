<?php

/**
 * Test: Kdyby\Facebook\Facebook.
 *
 * @testCase KdybyTests\Facebook\FacebookTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Facebook
 */

namespace KdybyTests\Facebook;

use Kdyby;
use Kdyby\Facebook\FacebookApiException;
use Kdyby\Facebook\Resource\ResourceLoader;
use KdybyTests;
use Nette;
use Nette\Application\Routers\Route;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class FacebookTest extends KdybyTests\FacebookTestCase
{
	const TEST_USER = 499834690;
	const TEST_USER_2 = 499835484;
	const EXPIRED_ACCESS_TOKEN = 'AAABrFmeaJjgBAIshbq5ZBqZBICsmveZCZBi6O4w9HSTkFI73VMtmkL9jLuWsZBZC9QMHvJFtSulZAqonZBRIByzGooCZC8DWr0t1M4BL9FARdQwPWPnIqCiFQ';



	protected function setUp()
	{
		$this->createContainer();
	}



	public function testSetAccessToken()
	{
		$this->facebook->setAccessToken('saltydog');
		Assert::same('saltydog', $this->facebook->getAccessToken());
	}



	public function testGetCurrentURL()
	{
		// fake the HPHP $_SERVER globals
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests.php?one=one&two=two&three=three');
		Assert::equal('http://kdyby.org/unit-tests.php?one=one&two=two&three=three', (string) $facebook->getCurrentUrl());

		// ensure structure of valueless GET params is retained (sometimes
		// an = sign was present, and sometimes it was not)
		// first test when equal signs are present
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests.php?one=&two=&three=');
		Assert::equal('http://kdyby.org/unit-tests.php?one=&two=&three=', (string) $facebook->getCurrentUrl());

		// now confirm that getCurrentUrl function is changing the current URL
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests.php?one&two&three');
		Assert::equal('http://kdyby.org/unit-tests.php?one=&two=&three=', (string) $facebook->getCurrentUrl());
	}



	public function testGetLoginURL()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		$loginUrl = new Nette\Http\UrlScript($login->getUrl());

		Assert::same('https', $loginUrl->scheme);
		Assert::same('www.facebook.com', $loginUrl->host);
		Assert::same('/dialog/oauth', $loginUrl->path);

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

		Assert::same(self::TEST_USER, $facebook->getUser());
	}



	public function testSignedRequestRewrite()
	{
		$facebook = $this->createWithRequest(NULL, array(
			'signed_request' => $this->kValidSignedRequest(self::TEST_USER, 'Hello sweetie'),
		));

		Assert::same(self::TEST_USER, $facebook->getUser());
		Assert::same('Hello sweetie', $facebook->getAccessToken());

		$facebook->uncache();
		$facebook->setHttpRequest(NULL, array(
			'signed_request' => $this->kValidSignedRequest(self::TEST_USER_2, 'spoilers')
		));

		Assert::equal(self::TEST_USER_2, $facebook->getUser());

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
		Assert::same(self::TEST_USER, $facebook->getUser());
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



	public function testNonUserAccessToken()
	{
		$facebook = $this->createWithRequest();

		// no cookies, and no request params, so no user or code,
		// so no user access token (even with cookie support)
		Assert::equal($this->config->getApplicationAccessToken(), $facebook->getAccessToken());
	}



	public function testMissingMetadataCookie()
	{
		$facebook = $this->createWithRequest();

		Assert::same(array(), $facebook->publicGetMetadataCookie());
	}



	public function testEmptyMetadataCookie()
	{
		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getMetadataCookieName() => ''
		));

		Assert::same(array(), $facebook->publicGetMetadataCookie());
	}



	public function testMetadataCookie()
	{
		$key = 'foo';
		$val = '42';

		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getMetadataCookieName() => "$key=$val"
		));

		Assert::same(array($key => $val), $facebook->publicGetMetadataCookie());
	}



	public function testQuotedMetadataCookie()
	{
		$key = 'foo';
		$val = '42';

		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getMetadataCookieName() => "\"$key=$val\""
		));

		Assert::same(array($key => $val), $facebook->publicGetMetadataCookie());
	}



	public function testAPIForLoggedOutUsers()
	{
		$facebook = $this->createWithRequest();

		$response = $facebook->api(array(
			'method' => 'fql.query',
			'query' => 'SELECT name FROM user WHERE uid=4',
		));

		Assert::equal(1, count($response));
		Assert::equal($response[0]['name'], 'Mark Zuckerberg');
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
			$facebook->api(array(
				'method' => 'fql.query',
				'query' => 'SELECT name FROM profile WHERE id=4',
			));

			Assert::fail('Should not get here.');

		} catch (FacebookApiException $e) {
			$result = $e->getResult();
			Assert::true(is_array($result), 'expect a result object');
			Assert::same('190', $result['error_code']);
		}
	}



	public function testAPIGraphPublicData()
	{
		$facebook = $this->createWithRequest();

		$response = $facebook->api('/jerry');
		Assert::same($response['id'], '214707');
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



	public function testGraphAPIWithExpiredAccessToken()
	{
		$facebook = $this->createWithRequest();
		$facebook->setAccessToken(self::EXPIRED_ACCESS_TOKEN);

		try {
			$facebook->api('/me');
			Assert::fail('Should not get here.');

		} catch (FacebookApiException $e) {
			// means the server got the access token and didn't like it
			Assert::match('OAuthException: 190: Error validating access token: %a%', (string) $e);
		}
	}



	public function testIteratePublicData()
	{
		$facebook = $this->createWithRequest();

		$posts = $facebook->iterate('/jerry/posts');
		Assert::true($posts instanceof ResourceLoader);
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
			$facebook->api('/naitik');

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

		$response = $facebook->api('/jerry');
		Assert::true(isset($response['id'])); // User ID should be public.
		Assert::true(isset($response['name'])); // User's name should be public.
		Assert::true(isset($response['first_name'])); // User's first name should be public.
		Assert::true(isset($response['last_name'])); // User's last name should be public.
		Assert::false(isset($response['work'])); // User's work history should only be available with a valid access token.
		Assert::false(isset($response['education'])); // User's education history should only be available with a valid access token.
		Assert::false(isset($response['verified'])); // User's verification status should only be available with a valid access token.
	}



	public function testLoginURLDefaults()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('http://kdyby.org/unit-tests')) !== FALSE);
	}



	public function testLoginURLDefaultsDropStateQueryParam()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?state=xx42xx');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('http://kdyby.org/unit-tests/')) !== FALSE);
		Assert::false(strpos($login->getUrl(), 'xx42xx'));
	}



	public function testLoginURLDefaultsDropCodeQueryParam()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?code=xx42xx');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('http://kdyby.org/unit-tests/')) !== FALSE);
		Assert::false(strpos($login->getUrl(), 'xx42xx'));
	}



	public function testLoginURLDefaultsDropSignedRequestParamButNotOthers()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?signed_request=xx42xx&do_not_drop=xx43xx');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('http://kdyby.org/unit-tests/')) !== FALSE);
		Assert::false(strpos($login->getUrl(), 'xx42xx'));
		Assert::true(strpos($login->getUrl(), 'xx43xx') !== FALSE);
	}



	public function testLogoutURLDefaults()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/');
		$logout = $this->toPresenter($facebook->createDialog('logout'));

		Assert::true(strpos($logout->getUrl(), rawurlencode('http://kdyby.org/unit-tests/')) !== FALSE);
		Assert::false(strpos($logout->getUrl(), $this->config->appSecret));
	}



	public function testLoginStatusURLDefaults()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/');
		$status = $this->toPresenter($facebook->createDialog('status'));

		Assert::true(strpos($status->getUrl(), rawurlencode('http://kdyby.org/unit-tests/')) !== FALSE);
	}



	public function testNonDefaultPort()
	{
		$facebook = $this->createWithRequest('http://kdyby.org:8080/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('http://kdyby.org:8080/unit-tests/')) !== FALSE);
	}



	public function testSecureCurrentUrl()
	{
		$facebook = $this->createWithRequest('https://kdyby.org/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('https://kdyby.org/unit-tests/')) !== FALSE);
	}



	public function testSecureCurrentUrlWithNonDefaultPort()
	{
		$facebook = $this->createWithRequest('https://kdyby.org:8080/unit-tests/');
		$login = $this->toPresenter($facebook->createDialog('login'));

		Assert::true(strpos($login->getUrl(), rawurlencode('https://kdyby.org:8080/unit-tests/')) !== FALSE);
	}



	public function testSignedToken()
	{
		$facebook = $this->createWithRequest();

		$payload = Kdyby\Facebook\SignedRequest::decode(self::kValidSignedRequest(), $facebook->config->appSecret);
		Assert::true(!empty($payload));
		Assert::null($facebook->getSignedRequest());

		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?signed_request=' . rawurlencode(self::kValidSignedRequest()));
		Assert::same($payload, $facebook->getSignedRequest());
	}



	public function testNonTossedSignedToken()
	{
		$facebook = $this->createWithRequest();

		$tossedSignedRequest = Kdyby\Facebook\SignedRequest::encode(array(), $this->config->appSecret);

		$payload = Kdyby\Facebook\SignedRequest::decode($tossedSignedRequest, $facebook->config->appSecret);
		Assert::true(!empty($payload));
		Assert::null($facebook->getSignedRequest());

		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?signed_request=' . rawurlencode($tossedSignedRequest));
		$rq = $facebook->getSignedRequest();
		Assert::true(isset($rq['algorithm']));
	}



	public function testSignedRequestWithEmptyValue()
	{
		$signedRequestWithEmptyValue = '';

		$facebook = $this->createWithRequest($url = 'http://kdyby.org/unit-tests/?signed_request=' . rawurlencode($signedRequestWithEmptyValue));
		Assert::null($facebook->getSignedRequest());

		$facebook = $this->createWithRequest($url, NULL, array($facebook->config->getSignedRequestCookieName() => $signedRequestWithEmptyValue));
		Assert::null($facebook->getSignedRequest());
	}



	public function testBundledCACert()
	{
		$facebook = $this->createWithRequest();

		// use the bundled cert from the start
		$this->apiClient->curlOptions[CURLOPT_CAINFO] = __DIR__ . '/../../../src/Kdyby/Facebook/Api/fb_ca_chain_bundle.crt';
		$response = $facebook->api('/naitik');
		Assert::same($response['id'], '5526183'); // should get expected id.
	}



	public function testVideoUpload()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = '{}';

		$facebook->api(array('method' => 'video.upload'));
		Assert::contains('//api-video.', (string) $apiClient->calls[0][0]);
	}



	public function testVideoUploadGraph()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = '{}';

		$facebook->api('/me/videos', 'POST');
		Assert::contains('//graph-video.', (string) $apiClient->calls[0][0]);
	}



	public function testGetUserAndAccessTokenFromSession()
	{
		$facebook = $this->createWithRequest();
		$this->session->access_token = self::EXPIRED_ACCESS_TOKEN;
		$this->session->user_id = 12345;

		Assert::same(self::EXPIRED_ACCESS_TOKEN, $facebook->getAccessToken());
		Assert::same(12345, $facebook->getUser());
	}



	public function testGetUserAndAccessTokenFromSignedRequestNotSession()
	{
		$facebook = $this->createWithRequest('http://kdyby.org/unit-tests/?signed_request=' . rawurlencode(self::kValidSignedRequest()));
		$this->session->access_token = self::EXPIRED_ACCESS_TOKEN;
		$this->session->user_id = 41572;

		Assert::notEqual(41572, $facebook->getUser());
		Assert::equal(499834690, $facebook->getUser());

		Assert::notEqual(self::EXPIRED_ACCESS_TOKEN, $accessToken = $facebook->getAccessToken());
		Assert::true(!empty($accessToken));
	}



	public function testGetUserWithoutCodeOrSignedRequestOrSession()
	{
		$facebook = $this->createWithRequest();

		$userId = $facebook->getUser();
		Assert::true(empty($userId));
	}



	public function testGetAccessTokenUsingCodeInJsSdkCookie()
	{
		$facebook = $this->createWithRequest();

		$facebook->forcedSignedRequest = array('code' => $code = 'code1');
		$facebook->forcedAccessTokenFromCode_Map[json_encode(array($code, ''))] = $access_token = 'at1';

		Assert::same($access_token, $facebook->getAccessToken());
	}



	public function testSignedRequestWithoutAuthClearsData()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedSignedRequest = array('foo' => 1);
		$sessionStorage = $facebook->mockSessionStorage();

		Assert::false($sessionStorage->clearCalled);
		Assert::same($this->config->getApplicationAccessToken(), $facebook->getAccessToken());
		Assert::true($sessionStorage->clearCalled);
	}



	public function testInvalidCodeInSignedRequestWillClearData()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedSignedRequest = array('code' => 'code1');
		$facebook->forcedAccessTokenFromCode = NULL;
		$sessionStorage = $facebook->mockSessionStorage();

		Assert::false($sessionStorage->clearCalled);
		Assert::same($this->config->getApplicationAccessToken(), $facebook->getAccessToken());
		Assert::true($sessionStorage->clearCalled);
	}



	public function testInvalidCodeWillClearData()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedCode = 'code1';
		$facebook->forcedAccessTokenFromCode = NULL;
		$sessionStorage = $facebook->mockSessionStorage();

		Assert::false($sessionStorage->clearCalled);
		Assert::same($this->config->getApplicationAccessToken(), $facebook->getAccessToken());
		Assert::true($sessionStorage->clearCalled);
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



	public function testUserFromAccessTokenIsStored()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedAccessToken = 'at1';
		$facebook->forcedUserFromAccessToken = $user = 42;

		Assert::same(FALSE, $this->session->user_id);
		Assert::same($user, $facebook->getUser());
		Assert::same(42, $this->session->user_id);
	}



	public function testUserFromAccessTokenPullsID()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedAccessToken = 'at1';

		$apiClient = $facebook->mockApiClient();
		$apiClient->response = json_encode(array('id' => $user = 42));

		Assert::same($user, $facebook->getUser());
	}



	public function testUserFromAccessTokenResetsOnApiException()
	{
		$facebook = $this->createWithRequest();
		$facebook->forcedAccessToken = 'at1';
		$sessionStorage = $facebook->mockSessionStorage();

		$apiClient = $facebook->mockApiClient();
		$apiClient->throw = new FacebookApiException(array());

		Assert::false($sessionStorage->clearCalled);
		Assert::same(0, $facebook->getUser());
		Assert::true($sessionStorage->clearCalled);
	}



	public function testEmptyCodeReturnsFalse()
	{
		$facebook = $this->createWithRequest();

		Assert::false($facebook->publicGetAccessTokenFromCode(''));
		Assert::false($facebook->publicGetAccessTokenFromCode(NULL));
		Assert::false($facebook->publicGetAccessTokenFromCode(FALSE));
	}



	public function testNullRedirectURIUsesCurrentURL()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();

		$access_token = 'at1';
		$apiClient->response = "access_token=$access_token";

		Assert::false($facebook->getCurrentUrlCalled);
		Assert::same($access_token, $facebook->publicGetAccessTokenFromCode('c'));
		Assert::true($facebook->getCurrentUrlCalled);
	}



	public function testNullRedirectURIAllowsEmptyStringForCookie()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();

		$access_token = 'at1';
		$apiClient->response = "access_token=$access_token";

		Assert::false($facebook->getCurrentUrlCalled);
		Assert::same($access_token, $facebook->publicGetAccessTokenFromCode('c', ''));
		Assert::false($facebook->getCurrentUrlCalled);
	}



	public function testAPIExceptionDuringCodeExchangeIsIgnored()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->throw = new FacebookApiException(array());

		Assert::false($facebook->publicGetAccessTokenFromCode('c', ''));
	}



	public function testEmptyResponseInCodeExchangeIsIgnored()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = '';

		Assert::false($facebook->publicGetAccessTokenFromCode('c', ''));
	}



	public function testMissingAccessTokenInCodeExchangeIsIgnored()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = 'foo=1';

		Assert::false($facebook->publicGetAccessTokenFromCode('c', ''));
	}



	public function testDestroyClearsCookie()
	{
		$facebook = $this->createWithRequest(NULL, NULL, array(
			$this->config->getSignedRequestCookieName() => 'foo',
			$this->config->getMetadataCookieName() => 'base_domain=kdyby.org',
		));

		Assert::true(array_key_exists($this->config->getSignedRequestCookieName(), $_COOKIE));
		$facebook->destroySession();
		Assert::false(array_key_exists($this->config->getSignedRequestCookieName(), $_COOKIE));
	}



	public function testAuthExpireSessionDestroysSession()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = json_encode($result = array('foo' => 42));

		Assert::false($facebook->destroySessionCalled);
		Assert::same($result, (array) $facebook->api(array('method' => 'auth.expireSession')));
		Assert::true($facebook->destroySessionCalled);
	}



	public function testLowercaseAuthRevokeAuthDestroysSession()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = json_encode($result = array('foo' => 42));

		Assert::false($facebook->destroySessionCalled);
		Assert::same($result, (array) $facebook->api(array('method' => 'auth.revokeauthorization')));
		Assert::true($facebook->destroySessionCalled);
	}



	/**
	 * @expectedException FacebookAPIException
	 */
	public function testErrorCodeFromRestAPIThrowsException()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();
		$apiClient->response = json_encode($result = array('error_code' => 500));

		Assert::exception(function () use ($facebook) {
			$facebook->api(array('method' => 'foo'));
		}, 'Kdyby\Facebook\FacebookApiException');
	}



	public function testJsonEncodeOfNonStringParams()
	{
		$facebook = $this->createWithRequest();
		$apiClient = $facebook->mockApiClient();

		$facebook->api('/naitik', $params = array(
			'method' => 'get',
			'foo' => $foo = array(1, 2),
		));

		Assert::same(json_encode($foo), $apiClient->calls[0][1]['foo']);
	}



	private function kValidSignedRequest($id = self::TEST_USER, $oauth_token = NULL)
	{
		return Kdyby\Facebook\SignedRequest::encode(array(
			'user_id' => $id,
			'oauth_token' => $oauth_token
		), $this->config->appSecret);
	}

}

KdybyTests\run(new FacebookTest());
