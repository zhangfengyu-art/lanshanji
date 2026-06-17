<?php

namespace App\Foundation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application as BaseApplication;

class Application extends BaseApplication
{
    protected function registerBaseBindings()
    {
        static::setInstance($this);

        $this->instance('app', $this);

        $this->instance(\Illuminate\Container\Container::class, $this);

        $this->instance(\Illuminate\Foundation\PackageManifest::class, new Composer2PackageManifest(
            new Filesystem(),
            $this->basePath(),
            $this->getCachedPackagesPath()
        ));
    }
}
