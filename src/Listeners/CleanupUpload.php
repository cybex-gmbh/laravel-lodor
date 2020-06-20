<?php

namespace Cybex\Lodor\Listeners;

use Cybex\Lodor\LodorFacade as Lodor;
use Cybex\Lodor\Events\UploadFinished;

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
     * @param UploadFinished $event
     *
     * @return void
     */
    public function handle(UploadFinished $event)
    {
        Lodor::cleanupUpload($event->uuid, $event->metadata['chunked'] ?? null);
    }
}
