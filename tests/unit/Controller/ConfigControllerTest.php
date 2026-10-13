<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests;

use OCA\Nuiteq\Controller\ConfigController;
use OCA\Nuiteq\Service\NuiteqAPIService;
use OCP\IConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class ConfigControllerTest extends TestCase {

	private IConfig $config;
	private ICrypto $crypto;
	private ConfigController $controller;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(IConfig::class);
		$this->crypto = $this->createMock(ICrypto::class);
		$this->crypto->method('encrypt')->willReturnCallback(fn ($value) => 'encrypted(' . $value . ')');

		$this->controller = new ConfigController(
			'integration_nuiteq',
			$this->createMock(IRequest::class),
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
		$this->config->expects($this->once())
			->method('setAppValue')
			->with('integration_nuiteq', 'base_url', 'https://stage.example.com');

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
		$this->config->method('setAppValue')
			->willReturnCallback(function ($app, $key, $value) use (&$stored): void {
				$stored[$key] = $value;
			});

		$this->controller->setAdminConfig(['client_key' => 'a-real-key']);

		$this->assertSame(['client_key' => 'encrypted(a-real-key)'], $stored);
	}

	public function testAClearedAdminClientKeyIsStoredAsItIs(): void {
		$stored = [];
		$this->config->method('setAppValue')
			->willReturnCallback(function ($app, $key, $value) use (&$stored): void {
				$stored[$key] = $value;
			});

		$this->controller->setAdminConfig(['client_key' => '']);

		$this->assertSame(['client_key' => ''], $stored);
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
