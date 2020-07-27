<?php

namespace Cybex\Lodor;

use Exception;
use voku\helper\ASCII;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use InvalidArgumentException;
use UnexpectedValueException;
use Illuminate\Routing\Router;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Cybex\Lodor\Events\FileUploaded;
use Cybex\Lodor\Events\UploadFailed;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Cybex\Lodor\Events\UploadFinished;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Cybex\Lodor\ChunkUploaders\ChunkUploader;
use Cybex\Lodor\Exceptions\UploadNotFoundException;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

class Lodor
{
    /**
     * Returns the name of the filesystem disk
     * used for single (non-chunked) uploads.
     *
     * @return string
     */
    public static function getSingleUploadDiskName(): string
    {
        return config('lodor.disk_uploads', '');
    }

    /**
     * Returns the name of the filesystem disk
     * used for chunked uploads.
     *
     * @return string
     */
    public static function getChunkedUploadDiskName(): string
    {
        return config('lodor.disk_chunked', '');
    }

    public function getInitialPollingProgress(): int
    {
        return config('lodor.server_upload_waiting.progress', 50);
    }

    /**
     * Returns the target URL for uploads.
     *
     * @return string
     */
    public function getUploadRoute(): string
    {
        return route('lodor_upload');
    }

    /**
     * Returns the URL for the polling endpoint.
     *
     * @return string
     */
    public function getPollingRoute(): string
    {
        return route('lodor_poll');
    }

    /**
     * Returns the redirect route, if any is set, that gets called after an upload finished.
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function getRedirectRoute(): ?array
    {
        if (Route::has('lodor_uploaded') && $route = app(Router::class)->getRoutes()->getByName('lodor_uploaded')) {
            $controller   = $route->getController();
            $actionMethod = $route->getActionMethod();

            if (!($controller instanceof Controller)) {
                throw new Exception('Invalid route definition: lodor_uploaded route is required to be handled by a controller action.');
            }

            return [$route, $controller, $actionMethod];
        }

        return null;
    }

    /**
     * Returns the status record for the upload with the specified $uuid.
     * The record is used in conjunction with a Dropzone to poll the
     * status of a running upload via UploadController@poll.
     *
     * @param String $uuid
     *
     * @return array
     *
     */
    public function getUploadStatus(string $uuid): array
    {
        $cachePrefix = config('lodor.cache.upload_status.prefix', 'LODOR_STATUS');
        return Cache::get(sprintf('%s_%s', $cachePrefix, $uuid)) ?? [];
    }

    /**
     * Returns the status record for the uploads with the specified $uuids.
     * This is the multi upload version of getUploadStatus() and returns a
     * calculated percentage that combines the status of all uploads into
     * one.
     *
     * @param array $uuids
     *
     * @return array
     * @see UploadController
     */
    public function getMultiUploadStatus(array $uuids): array
    {
        if (!count($uuids)) {
            return [];
        }

        $uuidCollection   = collect($uuids);
        $statusCollection = $uuidCollection->map(function ($uuid) {
            return $this->getUploadStatus($uuid);
        })->filter();

        $totalUploads = $uuidCollection->count();

        $completedUploads = $statusCollection->reduce(function ($carry, $uploadStatus) {
            return $carry + ($uploadStatus['state'] == 'done' ? 1 : 0);
        });

        $remainingUploads = $totalUploads - $completedUploads;

        $progress = $statusCollection->reduce(function ($carry, $uploadStatus) {
                return $carry + $uploadStatus['progress'];
            }) / $totalUploads;

        $metadata = $statusCollection->mapWithKeys(function ($status) {
            return [$status['uuid'] => $status['metadata']];
        });

        // If there is only one upload in the uuid list, return the detailed info for this upload.
        // In multi uploads, we only include the generic info below.
        if ($totalUploads == 1) {
            return array_merge($statusCollection->first() ?? [], ['uuids' => $uuids, 'metadata' => $metadata]);
        }

        return [
            'state'    => $remainingUploads ? 'processing' : 'done',
            'status'   => $remainingUploads ? __('Waiting for :remaining_uploads_count uploads to finish processing...',
                ['remaining_uploads_count' => $remainingUploads]) : __('Processing complete.'),
            'info'     => $remainingUploads ? __(':remaining_uploads_count of :total_uploads_count files left...',
                ['remaining_uploads_count' => $remainingUploads, 'total_uploads_count' => $totalUploads]) : __('Completed upload of :total_uploads_count files.',
                ['total_uploads_count' => $totalUploads]),
            'progress' => $progress,
            'uuids'    => $uuids,
            'metadata' => $metadata,
        ];
    }

