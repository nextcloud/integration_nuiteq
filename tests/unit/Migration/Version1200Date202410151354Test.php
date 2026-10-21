<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Migration;

use Doctrine\DBAL\ParameterType;
use OCA\Nuiteq\Migration\Version1200Date202410151354;
use OCP\DB\IResult;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class Version1200Date202410151354Test extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		if (!class_exists(ParameterType::class)) {
			// the constants of IQueryBuilder are Doctrine's
			$this->markTestSkipped('a database connection cannot be mocked without Doctrine, which a server checkout ships');
		}
	}

	/**
	 * A write sets the flag of the row it writes, so encrypting the admin key
	 * of an install older than 1.0.8 has to keep it lazy.
	 */
	public function testTheAdminClientKeyIsReadAndWrittenLazily(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('getValueString')
			->with('integration_nuiteq', 'client_key', '', true)
			->willReturn('a-key-in-clear');
		$appConfig->expects($this->once())
			->method('setValueString')
			->with('integration_nuiteq', 'client_key', 'encrypted(a-key-in-clear)', true)
			->willReturn(true);
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(fn (string $value): string => 'encrypted(' . $value . ')');

		// no user has a client key of their own
		$result = $this->createMock(IResult::class);
		$result->method('fetch')->willReturn(false);
		$qb = $this->createMock(IQueryBuilder::class);
		foreach (['update', 'set', 'where', 'andWhere', 'select', 'from'] as $method) {
			$qb->method($method)->willReturnSelf();
		}
		$qb->method('expr')->willReturn($this->createMock(IExpressionBuilder::class));
		$qb->method('executeQuery')->willReturn($result);
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('getQueryBuilder')->willReturn($qb);

		(new Version1200Date202410151354($connection, $crypto, $appConfig))
			->postSchemaChange($this->createMock(IOutput::class), fn () => null, []);
	}
}
