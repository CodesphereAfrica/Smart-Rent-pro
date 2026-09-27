<?php
declare(strict_types=1);
namespace SmartRentPro\Sms\Drivers;

use SmartRentPro\Sms\SmsProviderInterface;

/**
 * Pluggable driver for any Rwandan SMS gateway exposing a simple HTTP JSON API.
 * Configure via .env: RW_SMS_URL, RW_SMS_KEY, RW_SMS_SENDER.
 */
class RwandaGatewayDriver implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        $url    = $_ENV['RW_SMS_URL']    ?? '';
        $key    = $_ENV['RW_SMS_KEY']    ?? '';
        $sender = $_ENV['RW_SMS_SENDER'] ?? 'SMARTRENT';
        if ($url === '' || $key === '') {
            return ['sent' => false, 'provider_id' => null, 'error' => 'Rwanda SMS gateway not configured'];
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'sender' => $sender, 'to' => $to, 'message' => $message,
            ]),
            CURLOPT_TIMEOUT => 15,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($err) return ['sent' => false, 'provider_id' => null, 'error' => $err];
        $json = json_decode((string)$body, true) ?: [];
        return [
            'sent' => $code >= 200 && $code < 300,
            'provider_id' => $json['id'] ?? ($json['message_id'] ?? null),
            'error' => $code >= 300 ? ($json['error'] ?? 'HTTP ' . $code) : null,
        ];
    }
}