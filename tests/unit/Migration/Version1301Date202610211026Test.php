<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Tests\Unit\Migration;

use OCA\Nuiteq\Migration\Version1301Date202610211026;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1301Date202610211026Test extends TestCase {

	/**
	 * An existing install has the admin key stored non-lazy, which is how the
	 * old API wrote it. The Stage URL and override_link_click are read on
	 * every page and stay as they are.
	 */
	public function testOnlyTheAdminClientKeyIsMarkedLazy(): void {
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->expects($this->once())
			->method('updateLazy')
			->with('integration_nuiteq', 'client_key', true)
			->willReturn(true);

		(new Version1301Date202610211026($appConfig))
			->postSchemaChange($this->createMock(IOutput::class), fn () => null, []);
	}
}
