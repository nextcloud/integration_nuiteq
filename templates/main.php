<?php

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$appId = OCA\Nuiteq\AppInfo\Application::APP_ID;
\OCP\Util::addScript($appId, $appId . '-main');
