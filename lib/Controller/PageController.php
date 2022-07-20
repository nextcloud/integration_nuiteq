<?php
/**
 * Nextcloud - Nuiteq integration
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Nuiteq\Controller;

use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;

use OCA\Nuiteq\AppInfo\Application;

class PageController extends Controller {

	/**
	 * @var string|null
	 */
	private $userId;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IAppManager
	 */
	private $appManager;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var NuiteqAPIService
	 */
	private $nuiteqAPIService;

	public function __construct(string $appName,
								IRequest $request,
								IConfig $config,
								IAppManager $appManager,
								IInitialState $initialStateService,
								LoggerInterface $logger,
								NuiteqAPIService $nuiteqAPIService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->config = $config;
		$this->appManager = $appManager;
		$this->initialStateService = $initialStateService;
		$this->nuiteqAPIService = $nuiteqAPIService;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		$clientKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'client_key');
		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$baseUrl = $this->nuiteqAPIService->getBaseUrl($this->userId);
		$userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
		$talkEnabled = $this->appManager->isEnabledForUser('spreed', $this->userId);
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
