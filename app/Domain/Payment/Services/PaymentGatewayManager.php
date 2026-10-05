<?php

namespace App\Domain\Payment\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Domain\Payment\Gateways\ManualGateway;
use App\Domain\Payment\Gateways\MidtransGateway;
use App\Domain\Payment\Gateways\XenditGateway;
use App\Domain\Payment\Models\Payment;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * Resolve the requested gateway driver, optionally configured for a specific business.
     */
    public function resolve(string $provider = Payment::PROVIDER_MIDTRANS, ?Business $business = null): PaymentGatewayInterface
    {
        $tenantSettings = $business?->settings['payment_gateway'] ?? [];

        return match ($provider) {
            Payment::PROVIDER_MIDTRANS => new MidtransGateway(
                serverKey: ! empty($tenantSettings['midtrans_server_key']) ? (string) $tenantSettings['midtrans_server_key'] : null,
                clientKey: ! empty($tenantSettings['midtrans_client_key']) ? (string) $tenantSettings['midtrans_client_key'] : null,
                isProduction: isset($tenantSettings['is_production']) ? (bool) $tenantSettings['is_production'] : (bool) config('services.midtrans.is_production', false),
            ),
            Payment::PROVIDER_XENDIT => new XenditGateway(
                secretKey: ! empty($tenantSettings['xendit_secret_key']) ? (string) $tenantSettings['xendit_secret_key'] : null,
                publicKey: ! empty($tenantSettings['xendit_public_key']) ? (string) $tenantSettings['xendit_public_key'] : null,
                webhookToken: ! empty($tenantSettings['xendit_webhook_token']) ? (string) $tenantSettings['xendit_webhook_token'] : null,
                isProduction: isset($tenantSettings['is_production']) ? (bool) $tenantSettings['is_production'] : (bool) config('services.xendit.is_production', false),
            ),
            Payment::PROVIDER_MANUAL, Payment::PROVIDER_CASH => new ManualGateway,
            default => throw new InvalidArgumentException("Payment gateway provider [{$provider}] tidak didukung."),
        };
    }
}
