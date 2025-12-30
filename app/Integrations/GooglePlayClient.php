<?php

namespace App\Integrations;

use Google\Client as GoogleClient;
use Google\Service\AndroidPublisher;
use Google\Service\AndroidPublisher\SubscriptionPurchasesAcknowledgeRequest;
use RuntimeException;
use Illuminate\Support\Facades\Log;

class GooglePlayClient
{
    private AndroidPublisher $service;
    private string $package;

    public function __construct(?string $keyFile = null, ?string $packageName = null)
    {
        // 1. Беремо ім'я пакета з конфіга
        $this->package = $packageName ?? config('services.google_play.package_name');

        $client = new GoogleClient();
        $client->setScopes(['https://www.googleapis.com/auth/androidpublisher']);

        // --- НОВИЙ БЛОК: ПРІОРИТЕТ ЗМІННІЙ ОТОЧЕННЯ ---
        $jsonKey = env('GOOGLE_PLAY_SERVICE_ACCOUNT');

        if (!empty($jsonKey)) {
            // Якщо в Railway задана змінна з текстом JSON — використовуємо її
            $config = json_decode($jsonKey, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("GooglePlayClient: JSON in GOOGLE_PLAY_SERVICE_ACCOUNT is invalid.");
                throw new RuntimeException("Invalid JSON in GOOGLE_PLAY_SERVICE_ACCOUNT variable.");
            }
            $client->setAuthConfig($config);
            Log::info("GooglePlayClient: Initialized using Environment Variable.");
        } else {
            // --- ЗАПАСНИЙ ВАРІАНТ: ПОШУК ФАЙЛУ (як було раніше) ---
            $keyFileRelative = $keyFile ?? config('services.google_play.key_file');
            $keyFilePath = storage_path('app/' . $keyFileRelative);

            if (empty($keyFileRelative) || !is_readable($keyFilePath)) {
                Log::error("GooglePlayClient: Service account key not found in ENV and file not readable.", ['path' => $keyFilePath]);
                throw new RuntimeException("Google Play credentials not found. Set GOOGLE_PLAY_SERVICE_ACCOUNT env var.");
            }

            $client->setAuthConfig($keyFilePath);
            Log::info("GooglePlayClient: Initialized using local file: $keyFileRelative");
        }
        // --- КІНЕЦЬ ПРАВКИ ---

        $this->service = new AndroidPublisher($client);
    }

    /** Subscriptions V2 — отримати дані по токену */
    public function getSubscriptionV2(string $purchaseToken, ?string $packageName = null): array
    {
        $package = $this->resolvePackage($packageName);
        $tokenSuffix = substr($purchaseToken, -10);

        Log::info("GooglePlayClient: [GET] Fetching Subscription V2 details.", [
            'package' => $package,
            'token_suffix' => $tokenSuffix,
        ]);

        try {
            $resp = $this->service->purchases_subscriptionsv2->get(
                $package,
                $purchaseToken
            );

            return json_decode(json_encode($resp), true);
        } catch (\Throwable $e) {
            Log::error("GooglePlayClient: [GET] API error.", [
                'token_suffix' => $tokenSuffix,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /** Підтвердження (acknowledge) покупки підписки */
    public function acknowledgeSubscription(string $productId, string $purchaseToken, ?string $packageName = null): void
    {
        $package = $this->resolvePackage($packageName);
        $tokenSuffix = substr($purchaseToken, -10);

        Log::info("GooglePlayClient: [ACK] Attempting Acknowledge.", [
            'package' => $package,
            'product_id' => $productId,
            'token_suffix' => $tokenSuffix,
        ]);

        $request = new SubscriptionPurchasesAcknowledgeRequest();

        try {
            $this->service->purchases_subscriptions->acknowledge(
                $package,
                $productId,
                $purchaseToken,
                $request
            );
            Log::info("GooglePlayClient: [ACK] Success.");
        } catch (\Throwable $e) {
            Log::error("GooglePlayClient: [ACK] API error.", [
                'token_suffix' => $tokenSuffix,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function resolvePackage(?string $packageName = null): string
    {
        return $packageName ?: $this->package;
    }
}