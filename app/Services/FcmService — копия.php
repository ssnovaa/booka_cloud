<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 🇺🇦 Сервіс відправлення push-сповіщень через FCM HTTP v1.
 * - Кешує access_token Google, щоб не дергати OAuth на кожне повідомлення
 * - Використовує cURL-хендлер Guzzle (рекомендовано мати увімкнений ext-curl)
 * - Таймаути, форс IPv4 (на випадок проблемних IPv6-маршрутів)
 *
 * Налаштування в config/fcm.php:
 *   'project_id'       => env('FCM_PROJECT_ID'),
 *   'credentials_json' => storage_path('app/google/service-account.json'),
 */
class FcmService
{
    /** @var string */
    private string $projectId;

    /** @var string */
    private string $credentialsPath;

    /** @var Client */
    private Client $http;

    /** @var LoggerInterface */
    private LoggerInterface $log;

    /** 🇺🇦 Кешований токен доступу Google OAuth2 */
    private ?string $cachedToken = null;

    /** 🇺🇦 Час закінчення дії токена (unix time) */
    private int $cachedTokenExp = 0;

    public function __construct(LoggerInterface $log)
    {
        $this->projectId       = (string) config('fcm.project_id');
        $this->credentialsPath = (string) config('fcm.credentials_json');

        // 🇺🇦 Загальні налаштування HTTP-клієнта
        $this->http = new Client([
            'timeout'         => 5.0,
            'connect_timeout' => 3.0,
            'http_errors'     => false,
            'verify'          => true,
            'curl'            => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
            'on_stats'        => function (TransferStats $stats) use ($log) {
                $uri = (string) $stats->getEffectiveUri();
                $time = $stats->getTransferTime();
                $log->debug('FCM request completed', [
                    'uri'         => $uri,
                    'time'        => $time,
                    'time_ms'     => (int) round($time * 1000),
                    'status_code' => optional($stats->getResponse())->getStatusCode(),
                ]);
            },
        ]);

        $this->log = $log;
    }

    /**
     * Надіслати повідомлення на конкретний FCM-токен.
     *
     * @param string      $token  FCM device token
     * @param string|null $title  Заголовок повідомлення. Якщо null — не додаємо notification-блок (тільки data).
     * @param string|null $body   Текст повідомлення. Якщо null — не додаємо notification-блок (тільки data).
     * @param array       $data   Додаткові data-поля (map<string,string> в результаті).
     *
     * @return bool true, якщо HTTP 200–299
     */
    function sendToToken(string $token, ?string $title = null, ?string $body = null, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();
        $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $this->projectId);

        // 🇺🇦 Базове повідомлення
        $message = [
            'token' => $token,
        ];

        // Якщо title/body не передані (null) — шлемо «тихе» data-повідомлення
        if ($title !== null || $body !== null) {
            $message['notification'] = [
                'title' => $title ?? '',
                'body'  => $body ?? '',
            ];
        }

        $message['android'] = [
            'priority'     => 'HIGH',
            'notification' => [
                'channel_id' => 'booka_default',
                'sound'      => 'default',
            ],
        ];
        $message['apns'] = [
            'headers' => ['apns-priority' => '10'],
            'payload' => ['aps' => ['sound' => 'default', 'content-available' => 1]],
        ];

        // 🇺🇦 FCM data має бути map<string,string>; прибираємо лише null і кастимо у рядки
        $cleanData = [];
        foreach ($data as $k => $v) {
            if ($v !== null) {
                $cleanData[(string) $k] = (string) $v;
            }
        }
        if ($cleanData) {
            $message['data'] = $cleanData;
        }

        $payload = ['message' => $message];

        $elapsed = null;
        try {
            $res = $this->http->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type'  => 'application/json; charset=UTF-8',
                ],
                'json' => $payload,
            ]);

            $code = $res->getStatusCode();
            $bodyStr = (string) $res->getBody();

            if ($code < 200 || $code >= 300) {
                $this->log->warning('FCM send failed', [
                    'token'   => $token,
                    'code'    => $code,
                    'body'    => $bodyStr,
                    'message' => $message,
                ]);
                return false;
            }

            $this->log->info('FCM sent successfully', [
                'token' => $token,
                'code'  => $code,
            ]);

            return true;
        } catch (RequestException $e) {
            $this->log->error('FCM request exception', [
                'token'   => $token,
                'error'   => $e->getMessage(),
                'handler' => 'guzzle',
            ]);
        } catch (Throwable $e) {
            $this->log->error('FCM unexpected exception', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Отримати (і, при потребі, оновити) access_token для FCM HTTP v1.
     *
     * @return string
     */
    private function getAccessToken(): string
    {
        $now = time();

        if ($this->cachedToken && $this->cachedTokenExp > ($now + 60)) {
            return $this->cachedToken;
        }

        if (!is_file($this->credentialsPath)) {
            throw new \RuntimeException('FCM credentials file not found: ' . $this->credentialsPath);
        }

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $creds = new ServiceAccountCredentials($scopes, $this->credentialsPath);
        $auth = $creds->fetchAuthToken();

        if (empty($auth['access_token'])) {
            throw new \RuntimeException('Unable to fetch FCM access token');
        }

        $this->cachedToken = (string) $auth['access_token'];

        // 🇺🇦 Більшість реалізацій повертає expires_at (unix time). Якщо ні — ставимо ~55 хв.
        if (!empty($auth['expires_at'])) {
            $this->cachedTokenExp = (int) $auth['expires_at'];
        } else {
            $this->cachedTokenExp = $now + 3300; // 55 хвилин
        }

        return $this->cachedToken;
    }
}
