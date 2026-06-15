<?php

namespace App\Services\Ribenyan;

class RibenyanHttpClient
{
    public function get($url)
    {
        return $this->request($url, false);
    }

    public function getBinary($url)
    {
        return $this->request($url, true);
    }

    protected function request($url, $binary)
    {
        $ch = curl_init($url);
        if (!$ch) {
            throw new \RuntimeException('curl 初始化失败');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => (int) config('ribenyan_import.request_timeout', 60),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_USERAGENT => config('ribenyan_import.user_agent'),
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            throw new \RuntimeException('请求失败: '.$error);
        }

        if ($status >= 400) {
            throw new \RuntimeException('HTTP '.$status.' for '.$url);
        }

        $delayMs = (int) config('ribenyan_import.request_delay_ms', 1200);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        return $binary ? (string) $body : $this->normalizeEncoding((string) $body);
    }

    protected function normalizeEncoding($body)
    {
        if ($body === '') {
            return $body;
        }

        if (!mb_check_encoding($body, 'UTF-8')) {
            $body = mb_convert_encoding($body, 'UTF-8', 'UTF-8, GB18030, GBK, ISO-8859-1');
        }

        return $body;
    }
}
