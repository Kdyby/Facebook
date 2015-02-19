<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\Dialog;

use Kdyby\Facebook;
use Nette;
use Nette\Application\UI\PresenterComponent;
use Nette\Http\UrlScript;
use Nette\Utils\Html;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property Facebook\Facebook $facebook
 * @method onResponse(AbstractDialog $dialog)
 */
abstract class AbstractDialog extends PresenterComponent implements Facebook\Dialog
{

	/**
	 * @var array of function(AbstractDialog $dialog)
	 */
	public $onResponse = array();

	/**
	 * @var Facebook\Facebook
	 */
	protected $facebook;

	/**
	 * @var Facebook\Configuration
	 */
	protected $config;

	/**
	 * Display mode in which to render the Dialog.
	 * @var string
	 */
	protected $display;

	/**
	 * @var bool
	 */
	protected $showError;

	/**
	 * @var UrlScript
	 */
	protected $currentUrl;



	/**
	 * @param Facebook\Facebook $facebook
	 */
	public function __construct(Facebook\Facebook $facebook)
	{
		$this->facebook = $facebook;
		$this->config = $facebook->config;
		$this->currentUrl = $facebook->getCurrentUrl();

		$this->monitor('Nette\Application\UI\Presenter');
		parent::__construct();
	}



	/**
	 * @return Facebook\Facebook
	 */
	public function getFacebook()
	{
		return $this->facebook;
	}



	/**
	 * @param \Nette\ComponentModel\Container $obj
	 */
	protected function attached($obj)
	{
		parent::attached($obj);

		if ($obj instanceof Nette\Application\UI\Presenter) {
			$this->currentUrl = new UrlScript($this->link('//response!'));
		}
	}



	/**
	 * Facebook get's the url for this handle when redirecting to login dialog.
	 * It automatically calls the onResponse event.
	 */
	public function handleResponse()
	{
		$this->onResponse($this);

		if (!empty($this->config->canvasBaseUrl)) {
			$this->presenter->redirectUrl($this->config->canvasBaseUrl);
		}

		$this->presenter->redirect('this', array('state' => NULL, 'code' => NULL));
	}



	/**
	 * @return array
	 */
	public function getQueryParams()
	{
		$data = array(
			'client_id' => $this->facebook->config->appId,
			'redirect_uri' => (string)$this->currentUrl,
			'show_error' => $this->showError
		);

		if ($this->display !== NULL) {
			$data['display'] = $this->display;
		}

		return $data;
	}



	/**
	 * @param string $display
	 * @param bool $showError
	 *
	 * @return string
	 */
	public function getUrl($display = NULL, $showError = FALSE)
	{
		$url = clone $this->currentUrl;

		$this->display = $display;
		$this->showError = $showError;

		$url->appendQuery($this->getQueryParams());
		return (string)$url;
	}



	/**
	 * @throws \Nette\Application\AbortException
	 */
	public function open()
	{
		$this->presenter->redirectUrl($this->getUrl());
	}



	/**
	 * Opens the dialog.
	 */
	public function handleOpen()
	{
		$this->open();
	}



	/**
	 * @param string $display
	 * @param bool $showError
	 * @return Html
	 */
	public function getControl($display = NULL, $showError = FALSE)
	{
		return Html::el('a')->href($this->getUrl($display, $showError));
	}

}
