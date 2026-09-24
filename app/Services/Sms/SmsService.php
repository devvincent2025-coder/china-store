<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SmartSMSSolutions (smartsmssolutions.com) gateway — used for vendor phone-number OTP
 * verification (see App\Services\Vendor\VendorIdentityService).
 *
 * Integrated against their long-documented GET endpoint (username/password/sender/recipient/
 * message query params). This is the only concrete, independently verifiable API contract
 * available at the time this was written — their current docs portal
 * (docs.smartsmssolutions.com / developer.smartsmssolutions.com) blocks automated access, so it
 * could not be cross-checked against whatever their dashboard currently recommends. If your
 * SmartSMSSolutions account page shows a newer token-based API instead of a username/password
 * one, update send() below — every OTP call in the app already goes through this one method.
 *
 * Falls back to logging the message (no real send) whenever SMARTSMS_USERNAME/PASSWORD aren't
 * set, so phone verification stays fully testable without a live account.
 */
class SmsService
{
    // https, not the bare http the legacy docs snippet uses — credentials go in the query
    // string, so this must never go out in plaintext.
    private const ENDPOINT = 'https://api.smartsmssolutions.com/smsapi.php';

    // From SmartSMSSolutions' own documented error codes for this endpoint.
    private const ERROR_MESSAGES = [
        '2904' => 'SMS sending failed',
        '2905' => 'Invalid SmartSMSSolutions username/password',
        '2906' => 'SmartSMSSolutions credit exhausted',
        '2907' => 'SmartSMSSolutions gateway unavailable',
        '2910' => 'SmartSMSSolutions username is empty',
        '2911' => 'SmartSMSSolutions password is empty',
        '2912' => 'Recipient is empty',
        '2913' => 'Message is empty',
        '2914' => 'Sender is empty',
        '2915' => 'One or more required fields are empty',
        '2916' => 'Sender ID not allowed',
    ];

    public function isConfigured(): bool
    {
        return filled(config('services.smart_sms.username')) && filled(config('services.smart_sms.password'));
    }

    public function send(string $toPhoneNumber, string $message): void
    {
        if (! $this->isConfigured()) {
            Log::info('SMS (SmartSMSSolutions not configured — logged only)', [
                'to' => $toPhoneNumber,
                'message' => $message,
            ]);

            return;
        }

        try {
            $response = Http::timeout(10)->get(self::ENDPOINT, [
                'username' => config('services.smart_sms.username'),
                'password' => config('services.smart_sms.password'),
                'sender' => config('services.smart_sms.sender_id'),
                'recipient' => $toPhoneNumber,
                'message' => $message,
            ]);

            $this->assertSuccessful($response->body());
        } catch (\Throwable $e) {
            // An OTP SMS failing to send should surface to the caller (they need to tell the
            // vendor "try again"), never fail silently the way a marketing notification would.
            Log::error('SmartSMSSolutions send failed', ['to' => $toPhoneNumber, 'message' => $e->getMessage()]);

            throw new SmsSendException('Could not send SMS via SmartSMSSolutions: '.$e->getMessage());
        }
    }

    /**
     * SmartSMSSolutions doesn't return JSON — a success looks like "OK <units_used> <failed>",
     * anything else is a bare numeric error code from ERROR_MESSAGES above.
     */
    private function assertSuccessful(string $rawResponse): void
    {
        $parts = explode(' ', trim($rawResponse));
        $first = $parts[0] ?? '';

        if (strtolower($first) === 'ok') {
            return;
        }

        $message = self::ERROR_MESSAGES[$first] ?? "Unrecognized SmartSMSSolutions response: {$rawResponse}";

        Log::error('SmartSMSSolutions rejected the SMS', ['response' => $rawResponse]);

        throw new SmsSendException($message);
    }
}
