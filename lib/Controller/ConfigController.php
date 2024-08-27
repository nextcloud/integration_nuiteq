<?php
/**
 * Nextcloud - Nuiteq
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Nuiteq\Controller;

use OCA\Nuiteq\AppInfo\Application;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;

use OCP\IURLGenerator;

class ConfigController extends Controller {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IURLGenerator
	 */
	private $urlGenerator;
	/**
	 * @var IL10N
	 */
	private $l;
	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var NuiteqAPIService
	 */
	private $nuiteqAPIService;

	public function __construct(string $appName,
		IRequest $request,
		IConfig $config,
		IURLGenerator $urlGenerator,
		NuiteqAPIService $nuiteqAPIService,
		IL10N $l,
		?string $userId) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
		$this->userId = $userId;
		$this->nuiteqAPIService = $nuiteqAPIService;
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setConfig(array $values): DataResponse {
		if (isset($values['base_url'], $values['login'], $values['password'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'base_url', $values['base_url']);
			return $this->loginWithCredentials($values['login'], $values['password']);
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		return new DataResponse('');
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse {
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse('');
	}

	private function loginWithCredentials(string $login, string $password): DataResponse {
		$result = $this->nuiteqAPIService->login($this->userId, $login, $password);
		if (isset($result['apiKey'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'api_key', $result['apiKey']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $login);
			return new DataResponse([
				'user_name' => $login,
			]);
		}
		return new DataResponse([
			'error' => $result['error'] ?? 'unknown error',
		], Http::STATUS_BAD_REQUEST);
	}
}
