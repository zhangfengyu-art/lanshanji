<?php

namespace App\Services;

use App\Services\Google\GoogleOAuthRefreshTokenClient;
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
        if (config('daily_export.google.supports_all_drives')) {
            $url .= '&supportsAllDrives=true';
        }

        $response = $this->multipartUpload($url, $token, $metadata, $localPath, $mimeType);
        if (empty($response['id'])) {
            $message = isset($response['error']['message']) ? $response['error']['message'] : 'Google Drive 上传失败';
            if (stripos($message, 'Service Accounts do not have storage quota') !== false) {
                $message .= '。个人 Gmail 请改用 OAuth refresh token；Google Workspace 请把文件夹放到「共享云端硬盘」并设置 GOOGLE_DRIVE_SUPPORTS_ALL_DRIVES=true，或配置 GOOGLE_DRIVE_IMPERSONATE_EMAIL 域委派。';
            }

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

        $oauth = (array) config('daily_export.google.oauth', []);
        if (!empty($oauth['refresh_token']) && !empty($oauth['client_id']) && !empty($oauth['client_secret'])) {
            return $this->auth = new GoogleOAuthRefreshTokenClient(
                $oauth['client_id'],
                $oauth['client_secret'],
                $oauth['refresh_token']
            );
        }

        $jsonPath = (string) config('daily_export.google.service_account_json', '');
        $impersonate = (string) config('daily_export.google.impersonate_email', '');

        return $this->auth = new GoogleServiceAccountClient($jsonPath, 'https://www.googleapis.com/auth/drive.file', $impersonate);
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
            $client = new \GuzzleHttp\Client(['timeout' => 120, 'http_errors' => false]);
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'multipart/related; boundary='.$boundary,
                ],
                'body' => $body,
            ]);
            $decoded = json_decode((string) $response->getBody(), true) ?: [];
            if ($response->getStatusCode() >= 400) {
                $this->throwDriveApiError($decoded, $response->getStatusCode());
            }

            return $decoded;
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

    protected function throwDriveApiError(array $response, $statusCode)
    {
        $message = isset($response['error']['message']) ? $response['error']['message'] : 'Google Drive 上传失败（HTTP '.$statusCode.'）';
        if (stripos($message, 'Service Accounts do not have storage quota') !== false) {
            $message .= '。个人 Gmail 请改用 OAuth refresh token；Google Workspace 请把文件夹放到「共享云端硬盘」并设置 GOOGLE_DRIVE_SUPPORTS_ALL_DRIVES=true，或配置 GOOGLE_DRIVE_IMPERSONATE_EMAIL 域委派。';
        }

        throw new \RuntimeException($message);
    }
}
