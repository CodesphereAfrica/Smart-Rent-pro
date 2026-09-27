<?php
declare(strict_types=1);
namespace SmartRentPro\Sms\Drivers;

use SmartRentPro\Sms\SmsProviderInterface;

class LogDriver implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        error_log(sprintf('[SMS-log] to=%s msg=%s', $to, $message));
        return ['sent' => true, 'provider_id' => 'log-' . uniqid(), 'error' => null];
    }
}