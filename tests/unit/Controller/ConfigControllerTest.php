<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests;

use OCA\Nuiteq\Controller\ConfigController;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigControllerTest extends TestCase {

	private IAppConfig $appConfig;
	private IConfig $config;
	private ICrypto $crypto;
	private ConfigController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->config = $this->createMock(IConfig::class);
		// the old API answers a lazy value with the default, so a read through
		// it must not pass for the right one
		foreach (['getAppValue', 'setAppValue', 'deleteAppValue'] as $method) {
			$this->config->expects($this->never())->method($method);
		}
		$this->crypto = $this->createMock(ICrypto::class);
		$this->crypto->method('encrypt')->willReturnCallback(fn ($value) => 'encrypted(' . $value . ')');

		$this->controller = new ConfigController(
			'integration_nuiteq',
			$this->createMock(IRequest::class),
			$this->appConfig,
			$this->config,
			$this->createMock(NuiteqAPIService::class),
			$this->crypto,
			'admin',
		);
	}

	/**
	 * The settings page shows this in place of a stored key, so saving any
	 * other admin setting used to replace the real key with the placeholder.
	 */
	public function testThePlaceholderSecretIsNotStoredAsTheAdminClientKey(): void {
		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with('base_url', 'https://stage.example.com');

		$this->controller->setAdminConfig([
			'client_key' => 'dummySecret',
			'base_url' => 'https://stage.example.com',
		]);
	}

	public function testAUserClientKeyIsStoredEncrypted(): void {
		$stored = [];
		$this->config->method('setUserValue')
			->willReturnCallback(function ($userId, $app, $key, $value) use (&$stored): void {
				$stored[$key] = $value;
			});
		$this->controller->setConfig(['client_key' => 'a-real-key']);

		// a regression here writes every user's key to the database in clear
		$this->assertSame('encrypted(a-real-key)', $stored['client_key'] ?? null);
	}

	public function testAUserClientKeyIsTheOnlyValueEncrypted(): void {
		$stored = [];
		$this->config->method('setUserValue')
			->willReturnCallback(function ($userId, $app, $key, $value) use (&$stored): void {
				$stored[$key] = $value;
			});
		$this->crypto->expects($this->once())->method('encrypt')->willReturn('encrypted');

		$this->controller->setConfig(['client_key' => 'a-real-key', 'base_url' => 'https://stage.example.com']);

		$this->assertSame('https://stage.example.com', $stored['base_url'] ?? null);
	}

	public function testAnAdminClientKeyIsStoredEncrypted(): void {
		$stored = [];
		$this->appConfig->method('setAppValueString')
			->willReturnCallback(function ($key, $value) use (&$stored): bool {
				$stored[$key] = $value;
				return true;
			});

		$this->controller->setAdminConfig(['client_key' => 'a-real-key']);

		$this->assertSame(['client_key' => 'encrypted(a-real-key)'], $stored);
	}

	public function testAClearedAdminClientKeyIsStoredAsItIs(): void {
		$stored = [];
		$this->appConfig->method('setAppValueString')
			->willReturnCallback(function ($key, $value) use (&$stored): bool {
				$stored[$key] = $value;
				return true;
			});

		$this->controller->setAdminConfig(['client_key' => '']);

		$this->assertSame(['client_key' => ''], $stored);
	}

	/**
	 * A write sets the flag of the row it writes: written non-lazy, the admin
	 * key would be loaded on every request again.
	 */
	public function testTheAdminClientKeyIsWrittenLazily(): void {
		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with('client_key', 'encrypted(a-real-key)', true);

		$this->controller->setAdminConfig(['client_key' => 'a-real-key']);
	}

	public function testAClearedAdminClientKeyIsWrittenLazilyToo(): void {
		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with('client_key', '', true);

		$this->controller->setAdminConfig(['client_key' => '']);
	}

	/**
	 * The Stage URL is read on every page, so it stays a non-lazy value.
	 */
	public function testTheAdminBaseUrlIsWrittenNonLazily(): void {
		$this->appConfig->expects($this->once())
			->method('setAppValueString')
			->with('base_url', 'https://stage.example.com', false);

		$this->controller->setAdminConfig(['base_url' => 'https://stage.example.com']);
	}

	/**
	 * What the admin settings send: the Stage URL always, and the key unless
	 * the field still shows the placeholder for a stored one.
	 */
	#[DataProvider('provideWhatTheAdminPageSends')]
	public function testWhatTheAdminPageSendsIsSaved(array $values, array $expected): void {
		$stored = [];
		$this->appConfig->method('setAppValueString')
			->willReturnCallback(function (string $key, string $value, bool $lazy = false) use (&$stored): bool {
				$stored[$key] = [$value, $lazy];
				return true;
			});

		$response = $this->controller->setAdminConfig($values);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($expected, $stored);
	}

	public static function provideWhatTheAdminPageSends(): array {
		return [
			'a Stage URL, with the key field empty' => [
				['base_url' => 'https://new.example.com', 'client_key' => ''],
				['base_url' => ['https://new.example.com', false], 'client_key' => ['', true]],
			],
			'a Stage URL, with a key stored before' => [
				['base_url' => 'https://new.example.com'],
				['base_url' => ['https://new.example.com', false]],
			],
			'a new key' => [
				['base_url' => 'https://stage.example.com', 'client_key' => 'a-real-key'],
				['base_url' => ['https://stage.example.com', false], 'client_key' => ['encrypted(a-real-key)', true]],
			],
		];
	}

	/**
	 * A typed write sets the type of the row it writes, and Nextcloud's own
	 * rows of this app are untyped: once installed_version is a string, the
	 * next upgrade fails on the conflict and leaves the instance in
	 * maintenance mode, and once enabled is one, enabling the app fails.
	 */
	#[DataProvider('provideWhatIsNotAnAdminSetting')]
	public function testNothingButTheAdminSettingsIsStored(array $values): void {
		$this->appConfig->expects($this->never())->method('setAppValueString');

		$response = $this->controller->setAdminConfig($values);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}

	public static function provideWhatIsNotAnAdminSetting(): array {
		return [
			'the installed version' => [['installed_version' => '1.3.0']],
			'whether the app is enabled' => [['enabled' => 'no']],
			'the link override, which has no setting' => [['override_link_click' => '1']],
			'a value without a name' => [['https://stage.example.com']],
			// every key is checked before any is written
			'a Stage URL beside the installed version' => [['base_url' => 'https://stage.example.com', 'installed_version' => '1.3.0']],
		];
	}

	/**
	 * The admin settings send only strings. Anything else used to be stored
	 * as some string, null as an empty one, and the typed setter answers null
	 * or a list with a server error.
	 */
	#[DataProvider('provideValuesThatAreNotStrings')]
	public function testAValueThatIsNotAStringIsRefused(array $values): void {
		$this->appConfig->expects($this->never())->method('setAppValueString');

		$response = $this->controller->setAdminConfig($values);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertArrayHasKey('error', $response->getData());
	}

	public static function provideValuesThatAreNotStrings(): array {
		return [
			'a Stage URL that is null' => [['base_url' => null]],
			'a key that is null' => [['client_key' => null]],
			'a Stage URL in a list' => [['base_url' => ['https://stage.example.com']]],
			'a Stage URL that is a boolean' => [['base_url' => true]],
			'a key that is a number' => [['client_key' => 42]],
			// nothing is written before the value that is refused
			'a key beside a Stage URL that is null' => [['client_key' => 'a-real-key', 'base_url' => null]],
		];
	}

	public function testThePlaceholderSecretIsNotStoredAsTheUserClientKeyEither(): void {
		$stored = [];
		$this->config->method('setUserValue')
			->willReturnCallback(function ($user, $app, $key, $value) use (&$stored): void {
				$stored[$key] = $value;
			});

		$this->controller->setConfig(['client_key' => 'dummySecret', 'base_url' => 'https://b.example']);

		$this->assertSame(['base_url' => 'https://b.example'], $stored);
	}
}
