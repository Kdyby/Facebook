<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\DI;

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
		'fileUploadSupport' => FALSE,
		'trustForwarded' => FALSE,
		'domains' => array(),
		'permissions' => array(),
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();

		$config = $this->getConfig($this->defaults);
		Validators::assert($config['appId'], 'string', 'Application ID');
		Validators::assert($config['appSecret'], 'string:32', "Application secret");
		Validators::assert($config['fileUploadSupport'], 'bool', "file upload support");
		Validators::assert($config['trustForwarded'], 'bool', "trust forwarded");
		Validators::assert($config['domains'], 'array', "api domains");
		Validators::assert($config['permissions'], 'list', "permissions scope");

		$configurator = $builder->addDefinition($this->prefix('config'))
			->setClass('Kdyby\Facebook\Configuration')
			->setArguments(array($config['appId'], $config['appSecret']))
			->addSetup('$fileUploadSupport', array($config['fileUploadSupport']))
			->addSetup('$trustForwarded', array($config['trustForwarded']))
			->addSetup('$permissions', array($config['permissions']))
			->setInject(FALSE);

		if ($config['domains']) {
			$configurator->addSetup('$service->domains = ? + $service->domains', array($config['domains']));
		}

		$builder->addDefinition($this->prefix('session'))
			->setClass('Kdyby\Facebook\SessionStorage')
			->setInject(FALSE);

		$apiClient = $builder->addDefinition($this->prefix('apiClient'))
			->setFactory('Kdyby\Facebook\Api\CurlClient')
			->setClass('Kdyby\Facebook\ApiClient')
			->setInject(FALSE);

		if ($builder->parameters['debugMode']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Facebook\Diagnostics\Panel')
				->setInject(FALSE);

			$apiClient->addSetup($this->prefix('@panel') . '::register', array('@self'));
		}

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Facebook\Facebook')
			->setInject(FALSE);
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