    /**
     * Set a new status record for the upload with the specified $uuid.
     * The record is used in conjunction with a dropzone to poll the
     * status of a running upload via UploadController@poll.
     *
     * Accepted values for the $state variable are:
     *
     *      'waiting':       Upload is waiting, e.g. for a post-processing job to launch.
     *      'processing':    Upload post-processing is running.
     *      'done':          Upload and post-processing are done and the transfer has successfully completed.
     *      'error':         An error occured during upload/validation or post-processing.
     *
     * Of course you can use your own states if you are handling the polling in the frontend yourself.
     *
     * @param String     $uuid
     * @param String     $state
     * @param String     $status
     * @param String     $info
     * @param float|null $progress
     * @param array      $metadata
     *
     * @return void
     */
    public function setUploadStatus(string $uuid, string $state = 'error', string $status = '', string $info = '', ?float $progress = null, array $metadata = [])
    {
        $cacheConfig = config('lodor.cache.upload_status');

        Cache::set(sprintf('%s_%s', $cacheConfig['prefix'] ?? 'LODOR_STATUS', $uuid),
            [
                'state'     => $state,
                'status'    => $status,
                'info'      => $info,
                'progress'  => $progress ?? $this->getInitialPollingProgress(),
                'uuid'      => $uuid,
                'metadata'  => $metadata,
                'timestamp' => time(),
            ],
            $cacheConfig['ttl'] ?? 3600);
    }

    /**
     * Sets the upload status according to a predefined state from the config file.
     *
     * @param string $uuid
     * @param string $state
     * @param array  $values
     * @param int    $progress
     * @param array  $metadata
     */
    public function setUploadState(string $uuid, string $state, array $values = [], int $progress = 0, array $metadata = [])
    {
        $state = config(sprintf('lodor.states.%s', $state));
        if ($state !== null) {
            $startProgress = ($state['progress'] ?? 0);
            $progress      = $startProgress + $progress * (($state['progress_end'] ?? $startProgress) - $startProgress);
            $this->setUploadStatus($uuid, $state['state'], __($state['status'], $values), __($state['info'], $values), $progress, $metadata);
        }
    }

    /**
     * Removes the status cache for the specified UUID.
     * This should only be called by the cleanup job
     * or if you are absolutely sure that the
     * frontend already stopped polling.
     *
     * @param string $uuid
     */
    public function removeUploadStatus(string $uuid)
    {
        $cachePrefix = config('lodor.cache.upload_status.prefix', 'LODOR_STATUS');
        Cache::forget(sprintf('%s_%s', $cachePrefix, $uuid));
    }

    /**
     * Returns the configuration record for the upload with the specified $uuid.
     * Used mainly by the upload-related jobs.
     *
     * @param String $uuid
     *
     * @return array
     *
     * @throws InvalidArgumentException
     */
    public function getUploadConfig(string $uuid): array
    {
        $cachePrefix = config('lodor.cache.upload_config.prefix', 'LODOR');
        $cacheKey    = sprintf('%s_%s', $cachePrefix, $uuid);
        if (!Cache::has($cacheKey)) {
            throw new InvalidArgumentException(__('No upload config found for upload with UUID :uuid.', ['uuid' => $uuid]));
        }

        return Cache::get($cacheKey);
    }

    /**
     * Checks if the upload config exists for the specified uuid.
     *
     * @param String $uuid
     *
     * @return bool
     *
     */
    public function hasUploadConfig(string $uuid): bool
    {
        $cachePrefix = config('lodor.cache.upload_config.prefix', 'LODOR');
        $cacheKey    = sprintf('%s_%s', $cachePrefix, $uuid);

        return Cache::has($cacheKey);
    }

