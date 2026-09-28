<?php
/**
 * Pluggable online payment gateways.
 *
 * The giving page never talks to a provider directly. To add a gateway later
 * (e.g. Flutterwave, Paystack, Stripe, an MTN MoMo / Orange Money API):
 *   1. create a class implementing PaymentGatewayDriver
 *   2. register it in PaymentGateway::drivers()
 *   3. in Admin → Giving → Payment Methods, add a method of type
 *      "online_gateway" with its driver key.
 * The giving page and transaction records need no redesign.
 */
interface PaymentGatewayDriver
{
    public function label(): string;

    /**
     * Start a payment for a pending transaction. Return a URL to send the
     * giver to (hosted checkout), or null when the gift is completed offline.
     */
    public function initiate(array $transaction, array $method): ?string;
}

/** Default driver: records the gift as pending for staff to confirm manually. */
final class ManualGateway implements PaymentGatewayDriver
{
    public function label(): string
    {
        return 'Manual confirmation';
    }

    public function initiate(array $transaction, array $method): ?string
    {
        return null;
    }
}

final class PaymentGateway
{
    public static function drivers(): array
    {
        return [
            'manual' => ManualGateway::class,
            // 'flutterwave' => FlutterwaveGateway::class,
        ];
    }

    public static function for(array $method): PaymentGatewayDriver
    {
        $class = self::drivers()[$method['gateway_driver'] ?? 'manual'] ?? ManualGateway::class;
        return new $class();
    }
}
