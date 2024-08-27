<?php
/**
 * Nextcloud - Nuiteq integration
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Nuiteq\AppInfo;

use OCA\Nuiteq\Listener\AddContentSecurityPolicyListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Util;

/**
 * Class Application
 *
 * @package OCA\Nuiteq\AppInfo
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_nuiteq';
	public const DEFAULT_BASE_URL = 'https://nuiteqstage.se';
	public const DEFAULT_CLIENT_KEY = 'EJZBdijln5TcgjAbzxDwm8Ms0AQa99RsBPiWVEhoMMg0dnsLYZiCS0R4C6pmspt';

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, AddContentSecurityPolicyListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (
			IInitialState $initialState,
			IConfig $config,
			?string $userId
		) {
			$adminBaseUrl = $config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_BASE_URL) ?: Application::DEFAULT_BASE_URL;
			if ($userId === null) {
				$baseUrl = $adminBaseUrl;
			} else {
				$baseUrl = $config->getUserValue($userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;
			}
			$overrideClick = $config->getAppValue(Application::APP_ID, 'override_link_click', '0') === '1';

			$initialState->provideInitialState('base_url', $baseUrl);
			// TODO add setting to set this value
			$initialState->provideInitialState('override_link_click', $overrideClick);
			Util::addScript(self::APP_ID, self::APP_ID . '-standalone');
		});
	}
}
