<?php

namespace App\Services;

class GoogleDriveUploadService
{
    /**
     * @return array{id:string,name:string,webViewLink:?string}
     */
    public function uploadFile($localPath, $remoteFilename, $folderId, $mimeType = 'application/octet-stream')
    {
        $localPath = (string) $localPath;
        $folderId = trim((string) $folderId);
        $remoteFilename = trim((string) $remoteFilename);

        if ($localPath === '' || !is_file($localPath)) {
            throw new \InvalidArgumentException('待上传文件不存在：'.$localPath);
        }

        if ($folderId === '') {
            throw new \InvalidArgumentException('未配置 Google Drive 文件夹 ID。');
        }

        if (!class_exists(\Google\Client::class)) {
            throw new \RuntimeException('缺少 google/apiclient，请在服务器执行 composer install');
        }

        $jsonPath = (string) config('daily_export.google.service_account_json', '');
        if ($jsonPath === '' || !is_file($jsonPath)) {
            throw new \RuntimeException('Google 服务账号 JSON 不存在：'.$jsonPath);
        }

        $client = new \Google\Client();
        $client->setAuthConfig($jsonPath);
        $client->setScopes([\Google\Service\Drive::DRIVE_FILE]);

        $service = new \Google\Service\Drive($client);
        $file = new \Google\Service\Drive\DriveFile([
            'name' => $remoteFilename,
            'parents' => [$folderId],
        ]);

        $created = $service->files->create($file, [
            'data' => file_get_contents($localPath),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,webViewLink',
        ]);

        return [
            'id' => (string) $created->getId(),
            'name' => (string) $created->getName(),
            'webViewLink' => $created->getWebViewLink(),
        ];
    }
}
