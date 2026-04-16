<?php

namespace App\Services;

use InvalidArgumentException;

class PaymentReturnTicketService
{
    public function make(array $claims)
    {
        $payload = $this->normalizeClaims($claims);
        $json = $this->encodePayload($payload);

        return $this->base64UrlEncode($json) . '.' . $this->sign($json);
    }

    public function verify($ticket)
    {
        if (!is_string($ticket) || trim($ticket) === '') {
            return null;
        }

        $parts = explode('.', $ticket, 2);
        if (count($parts) !== 2) {
            return null;
        }

        list($encodedPayload, $providedSign) = $parts;
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($payloadJson === null) {
            return null;
        }

        $expectedSign = $this->sign($payloadJson);
        if (!hash_equals($expectedSign, $providedSign)) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (!$this->isPayloadValid($payload)) {
            return null;
        }

        return $payload;
    }

    protected function normalizeClaims(array $claims)
    {
        $orderNo = trim((string) data_get($claims, 'order_no', ''));
        $nonce = trim((string) data_get($claims, 'nonce', ''));
        $origin = trim((string) data_get($claims, 'origin', 'B'));
        $returnPath = trim((string) data_get($claims, 'return_path', '/payment/return'));
        $iat = (int) data_get($claims, 'iat', time());
        $exp = (int) data_get($claims, 'exp', 0);

        if ($orderNo === '' || $nonce === '' || $origin === '' || $returnPath === '') {
            throw new InvalidArgumentException('Payment return ticket claims are incomplete');
        }

        if ($exp <= 0) {
            $exp = $iat + (int) config('site.payment_return_sign_ttl', 300);
        }

        return [
            'v' => 1,
            'order_no' => $orderNo,
            'origin' => strtoupper($origin),
            'nonce' => $nonce,
            'iat' => $iat,
            'exp' => $exp,
            'return_path' => $returnPath,
        ];
    }

    protected function isPayloadValid(array $payload)
    {
        $orderNo = trim((string) data_get($payload, 'order_no', ''));
        $origin = trim((string) data_get($payload, 'origin', ''));
        $nonce = trim((string) data_get($payload, 'nonce', ''));
        $iat = (int) data_get($payload, 'iat', 0);
        $exp = (int) data_get($payload, 'exp', 0);

        if ($orderNo === '' || $origin === '' || $nonce === '' || $iat <= 0 || $exp <= 0) {
            return false;
        }

        $now = time();
        $futureSkew = (int) config('site.payment_return_sign_future_skew', 300);
        if ($iat > ($now + $futureSkew)) {
            return false;
        }

        if ($exp < $now) {
            return false;
        }

        return true;
    }

    protected function sign($payloadJson)
    {
        $secret = $this->getSecret();
        return base64_encode(hash_hmac('sha256', $payloadJson, $secret, true));
    }

    protected function getSecret()
    {
        $secret = (string) config('site.payment_return_sign_secret', '');
        if ($secret === '') {
            $secret = (string) config('site.shadow_order_sign_secret', '');
        }

        if ($secret === '') {
            throw new InvalidArgumentException('Payment return sign secret is empty');
        }

        return $secret;
    }

    protected function encodePayload(array $payload)
    {
        ksort($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    protected function base64UrlDecode($value)
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}