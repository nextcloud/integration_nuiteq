<?php

/**
 * @copyright Copyright (c) 2022, Julien Veyssier <eneiluj@posteo.net>
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
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
