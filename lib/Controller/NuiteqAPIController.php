<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Controller;

use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class NuiteqAPIController extends Controller {

	private NuiteqAPIService $nuiteqAPIService;

	public function __construct(
		string            $appName,
		IRequest          $request,
		NuiteqAPIService  $nuiteqAPIService,
		private ?string   $userId,
	) {
		parent::__construct($appName, $request);
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
