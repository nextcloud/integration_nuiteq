<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests;

use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
		$l10n->method('t')->willReturnCallback(fn (string $text, array $parameters = []) => vsprintf($text, $parameters));

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

	/**
	 * What Nextcloud's client throws for an answer of 400 or above, built by
	 * Guzzle itself, so its message is the one the client really carries.
	 */
	private function refuses(int $status, string $body, string $endPoint): void {
		$url = 'https://stage.example.com/api/v1/' . $endPoint;
		$type = str_starts_with($body, '<') ? 'text/html' : 'application/json';
		$response = new Response($status, ['Content-Type' => $type], $body);
		$this->client->expects($this->once())->method('post')
			->with($url, $this->anything())
			->willThrowException(RequestException::create(new Request('POST', $url), $response));
	}

	private function call(string $endPoint): array {
		return match ($endPoint) {
			'login' => $this->apiService->login('admin', 'jane', 'not-her-password'),
			'list' => $this->apiService->getBoards('admin'),
			'delete' => $this->apiService->deleteBoard('admin', 'b-1'),
		};
	}

	/**
	 * NUITEQ answers an unknown username and password with a 401 and its
	 * reason, and the settings showed the request that failed instead: the
	 * URL, the status line and NUITEQ's answer as it came.
	 */
	public function testARefusedLoginIsToldInNuiteqsWords(): void {
		$this->refuses(401, '{"error":"Invalid username or password"}', 'login');

		$this->assertSame(['error' => 'Invalid username or password'], $this->call('login'));
	}

	#[DataProvider('provideRefusedLogins')]
	public function testALoginRefusedWithoutAReasonIsToldByItsStatus(int $status, string $body, string $expected): void {
		$this->refuses($status, $body, 'login');

		$this->assertSame(['error' => $expected], $this->call('login'));
	}

	public static function provideRefusedLogins(): array {
		return [
			'401 whose body has no error' => [401, '{"message":"Unauthorized"}', 'Login failed: the NUITEQ Stage URL answered 401'],
			'403 whose body has no error' => [403, '{"status":"denied"}', 'Login failed: the NUITEQ Stage URL answered 403'],
			'403 with a firewall\'s page' => [403, '<html><body>Forbidden</body></html>', 'Login failed: the NUITEQ Stage URL answered 403'],
			'401 with a page asking for a password' => [401, '<html><body>Authorization Required</body></html>', 'Login failed: the NUITEQ Stage URL answered 401'],
			'401 with a JSON string' => [401, '"Unauthorized"', 'Login failed: the NUITEQ Stage URL answered 401'],
			'401 with nothing in the body' => [401, '', 'Login failed: the NUITEQ Stage URL answered 401'],
			'400 whose error is inside a list' => [400, '[{"error":"Board is locked"}]', 'Login failed: the NUITEQ Stage URL answered 400'],
			'400, which says nothing of the credentials' => [400, '{}', 'Login failed: the NUITEQ Stage URL answered 400'],
			'a Stage URL that is not NUITEQ\'s' => [404, '<html><body>Not Found</body></html>', 'Login failed: the NUITEQ Stage URL answered 404'],
			'too many attempts' => [429, '', 'Login failed: the NUITEQ Stage URL answered 429'],
			'an outage' => [500, '<!DOCTYPE html><p>Fatal error</p>', 'Login failed: the NUITEQ Stage URL answered 500'],
		];
	}

	/**
	 * NUITEQ's own reason is the one part of its answer meant for reading.
	 */
	#[DataProvider('provideReasons')]
	public function testTheReasonNuiteqGivesIsHandedBack(string $endPoint, int $status, string $body, string $reason): void {
		$this->refuses($status, $body, $endPoint);

		$this->assertSame(['error' => $reason], $this->call($endPoint));
	}

	public static function provideReasons(): array {
		return [
			'an unknown key, listing the boards' => ['list', 403, '{"error":"Permission denied"}', 'Permission denied'],
			'a deletion refused with a 403' => ['delete', 403, '{"error":"Permission denied"}', 'Permission denied'],
			'a deletion refused with a 400' => ['delete', 400, '{"error":"This board is locked"}', 'This board is locked'],
			'a login refused with a 400' => ['login', 400, '{"error":"This account is locked"}', 'This account is locked'],
			'an outage that says why' => ['list', 503, '{"error":"Down for maintenance"}', 'Down for maintenance'],
			'a reason beside other keys' => ['login', 403, '{"error":"Permission denied","apiKey":"not-for-the-user"}', 'Permission denied'],
			'a request refused with a 401' => ['list', 401, '{"error":"Token expired"}', 'Token expired'],
		];
	}

	/**
	 * A 403 whose body was a JSON object was handed back as NUITEQ wrote it,
	 * so a deletion refused without an error key read as success, and the
	 * board page took the board off the list.
	 */
	public function testARefusedDeletionIsAFailureEvenWithoutAReason(): void {
		$this->refuses(403, '{"status":"denied"}', 'delete');

		$this->assertSame(['error' => 'The NUITEQ Stage URL answered 403'], $this->call('delete'));
	}

	#[DataProvider('provideRefusalsWithoutAReason')]
	public function testARefusalWithoutAReasonDoesNotShowTheRequest(int $status, string $body, string $expected): void {
		$this->refuses($status, $body, 'list');

		$this->assertSame(['error' => $expected], $this->call('list'));
	}

	public static function provideRefusalsWithoutAReason(): array {
		return [
			'401 whose body has no error' => [401, '{"message":"Unauthorized"}', 'The NUITEQ Stage URL answered 401'],
			'401 with a page asking for a password' => [401, '<html><body>Authorization Required</body></html>', 'The NUITEQ Stage URL answered 401'],
			'an error page' => [500, '<!DOCTYPE html><p>Fatal error in /srv/stage/index.php</p>', 'The NUITEQ Stage URL answered 500'],
			'an error made of spaces' => [400, '{"error":"   "}', 'The NUITEQ Stage URL answered 400'],
			'an error that is an object' => [400, '{"error":{"code":7}}', 'The NUITEQ Stage URL answered 400'],
			'an error that is a number' => [400, '{"error":7}', 'The NUITEQ Stage URL answered 400'],
		];
	}

	/**
	 * The request that failed still matters to whoever reads the log.
	 */
	#[DataProvider('provideLoggedRefusals')]
	public function testARefusalIsLoggedWithTheRequestThatFailed(string $endPoint, int $status, string $body, string $statusLine): void {
		$this->refuses($status, $body, $endPoint);
		$this->logger->expects($this->once())->method('warning')
			->with($this->logicalAnd(
				$this->stringContains('https://stage.example.com/api/v1/' . $endPoint),
				$this->stringContains($statusLine),
			), ['app' => 'integration_nuiteq']);

		$this->call($endPoint);
	}

	public static function provideLoggedRefusals(): array {
		return [
			'a login without a reason' => ['login', 401, '{"message":"Unauthorized"}', '401 Unauthorized'],
			'a listing with a reason' => ['list', 403, '{"error":"Permission denied"}', '403 Forbidden'],
			'a listing answered with a proxy\'s page' => ['list', 502, '<html><body>Bad Gateway</body></html>', '502 Bad Gateway'],
		];
	}
}
