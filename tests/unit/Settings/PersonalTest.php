<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Settings;

use Exception;
use OCA\Nuiteq\Settings\Personal;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The personal settings build the same initial state the board page does, from
 * the same stored values, so the two have to agree about whether the user is
 * connected.
 */
class PersonalTest extends TestCase {
	private IConfig $config;
	private ICrypto $crypto;
	private IInitialState $initialState;
	private Personal $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->initialState = $this->createMock(IInitialState::class);

		$this->settings = new Personal(
			$this->config,
			$this->initialState,
			$this->crypto,
			$this->createMock(LoggerInterface::class),
			'jdoe',
		);
	}

	/**
	 * @param array $values the stored user values
	 */
	private function stored(array $values): void {
		$this->config->method('getUserValue')->willReturnCallback(
			fn (string $userId, string $app, string $key, string $default = '') => $values[$key] ?? $default
		);
		$this->config->method('getAppValue')->willReturn('');
	}

	private function state(): array {
		$state = null;
		$this->initialState->expects($this->once())
			->method('provideInitialState')
			->willReturnCallback(function (string $key, $value) use (&$state): void {
				$state = $value;
			});

		$this->settings->getForm();

		return $state;
	}

	public function testAConnectedAccountIsShownAsConnected(): void {
		$this->stored(['client_key' => 'encrypted', 'api_key' => 'an-api-key', 'user_name' => 'jdoe']);
		$this->crypto->method('decrypt')->willReturn('the-real-key');

		$state = $this->state();

		$this->assertSame('jdoe', $state['user_name']);
		// the stored key is never shipped to the browser
		$this->assertSame('dummySecret', $state['client_key']);
	}

	public function testAKeyThatCannotBeReadIsShownAsNotConnected(): void {
		$this->stored(['client_key' => 'encrypted-elsewhere', 'api_key' => 'an-api-key', 'user_name' => 'jdoe']);
		$this->crypto->method('decrypt')->willThrowException(new Exception('Authenticated ciphertext could not be decoded'));

		$state = $this->state();

		// it read as connected while every request failed on the key, and the
		// field that replaces the key is the one a connection hides
		$this->assertSame('', $state['user_name']);
		$this->assertSame('', $state['client_key']);
	}

	public function testAnAccountWithNoKeyIsLeftAlone(): void {
		$this->stored(['client_key' => '', 'api_key' => 'an-api-key', 'user_name' => 'jdoe']);
		$this->crypto->expects($this->never())->method('decrypt');

		$state = $this->state();

		$this->assertSame('jdoe', $state['user_name']);
		$this->assertSame('', $state['client_key']);
	}
}
