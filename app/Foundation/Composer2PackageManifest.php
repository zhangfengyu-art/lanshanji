<?php

namespace App\Foundation;

use Illuminate\Foundation\PackageManifest as BasePackageManifest;

/**
 * Laravel 5.5 默认按 Composer 1 解析 installed.json；Composer 2 使用 packages 键。
 */
class Composer2PackageManifest extends BasePackageManifest
{
    public function build()
    {
        $packages = [];

        if ($this->files->exists($path = $this->vendorPath.'/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);
            if (isset($installed['packages']) && is_array($installed['packages'])) {
                $packages = $installed['packages'];
            } elseif (is_array($installed)) {
                $packages = $installed;
            }
        }

        $ignoreAll = in_array('*', $ignore = $this->packagesToIgnore());

        $this->write(collect($packages)->filter(function ($package) {
            return is_array($package) && isset($package['name']);
        })->mapWithKeys(function ($package) {
            return [$this->format($package['name']) => $package['extra']['laravel'] ?? []];
        })->each(function ($configuration) use (&$ignore) {
            $ignore = array_merge($ignore, $configuration['dont-discover'] ?? []);
        })->reject(function ($configuration, $package) use ($ignore, $ignoreAll) {
            return $ignoreAll || in_array($package, $ignore);
        })->filter()->all());
    }
}
