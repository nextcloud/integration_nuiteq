<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$serverBootstrap = __DIR__ . '/../../../tests/bootstrap.php';

if (file_exists($serverBootstrap)) {
	// inside a server checkout, which is how CI runs these: the server's
	// autoloader carries OCP, and loadApp registers this app's own classes
	require_once $serverBootstrap;
	\OC_App::loadApp(\OCA\Nuiteq\AppInfo\Application::APP_ID);
	OC_Hook::clear();
	return;
}

// a clone on its own, where every collaborator these tests use is a mocked
// OCP interface. nextcloud/ocp ships those interfaces without an autoloader,
// so register one rather than require a server to run a unit test.
require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
	foreach (['OCP', 'NCU'] as $prefix) {
		if (!str_starts_with($class, $prefix . '\\')) {
			continue;
		}
		$path = __DIR__ . '/../vendor/nextcloud/ocp/' . $prefix . '/'
			. str_replace('\\', '/', substr($class, strlen($prefix) + 1)) . '.php';
		if (file_exists($path)) {
			require_once $path;
		}
		return;
	}
});