    /**
     * Saves the config record for the upload with the specified $uuid.
     *
     * @param String $uuid
     * @param array  $uploadConfig
     *
     * @return bool
     */
    public function setUploadConfig(string $uuid, array $uploadConfig): bool
    {
        $cacheConfig = config('lodor.cache.upload_config');
        return Cache::set(sprintf('%s_%s', $cacheConfig['prefix'] ?? 'LODOR', $uuid), $uploadConfig, $cacheConfig['ttl'] ?? 3600);
    }

    /**
     * Removes the upload config cache for the specified UUID.
     * The upload status cache needs to stay intact for
     * frontend feedback and should be removed by
     * a separate cleanup job only.
     *
     * @param string $uuid
     */
    public function removeUploadConfig(string $uuid)
    {
        $cachePrefix = config('lodor.cache.upload_config.prefix', 'LODOR');
        Cache::forget(sprintf('%s_%s', $cachePrefix, $uuid));
    }

    /**
     * Sets the finalFilename info in the config for the upload identified by the specified $uuid.
     * The finalFilename is used by the UploadToCloudinary job to determine which file to upload.
     *
     * The idea behind this field is to upload all files from the location where they really are,
     * instead of moving it across disks, e.g. from chunkedUploads to uploads/complete. If the
     * disks are in physically different machines, moving that file across the network prior to
     * uploading is a costly process.
     *
     * @param String $uuid
     * @param string $absoluteDestinationFilename
     *
     * @return bool
     * @throws InvalidArgumentException
     *
     */
    public function setUploadDestinationFilename(string $uuid, string $absoluteDestinationFilename): bool
    {
        $uploadConfig                  = $this->getUploadConfig($uuid);
        $uploadConfig['finalFilename'] = $absoluteDestinationFilename;
        return $this->setUploadConfig($uuid, $uploadConfig);
    }

    /**
     * Returns an array of absolute filenames to all chunks of the upload with the specified $uuid.
     * Returns an empty array if the upload is not chunked, throws exceptions if chunks are missing.
     *
     * @param String $uuid
     *
     * @return array
     * @throws FileNotFoundException
     */
    public function getUploadChunks(string $uuid): array
    {
        $chunks     = [];
        $config     = $this->getUploadConfig($uuid);
        $chunkCount = $config['chunkCount'] ?? 0;

        if ($chunkCount > 0) {
            $storageDisk = Storage::disk(Lodor::getChunkedUploadDiskName());
            if (!$storageDisk->exists($uuid)) {
                throw new FileNotFoundException(__('No upload config found for upload with UUID :uuid.', ['uuid' => $uuid, 'path' => $storageDisk->path($uuid)]));
            }

            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkFilename         = sprintf('%s%schunk_%03d', $uuid, DIRECTORY_SEPARATOR, $i);
                $absoluteChunkFilename = $storageDisk->path($chunkFilename);
                if (File::exists($absoluteChunkFilename)) {
                    $chunks[] = $absoluteChunkFilename;
                } else {
                    throw new FileNotFoundException(__('Chunk number :chunk_number of :total_chunk_count (:chunk_filename) not found for upload with UUID :uuid.',
                        ['total_chunk_count' => $chunkCount, 'chunk_number' => $i + 1, 'chunk_filename' => $absoluteChunkFilename, 'uuid' => $uuid]));
                }
            }
        }

