<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Nuiteq\Migration;

use Closure;
use OCA\Nuiteq\AppInfo\Application;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1301Date202610211026 extends SimpleMigrationStep {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return null|ISchemaWrapper
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options) {
		// the admin client key is read only by this app's own settings and
		// requests; base_url and override_link_click are read on every page
		// and stay non-lazy
		$this->appConfig->updateLazy(Application::APP_ID, 'client_key', true);

		return null;
	}
}
