<?php

namespace Cybex\Lodor\Tests;

use Cybex\Lodor\LodorFacade as Lodor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class TestHelper {
    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public static function fakeUploadStorage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::fake(Lodor::getSingleUploadDiskName());
    }

    /**
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    public static function fakeChunkStorage(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::fake(Lodor::getChunkedUploadDiskName());
    }

    public static function createFileChunks(string $filename, int $filesize, int $numberOfChunks, int $width = 0, int $height = 0): Collection
    {
        $imageFiles = collect();
        $chunkSize = floor($filesize / $numberOfChunks);
        while ($filesize > 0) {
            $filesize -= $chunkSize;

            if ($filesize < 0) {
                $chunkSize += $filesize;
            }

            $file = $width ? UploadedFile::fake()->image($filename, $width, $height) : UploadedFile::fake()->create($filename, $chunkSize);

            $imageFiles->push($file);
        }

        return $imageFiles;
    }

    public static function getChunkParametersForDropzone(int $totalChunks, int $currentChunk, string $uploadUuid = null)
    {
        return [
            'dzuuid' => $uploadUuid ?? Uuid::uuid4()->toString(),
            'dztotalchunkcount' => $totalChunks,
            'dzchunkindex' => $currentChunk,
        ];
    }
    public static function getChunkParametersForResumableJs(int $totalChunks, int $currentChunk, string $uploadUuid = null)
    {
        return [
            'dzuuid' => $uploadUuid ?? Uuid::uuid4()->toString(),
            'dztotalchunkcount' => $totalChunks,
            'dzchunkindex' => $currentChunk,
        ];
    }
}

class LodorFilenameSanitizer {
    function getFilename(string $uuid, string $requestFilename, string $originalFilename, string $originalExtension, UploadedFile $file = null, Request $request = null, array $config = null) {
        return $uuid . '-binding';
    }
}