        return $chunks;
    }

    /**
     * Returns the finalFilename info from the upload configuration.
     * Used by the upload job to determine which file to upload.
     *
     * @param String $uuid
     *
     * @return string
     * @throws UnexpectedValueException
     *
     */
    public function getUploadDestinationFilename(string $uuid): string
    {
        $uploadConfig = $this->getUploadConfig($uuid);

        if (!array_key_exists('finalFilename', $uploadConfig)) {
            throw new UnexpectedValueException(__('No file info found in config for upload with UUID :uuid.', ['uuid' => $uuid]));
        }

        return $uploadConfig['finalFilename'];
    }

    /**
     * Removes the upload file(s) for the upload specified by the $uuid.
     * If the optional $isChunked parameter is not specified, we try to
     * determine whether the upload is chunked or not from the config.
     *
     * @param string    $uuid
     *
     * @param bool|null $isChunked
     *
     * @return bool
     */
    public function removeUploadFiles(string $uuid, bool $isChunked = null): bool
    {
        $isChunked = $isChunked ?? $this->isChunked($uuid);

        if ($isChunked) {
            // Chunked: upload is on chunked upload disk in folder <uuid>.$
            // The whole directory must be deleted.
            if ($uuid) {
                $storageDisk = Storage::disk(Lodor::getChunkedUploadDiskName());
                try {
                    $storageDisk->deleteDirectory($uuid);
                } catch (Exception $e) {
                    return false;
                }
            }
        } else {
            // Not chunked: upload is on single upload disk, file <uuid>.
            // Single file, no directory removal necessary.
            $storageDisk = Storage::disk(Lodor::getSingleUploadDiskName());
            try {
                $storageDisk->delete($uuid);
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cleans up files and configs that are not needed anymore after an upload succeeded or failed.
     * We do not remove the upload status cache yet, as it might still be needed for frontend use.
     *
     * @param string    $uuid
     * @param bool|null $isChunked
     */
    public function cleanupUpload(string $uuid, bool $isChunked = null)
    {
        $this->removeUploadFiles($uuid, $isChunked);
        $this->removeUploadConfig($uuid);
    }

    /**
     * Returns true if the upload with the specified $uuid is chunked, false if not.
     *
     * @param string $uuid
     *
     * @return bool
     */
    public function isChunked(string $uuid): bool
    {
        if ($this->hasUploadConfig($uuid)) {
            $config = $this->getUploadConfig($uuid);
            return $config['chunked'] ?? false;
        }

        $storageDiskChunked = Storage::disk(Lodor::getChunkedUploadDiskName());

        if ($storageDiskChunked->exists($uuid)) {
            return true;
        }

        return false;
    }

    /**
     * If an upload file already exists, this overwrites or renames that file
     * according to the configured file exists strategy.
     *
     * Returns null if no strategy is defined, this should throw an exception.
     *
     * @param FilesystemAdapter $disk
     * @param string            $relativeFilename
     *
     * @return string|void
     */
    public function resolveFileExists(FilesystemAdapter $disk, string $relativeFilename): ?string
    {
        switch (config('lodor.file_exists_strategy')) {
            case 'overwrite':
                $disk->delete($relativeFilename);
                break;
            case 'rename':
                $extension = File::extension($relativeFilename);
                $filename  = substr($relativeFilename, 0, -strlen($extension) - 1);
                $count     = 0;

                do {
                    $newFilename = sprintf('%s_%04d.%s', $filename, ++$count, $extension);
                } while ($disk->exists($newFilename));

                $relativeFilename = $newFilename;

                break;
            default:
                return null;
        }

        return $relativeFilename;
    }

    /**
     * Returns the destination filename of the upload according to the configuration.
     *
     * @param string       $uuid
     * @param string       $requestFilename
     * @param string       $originalFilename
     * @param string       $originalExtension
     * @param UploadedFile $file
     * @param Request|null $request
     * @param array|null   $config
     *
     * @return string
     */
    public function getUploadFilename(string $uuid, string $requestFilename, string $originalFilename, string $originalExtension, UploadedFile $file = null, Request $request = null, array $config = null): string
    {
        $originalFilename  = $this->cleanFilename($originalFilename);
        $originalExtension = $this->cleanFilename($originalExtension);
        $requestFilename   = $this->cleanFilename($requestFilename);

        if ($requestFilename != '') {
            // If filename has been specified in request, use this as long
            // as it has an extension or explicitly ends with '.'.
            if (!File::extension($requestFilename) && !Str::endsWith($requestFilename, '.')) {
                // If extension is missing, substitute extension from the original filename.
                $requestFilename .= '.' . $originalExtension;
            }

            return $requestFilename;
        }

        switch (config('lodor.filename')) {
            case 'uuid':
                return $uuid;
            case 'uuid_ext':
                return sprintf('%s.%s', $uuid, $originalExtension);
            case 'class':
                if (app()->has('lodorFilename')) {
                    return app('lodorFilename')->getFilename($uuid, $requestFilename, $originalFilename, $originalExtension, $file, $request, $config);
                }
            // Intentional fallthrough.
            default:
                return $originalFilename;
        }
    }

    public function getUploadFilenameFromConfig(array $config)
    {
        $requestFilename   = Arr::get($config, 'metadata.lodor_filename', '');
        $uuid              = Arr::get($config, 'uuid', '');
        $originalFilename  = $config['originalFilename'] ?? '';
        $originalExtension = $config['originalExtension'] ?? '';

        return $this->getUploadFilename($uuid, $requestFilename, $originalFilename, $originalExtension, null, null, $config);
    }

    public function getUploadFilenameFromRequest(string $uuid, Request $request, UploadedFile $file)
    {
        $requestFilename   = $request->get('lodor_filename', '');
        $originalFilename  = $file->getClientOriginalName();
        $originalExtension = $file->getClientOriginalExtension();

        return $this->getUploadFilename($uuid, $requestFilename, $originalFilename, $originalExtension, $file, $request);
    }

    /**
     * Makes sure that the upload is put in waiting state if there are any
     * listeners registered for the FileUploaded event. Otherwise, sets
     * the upload to done and triggers the FileUploaded event.
     *
     * Called when an un-chunked upload has finished uploading or
     * a chunked upload has been merged successfully.
     *
     * @param string $uploadUuid
     * @param array  $uploadInfo
     */
    public function finishUpload(string $uploadUuid, array $uploadInfo = [])
    {
        $state = 'server_upload_done';

        $hasListeners = Event::hasListeners(FileUploaded::class);
        if ($hasListeners) {
            $state = 'server_upload_waiting';
        }

        $this->setUploadState($uploadUuid, $state, ['uuid' => $uploadUuid]);

        event(new FileUploaded($uploadUuid, $uploadInfo));
        if (!$hasListeners) {
            // No listeners waiting to act on this upload:
            // We are done and can do cleanup.
            event(new UploadFinished($uploadUuid, $uploadInfo));
        }
    }

    /**
     * Fails the upload after an error occured, fires the UploadFailed event
     * and cleans up after the upload.
     *
     * @param string $uploadUuid
     * @param string $status
     * @param string $info
     * @param array  $uploadInfo
     */
    public function failUpload(string $uploadUuid, string $status, string $info, array $uploadInfo = [])
    {
        $this->setUploadStatus($uploadUuid, 'error', $status, $info, 100);
        try {
            $this->cleanupUpload($uploadUuid);
        } catch (Exception $exception) {
            // Do nothing.
        }

        event(new UploadFailed($uploadUuid, $info, $uploadInfo));
    }

    /**
     * Returns an instance of an auto-detected Chunk Uploader or null if the request
     * is not part of a chunked upload or if no uploader matched the request.
     *
     * @param Request $request
     *
     * @return ChunkUploader|null
     */
    public function detectChunkUploader(Request $request): ?ChunkUploader
    {
        foreach (ChunkUploader::$uploaders as $uploaderClass) {
            if ($uploaderClass::isChunkedRequest($request)) {
                return new $uploaderClass($request);
            }
        }

        return null;
    }

    /**
     * Returns a chunk uploader that is either configured or auto-detected and matches the current request.
     * Returns null if no matching uploader could be found or the request is not part of a chunked upload.
     *
     * @param Request $request
     *
     * @return ChunkUploader|null
     */
    public function getChunkUploader(Request $request): ?ChunkUploader
    {
        $uploaderClass = config('lodor.merge_chunks.uploader');

        if ($uploaderClass && $uploaderClass instanceof ChunkUploader) {
            if ($uploaderClass::isChunkedRequest($request)) {
                return new $uploaderClass($request);
            }
            return null;
        }

        return $this->detectChunkUploader($request);
    }

    /**
     * Returns a string with all characters removed that are not UTF8 or not allowed in any of the major file systems.
     * By default, Lodor is very permissive with filenames so that UTF8 names remain unchanged if possible.
     * For extra security, you may want to implement your own filename sanitizing by using 'class' for
     * the lodor.filename config and registering an instance of your own filenameSanitizer class in
     * the service container as lodorFilename. See documentation for more info.
     *
     * @param string $filename
     *
     * @return string
     */
    public function cleanFilename(string $filename): string
    {
        // Eliminate any protocols and paths.
        $filename = File::basename($filename);

        // Remove characters not allowed in one of the major file systems.
        $filename = preg_replace('/[\/\\\:*?"<>|]/', '', $filename);

        // Remove non-UTF8 characters.
        return ASCII::clean($filename);
    }

    /**
     * Merges the chunks for the upload with the specified uuid.
     *
     * @param string $uuid
     *
     * @throws FileExistsException
     * @throws FileNotFoundException
     * @throws UploadNotFoundException
     */
    public function mergeChunkedFile(string $uuid = '')
    {
        if (!$this->isChunked($uuid)) {
            // Do not run if this upload is not a chunked upload.
            return;
        }

        $chunkedStorageDisk  = Storage::disk($this->getChunkedUploadDiskName());
        $completeStorageDisk = Storage::disk($this->getSingleUploadDiskName());

        if (!$chunkedStorageDisk->exists($uuid)) {
            // No directory exists for the specified uuid.
            throw new UploadNotFoundException(__('Upload not found with UUID :uuid at :path.', ['uuid' => $uuid, 'path' => $chunkedStorageDisk->path($uuid)]));
        } else {
            $config     = $this->getUploadConfig($uuid);
            $chunkCount = $config['chunkCount'] ?? 0;

            $destinationFilename = $this->getUploadFilenameFromConfig($config);

            if ($destinationFilename === null || $destinationFilename === '') {
                throw new InvalidArgumentException(__('Unable to determine destination filename for upload.'));
            }

            if ($completeStorageDisk->exists($destinationFilename)) {
                // Destination file already exists: act according to configuration.
                $destinationFilename = $this->resolveFileExists($completeStorageDisk, $destinationFilename);
                if ($destinationFilename === null) {
                    $this->setUploadStatus($uuid,
                        'error',
                        __('Merging chunks...'),
                        __('The destination file :filename already exists.', ['filename' => $destinationFilename]),
                        100);

                    throw new FileExistsException(__('The destination file :filename already exists.', ['filename' => $destinationFilename]));
                }
            }

            $absoluteDestinationFilename = $completeStorageDisk->path($destinationFilename);

            try {
                for ($i = 0; $i < $chunkCount; $i++) {
                    $chunkFilename         = sprintf('%s%schunk_%03d', $uuid, DIRECTORY_SEPARATOR, $i);
                    $absoluteChunkFilename = $chunkedStorageDisk->path($chunkFilename);
                    if ($chunkedStorageDisk->exists($chunkFilename)) {
                        $sourceFileHandle      = fopen($absoluteChunkFilename, 'rb');
                        $destinationFileHandle = fopen($absoluteDestinationFilename, $i === 0 ? 'w' : 'a+');

                        $this->setUploadState($uuid, 'merge_chunks', ['current_chunk' => $i + 1, 'total_chunks' => $chunkCount], (($i + 1) / $chunkCount));

                        stream_copy_to_stream($sourceFileHandle, $destinationFileHandle);
                    } else {
                        $this->setUploadStatus($uuid,
                            'error',
                            __('Merging chunks...'),
                            __('Missing chunk :missing_chunk of :total_chunks: expected file :missing_filename',
                                ['missing_chunk' => $i + 1, 'total_chunks' => $chunkCount, 'missing_filename' => $absoluteChunkFilename]),
                            100);

                        throw new FileNotFoundException(sprintf('File not found for chunk %d of %d: expected file %s', $i + 1, $chunkCount, $absoluteChunkFilename));
                    }
                }
            } catch (Exception $e) {
                // File/disk related error.
                $this->setUploadStatus($uuid,
                    'error',
                    __('Merging chunks...'),
                    __(':exception_class while merging: :exception_message', ['exception_class' => get_class($e), 'exception_message' => $e->getMessage()], 100));

                throw new FileNotFoundException($e);
            } finally {
                if ($sourceFileHandle ?? false) {
                    fclose($sourceFileHandle);
                }

                if ($destinationFileHandle ?? false) {
                    fclose($destinationFileHandle);
                }

                $chunkedStorageDisk->delete($destinationFilename);
            }
        }

        $this->setUploadDestinationFilename($uuid, $absoluteDestinationFilename);
    }
}
