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
    abstract public static function isChunkedRequest(Request $request): bool;

    /**
     * Returns the total number of chunks in this upload.
     * Counting needs to start at 0.
     *
     * @param int $default
     *
     * @return int
     */
    abstract public function getChunkCount(int $default = 0): int;

    /**
     * Returns the Uuid for this upload, if any.
     *
     * @param string $default
     *
     * @return string|null
     */
    abstract public function getUploadUuid(string $default = null): ?string;

    /**
     * Returns the file size of the current chunk.
     *
     * @param int $default
     *
     * @return int
     */
    abstract public function getChunkSize(int $default = 0): int;

    /**
     * Returns the index of the current chunk or null.
     *
     * @return int|null
     */
    abstract public function getChunkIndex(): ?int;
}
