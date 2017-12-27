<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook;

use Nette;
use Tracy\Debugger;
use Tracy\Dumper;
use Nette\Utils\Json;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SignedRequest
{
    use Nette\SmartObject;

	/**
	 * Parses a signed_request and validates the signature.
	 *
	 * @param string $signedRequest A signed token
	 * @param string $appSecret
	 *
	 * @return array The payload inside it or null if the sig is wrong
	 */
	public static function decode($signedRequest, $appSecret)
	{
		if (!$signedRequest || strpos($signedRequest, '.') === false) {
			Debugger::log('Signed request is invalid! ' . json_encode($signedRequest), 'facebook');
			return NULL;
		}

		list($encoded_sig, $payload) = explode('.', $signedRequest, 2);

		// decode the data
		$sig = Helpers::base64UrlDecode($encoded_sig);
		$data = Json::decode(Helpers::base64UrlDecode($payload), Json::FORCE_ARRAY);

		if (!isset($data['algorithm']) || strtoupper($data['algorithm']) !== Configuration::SIGNED_REQUEST_ALGORITHM) {
			Debugger::log("Unknown algorithm '{$data['algorithm']}', expected " . Configuration::SIGNED_REQUEST_ALGORITHM, 'facebook');
			return NULL;
		}

		// check sig
		$expected_sig = hash_hmac('sha256', $payload, $appSecret, $raw = TRUE);
		if (strlen($expected_sig) !== strlen($sig)) {
			Debugger::log('Bad Signed JSON signature! Expected ' . Dumper::toText($expected_sig) . ', but given ' . Dumper::toText($sig), 'facebook');
			return NULL;
		}

		$result = 0;
		for ($i = 0; $i < strlen($expected_sig); $i++) {
			$result |= ord($expected_sig[$i]) ^ ord($sig[$i]);
		}

		if ($result !== 0) {
			Debugger::log('Bad Signed JSON signature! Expected ' . Dumper::toText($expected_sig) . ', but given ' . Dumper::toText($sig), 'facebook');
			return NULL;
		}

		return $data;
	}



	/**
	 * Makes a signed_request blob using the given data.
	 *
	 * @param array $data The data array.
	 * @param string $appSecret
	 * @throws InvalidArgumentException
	 * @return string The signed request.
	 */
	public static function encode($data, $appSecret)
	{
		if (!is_array($data)) {
			throw new InvalidArgumentException(__METHOD__ . ' expects an array, but given ' . print_r($data, TRUE));
		}

		$data['algorithm'] = Configuration::SIGNED_REQUEST_ALGORITHM;
		$data['issued_at'] = time();

		$b64 = Helpers::base64UrlEncode(Json::encode($data));
		$raw_sig = hash_hmac('sha256', $b64, $appSecret, $raw = TRUE);
		$sig = Helpers::base64UrlEncode($raw_sig);

		return $sig . '.' . $b64;
	}

}
