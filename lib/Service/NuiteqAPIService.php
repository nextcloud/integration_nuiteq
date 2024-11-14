<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Nuiteq\Service;

use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use OCA\Nuiteq\AppInfo\Application;
use OCP\AppFramework\Http;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use Throwable;

class NuiteqAPIService {

	private \OCP\Http\Client\IClient $client;

	/**
	 * Service to make requests to Nuiteq Stage API
	 */
	public function __construct(
		private string $appName,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IConfig $config,
		private ICrypto $crypto,
		IClientService $clientService,
	) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	private function getClientKey(string $userId): string {
		$adminClientKey = $this->config->getAppValue(Application::APP_ID, 'client_key', Application::DEFAULT_CLIENT_KEY) ?: Application::DEFAULT_CLIENT_KEY;
		$clientKey = $this->config->getUserValue($userId, Application::APP_ID, 'client_key', $adminClientKey) ?: $adminClientKey;

		// Decrypt the clientKey if it's encrypted
		if ($clientKey !== Application::DEFAULT_CLIENT_KEY) {
			$clientKey = $this->crypto->decrypt($clientKey);
		}
		return $clientKey;
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getBaseUrl(string $userId): string {
		$adminBaseUrl = $this->config->getAppValue(Application::APP_ID, 'base_url', Application::DEFAULT_BASE_URL) ?: Application::DEFAULT_BASE_URL;
		return $this->config->getUserValue($userId, Application::APP_ID, 'base_url', $adminBaseUrl) ?: $adminBaseUrl;
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function getBoards(string $userId): array {
		return $this->request($userId, 'list', [], 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $boardId
	 * @return array
	 */
	public function deleteBoard(string $userId, string $boardId): array {
		return $this->request($userId, 'delete', ['boardId' => $boardId], 'POST');
	}

	/**
	 * @param string $userId
	 * @param string $name
	 * @param string $password
	 * @return array
	 */
	public function newBoard(string $userId, string $name, string $password): array {
		$params = [
			'name' => $name,
			'password' => $password,
		];
		$result = $this->request($userId, 'new', $params, 'POST');
		if (isset($result['boardId'])) {
			$parts = explode('/', $result['boardId']);
			if (count($parts) > 0) {
				$result['id'] = array_pop($parts);
				unset($result['boardId']);
				return $result;
			}
			return ['error' => 'malformed NUITEQ response'];
		}
		return $result;
	}

	/**
	 * @param string $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $userId, string $endPoint, array $params = [],
		string $method = 'GET'): array {
		try {
			$baseUrl = $this->getBaseUrl($userId);
			$clientKey = $this->getClientKey($userId);
			$apiKey = $this->config->getUserValue($userId, Application::APP_ID, 'api_key');
			$url = $baseUrl . '/api/v1/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Nuiteq integration',
					'Content-Type' => 'application/json',
				],
				'json' => [
					'clientKey' => $clientKey,
					'apiKey' => $apiKey,
				],
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['json'] = array_merge($options['json'], $params);
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} elseif ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} elseif ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				try {
					return json_decode($body, true);
				} catch (Exception|Throwable $e) {
					$this->logger->warning('NUITEQ invalid request response : '.$e->getMessage(), ['app' => $this->appName]);
					return ['error' => $this->l10n->t('Invalid response')];
				}
			}
		} catch (ClientException|ServerException $e) {
			$response = $e->getResponse();
			if ($response->getStatusCode() === Http::STATUS_FORBIDDEN) {
				$body = $response->getBody();
				try {
					return json_decode($body, true);
				} catch (Exception|Throwable $e2) {
				}
			}
			return ['error' => $e->getMessage()];
		} catch (Exception|Throwable $e) {
			$this->logger->warning('Nuiteq API error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function login(string $userId, string $login, string $password): array {
		try {
			$baseUrl = $this->getBaseUrl($userId);
			$clientKey = $this->getClientKey($userId);
			$url = $baseUrl . '/api/v1/login';

			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud NUITEQ integration',
					'Content-Type' => 'application/json',
				],
				'json' => [
					'username' => $login,
					'password' => $password,
					'clientKey' => $clientKey,
				],
			];
			$response = $this->client->post($url, $options);
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Invalid credentials')];
			} else {
				try {
					return json_decode($body, true);
				} catch (Exception|Throwable $e) {
					$this->logger->warning('NUITEQ invalid login response : '.$e->getMessage(), ['app' => $this->appName]);
					return ['error' => $this->l10n->t('Invalid response')];
				}
			}
		} catch (ClientException|ServerException $e) {
			$response = $e->getResponse();
			if ($response->getStatusCode() === Http::STATUS_FORBIDDEN) {
				$body = $response->getBody();
				try {
					return json_decode($body, true);
				} catch (Exception|Throwable $e2) {
				}
			}
			return ['error' => $e->getMessage()];
		} catch (Exception|Throwable $e) {
			$this->logger->warning('NUITEQ login error : '.$e->getMessage(), ['app' => $this->appName]);
			return ['error' => $e->getMessage()];
		}
	}
}
