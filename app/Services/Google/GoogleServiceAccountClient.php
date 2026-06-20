<?php

namespace App\Services\Google;

class GoogleServiceAccountClient
{
    protected $credentials;
    protected $scope;
    protected $accessToken;
    protected $expiresAt = 0;

    public function __construct($jsonPath, $scope = 'https://www.googleapis.com/auth/drive.file')
    {
        $jsonPath = (string) $jsonPath;
        if ($jsonPath === '' || !is_file($jsonPath)) {
            throw new \RuntimeException('Google 服务账号 JSON 不存在：'.$jsonPath);
        }

        $raw = file_get_contents($jsonPath);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Google 服务账号 JSON 格式无效。');
        }

        foreach (['client_email', 'private_key', 'token_uri'] as $key) {
            if (empty($data[$key])) {
                throw new \RuntimeException('Google 服务账号 JSON 缺少字段：'.$key);
            }
        }

        $this->credentials = $data;
        $this->scope = (string) $scope;
    }

    public function accessToken()
    {
        if ($this->accessToken && time() < ($this->expiresAt - 60)) {
            return $this->accessToken;
        }

        $jwt = $this->buildJwt();
        $tokenUri = (string) $this->credentials['token_uri'];
        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&');

        $response = $this->httpPostForm($tokenUri, $body);
        if (empty($response['access_token'])) {
            $message = isset($response['error_description']) ? $response['error_description'] : '无法获取 Google access token';

            throw new \RuntimeException($message);
        }

        $this->accessToken = (string) $response['access_token'];
        $this->expiresAt = time() + (int) data_get($response, 'expires_in', 3600);

        return $this->accessToken;
    }

    protected function buildJwt()
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ]));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => $this->scope,
            'aud' => $this->credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $input = $header.'.'.$payload;
        $signature = '';
        $privateKey = openssl_pkey_get_private($this->credentials['private_key']);
        if (!$privateKey) {
            throw new \RuntimeException('Google 服务账号私钥无效。');
        }

        $signed = openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);
        if (!$signed) {
            throw new \RuntimeException('Google JWT 签名失败。');
        }

        return $input.'.'.$this->base64UrlEncode($signature);
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

    protected function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
