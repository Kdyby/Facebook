<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Facebook\Diagnostics;

use Kdyby\Facebook;
use Kdyby\Facebook\Api\CurlClient;
use Nette;
use Nette\Utils\Html;
use Tracy\Debugger;
use Tracy\IBarPanel;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 * @property callable $begin
 * @property callable $failure
 * @property callable $success
 */
class Panel extends Nette\Object implements IBarPanel
{

	/**
	 * @var int logged time
	 */
	private $totalTime = 0;

	/**
	 * @var array
	 */
	private $calls = array();

	/**
	 * @var \stdClass
	 */
	private $current;



	/**
	 * @return string
	 */
	public function getTab()
	{
		$logo = Html::el()->setHtml(file_get_contents(__DIR__ . '/logo.svg'));
		$tab = Html::el()->addHtml($logo);
		$title = Html::el('span', ['class' => 'tracy-label'])->title('Facebook');

		if ($this->calls) {
			$title->setText(
				count($this->calls) . ' call' . (count($this->calls) > 1 ? 's' : '') .
				' / ' . sprintf('%0.2f', $this->totalTime) . ' s'
			);

		} else {
			$title->setText('Facebook');
		}

		return (string)$tab->addHtml($title);
	}



	/**
	 * @return string
	 */
	public function getPanel()
	{
		if (!$this->calls) {
			return NULL;
		}

		ob_start();
		if (class_exists('Latte\Runtime\Filters')) {
			$esc = Nette\Utils\Callback::closure('Latte\Runtime\Filters::escapeHtml');
		} else {
			$esc = 'Nette\Templating\Helpers::escapeHtml';
		}

		$click = class_exists('\Tracy\Dumper')
			? function ($o, $c = FALSE) { return \Tracy\Dumper::toHtml($o, array('collapse' => $c)); }
			: '\Tracy\Helpers::clickableDump';
		$totalTime = $this->totalTime ? sprintf('%0.3f', $this->totalTime * 1000) . ' ms' : 'none';

		require __DIR__ . '/panel.phtml';
		return ob_get_clean();
	}



	/**
	 * @param string|object $url
	 * @param array $params
	 */
	public function begin($url, array $params)
	{
		if ($this->current) return;
		$this->calls[] = $this->current = (object)array(
			'url' => $url,
			'params' => $params,
			'result' => NULL,
			'exception' => NULL,
			'info' => array(),
			'time' => 0,
		);
	}



	/**
	 * @param mixed $result
	 * @param array $curlInfo
	 */
	public function success($result, array $curlInfo)
	{
		if (!$this->current) return;
		$this->totalTime += $this->current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$this->current->info = $curlInfo;

		try {
			$result = Nette\Utils\Json::decode($result);

		} catch (Nette\Utils\JsonException $e) {
			@parse_str($result, $params);
			$result = !empty($params) ? $params : $result;
		}

		$this->current->result = $result;

		$this->current = NULL;
	}



	/**
	 * @param Facebook\FacebookApiException $exception
	 * @param array $curlInfo
	 */
	public function failure(Facebook\FacebookApiException $exception, array $curlInfo)
	{
		if (!$this->current) return;

		$this->totalTime += $this->current->time = $curlInfo['total_time'];
		unset($curlInfo['total_time']);
		$this->current->info = $curlInfo;
		$this->current->exception = $exception;

		$this->current = NULL;
	}



	/**
	 * @param \Kdyby\Facebook\Api\CurlClient $client
	 */
	public function register(CurlClient $client)
	{
		$client->onRequest[] = $this->begin;
		$client->onError[] = $this->failure;
		$client->onSuccess[] = $this->success;

		Debugger::getBar()->addPanel($this);
	}

}
