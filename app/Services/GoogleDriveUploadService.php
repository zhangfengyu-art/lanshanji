<?php

namespace App\Services;

use App\Services\Google\GoogleServiceAccountClient;

class GoogleDriveUploadService
{
    /** @var GoogleServiceAccountClient|null */
    protected $auth;

    /**
     * @return array{id:string,name:string,webViewLink:?string}
     */
    public function uploadFile($localPath, $remoteFilename, $folderId, $mimeType = 'application/octet-stream')
    {
        $localPath = (string) $localPath;
        $folderId = trim((string) $folderId);
        $remoteFilename = trim((string) $remoteFilename);
        $mimeType = trim((string) $mimeType) ?: 'application/octet-stream';

        if ($localPath === '' || !is_file($localPath)) {
            throw new \InvalidArgumentException('待上传文件不存在：'.$localPath);
        }

        if ($folderId === '') {
            throw new \InvalidArgumentException('未配置 Google Drive 文件夹 ID。');
        }

        $token = $this->authClient()->accessToken();
        $metadata = json_encode([
            'name' => $remoteFilename,
            'parents' => [$folderId],
        ], JSON_UNESCAPED_UNICODE);

        $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink';
        $response = $this->multipartUpload($url, $token, $metadata, $localPath, $mimeType);
        if (empty($response['id'])) {
            $message = isset($response['error']['message']) ? $response['error']['message'] : 'Google Drive 上传失败';

            throw new \RuntimeException($message);
        }

        return [
            'id' => (string) $response['id'],
            'name' => (string) data_get($response, 'name', $remoteFilename),
            'webViewLink' => data_get($response, 'webViewLink'),
        ];
    }

    protected function authClient()
    {
        if ($this->auth) {
            return $this->auth;
        }

        $jsonPath = (string) config('daily_export.google.service_account_json', '');

        return $this->auth = new GoogleServiceAccountClient($jsonPath);
    }

    protected function multipartUpload($url, $token, $metadataJson, $filePath, $mimeType)
    {
        $boundary = 'export_boundary_'.bin2hex(random_bytes(8));
        $fileContents = file_get_contents($filePath);
        if ($fileContents === false) {
            throw new \RuntimeException('无法读取待上传文件：'.$filePath);
        }

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadataJson."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .$fileContents."\r\n"
            ."--{$boundary}--";

        if (class_exists(\GuzzleHttp\Client::class)) {
            $client = new \GuzzleHttp\Client(['timeout' => 120]);
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'multipart/related; boundary='.$boundary,
                ],
                'body' => $body,
            ]);

            return json_decode((string) $response->getBody(), true) ?: [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Authorization: Bearer '.$token,
                    'Content-Type: multipart/related; boundary='.$boundary,
                    'Content-Length: '.strlen($body),
                ]),
                'content' => $body,
                'timeout' => 120,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException('Google Drive 上传请求失败。');
        }

        return json_decode($raw, true) ?: [];
    }
}
