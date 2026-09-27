<?php
declare(strict_types=1);
namespace SmartRentPro\Sms\Drivers;

use SmartRentPro\Sms\SmsProviderInterface;

class TwilioDriver implements SmsProviderInterface
{
    public function send(string $to, string $message): array
    {
        $sid   = $_ENV['TWILIO_SID']   ?? '';
        $token = $_ENV['TWILIO_TOKEN'] ?? '';
        $from  = $_ENV['TWILIO_FROM']  ?? '';
        if ($sid === '' || $token === '' || $from === '') {
            return ['sent' => false, 'provider_id' => null, 'error' => 'Twilio not configured'];
        }
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $sid . ':' . $token,
            CURLOPT_POSTFIELDS => http_build_query(['To' => $to, 'From' => $from, 'Body' => $message]),
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
            'provider_id' => $json['sid'] ?? null,
            'error' => $code >= 300 ? ($json['message'] ?? 'HTTP ' . $code) : null,
        ];
    }
}