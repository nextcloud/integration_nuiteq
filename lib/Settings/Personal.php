<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Settings;

use Exception;
use OCA\Nuiteq\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\Settings\ISettings;
use Psr\Log\LoggerInterface;
use Throwable;

class Personal implements ISettings {

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
		private IInitialState $initialStateService,
		private ICrypto $crypto,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$adminBaseUrl = $this->appConfig->getAppValueString('base_url', 'https://nuiteqstage.se') ?: 'https://nuiteqstage.se';
		$baseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;

		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$clientKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_key');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');

		if ($clientKey !== '' && !$this->isReadable($clientKey)) {
			// every request fails on a key that cannot be decrypted, so offer
			// the form that replaces it rather than reporting a connection
			$apiKey = '';
			$clientKey = '';
		}

		$userConfig = [
			'base_url' => $baseUrl,
			'user_name' => $apiKey ? $userName : '',
			'client_key' => $clientKey !== '' ? 'dummySecret' : '',
		];
		$this->initialStateService->provideInitialState('nuiteq-state', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	private function isReadable(string $clientKey): bool {
		try {
			$this->crypto->decrypt($clientKey);
			return true;
		} catch (Exception|Throwable $e) {
			$this->logger->warning(
				'Nuiteq client key of ' . $this->userId . ' could not be read: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
			return false;
		}
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
