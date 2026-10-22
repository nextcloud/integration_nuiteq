<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Settings;

use OCA\Nuiteq\Settings\Admin;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase {
	/** @var list<array{string, bool}> the admin values asked for, and whether lazily */
	private array $reads = [];

	/**
	 * The admin settings as they are handed to the page.
	 *
	 * @param array<string, string> $values the admin values stored as non-lazy ones
	 * @param array<string, string> $lazyValues the admin values stored as lazy ones
	 */
	private function state(array $values, array $lazyValues): array {
		// as Nextcloud answers before anything has loaded the lazy values: a
		// non-lazy read of a lazy value gets the default, as does a read of a
		// value never set
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			function (string $key, string $default = '', bool $lazy = false) use ($values, $lazyValues): string {
				$this->reads[] = [$key, $lazy];
				return $values[$key] ?? ($lazy ? ($lazyValues[$key] ?? $default) : $default);
			}
		);
		$state = null;
		$initialState = $this->createMock(IInitialState::class);
		$initialState->expects($this->once())
			->method('provideInitialState')
			->willReturnCallback(function (string $key, $value) use (&$state): void {
				$state = $value;
			});

		(new Admin($appConfig, $initialState))->getForm();

		return $state;
	}

	/**
	 * Read non-lazily, the admin key would read as unset, and the page would
	 * ask for a key the admin has already set.
	 */
	public function testAStoredAdminClientKeyIsShownAsSet(): void {
		$state = $this->state(['base_url' => 'https://stage.example.com'], ['client_key' => 'encrypted']);

		// the stored key is never shipped to the browser
		$this->assertSame('dummySecret', $state['client_key']);
		$this->assertContains(['client_key', true], $this->reads);
		$this->assertNotContains(['client_key', false], $this->reads);
	}

	public function testAnInstallWithNothingSetShowsTheShippedStageUrlAndNoKey(): void {
		$state = $this->state([], []);

		$this->assertSame('https://nuiteqstage.se', $state['base_url']);
		$this->assertSame('', $state['client_key']);
	}

	/**
	 * The Stage URL is read on every page, so it stays a non-lazy value, and a
	 * lazy read of it would load every lazy value for nothing.
	 */
	public function testTheAdminBaseUrlIsReadNonLazily(): void {
		$state = $this->state(['base_url' => 'https://stage.example.com'], []);

		$this->assertSame('https://stage.example.com', $state['base_url']);
		$this->assertContains(['base_url', false], $this->reads);
		$this->assertNotContains(['base_url', true], $this->reads);
	}
}
