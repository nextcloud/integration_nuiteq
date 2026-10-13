<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests;

use Exception;
use OCA\Nuiteq\AppInfo\Application;
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
	private ICrypto $crypto;
	private string $storedClientKey = 'encrypted';
	private NuiteqAPIService $apiService;

	public function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);

		$this->crypto = $this->createMock(ICrypto::class);
		$this->crypto->method('decrypt')->willReturnCallback(
			fn (string $value) => $value === 'unreadable' ? throw new Exception('Authenticated ciphertext could not be decoded.') : 'a-client-key'
		);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($userId, $appName, $key, $default = '') => match ($key) {
				'base_url' => 'https://stage.example.com',
				'api_key' => 'an-api-key',
				'client_key' => $this->storedClientKey,
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
			$this->crypto,
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
			// stripping the separator and taking the segment before it read
			// these as the boards "board" and "stage.example.com"
			'a path ending after the board segment' => [['boardId' => 'https://stage.example.com/board/']],
			'a relative path ending in a separator' => [['boardId' => 'board/']],
			'nothing but a base url' => [['boardId' => 'https://stage.example.com/']],
			// a board may really be named here, but which one is a guess
			'a path ending in a separator' => [['boardId' => 'board/b-42/']],
			'a dot segment' => [['boardId' => 'board/.']],
			'a parent segment' => [['boardId' => 'board/..']],
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
			'a query string' => ['board/b-42?token=abc', 'b-42'],
			'a fragment' => ['https://stage.example.com/board/b-42#top', 'b-42'],
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

	/**
	 * Connecting again is the way out of a key that cannot be read, and the
	 * form does not send one: it answered with the raw crypto failure instead.
	 */
	public function testConnectingWorksWithAStoredKeyThatCannotBeRead(): void {
		$this->storedClientKey = 'unreadable';
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn('{"apiKey":"a-fresh-api-key"}');
		$this->client->method('post')->willReturn($response);

		$result = $this->apiService->login('admin', 'jdoe', 'secret');

		$this->assertSame('a-fresh-api-key', $result['apiKey'] ?? null);
		$this->assertArrayNotHasKey('error', $result);
	}

	public function testAKeyThatCannotBeReadFallsBackToTheConfiguredOne(): void {
		$this->storedClientKey = 'unreadable';
		$sent = [];
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn('{"apiKey":"a-fresh-api-key"}');
		$this->client->method('post')->willReturnCallback(function ($url, $options) use (&$sent, $response) {
			$sent = $options['json'] ?? [];
			return $response;
		});

		$this->apiService->login('admin', 'jdoe', 'secret');

		// the configured default, since this instance has no admin key set
		$this->assertSame(Application::DEFAULT_CLIENT_KEY, $sent['clientKey'] ?? null);
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

	/**
	 * JSON carries a number as a number, and an id that arrives as one still
	 * names a board: refusing it would stop board creation outright.
	 */
	#[DataProvider('provideNumericBoardIds')]
	public function testABoardIdThatArrivesAsANumberIsUsed(mixed $boardId, string $expected): void {
		$this->answers(['boardId' => $boardId]);

		$result = $this->apiService->newBoard('admin', 'New board', '');

		$this->assertSame($expected, $result['id'] ?? null);
	}

	public static function provideNumericBoardIds(): array {
		return [
			'an integer' => [12345, '12345'],
			'a zero' => [0, '0'],
		];
	}
}
