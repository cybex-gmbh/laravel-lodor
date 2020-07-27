<?php

namespace Cybex\Lodor\ChunkUploaders;

use Illuminate\Http\Request;

abstract class ChunkUploader
{
    /**
     * Array of uploader classes that should be used in auto-detection.
     * Should include all uploaders that are supported by Lodor.
     *
     * @var string[]
     */
    public static $uploaders = [
        DropzoneChunkUploader::class,
        ResumableJsChunkUploader::class,
    ];

    /**
     * @var Request
     */
    protected $request;

    /**
     * Request key name that is used for the upload uuid.
     *
     * @var string
     */
    protected static $keyUuid;

    /**
     * Request key name specifying the total number of chunks of the upload.
     *
     * @var string
     */
    protected static $keyChunkCount;

    /**
     * Request key name specifying the index of the currently transferred chunk.
     *
     * @var string
     */
    protected static $keyChunkIndex;

    /**
     * Request key name specifying the chunk size in bytes.
     *
     * @var string
     */
    protected static $keyChunkSize;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Returns true if the specified request seems to be a chunked upload that can be handled by this chunk uploader.
     *
     * @param Request $request
     *
     * @return bool
     */
    public static function isChunkedRequest(Request $request): bool
    {
        return $request->has([static::$keyUuid, static::$keyChunkCount, static::$keyChunkIndex]) && $request->input(static::$keyChunkCount, 0) > 0;
    }

    /**
     * Returns the total number of chunks in this upload.
     * Counting needs to start at 0.
     *
     * @param int $default
     *
     * @return int
     */
    public function getChunkCount(int $default = 0): int
    {
        return $this->request->input(static::$keyChunkCount, 0);
    }

    /**
     * Returns the Uuid for this upload, if any.
     *
     * @param string $default
     *
     * @return string|null
     */
    public function getUploadUuid(string $default = null): ?string
    {
        return $this->request->input(static::$keyUuid, $default);
    }

    /**
     * Returns the file size of the current chunk.
     *
     * @param int $default
     *
     * @return int
     */
    public function getChunkSize(int $default = 0): int
    {
        return $this->request->input(static::$keyChunkSize, $default);
    }

    /**
     * Returns the index of the current chunk or null.
     *
     * @return int|null
     */
    public function getChunkIndex(): ?int
    {
        return $this->request->input(static::$keyChunkIndex);
    }
}
