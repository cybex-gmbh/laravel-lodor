<?php

namespace Cybex\Lodor\Listeners;

use Exception;
use Illuminate\Config\Repository;
use Cybex\Lodor\LodorFacade as Lodor;
use Illuminate\Queue\InteractsWithQueue;
use Cybex\Lodor\Events\ChunkedFileUploaded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Foundation\Application;

class MergeChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    /**
     * The number of times the execution of this job is tried before it is failed.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The name of the queue the listener should be run on.
     *
     * @var string
     */
    public $queue = 'default';

    /**
     * Make sure that we only queue this listener if auto merging chunks and asynchronous merging are enabled.
     *
     * @param $event
     *
     * @return bool
     */
    public function shouldQueue(ChunkedFileUploaded $event)
    {
        $mergeConfig = config('lodor.merge_chunks', []);

        if (($mergeConfig['auto_merge_chunks'] ?? true) === false) {
            // Finish upload.
            Lodor::finishUpload($event->uuid, $event->metadata);

            return false;
        }

        if (!($mergeConfig['run_async'] ?? false)) {
            // Run synchronously.
            $this->handle($event);

            return false;
        }

        return Lodor::isChunked($event->uuid);
    }

    /**
     * Run the listener on the configured queue.
     * Only works on Laravel 7.15.0 and above.
     *
     * @return Repository|Application|mixed
     */
    public function viaQueue()
    {
        return config('lodor.merge_chunks.default_queue', $this->queue);
    }

    /**
     * Handle the event.
     *
     * @param ChunkedFileUploaded $event
     *
     * @return void
     */
    public function handle(ChunkedFileUploaded $event = null)
    {
        try {
            Lodor::mergeChunkedFile($event->uuid);
        } catch (Exception $exception) {
            Lodor::failUpload($event->uuid, __('Merging chunks...'), $exception->getMessage(), $event->metadata);

            return;
        }

        Lodor::setUploadState($event->uuid, 'merge_cleanup');
        Lodor::finishUpload($event->uuid, $event->metadata);
    }
}
