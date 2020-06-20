<?php

namespace Cybex\Lodor\ChunkUploaders;

use Illuminate\Http\Request;

class ResumableJsChunkUploader extends ChunkUploader
{
    /**
     * Returns true if the specified request seems to be a chunked upload that can be handled by this chunk uploader.
     *
     * @param Request $request
     *
     * @return bool
     */
    public static function isChunkedRequest(Request $request): bool
    {
        return $request->has(['resumableIdentifier', 'resumableTotalChunks', 'resumableChunkNumber']) && $request->resumableTotalChunks > 0;
    }

    /**
     * Returns the total number of chunks in this upload.
     *
     * @param int $default
     *
     * @return int
     */
    public function getChunkCount(int $default = 0): int
    {
        return $this->request->input('resumableTotalChunks', 0);
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
        return $this->request->input('resumableIdentifier', $default);
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
        return $this->request->input('resumableChunkSize', $default);
    }

    /**
     * Returns the index of the current chunk or null.
     * The index is not zero-based in ResumableJS.
     *
     * @return int|null
     */
    public function getChunkIndex(): ?int
    {
        $chunkIndex = $this->request->input('resumableChunkNumber');

        return $chunkIndex === null ? null : $chunkIndex - 1;
    }
}
