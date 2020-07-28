<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Upload Route Path
    |--------------------------------------------------------------------------
    |
    | Here you may customize the route path for handling the uploads. All file
    | uploads or libraries should use this path as action / URL.
    |
    */
    'upload_route_path' => env('LODOR_URL_UPLOAD', 'uploadmedia'),

    /*
    |--------------------------------------------------------------------------
    | Polling Route Path
    |--------------------------------------------------------------------------
    |
    | Here you may customize the default polling route path to be registered.
    | All frontend implementations informing the user about the progress of
    | the processing of the files after the upload should fetch the info
    | here periodically (unless you are using websockets instead).
    |
    */
    'poll_route_path'   => env('LODOR_URL_POLL', 'uploadpolling'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Here you may customize middleware that will be applied to both the upload
    | and polling routes. By default, web and auth middleware are active.
    |
    */
    'route_middleware' => [
        'web',
        'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | File Naming
    |--------------------------------------------------------------------------
    |
    | Here you may customize the way the uploaded files are named once they are
    | written to your destination disk (disk_uploads).
    |
    | Possible options are "original" for the original client filename, "uuid"
    | for the unique identifier that was created during the upload process,
    | "uuid_ext" for the uuid with the original file extension.
    |
    | This option can be overridden by defining a lodor_filename parameter in
    | the form request.
    |
    */
    'filename' => env('LODOR_FILE_NAMING', 'original'),

    /*
    |--------------------------------------------------------------------------
    | File Exists Strategy
    |--------------------------------------------------------------------------
    |
    | This determines what happens if the destination filename already exists
    | when trying to write the chunks to the final file or moving the temp
    | upload over to the final location.
    |
    | When set to "rename", the file is renamed by adding an incrementing
    | number to the original filename. When assigning "overwrite", the
    | existing file is replaced with the newly uploaded version.
    |
    | If any other or no value is assigned, the upload will fail with a
    | FileExistsException.
    |
    */
    'file_exists_strategy' => env('LODOR_FILE_EXISTS_STRATEGY', 'rename'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure the disks that are used for chunked uploads and
    | single and merged uploads. Alternatively, you may change the disk_chunked
    | and disk_uploads settings to use custom disks you configured in your
    | app's filesystems.php.
    |
    */

    'disks'        => [
        // This is where the file chunks are saved until they are merged.
        'lodor_chunked' => [
            'driver' => 'local',
            'root'   => storage_path('lodor/chunked'),
        ],

        // This is where non-chunked uploads go, and where the chunked uploads go after merging.
        'lodor_uploads' => [
            'driver' => 'local',
            'root'   => storage_path('lodor/uploads'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Disk Names
    |--------------------------------------------------------------------------
    |
    | Here you may configure the disk names that are used for chunked or single
    | uploads. You can use the same disk for single and chunked uploads: single
    | uploads will be files in that disk, while chunked uploads are stored in
    | subfolders until they are merged.
    |
    | If you change these settings, make sure the disks are properly configured
    | in your app's filesystems.php.
    |
    */
    'disk_uploads'  => env('LODOR_DISK_UPLOADS', 'lodor_uploads'),
    'disk_chunked' => env('LODOR_DISK_CHUNKED', 'lodor_chunked'),

    /*
    |--------------------------------------------------------------------------
    | Information Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may customize the cache use for the uploaded file details and
    | the upload status. The prefix option specifies the prefix that is used
    | for the cache entries and the ttl determines how long the cache items
    | should live before they expire.
    |
    */
    'cache' => [
        'upload_config' => [
            'prefix' => 'LODOR',
            'ttl' => 3600,
        ],
        'upload_status' => [
            'prefix' => 'LODOR_STATUS',
            'ttl' => 3600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | State Control
    |--------------------------------------------------------------------------
    |
    | Here you may customize the state and progress values that are set at each
    | period of the upload. E.g., if you want to trigger a time consuming post
    | processing for each upload, you may want to set the progress for
    | server_upload_finished to 50. If no listeners are attached to the
    | FileUploaded event, the state is automatically set to upload_done.
    |
    */
    'states' => [
        'awaiting_merge' => [
            'progress' => env('LODOR_PROGRESS_SERVER_UPLOAD', 50),
            'state' => 'waiting',
            'status' => 'Chunked file upload complete.',
            'info' => 'Waiting for processing...',
        ],

        // The server starts merging chunks.
        'merging_chunks' => [
            'progress' => env('LODOR_PROGRESS_SERVER_UPLOAD', 50),
            'progress_end' => env('LODOR_PROGRESS_MERGED', 75),
            'state' => 'processing',
            'status' => 'Merging chunks...',
            'info' => 'Concatenating chunk :current_chunk of :total_chunks...',
        ],

        // The server upload finished, but a listener is waiting for execution.
        'server_upload_waiting' => [
            'progress' => env('LODOR_PROGRESS_AWAIT_PROCESSING', 75),
            'state' => 'waiting',
            'status' => 'Server upload finished. Waiting for processing...',
            'info' => 'Processing upload with ID :uuid.',
        ],

        'merge_cleanup' => [
            'progress' => env('LODOR_PROGRESS_MERGED', 75),
            'state' => 'processing',
            'status' => 'Cleaning up...',
            'info' => 'Successfully concatenated all file parts.',
        ],

        // The server upload finished and no listener is waiting for execution.
        'server_upload_done' => [
            'progress' => 100,
            'state' => 'done',
            'status' => 'Server upload finished.',
            'info' => 'Upload complete.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Broadcast Channel Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify the name of the default channel the upload related
    | events should be broadcast on.
    |
    */

    'event_channel_name' => env('LODOR_EVENT_CHANNEL', 'upload'),

    /*
    |--------------------------------------------------------------------------
    | Automatic cleanup
    |--------------------------------------------------------------------------
    |
    | This setting determines whether Lodor should automatically clean up files
    | and cache entries after itself.
    |
    */
    'auto_cleanup' => env('LODOR_AUTO_CLEANUP', true),

    /*
    |--------------------------------------------------------------------------
    | Upload Cleanup Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may specify the amount of seconds to wait after the last status
    | update of an upload before it can be regarded as stalled and thus be
    | deleted by the cleanup job.
    |
    | Note: this is currently not in use and may be utilized by a cleanup cron
    | or command in a later version of Lodor.
    |
    */

    'upload_cleanup_timeout' => env('LODOR_UPLOAD_CLEANUP_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Merge Config
    |--------------------------------------------------------------------------
    |
    | The following key configures all aspects of the feature to automatically
    | merge the chunks of an upload back to a complete file.
    |
    */
    'merge_chunks' => [

        /*
        |--------------------------------------------------------------------------
        | Chunk Uploader Class
        |--------------------------------------------------------------------------
        |
        | Here you can configure the ChunkUploader class to be used by Lodor for
        | detecting and handling incoming chunked uploads.
        |
        | By default, uploader is set to null, which means that the appropriate
        | uploader will be auto-detected by analyzing the form request.
        |
        | To set a specific ChunkUploader, specify the class name of the uploader
        | class, e.g. 'Cybex\Lodor\DropzoneChunkUploader' or
        | ResumableJsChunkUploader::class.
        |
        */
        'uploader' => null,

        /*
        |--------------------------------------------------------------------------
        | Chunk Auto-Merging
        |--------------------------------------------------------------------------
        |
        | If set to true and a supported upload library sends a file in chunks,
        | the chunks are automatically joined after the last chunk has arrived.
        | By default, this will be accomplished asynchronously by the
        | MergeChunks listener (see option below).
        |
        */
        'auto_merge_chunks' => env('LODOR_AUTO_MERGE_CHUNKS', true),

        /*
        |--------------------------------------------------------------------------
        | Merge Chunks Asynchronously
        |--------------------------------------------------------------------------
        |
        | By default, the MergeChunks listener will asynchronously merge all
        | chunks of an upload. This requires that a queue worker is running,
        | otherwise the upload will get stuck waiting for the chunks to be
        | merged.
        |
        | If you do not wish to use queues, you can set this option to false,
        | which will cause the script to try to join the chunks synchronously
        | after the last chunk came in. This is NOT RECOMMENDED, since the
        | upload of the last chunk plus the merging might take longer than
        | your maximum PHP script execution time allows, which will then
        | cause the script to be killed and the upload to fail.
        |
        */
        'run_async' => env('LODOR_MERGE_ASYNC', true),

        /*
        |--------------------------------------------------------------------------
        | Default Queue
        |--------------------------------------------------------------------------
        |
        | Here you may define on which queue the MergeChunks jobs will run.
        | For simplicity, the merges run on the same queue as the uploads
        | by default; it may make sense for you to configure them to run
        | on a different queue to grant a higher priority to the uploads
        | themselves.
        |
        | Note: only works on Laravel 7.15.0 and above.
        |
        */
        'default_queue' => env('LODOR_DEFAULT_MERGE_QUEUE', 'default'),
    ],
];
