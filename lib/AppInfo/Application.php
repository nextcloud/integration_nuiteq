<?php
/**
 * Nextcloud - Nuiteq integration
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2022
 */

namespace OCA\Nuiteq\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

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
	}

	public function boot(IBootContext $context): void {
	}
}

