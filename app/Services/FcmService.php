<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Log\LoggerInterface;
use Throwable;
use Illuminate\Support\Facades\Log;

/**
 * Сервіс відправлення push-сповіщень через FCM HTTP v1.
 * - Пріоритетно використовує JSON-ключ із змінних оточення Railway.
 * - Детально логує відповіді API Google у разі помилок (400, 401, 404).
 */
class FcmService
{
    /** @var string */
    private string $projectId;

    /** @var string|array */
    private $credentials;

    /** @var Client */
    private Client $http;

    /** @var LoggerInterface */
    private LoggerInterface $log;

    private ?string $cachedToken = null;
    private int $cachedTokenExp = 0;

    public function __construct(LoggerInterface $log)
    {
        // Отримуємо Project ID з конфігу (config/fcm.php)
        $this->projectId = (string) config('fcm.project_id');
        $this->log = $log;

        // Пріоритет: спочатку перевіряємо змінні оточення Railway
        $jsonKey = env('FCM_CREDENTIALS_JSON');

        if (!empty($jsonKey)) {
            // Використовуємо JSON-текст безпосередньо
            $this->credentials = json_decode($jsonKey, true);
            $this->log->info('FcmService: Initialized using Environment Variable.');
        } else {
            // Запасний варіант — шлях до локального файлу
            $this->credentials = (string) config('fcm.credentials_json');
            $this->log->info('FcmService: Initialized using local file path: ' . $this->credentials);
        }

        $this->http = new Client([
            'timeout'         => 5.0,
            'connect_timeout' => 3.0,
            'http_errors'     => false,
            'verify'          => true,
            'curl'            => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
            'on_stats'        => function (TransferStats $stats) use ($log) {
                $time = $stats->getTransferTime();
                $log->debug('FCM request completed', [
                    'time_ms'     => (int) round($time * 1000),
                    'status_code' => optional($stats->getResponse())->getStatusCode(),
                ]);
            },
        ]);
    }

    /**
     * Надіслати повідомлення на конкретний FCM-токен.
     */
    public function sendToToken(string $token, ?string $title = null, ?string $body = null, array $data = []): bool
    {
        try {
            $accessToken = $this->getAccessToken();
            $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $this->projectId);

            $message = ['token' => $token];

            if ($title !== null || $body !== null) {
                $message['notification'] = [
                    'title' => $title ?? '',
                    'body'  => $body ?? '',
                ];
                $message['android'] = [
                    'priority'     => 'HIGH',
                    'notification' => ['channel_id' => 'booka_default', 'sound' => 'default'],
                ];
                $message['apns'] = [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['sound' => 'default']],
                ];
            } else {
                $message['apns'] = [
                    'headers' => ['apns-priority' => '5'],
                    'payload' => ['aps' => ['content-available' => 1]],
                ];
                $message['android'] = ['priority' => 'HIGH'];
            }

            $cleanData = [];
            foreach ($data as $k => $v) {
                if ($v !== null) $cleanData[(string) $k] = (string) $v;
            }
            if ($cleanData) $message['data'] = $cleanData;

            // Відправляємо запит до Google
            $res = $this->http->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json; charset=UTF-8',
                ],
                'json' => ['message' => $message],
            ]);

            $statusCode = $res->getStatusCode();
            $responseBody = (string) $res->getBody();

            // Якщо статус не 200 — логуємо детальну помилку від Google
            if ($statusCode !== 200) {
                Log::error("FCM API Error Details", [
                    'status_code' => $statusCode,
                    'project_id'  => $this->projectId,
                    'response'    => json_decode($responseBody, true),
                    'token_prefix' => substr($token, 0, 10) . '...'
                ]);
                return false;
            }

            return true;
        } catch (Throwable $e) {
            $this->log->error('FCM send critical failure', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Отримати access_token Google OAuth2.
     */
    private function getAccessToken(): string
    {
        $now = time();
        if ($this->cachedToken && $this->cachedTokenExp > ($now + 60)) {
            return $this->cachedToken;
        }

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        if (is_array($this->credentials)) {
            // Використовуємо дані прямо з масиву (ENV)
            $creds = new ServiceAccountCredentials($scopes, $this->credentials);
        } else {
            // Використовуємо шлях до файлу (запасний варіант)
            if (!is_file($this->credentials)) {
                throw new \RuntimeException('FCM credentials not found: ' . $this->credentials);
            }
            $creds = new ServiceAccountCredentials($scopes, $this->credentials);
        }

        $auth = $creds->fetchAuthToken();

        if (empty($auth['access_token'])) {
            throw new \RuntimeException('Unable to fetch FCM access token');
        }

        $this->cachedToken = (string) $auth['access_token'];
        $this->cachedTokenExp = !empty($auth['expires_at']) ? (int) $auth['expires_at'] : $now + 3300;

        return $this->cachedToken;
    }
}