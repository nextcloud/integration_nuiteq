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
	private IConfig $config;
	private IInitialState $initialStateService;
	private NuiteqAPIService $nuiteqAPIService;

	public function __construct(string            $appName,
								IRequest          $request,
								IConfig           $config,
								IInitialState     $initialStateService,
								LoggerInterface   $logger,
								NuiteqAPIService  $nuiteqAPIService,
								?string           $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->config = $config;
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
		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$baseUrl = $this->nuiteqAPIService->getBaseUrl($this->userId);
		$isConfigured = ($apiKey && $baseUrl);
		$pageInitialState = [
			'is_configured' => $isConfigured,
		];
		if ($isConfigured) {
			$pageInitialState['base_url'] = $baseUrl;
			$boards = $this->nuiteqAPIService->getBoards($this->userId);
			$pageInitialState['boards'] = $boards;
		}
		$this->initialStateService->provideInitialState('page-state', $pageInitialState);
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
