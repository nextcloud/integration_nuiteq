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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class NuiteqAPIController extends Controller {

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
	 * @var NuiteqAPIService
	 */
	private $nuiteqAPIService;

	public function __construct(string            $appName,
		IRequest          $request,
		IConfig           $config,
		LoggerInterface   $logger,
		NuiteqAPIService  $nuiteqAPIService,
		?string           $userId) {
		parent::__construct($appName, $request);
		$this->userId = $userId;
		$this->logger = $logger;
		$this->config = $config;
		$this->nuiteqAPIService = $nuiteqAPIService;
	}

	/**
	 * @NoAdminRequired
	 *
	 * @param string $name
	 * @param string $password
	 * @return DataResponse
	 */
	public function newBoard(string $name, string $password): DataResponse {
		$result = $this->nuiteqAPIService->newBoard($this->userId, $name, $password);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function getBoards(): DataResponse {
		$result = $this->nuiteqAPIService->getBoards($this->userId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}

	/**
	 * @NoAdminRequired
	 *
	 * @return DataResponse
	 */
	public function deleteBoard(string $boardId): DataResponse {
		$result = $this->nuiteqAPIService->deleteBoard($this->userId, $boardId);
		if (isset($result['error'])) {
			return new DataResponse($result, Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($result);
	}
}
