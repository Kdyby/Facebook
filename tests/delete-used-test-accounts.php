<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */


require_once __DIR__ . '/../vendor/autoload.php';


$config = new Nette\Configurator();
$config->setTempDirectory(__DIR__ . '/tmp');
Kdyby\Facebook\DI\FacebookExtension::register($config);
$config->addConfig(__DIR__ . '/KdybyTests/Facebook/files/config.kdyby.neon');
$config->addConfig(__DIR__ . '/KdybyTests/nette-reset.neon');

/** @var \Nette\DI\Container $container */
$container = $config->createContainer();

/** @var Kdyby\Facebook\Facebook $fb */
$fb = $container->getByType('Kdyby\Facebook\Facebook');

foreach ($userJsonFiles = glob(__DIR__ . '/tmp/test-user-*.json') as $userJsonFile) {
	$users = json_decode(file_get_contents($userJsonFile));

	foreach ($users as $user) {
		echo "Deleting test user ", $user->id, " from ", basename($userJsonFile), " ... ";
		try {
			$result = $fb->api('/' . $user->id, 'DELETE', ['access_token' => $user->access_token]);
			echo json_encode((array) $result), "\n";

		} catch (\Kdyby\Facebook\FacebookApiException $e) {
			echo $e->getMessage(), ": ", json_encode((array) $e->getResult()), "\n";
		}
	}
}

foreach ($userJsonFiles as $userJsonFile) {
	@unlink($userJsonFile);
}
