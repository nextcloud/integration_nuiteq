<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Listener;

use OCA\Nuiteq\AppInfo\Application;
use OCA\Nuiteq\Listener\AddContentSecurityPolicyListener;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AddContentSecurityPolicyListenerTest extends TestCase {

	/**
	 * The policy the listener adds for a user without a Stage URL of their own.
	 */
	private function policy(IAppConfig $appConfig): string {
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnArgument(3);
		// the old API answers a lazy value with the default, so a read through
		// it must not pass for the right one
		foreach (['getAppValue', 'setAppValue', 'deleteAppValue'] as $method) {
			$config->expects($this->never())->method($method);
		}
		$policy = '';
		$event = $this->createMock(AddContentSecurityPolicyEvent::class);
		$event->expects($this->once())
			->method('addPolicy')
			->willReturnCallback(function (ContentSecurityPolicy $csp) use (&$policy): void {
				$policy = $csp->buildPolicy();
			});

		(new AddContentSecurityPolicyListener($config, $appConfig, 'jdoe'))->handle($event);

		return $policy;
	}

	/**
	 * The policy is built for every page, so the Stage URL it allows is read
	 * on every request, from the values Nextcloud loads up front: a lazy read
	 * would load every lazy value of every app with each page.
	 */
	public function testTheAdminBaseUrlIsReadNonLazily(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('getAppValueString')
			->with('base_url', Application::DEFAULT_BASE_URL, false)
			->willReturn('https://stage.example.com');

		$this->assertStringContainsString('https://stage.example.com', $this->policy($appConfig));
	}

	/**
	 * @param array<string, string> $adminValues the stored admin values; a
	 *                                           missing one reads as the default
	 */
	#[DataProvider('provideStageUrlsThatAreNotSet')]
	public function testWithoutAStageUrlTheShippedOneIsAllowed(array $adminValues): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key, string $default = '', bool $lazy = false): string => $adminValues[$key] ?? $default
		);

		$this->assertStringContainsString(Application::DEFAULT_BASE_URL, $this->policy($appConfig));
	}

	public static function provideStageUrlsThatAreNotSet(): array {
		return [
			'never set' => [[]],
			'cleared on the admin page' => [['base_url' => '']],
		];
	}
}
