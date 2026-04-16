$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $projectRoot

$envName = 'B'
$siteMode = 'B'
$port = 8001
$targetEnvFile = Join-Path $projectRoot '.env.B'
$baseEnvFile = Join-Path $projectRoot '.env'

function Set-Or-AddEnvVar {
    param(
        [string]$FilePath,
        [string]$Key,
        [string]$Value
    )

    $content = Get-Content -Path $FilePath -Raw
    $pattern = "(?m)^$([regex]::Escape($Key))=.*$"

    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, "$Key=$Value")
    } else {
        if (-not $content.EndsWith("`r`n")) {
            $content += "`r`n"
        }
        $content += "$Key=$Value`r`n"
    }

    Set-Content -Path $FilePath -Value $content -NoNewline
}

if (-not (Test-Path $targetEnvFile)) {
    if (Test-Path $baseEnvFile) {
        Copy-Item -Path $baseEnvFile -Destination $targetEnvFile -Force
    } else {
        throw '未找到 .env 文件，无法生成 .env.B'
    }
}

Set-Or-AddEnvVar -FilePath $targetEnvFile -Key 'APP_ENV' -Value 'local'
Set-Or-AddEnvVar -FilePath $targetEnvFile -Key 'SITE_MODE' -Value $siteMode
Set-Or-AddEnvVar -FilePath $targetEnvFile -Key 'APP_URL' -Value "http://127.0.0.1:$port"

Write-Host "[B站] 使用环境文件: .env.$envName" -ForegroundColor Cyan
Write-Host "[B站] 启动地址: http://127.0.0.1:$port/products" -ForegroundColor Cyan

php artisan config:clear --env=$envName | Out-Host
php artisan view:clear --env=$envName | Out-Host
php artisan serve --host=127.0.0.1 --port=$port --env=$envName
