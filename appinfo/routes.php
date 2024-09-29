<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

		['name' => 'nuiteqAPI#newBoard', 'url' => '/new', 'verb' => 'POST'],
		['name' => 'nuiteqAPI#getBoards', 'url' => '/list', 'verb' => 'GET'],
		['name' => 'nuiteqAPI#deleteBoard', 'url' => '/delete', 'verb' => 'POST'],

		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
	]
];
