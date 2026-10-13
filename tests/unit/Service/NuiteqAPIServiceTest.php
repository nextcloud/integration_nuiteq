<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests;

use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NuiteqAPIServiceTest extends TestCase {

	private IClient $client;
	private LoggerInterface $logger;
	private NuiteqAPIService $apiService;

	public function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('decrypt')->willReturn('a-client-key');

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => match ($key) {
				'base_url' => 'https://stage.example.com',
				'api_key' => 'an-api-key',
				'client_key' => 'encrypted',
				default => $default,
			}
		);
		$config->method('getAppValue')->willReturn('');

		$this->logger = $this->createMock(LoggerInterface::class);

		$this->apiService = new NuiteqAPIService(
			'integration_nuiteq',
			$this->logger,
			$l10n,
			$config,
			$crypto,
			$clientService,
		);
	}

	private function answers(array $body): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn(json_encode($body));
		$this->client->method('post')->willReturn($response);
	}

	/**
	 * The board page adds whatever it is handed to its list, so an answer
	 * without an id becomes an entry that cannot be selected or deleted.
	 */
	#[DataProvider('provideAnswersWithoutABoardId')]
	public function testABoardWithNoIdIsReportedAsAFailure(array $body): void {
		$this->answers($body);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		$this->assertSame(
			'NUITEQ did not say which board it created',
			$result['error'] ?? null,
			'was reported as a created board: ' . json_encode($result)
		);
		$this->assertArrayNotHasKey('id', $result);
	}

	public static function provideAnswersWithoutABoardId(): array {
		return [
			'nothing but a status' => [['status' => 'created']],
			'an empty board id' => [['boardId' => '']],
			'a board id that is only slashes' => [['boardId' => '///']],
			'a board id that is only whitespace' => [['boardId' => '  ']],
			'a path whose last segment is whitespace' => [['boardId' => 'board/  ']],
		];
	}

	#[DataProvider('provideBoardPaths')]
	public function testTheBoardIdIsTheLastSegmentOfThePathNuiteqAnswersWith(string $boardId, string $expected): void {
		$this->answers(['boardId' => $boardId]);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		$this->assertSame($expected, $result['id'] ?? null);
		$this->assertArrayNotHasKey('boardId', $result);
	}

	public static function provideBoardPaths(): array {
		return [
			'a full path' => ['https://stage.example.com/board/b-42', 'b-42'],
			'a relative path' => ['board/b-42', 'b-42'],
			'an id on its own' => ['b-42', 'b-42'],
			'a trailing slash' => ['board/b-42/', 'b-42'],
			'a single segment with a trailing slash' => ['b-42/', 'b-42'],
		];
	}

	public function testAnAnswerWithNoBoardIdAtAllIsLogged(): void {
		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('not a path'), ['app' => 'integration_nuiteq']);
		$this->answers(['status' => 'ok']);

		$this->apiService->newBoard('admin', 'New board', '');
	}

	public function testAPathWithNoUsableSegmentIsLogged(): void {
		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('without saying which'), ['app' => 'integration_nuiteq']);
		$this->answers(['boardId' => 'https://stage.example.com/board/  ']);

		$this->apiService->newBoard('admin', 'New board', '');
	}

	public function testAnAnswerThatIsNotJsonIsReportedAsSuch(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn('not json at all');
		$this->client->method('post')->willReturn($response);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		// not "did not say which board it created": the request itself failed
		$this->assertSame('Invalid response', $result['error'] ?? null);
	}

	/**
	 * What NUITEQ said went wrong has to reach the user, rather than being
	 * reported as a board that was created without an id.
	 */
	public function testAFailureFromNuiteqIsHandedBackUntouched(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(401);
		$response->method('getBody')->willReturn('');
		$this->client->method('post')->willReturn($response);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		$this->assertSame('Bad credentials', $result['error'] ?? null);
		$this->assertArrayNotHasKey('id', $result);
	}

	/**
	 * @dataProvider provideBoardIdsThatAreNotStrings
	 */
	#[DataProvider('provideBoardIdsThatAreNotStrings')]
	public function testABoardIdThatIsNotAStringIsReportedAsAFailure(mixed $boardId): void {
		$this->answers(['boardId' => $boardId]);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		$this->assertArrayHasKey('error', $result, 'was accepted: ' . json_encode($result));
		$this->assertArrayNotHasKey('id', $result);
	}

	public static function provideBoardIdsThatAreNotStrings(): array {
		return [
			'a list' => [['a', 'b']],
			'an object' => [['nested' => 1]],
			'a boolean' => [true],
			'nothing' => [null],
		];
	}
}
