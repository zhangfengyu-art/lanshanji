<?php

namespace App\Services\Google;

class GoogleOAuthRefreshTokenClient
{
    protected $clientId;
    protected $clientSecret;
    protected $refreshToken;
    protected $accessToken;
    protected $expiresAt = 0;

    public function __construct($clientId, $clientSecret, $refreshToken)
    {
        $this->clientId = trim((string) $clientId);
        $this->clientSecret = trim((string) $clientSecret);
        $this->refreshToken = trim((string) $refreshToken);

        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new \RuntimeException('Google OAuth 凭据不完整（需 client_id、client_secret、refresh_token）。');
        }
    }

    public function accessToken()
    {
        if ($this->accessToken && time() < ($this->expiresAt - 60)) {
            return $this->accessToken;
        }

        $body = http_build_query([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
        ], '', '&');

        $response = $this->httpPostForm('https://oauth2.googleapis.com/token', $body);
        if (empty($response['access_token'])) {
            $message = isset($response['error_description']) ? $response['error_description'] : '无法刷新 Google OAuth access token';

            throw new \RuntimeException($message);
        }

        $this->accessToken = (string) $response['access_token'];
        $this->expiresAt = time() + (int) data_get($response, 'expires_in', 3600);

        return $this->accessToken;
    }

    protected function httpPostForm($url, $body)
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $response = $client->post($url, [
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => $body,
            ]);

            return json_decode((string) $response->getBody(), true) ?: [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 30,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('请求 Google OAuth 失败。');
        }

        return json_decode($raw, true) ?: [];
    }
}
