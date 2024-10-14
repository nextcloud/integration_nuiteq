<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Listener;

use OCA\Nuiteq\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class AddContentSecurityPolicyListener implements IEventListener {
	/**
	 * @var IConfig
	 */
	protected $config;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IConfig $config,
		?string $userId) {
		$this->config = $config;
		$this->userId = $userId;
	}

	public function handle(Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		$adminBaseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_BASE_URL) ?: Application::DEFAULT_BASE_URL;
		if ($this->userId === null) {
			$baseUrl = $adminBaseUrl;
		} else {
			$baseUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;
		}

		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain('\'self\'');
		$csp->addAllowedFrameAncestorDomain('\'self\'');
		$csp->addAllowedFrameDomain($baseUrl);

		$event->addPolicy($csp);
	}
}
