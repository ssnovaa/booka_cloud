<?php

namespace App\Integrations;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;
use Google\Service\AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest; // Добавлен импорт
use RuntimeException;
use Illuminate\Support\Facades\Log; // Добавлен импорт

class GooglePlayClient
{
    private AndroidPublisher $service;
    private string $package;

    public function __construct(?string $keyFile = null, ?string $packageName = null)
    {
        // --- ИСПРАВЛЕНИЕ: Читаем из config() вместо env() ---
        
        // 1. Берем относительный путь из КОНФИГА (config/services.php)
        $keyFileRelative = $keyFile ?? config('services.google_play.key_file');
        
        // 2. Строим АБСОЛЮТНЫЙ путь к файлу в storage/app/
        $keyFilePath = storage_path('app/' . $keyFileRelative);
        
        // 3. Берем имя пакета из КОНФИГА
        $this->package = $packageName ?? config('services.google_play.package_name');

        // 4. Проверяем
        if (empty($keyFileRelative) || !is_readable($keyFilePath)) {
            Log::error("GooglePlayClient: Service account key file not found or not readable.", ['path' => $keyFilePath]); // 🚨 DEBUG
            throw new RuntimeException("Google Play key file is NOT READABLE at: $keyFilePath (from config 'services.google_play.key_file')");
        }
        
        Log::info("GooglePlayClient: Initializing with package: {$this->package}"); // 🚨 DEBUG

        // --- КОНЕЦ ИСПРАВЛЕНИЯ ---

        $client = new GoogleClient();
        $client->setAuthConfig($keyFilePath); // Используем абсолютный путь
        $client->setScopes(['https://www.googleapis.com/auth/androidpublisher']);

        $this->service = new AndroidPublisher($client);
    }

    /** Subscriptions V2 — получить данные по токену */
    public function getSubscriptionV2(string $purchaseToken, ?string $packageName = null): array
    {
        $package = $this->resolvePackage($packageName);
        $tokenSuffix = substr($purchaseToken, -10);

        Log::info("GooglePlayClient: [GET] Fetching Subscription V2 details.", [
            'package' => $package,
            'token_suffix' => $tokenSuffix,
        ]); // 🚨 DEBUG START

        try {
            $resp = $this->service->purchases_subscriptionsv2->get(
                $package,
                $purchaseToken
            );

            $result = json_decode(json_encode($resp), true);

            Log::info("GooglePlayClient: [GET] Subscription V2 successful.", [
                'token_suffix' => $tokenSuffix,
                'state' => $result['subscriptionState'] ?? 'N/A',
            ]); // 🚨 DEBUG SUCCESS

            return $result;
        } catch (\Throwable $e) {
            // Логируем любые ошибки, включая сетевые и ошибки Google API
            Log::error("GooglePlayClient: [GET] CRITICAL API error.", [
                'token_suffix' => $tokenSuffix,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]); // 🚨 DEBUG ERROR
            throw $e;
        }
    }

    /** Подтверждение (acknowledge) покупки подписки */
    public function acknowledgeSubscription(string $productId, string $purchaseToken, ?string $packageName = null): void
    {
        $package = $this->resolvePackage($packageName);
        $tokenSuffix = substr($purchaseToken, -10);

        Log::info("GooglePlayClient: [ACK] Attempting Acknowledge.", [
            'package' => $package,
            'product_id' => $productId,
            'token_suffix' => $tokenSuffix,
        ]); // 🚨 DEBUG START

        $request = new SubscriptionPurchasesAcknowledgeRequest();

        try {
            $this->service->purchases_subscriptions->acknowledge(
                $package,
                $productId,
                $purchaseToken,
                $request
            );

            Log::info("GooglePlayClient: [ACK] Acknowledge successful.", [
                'token_suffix' => $tokenSuffix,
                'product_id' => $productId,
            ]); // 🚨 DEBUG SUCCESS
        } catch (\Throwable $e) {
            // Логируем любые ошибки, включая сетевые и ошибки Google API
            Log::error("GooglePlayClient: [ACK] CRITICAL API error.", [
                'token_suffix' => $tokenSuffix,
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]); // 🚨 DEBUG ERROR
            throw $e;
        }
    }

    private function resolvePackage(?string $packageName = null): string
    {
        return $packageName ?: $this->package;
    }
}