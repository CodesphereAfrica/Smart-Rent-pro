<?php
declare(strict_types=1);
namespace SmartRentPro\Sms;

require_once __DIR__ . '/SmsProviderInterface.php';

class SmsManager
{
    public static function driver(): SmsProviderInterface
    {
        $which = SMS_DRIVER;
        $cls = match ($which) {
            'twilio'  => __NAMESPACE__ . '\\Drivers\\TwilioDriver',
            'rwanda'  => __NAMESPACE__ . '\\Drivers\\RwandaGatewayDriver',
            default   => __NAMESPACE__ . '\\Drivers\\LogDriver',
        };
        return new $cls();
    }

    public static function send(string $to, string $message): array
    {
        try {
            return self::driver()->send($to, $message);
        } catch (\Throwable $e) {
            return ['sent' => false, 'provider_id' => null, 'error' => $e->getMessage()];
        }
    }
}