<?php

namespace App\Listeners;

use Cybex\Lodor\Events\FileUploaded;
use Cybex\Lodor\Events\UploadFinished;
use Cybex\Lodor\LodorFacade as Lodor;
use Exception;

/**
 * This listener is for demonstration purposes and will be called after an upload successfully finished.
 * Ideally, you should register your listeners for FileUploaded events in your app if you were to
 * perform any post-processing on uploaded files, like validating that the files meet the
 * requirements, moving them to a special directory, uploading them somewhere else or
 * transforming the uploads (thumbnails etc.).
 *
 * Also, you may want to process any form data that came along with the upload, such as names, titles
 * or settings. You will find all request data in the metadata attribute of the FileUploaded event.
 *
 * Any time consuming processing like transformations or further uploads, even moving large files
 * between locations, should always be done asynchronously using a queueable listener that is
 * executed via the queue worker. Otherwise you might exceed the maximum runtime of the PHP
 * upload script and long-running uploads will fail.
 *
 * You can turn your listener into a queued listener by implementing the ShouldQueue interface:
 *
 *      class MyFileUploadedListener implements ShouldQueue
 *      {
 *          ...
 *      }
 *
 * @package App\Listeners
 */
class ProcessUploadedFile // implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param FileUploaded $event
     *
     * @return void
     * @throws Exception
     */
    public function handle(FileUploaded $event)
    {
        $uuid     = $event->uuid;
        $metadata = $event->metadata;

        // You are also responsible to post updates on the status of the process using Lodor::setUploadStatus()
        // to keep the frontend up to date on the progress and any info you want to publish along with it.
        // After the server upload finishes, the upload is put in "waiting" state until the listener(s)
        // process(es) the upload and set the status to "done" state.
        Lodor::setUploadStatus($event->uuid,
            'done',
            __('Server upload finished.'),
            __('Upload complete.', ['uuid' => $uuid]),
            100,
            $metadata);

        // Then fire the UploadFinished event to signalize that the upload has completed processing.
        event(new UploadFinished($event->uuid, $event->metadata));
    }
}
