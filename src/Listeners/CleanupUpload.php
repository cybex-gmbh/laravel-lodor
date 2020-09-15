<?php

namespace Cybex\Lodor\Listeners;

use Cybex\Lodor\Events\UploadFailed;
use Cybex\Lodor\Events\UploadFinished;
use Cybex\Lodor\LodorFacade as Lodor;

/**
 * Class CleanupUpload
 *
 * Event listener that is called every time a file has finished uploading (UploadFinished event).
 * The listener cleans up the temporary files associated with the upload and runs synchronously.
 *
 * @package App\Listeners
 */
class CleanupUpload
{
    /**
     * Handle the event.
     *
     * @param UploadFinished|UploadFailed $event
     *
     * @return void
     */
    public function handle($event)
    {
        // If the listener is triggered by an UploadFailed event, force cleanup.
        Lodor::cleanupUpload($event->uuid, is_a($event, UploadFailed::class));
    }
}
