<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Controller;

use OCA\Nuiteq\AppInfo\Application;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private NuiteqAPIService $nuiteqAPIService,
		private ICrypto $crypto,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
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
			if (in_array($key, ['client_key']) && $value !== '') {
				$value = $this->crypto->encrypt($value);
			}
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
			if (in_array($key, ['client_key']) && $value !== '') {
				$value = $this->crypto->encrypt($value);
			}
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
