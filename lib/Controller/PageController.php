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
use Psr\Log\LoggerInterface;

class PageController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IAppManager $appManager,
		private IInitialState $initialStateService,
		private NuiteqAPIService $nuiteqAPIService,
		private ICrypto $crypto,
		private LoggerInterface $logger,
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
			$boards = $this->nuiteqAPIService->getBoards($this->userId);
			if (array_is_list($boards)) {
				$pageInitialState['board_list'] = $boards;
			} else {
				// an answer that is not a list of boards, an error for instance: the page is
				// rendered without boards, it used to be handed something it cannot work with
				// and stayed empty
				$this->logger->warning(
					'Nuiteq board list of ' . $this->userId . ' could not be read: ' . ($boards['error'] ?? 'unexpected answer'),
					['app' => Application::APP_ID]
				);
			}
		}
		$this->initialStateService->provideInitialState('nuiteq-state', $pageInitialState);
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
