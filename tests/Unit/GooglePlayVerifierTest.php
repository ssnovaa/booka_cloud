<?php

namespace Tests\Unit;

use App\Integrations\GooglePlayClient;
use App\Services\Subscriptions\GooglePlayVerifier;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class GooglePlayVerifierTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function makeVerifier(): GooglePlayVerifier
    {
        return new GooglePlayVerifier(Mockery::mock(GooglePlayClient::class));
    }

    private function bootstrapLogFacade(): void
    {
        $container = new Container();
        Facade::setFacadeApplication($container);

        $container->instance('log', new class implements LoggerInterface {
            public function emergency($message, array $context = []) {}
            public function alert($message, array $context = []) {}
            public function critical($message, array $context = []) {}
            public function error($message, array $context = []) {}
            public function warning($message, array $context = []) {}
            public function notice($message, array $context = []) {}
            public function info($message, array $context = []) {}
            public function debug($message, array $context = []) {}
            public function log($level, $message, array $context = []) {}
        });
    }

    public function testNormalizeV2MapsPendingState(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $normalize = $ref->getMethod('normalizeV2');
        $normalize->setAccessible(true);

        $expiresAt = '2025-01-01T12:00:00.000Z';
        $payload = [
            'subscriptionState' => 'SUBSCRIPTION_STATE_PENDING',
            'startTime' => '2024-12-25T10:00:00.000Z',
            'expiryTime' => $expiresAt,
        ];

        $normalized = $normalize->invoke($verifier, $payload);

        $this->assertSame('pending', $normalized['status']);
        $this->assertInstanceOf(Carbon::class, $normalized['expires_at']);
        $this->assertTrue($normalized['expires_at']->equalTo(Carbon::parse($expiresAt)));
    }

    public function testNormalizeV2CoversAllKnownStates(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $normalize = $ref->getMethod('normalizeV2');
        $normalize->setAccessible(true);

        $stateMap = [
            'SUBSCRIPTION_STATE_UNSPECIFIED'     => 'pending',
            'SUBSCRIPTION_STATE_PENDING'         => 'pending',
            'SUBSCRIPTION_STATE_ACTIVE'          => 'active',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace',
            'SUBSCRIPTION_STATE_ON_HOLD'         => 'on_hold',
            'SUBSCRIPTION_STATE_PAUSED'          => 'paused',
            'SUBSCRIPTION_STATE_CANCELED'        => 'canceled',
            'SUBSCRIPTION_STATE_EXPIRED'         => 'expired',
        ];

        foreach ($stateMap as $apiValue => $expectedStatus) {
            $normalized = $normalize->invoke($verifier, [
                'subscriptionState' => $apiValue,
                'expiryTime' => '2030-01-01T00:00:00.000Z',
            ]);

            $this->assertSame(
                $expectedStatus,
                $normalized['status'],
                "State {$apiValue} must map to {$expectedStatus}"
            );
        }

        $normalizedUnknown = $normalize->invoke($verifier, [
            'subscriptionState' => 'SUBSCRIPTION_STATE_FUTURE_UNKNOWN',
        ]);

        $this->assertSame('unknown', $normalizedUnknown['status']);
    }

    public function testDetermineIsPaidReturnsFalseForPendingSubscription(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $determineIsPaid = $ref->getMethod('determineIsPaid');
        $determineIsPaid->setAccessible(true);

        $paidUntil = Carbon::now()->addDay();

        $isPaid = $determineIsPaid->invoke($verifier, 'pending', $paidUntil);

        $this->assertFalse($isPaid);
    }

    public function testDetermineIsPaidReturnsFalseForUnknownStatus(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $determineIsPaid = $ref->getMethod('determineIsPaid');
        $determineIsPaid->setAccessible(true);

        $paidUntil = Carbon::now()->addDay();

        $isPaid = $determineIsPaid->invoke($verifier, 'unknown', $paidUntil);

        $this->assertFalse($isPaid);
    }

    public function testDetermineIsPaidReturnsTrueForCanceledButUnexpired(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $determineIsPaid = $ref->getMethod('determineIsPaid');
        $determineIsPaid->setAccessible(true);

        $paidUntil = Carbon::now()->addHour();

        $isPaid = $determineIsPaid->invoke($verifier, 'canceled', $paidUntil);

        $this->assertTrue($isPaid, 'Оставляем доступ до истечения оплаченного периода');
    }

    public function testDetermineIsPaidReturnsFalseForExpiredCanceled(): void
    {
        $verifier = $this->makeVerifier();
        $ref = new \ReflectionClass($verifier);
        $determineIsPaid = $ref->getMethod('determineIsPaid');
        $determineIsPaid->setAccessible(true);

        $paidUntil = Carbon::now()->subHour();

        $isPaid = $determineIsPaid->invoke($verifier, 'canceled', $paidUntil);

        $this->assertFalse($isPaid, 'После окончания периода доступ должен пропасть');
    }

    public function testAcknowledgeIsCalledWhenStateIsPending(): void
    {
        $this->bootstrapLogFacade();

        $client = Mockery::mock(GooglePlayClient::class);
        $client->expects('acknowledgeSubscription')
            ->once()
            ->with('product.sku', 'token-123', 'com.alt.app');

        $verifier = new GooglePlayVerifier($client);
        $ref = new \ReflectionClass($verifier);
        $ackMethod = $ref->getMethod('acknowledgeIfNeeded');
        $ackMethod->setAccessible(true);

        $raw = [
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_PENDING',
        ];

        $ackMethod->invoke($verifier, $raw, 'product.sku', 'token-123', 'com.alt.app');

        $this->assertSame('ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED', $raw['acknowledgementState']);
    }

    public function testAcknowledgeSkippedWhenAlreadyAcknowledged(): void
    {
        $this->bootstrapLogFacade();

        $client = Mockery::mock(GooglePlayClient::class);
        $client->expects('acknowledgeSubscription')->never();

        $verifier = new GooglePlayVerifier($client);
        $ref = new \ReflectionClass($verifier);
        $ackMethod = $ref->getMethod('acknowledgeIfNeeded');
        $ackMethod->setAccessible(true);

        $raw = [
            'acknowledgementState' => 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
        ];

        $ackMethod->invoke($verifier, $raw, 'product.sku', 'token-123', null);

        $this->assertSame('ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED', $raw['acknowledgementState']);
    }
}