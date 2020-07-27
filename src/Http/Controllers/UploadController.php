<?php

namespace Cybex\Lodor\Http\Controllers;

use Exception;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Routing\Controller;
use Cybex\Lodor\Events\UploadFailed;
use Illuminate\Http\RedirectResponse;
use Cybex\Lodor\Events\ChunkUploaded;
use Cybex\Lodor\LodorFacade as Lodor;
use Illuminate\Support\Facades\Storage;
use Cybex\Lodor\Events\ChunkedFileUploaded;
use Illuminate\Contracts\Foundation\Application;

class UploadController extends Controller
{
    /**
     * Store a new media upload for further processing.
     *
     * @param Request $request
     *
     * @return Application|JsonResponse|RedirectResponse|Redirector
     */
    public function store(Request $request)
    {
        $chunkUploader = Lodor::getChunkUploader($request);
        $file          = $request->file('file');
        $chunkIndex    = 0;
        $chunkCount    = 0;
        $finalFilename = null;
        $uploadUuid    = Uuid::uuid4()->toString();

        if ($chunkUploader) {
            // This is a chunked upload.
            $chunkCount = $chunkUploader->getChunkCount(0);
            $chunkSize  = $chunkUploader->getChunkSize(0);
            $chunkIndex = $chunkUploader->getChunkIndex();
            $uploadUuid = Str::slug($chunkUploader->getUploadUuid($uploadUuid));

            $status = Lodor::getUploadStatus($uploadUuid);
            if ($state = Arr::get($status, 'state')) {
                if (collect(['done', 'waiting', 'error'])->contains($state)) {
                    // If the upload has already finished (done, waiting) or an error
                    // occured, do not process further chunks of this upload.
                    return response()->json(['success' => false, 'uuid' => $uploadUuid, 'message' => __('The upload already finished.')])->setStatusCode(500);
                }
            }

            // Move file to appropriate folder.
            $chunkedUploadDisk   = Lodor::getChunkedUploadDiskName();
            $destinationFilename = $file->storeAs($uploadUuid, sprintf('chunk_%03d', $chunkIndex), ['disk' => $chunkedUploadDisk]);
            $destinationFilename = Storage::disk($chunkedUploadDisk)->path($destinationFilename);
        } else {
            // Not chunked: move directly to upload store.
            $storageDisk = Storage::disk(Lodor::getSingleUploadDiskName());
            $filename    = Lodor::getUploadFilenameFromRequest($uploadUuid, $request, $file);

            if ($filename === null || $filename === '') {
                return $this->failUpload($request, $uploadUuid, __('Unexpected error: Unable to obtain original filename.'));
            }

            if ($storageDisk->exists($filename)) {
                // Destination file already exists: act according to configuration.
                $newFilename = Lodor::resolveFileExists($storageDisk, $filename);

                if ($newFilename === null) {
                    return $this->failUpload($request, $uploadUuid, __('The destination file :filename already exists.', ['filename' => $filename]));
                }

                $filename = $newFilename;
            }

            $storageDisk->putFileAs('', $file, $filename);
            $finalFilename = $storageDisk->path($filename);
        }

        $uploadInfo = [
            'chunked'           => $chunkCount > 0,
            'chunkSize'         => $chunkSize ?? null,
            'chunkCount'        => $chunkCount,
            'uploadFilename'    => $destinationFilename ?? $finalFilename,
            'metadata'          => $request->except(['_token', 'file']),
            'originalFilename'  => $file->getClientOriginalName(),
            'originalExtension' => $file->clientExtension(),
            'mimeType'          => $file->getClientMimeType(),
            'fileSize'          => $file->getSize(),
            'finalFilename'     => $finalFilename ?? null,
        ];

        if ($chunkIndex == 0) {
            // Only write to the cache if this is the first chunk (or not a chunked
            // upload), to prevent race conditions that could lead to data loss.
            Lodor::setUploadConfig($uploadUuid, $uploadInfo);
        }

        if (!$chunkCount) {
            // Dispatch job via event if the upload is not chunked.
            // Otherwise we have to wait until all chunks are here.
            $this->uploadFinished($uploadUuid, $uploadInfo ?? []);
        } else {
            // See if all chunks are present, and if so, dispatch the event.
            try {
                $chunks = Lodor::getUploadChunks($uploadUuid);

                if (count($chunks)) {
                    Lodor::setUploadState($uploadUuid, 'awaiting_merge', ['uuid' => $uploadUuid]);
                    event(new ChunkUploaded($uploadUuid, $chunkIndex, $uploadInfo ?? []));
                    event(new ChunkedFileUploaded($uploadUuid, $uploadInfo ?? []));
                }
            } catch (Exception $e) {
                // There are still chunks missing...
                event(new ChunkUploaded($uploadUuid, $chunkIndex, $uploadInfo ?? []));
            }
        }

        if ($routeArray = Lodor::getRedirectRoute()) {
            [, $controller, $actionMethod] = $routeArray;
            return $controller->callAction($actionMethod, [$request, true, $uploadUuid, $uploadInfo]);
        }

        return response()->json(['success' => true, 'uuid' => $uploadUuid]);
    }

    /**
     * Return the upload status of the uploads with the specified uuids.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function poll(Request $request)
    {
        return response()->json(Lodor::getMultiUploadStatus($request->input('uuids', [])));
    }

    protected function uploadFinished(string $uploadUuid, array $uploadInfo)
    {
        Lodor::finishUpload($uploadUuid, $uploadInfo);
    }

    /**
     * @param Request $request
     * @param string  $uploadUuid
     * @param string  $errorMessage
     *
     * @return Application|JsonResponse|RedirectResponse|Redirector|object
     */
    protected function failUpload(Request $request, string $uploadUuid, string $errorMessage)
    {
        Lodor::failUpload($uploadUuid, __('Server upload failed.'), $errorMessage);

        if ($routeArray = Lodor::getRedirectRoute()) {
            [, $controller, $actionMethod] = $routeArray;
            return $controller->callAction($actionMethod, [$request, false, $uploadUuid, [], $errorMessage]);
        }

        return response()->json(['success' => false, 'uuid' => $uploadUuid, 'error' => $errorMessage])->setStatusCode(500);
    }
}
