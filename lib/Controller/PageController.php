<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Controller;

use OCA\Nuiteq\AppInfo\Application;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppManager $appManager,
		private IInitialState $initialStateService,
		private NuiteqAPIService $nuiteqAPIService,
		private ICrypto $crypto,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		$clientKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_key');
		$clientKey = $clientKey === '' ? '' : $this->crypto->decrypt($clientKey);
		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$baseUrl = $this->nuiteqAPIService->getBaseUrl($this->userId);
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$talkEnabled = $this->appManager->isEnabledForUser('spreed');
		$pageInitialState = [
			'client_key' => $clientKey,
			'api_key' => $apiKey !== '',
			'base_url' => $baseUrl,
			'user_name' => $apiKey ? $userName : '',
			'talk_enabled' => $talkEnabled,
			'board_list' => [],
		];
		if ($baseUrl !== '' && $apiKey !== '') {
			$pageInitialState['board_list'] = $this->nuiteqAPIService->getBoards($this->userId);
		}
		$this->initialStateService->provideInitialState('nuiteq-state', $pageInitialState);
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
