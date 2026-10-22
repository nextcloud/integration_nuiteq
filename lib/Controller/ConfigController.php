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
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
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
				if ($value === 'dummySecret') {
					continue;
				}
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
	#[PasswordConfirmationRequired]
	public function setAdminConfig(array $values): DataResponse {
		// only what the admin settings send: a typed write of any other key
		// would change the type of rows Nextcloud owns, installed_version or
		// enabled, and the next upgrade or enable fails on the conflict
		foreach ($values as $key => $value) {
			if (!in_array($key, ['base_url', 'client_key'], true)) {
				return new DataResponse(['error' => 'Unknown admin setting ' . $key], Http::STATUS_BAD_REQUEST);
			}
			if (!is_string($value)) {
				return new DataResponse(['error' => 'The admin setting ' . $key . ' must be a string'], Http::STATUS_BAD_REQUEST);
			}
		}
		foreach ($values as $key => $value) {
			if (in_array($key, ['client_key']) && $value !== '') {
				if ($value === 'dummySecret') {
					// what the settings page shows in place of a stored key
					continue;
				}
				$value = $this->crypto->encrypt($value);
			}
			// the client key is read only by this app's own settings and requests,
			// base_url on every page
			$this->appConfig->setAppValueString($key, $value, lazy: $key === 'client_key');
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
