<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Controller;

use Exception;
use OCA\Nuiteq\Controller\PageController;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The board page is this app's only page, and what it hands the frontend
 * decides whether the user is shown their boards, a connect form, or a server
 * error.
 */
class PageControllerTest extends TestCase {
	private IConfig&MockObject $config;
	private ICrypto&MockObject $crypto;
	private IInitialState&MockObject $initialState;
	private NuiteqAPIService&MockObject $apiService;
	private LoggerInterface&MockObject $logger;
	private PageController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->apiService = $this->createMock(NuiteqAPIService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->willReturn(false);

		$this->controller = new PageController(
			'integration_nuiteq',
			$this->createMock(IRequest::class),
			$this->config,
			$appManager,
			$this->initialState,
			$this->apiService,
			$this->crypto,
			$this->logger,
			'jdoe',
		);
	}

	/**
	 * The stored values, by the name the controller asks for them under.
	 *
	 * @param array $values the stored user values
	 */
	private function stored(array $values): void {
		$this->config->method('getUserValue')->willReturnCallback(
			fn (string $userId, string $app, string $key, string $default = '') => $values[$key] ?? $default
		);
		$this->apiService->method('getBaseUrl')->willReturn($values['base_url'] ?? '');
	}

	/**
	 * The state the page was handed.
	 */
	private function state(): array {
		$state = null;
		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->willReturnCallback(function (string $key, $value) use (&$state) {
				$state = $value;
			});

		$this->controller->index();

		return $state;
	}

	public function testTheBoardsAreOnThePageWhenTheKeyCanBeRead(): void {
		$this->stored(['client_key' => 'encrypted', 'api_key' => 'stored-api-key', 'user_name' => 'jdoe', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willReturn('the-real-key');
		$this->apiService->method('getBoards')->willReturn([['id' => 'b-1', 'name' => 'Sprint planning']]);

		$state = $this->state();

		$this->assertSame('the-real-key', $state['client_key']);
		$this->assertTrue($state['api_key']);
		$this->assertSame('jdoe', $state['user_name']);
		$this->assertSame([['id' => 'b-1', 'name' => 'Sprint planning']], $state['board_list']);
	}

	public function testAKeyThatCannotBeDecryptedRendersThePageInsteadOfFailing(): void {
		$this->stored(['client_key' => 'encrypted-with-another-secret', 'api_key' => 'stored-api-key', 'user_name' => 'jdoe', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willThrowException(new Exception('Authenticated ciphertext could not be decoded'));

		$state = $this->state();

		$this->assertSame('', $state['client_key']);
	}

	public function testAKeyThatCannotBeDecryptedReadsAsNotConnected(): void {
		$this->stored(['client_key' => 'encrypted-with-another-secret', 'api_key' => 'stored-api-key', 'user_name' => 'jdoe', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willThrowException(new Exception('Authenticated ciphertext could not be decoded'));

		$state = $this->state();

		// the page reads these as connected, which would show the user their
		// boards behind a key no request can use, and hide the field that
		// replaces it
		$this->assertFalse($state['api_key']);
		$this->assertSame('', $state['user_name']);
	}

	public function testNoBoardsAreFetchedWithAKeyThatCannotBeDecrypted(): void {
		$this->stored(['client_key' => 'encrypted-with-another-secret', 'api_key' => 'stored-api-key', 'user_name' => 'jdoe', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willThrowException(new Exception('Authenticated ciphertext could not be decoded'));
		$this->apiService->expects($this->never())->method('getBoards');

		$this->assertSame([], $this->state()['board_list']);
	}

	public function testAKeyThatCannotBeDecryptedIsLogged(): void {
		$this->stored(['client_key' => 'encrypted-with-another-secret', 'api_key' => 'stored-api-key', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willThrowException(new Exception('Authenticated ciphertext could not be decoded'));
		$this->logger->expects($this->once())->method('warning')
			->with($this->stringContains('could not be read'), ['app' => 'integration_nuiteq']);

		$this->state();
	}

	public function testAnEmptyKeyIsNotHandedToTheDecrypter(): void {
		$this->stored(['api_key' => '', 'user_name' => '', 'base_url' => '']);
		$this->crypto->expects($this->never())->method('decrypt');

		$state = $this->state();

		$this->assertSame('', $state['client_key']);
		$this->assertFalse($state['api_key']);
	}

	public function testAnAnswerThatIsNotAListOfBoardsLeavesThePageEmpty(): void {
		$this->stored(['client_key' => 'encrypted', 'api_key' => 'stored-api-key', 'user_name' => 'jdoe', 'base_url' => 'https://stage.example.com']);
		$this->crypto->method('decrypt')->willReturn('the-real-key');
		$this->apiService->method('getBoards')->willReturn(['error' => 'Bad credentials']);

		$state = $this->state();

		// the page used to be handed the error array as its board list
		$this->assertSame([], $state['board_list']);
		$this->assertTrue($state['api_key']);
	}
}
