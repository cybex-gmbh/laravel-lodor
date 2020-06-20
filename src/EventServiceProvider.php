<?php

namespace Cybex\Lodor;

use Cybex\Lodor\Events\UploadFinished;
use Cybex\Lodor\Listeners\CleanupUpload;
use Cybex\Lodor\Listeners\MergeChunks;
use Cybex\Lodor\Events\ChunkedFileUploaded;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        ChunkedFileUploaded::class => [
            MergeChunks::class,
        ],
        UploadFinished::class => [
            CleanupUpload::class,
        ]
    ];

    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        parent::boot();
    }
}
