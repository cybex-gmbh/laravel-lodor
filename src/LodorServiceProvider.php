<?php

namespace Cybex\Lodor;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Cybex\Lodor\Http\Controllers\UploadController;

class LodorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->registerDisks();
        $this->registerRoutes();
        $this->loadJsonTranslationsFrom(__DIR__ . '/../resources/lang');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/lodor.php' => config_path('lodor.php'),
            ],
                'config');

            $this->publishes([
                __DIR__ . '/../resources/lang' => resource_path('lang/vendor/lodor'),
            ],
                'lang');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/lodor.php', 'lodor');

        // Register the EventServiceProvider.
        $this->app->register(EventServiceProvider::class);

        // Register the main class to use with the facade
        $this->app->singleton('lodor',
            function () {
                return new Lodor;
            });
    }

    /**
     * Register all necessary routes.
     */
    protected function registerRoutes()
    {
        $middlewareArray = config('lodor.route_middlewares');

        // All uploads will go here.
        Route::post(config('lodor.upload_route_path', 'uploadmedia'), [UploadController::class, 'store'])->name('lodor_upload')->middleware($middlewareArray);

        // The frontend JS can poll this route to get information about the current upload(s).
        Route::post(config('lodor.poll_route_path', 'uploadpoll'), [UploadController::class, 'poll'])->name('lodor_poll')->middleware($middlewareArray);
    }

    /**
     * Registers the default disks, if necessary.
     */
    protected function registerDisks()
    {
        $this->registerDisk(Lodor::getSingleUploadDiskName());
        $this->registerDisk(Lodor::getChunkedUploadDiskName());
    }

    /**
     * Registers the disk with the specified $name only if it exists in the Lodor disks configuration.
     * If it does not exist, it is expected to be an existing disk in the app filesystem.
     *
     * @param string $name
     */
    protected function registerDisk(string $name)
    {
        if ($lodorDisk = config(sprintf('lodor.disks.%s', $name))) {
            config([sprintf('filesystems.disks.%s', $name) => $lodorDisk]);
        }
    }

}
