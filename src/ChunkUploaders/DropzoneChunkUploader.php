<?php

namespace Cybex\Lodor\ChunkUploaders;

use Illuminate\Http\Request;

class DropzoneChunkUploader extends ChunkUploader
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
        return $request->has(['dzuuid', 'dztotalchunkcount', 'dzchunkindex']) && $request->input('dztotalchunkcount', 0) > 0;
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
        return $this->request->input('dztotalchunkcount', 0);
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
        return $this->request->input('dzuuid', $default);
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
        return $this->request->input('dzchunksize', $default);
    }

    /**
     * Returns the zero-based index of the current chunk or null.
     *
     * @return int|null
     */
    public function getChunkIndex(): ?int
    {
        return $this->request->input('dzchunkindex');
    }
}
