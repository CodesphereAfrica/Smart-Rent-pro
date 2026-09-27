<?php
declare(strict_types=1);
namespace SmartRentPro\Sms;

interface SmsProviderInterface
{
    /** @return array{sent:bool, provider_id:?string, error:?string} */
    public function send(string $to, string $message): array;
}