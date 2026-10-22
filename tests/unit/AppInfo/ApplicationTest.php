<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\AppInfo;

use Closure;
use Error;
use OCA\Nuiteq\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;

class ApplicationTest extends TestCase {
	/** @var list<array{string, bool}> the admin values asked for, and whether lazily */
	private array $reads = [];

	/**
	 * Boots the app the way Nextcloud does, and returns what it hands every
	 * page.
	 *
	 * @param array<string, string> $adminValues the stored admin values; a
	 *                                           missing one reads as the default
	 * @param array<string, string> $userValues the stored values of the user
	 */
	private function boot(array $adminValues, ?string $userId, array $userValues = []): array {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			function (string $key, string $default = '', bool $lazy = false) use ($adminValues): string {
				$this->reads[] = [$key, $lazy];
				return $adminValues[$key] ?? $default;
			}
		);
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn (string $user, string $app, string $key, string $default = '') => $userValues[$key] ?? $default
		);
		// the old API answers a lazy value with the default, so a read through
		// it must not pass for the right one
		foreach (['getAppValue', 'setAppValue', 'deleteAppValue'] as $method) {
			$config->expects($this->never())->method($method);
		}
		$state = [];
		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')->willReturnCallback(
			function (string $key, $value) use (&$state): void {
				$state[$key] = $value;
			}
		);
		$services = [
			IAppConfig::class => $appConfig,
			IConfig::class => $config,
			IInitialState::class => $initialState,
		];
		$context = $this->createMock(IBootContext::class);
		$context->expects($this->once())
			->method('injectFn')
			->willReturnCallback(fn (callable $fn) => $this->inject($fn, $services, $userId));

		// the constructor asks a running server for its debug setting
		$application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
		$application->boot($context);

		return $state;
	}

	/**
	 * Calls the function as Nextcloud's injector does, with a service for
	 * each class it asks for and the user id for the rest.
	 */
	private function inject(callable $fn, array $services, ?string $userId): void {
		$arguments = [];
		foreach ((new ReflectionFunction(Closure::fromCallable($fn)))->getParameters() as $parameter) {
			$type = $parameter->getType();
			$arguments[] = $type instanceof ReflectionNamedType && !$type->isBuiltin()
				? $services[$type->getName()]
				: $userId;
		}
		try {
			$fn(...$arguments);
		} catch (Error $e) {
			// the script it adds last looks up the user's language with a
			// running server, which a clone on its own does not have; the
			// values it hands the page come before that
			if (!str_contains($e->getMessage(), 'Class "OC" not found')) {
				throw $e;
			}
		}
	}

	/**
	 * boot() runs on every request, so what it reads has to be among the
	 * values Nextcloud loads up front: a lazy read there would load every lazy
	 * value of every app with each page.
	 */
	public function testBootReadsTheAdminValuesNonLazily(): void {
		$this->boot([], 'jdoe');

		$this->assertSame([['base_url', false], ['override_link_click', false]], $this->reads);
	}

	/**
	 * override_link_click has no setting, so on nearly every install it is
	 * not set, and its default decides how a click on a board link behaves.
	 */
	#[DataProvider('provideUsers')]
	public function testAnInstallWithNothingSetGetsTheShippedStageUrlAndNoOverride(?string $userId): void {
		$state = $this->boot([], $userId);

		$this->assertSame(Application::DEFAULT_BASE_URL, $state['base_url'] ?? null);
		$this->assertFalse($state['override_link_click'] ?? null);
	}

	/**
	 * Clearing the Stage URL on the admin page stores it empty, which stands
	 * for the one the app ships with.
	 */
	#[DataProvider('provideUsers')]
	public function testAClearedStageUrlFallsBackToTheShippedOne(?string $userId): void {
		$state = $this->boot(['base_url' => ''], $userId);

		$this->assertSame(Application::DEFAULT_BASE_URL, $state['base_url'] ?? null);
	}

	public static function provideUsers(): array {
		return [
			'a visitor who is not logged in' => [null],
			'a user without a Stage URL of their own' => ['jdoe'],
		];
	}

	public function testTheAdminValuesReachThePage(): void {
		$state = $this->boot(['base_url' => 'https://stage.example.com', 'override_link_click' => '1'], 'jdoe');

		$this->assertSame('https://stage.example.com', $state['base_url'] ?? null);
		$this->assertTrue($state['override_link_click'] ?? null);
	}

	public function testAUsersOwnStageUrlComesFirst(): void {
		$state = $this->boot(['base_url' => 'https://stage.example.com'], 'jdoe', ['base_url' => 'https://own.example.com']);

		$this->assertSame('https://own.example.com', $state['base_url'] ?? null);
	}
}
