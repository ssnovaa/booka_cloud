<?php

namespace App\Integrations;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;
use Google\Service\AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest;
use RuntimeException;

class GooglePlayClient
{
    private AndroidPublisher $service;
    private string $package;

    public function __construct(?string $keyFile = null, ?string $packageName = null)
    {
        // --- ИСПРАВЛЕНИЕ: Читаем из config() вместо env() ---
        
        // 1. Берем относительный путь из КОНФИГА (config/services.php)
        //    Это будет работать, даже если config:cache включен.
        $keyFileRelative = $keyFile ?? config('services.google_play.key_file');
        
        // 2. Строим АБСОЛЮТНЫЙ путь к файлу в storage/app/
        $keyFilePath = storage_path('app/' . $keyFileRelative);
        
        // 3. Берем имя пакета из КОНФИГА
        $this->package = $packageName ?? config('services.google_play.package_name');

        // 4. Проверяем
        if (empty($keyFileRelative) || !is_readable($keyFilePath)) {
            // Эта ошибка теперь будет намного понятнее
            throw new RuntimeException("Google Play key file is NOT READABLE at: $keyFilePath (from config 'services.google_play.key_file')");
        }
        
        // --- КОНЕЦ ИСПРАВЛЕНИЯ ---

        $client = new GoogleClient();
        $client->setAuthConfig($keyFilePath); // Используем абсолютный путь
        $client->setScopes(['https://www.googleapis.com/auth/androidpublisher']);

        $this->service = new AndroidPublisher($client);
    }

    /** Subscriptions V2 — получить данные по токену */
    public function getSubscriptionV2(string $purchaseToken, ?string $packageName = null): array
    {
        $resp = $this->service->purchases_subscriptionsv2->get(
            $this->resolvePackage($packageName),
            $purchaseToken
        );
        return json_decode(json_encode($resp), true);
    }

    /** Подтверждение (acknowledge) покупки подписки */
    public function acknowledgeSubscription(string $productId, string $purchaseToken, ?string $packageName = null): void
    {
        $request = new SubscriptionPurchasesAcknowledgeRequest();

        $this->service->purchases_subscriptions->acknowledge(
            $this->resolvePackage($packageName),
            $productId,
            $purchaseToken,
            $request
        );
    }

    private function resolvePackage(?string $packageName = null): string
    {
        return $packageName ?: $this->package;
    }
}