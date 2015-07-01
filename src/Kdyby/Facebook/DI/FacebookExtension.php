<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\DI;

use Kdyby\Facebook\Api\CurlClient;
use Nette;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class FacebookExtension extends Nette\DI\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'appId' => NULL,
		'appSecret' => NULL,
		'verifyApiCalls' => TRUE,
		'fileUploadSupport' => FALSE,
		'trustForwarded' => FALSE,
		'clearAllWithLogout' => TRUE,
		'domains' => array(),
		'permissions' => array(),
		'canvasBaseUrl' => NULL,
		'graphVersion' => '',
		'curlOptions' => array(),
		'debugger' => '%debugMode%',
	);



	public function __construct()
	{
		$this->defaults['curlOptions'] = CurlClient::$defaultCurlOptions;
	}



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['appId'], 'string', 'Application ID');
		Validators::assert($config['appSecret'], 'string:32', 'Application secret');
		Validators::assert($config['fileUploadSupport'], 'bool', 'file upload support');
		Validators::assert($config['trustForwarded'], 'bool', 'trust forwarded');
		Validators::assert($config['clearAllWithLogout'], 'bool', 'clear the facebook session when user changes');
		Validators::assert($config['domains'], 'array', 'api domains');
		Validators::assert($config['permissions'], 'list', 'permissions scope');
		Validators::assert($config['canvasBaseUrl'], 'null|url', 'base url for canvas application');

		$configurator = $builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Facebook\Configuration')
			->setArguments(array($config['appId'], $config['appSecret']))
			->addSetup('$verifyApiCalls', array($config['verifyApiCalls']))
			->addSetup('$fileUploadSupport', array($config['fileUploadSupport']))
			->addSetup('$trustForwarded', array($config['trustForwarded']))
			->addSetup('$permissions', array($config['permissions']))
			->addSetup('$canvasBaseUrl', array($config['canvasBaseUrl']))
			->addSetup('$graphVersion', array($config['graphVersion']))
			->setInject(FALSE);

		if ($config['domains']) {
			$configurator->addSetup('$service->domains = ? + $service->domains', array($config['domains']));
		}

		$builder->addDefinition($this->prefix('session'))
			->setClass('Kdyby\Facebook\SessionStorage')
			->setInject(FALSE);

		foreach ($config['curlOptions'] as $option => $value) {
			if (defined($option)) {
				unset($config['curlOptions'][$option]);
				$config['curlOptions'][constant($option)] = $value;
			}
		}

		$apiClient = $builder->addDefinition($this->prefix('apiClient'))
			->setFactory('Kdyby\Facebook\Api\CurlClient')
			->setClass('Kdyby\Facebook\ApiClient')
			->addSetup('$service->curlOptions = ?;', array($config['curlOptions']))
			->setInject(FALSE);

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Facebook\Diagnostics\Panel')
				->setInject(FALSE);

			$apiClient->addSetup($this->prefix('@panel') . '::register', array('@self'));
		}

		$builder->addDefinition($this->prefix('synchronizeUserFacebook'))
			->setClass('Kdyby\Facebook\SynchronizeUserFacebook')
			->addTag('run')
			->setAutowired(FALSE)
			->setInject(FALSE);

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Facebook\Facebook')
			->setInject(FALSE);

		if ($config['clearAllWithLogout']) {
			$builder->getDefinition('user')
				->addSetup('$sl = ?; ?->onLoggedOut[] = function () use ($sl) { $sl->getService(?)->syncFacebookSession(); }', array(
					'@container', '@self', $this->prefix('synchronizeUserFacebook')
				));
		}
	}



	/**
	 * @param \Nette\Configurator $configurator
	 */
	public static function register(Nette\Configurator $configurator)
	{
		$configurator->onCompile[] = function ($config, Nette\DI\Compiler $compiler) {
			$compiler->addExtension('facebook', new FacebookExtension());
		};
	}

}
