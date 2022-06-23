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
	private NuiteqAPIService $NuiteqAPIService;
	private IConfig $config;
	private IInitialState $initialStateService;

	public function __construct(string            $appName,
								IRequest          $request,
								IConfig           $config,
								IInitialState     $initialStateService,
								LoggerInterface   $logger,
								NuiteqAPIService $NuiteqAPIService,
								?string           $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->NuiteqAPIService = $NuiteqAPIService;
		$this->config = $config;
		$this->initialStateService = $initialStateService;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		$apiKey = $this->config->getUserValue($this->userId, Application::APP_ID, 'api_key');
		$adminBaseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url');
		$baseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;
		$pageInitialState = [
			'is_configured' => ($apiKey && $baseUrl),
		];
		$this->initialStateService->provideInitialState('page-state', $pageInitialState);
		return new TemplateResponse(Application::APP_ID, 'main', []);
	}
}
