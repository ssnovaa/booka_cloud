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
        $this->projectId = (string) config('fcm.project_id');
        $this->log = $log;

        // --- ІСПРАВЛЕННЯ: Пріоритет змінній оточення ---
        $jsonKey = env('FCM_CREDENTIALS_JSON');

        if (!empty($jsonKey)) {
            // Якщо є текст у змінній — декодуємо його в масив
            $this->credentials = json_decode($jsonKey, true);
            $this->log->info('FcmService: Initialized using Environment Variable.');
        } else {
            // Запасний варіант — шлях до файлу (як було раніше)
            $this->credentials = (string) config('fcm.credentials_json');
            $this->log->info('FcmService: Using local credentials path: ' . $this->credentials);
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

            $res = $this->http->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json; charset=UTF-8',
                ],
                'json' => ['message' => $message],
            ]);

            return $res->getStatusCode() === 200;
        } catch (Throwable $e) {
            $this->log->error('FCM send failed', ['error' => $e->getMessage()]);
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

        // --- ІСПРАВЛЕННЯ: Підтримка масиву або шляху до файлу ---
        if (is_array($this->credentials)) {
            // Використовуємо масив прямо з ENV
            $creds = new ServiceAccountCredentials($scopes, $this->credentials);
        } else {
            // Перевіряємо наявність файлу, якщо ENV порожній
            if (!is_file($this->credentials)) {
                throw new \RuntimeException('FCM credentials not found (no ENV and no file).');
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