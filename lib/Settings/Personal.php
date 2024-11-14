<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Settings;

use OCA\Nuiteq\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;

use OCP\Settings\ISettings;

class Personal implements ISettings {

	public function __construct(
		private IConfig $config,
		private IInitialState $initialStateService,
		private ?string $userId,
	) {
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$adminBaseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', 'https://nuiteqstage.se') ?: 'https://nuiteqstage.se';
		$baseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;

		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$clientKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_key');
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');

		$userConfig = [
			'base_url' => $baseUrl,
			'user_name' => $apiKey ? $userName : '',
			'client_key' => $clientKey !== '' ? 'dummySecret' : '',
		];
		$this->initialStateService->provideInitialState('nuiteq-state', $userConfig);
		return new TemplateResponse(Application::APP_ID, 'personalSettings');
	}

	public function getSection(): string {
		return 'connected-accounts';
	}

	public function getPriority(): int {
		return 10;
	}
}
