<?php

namespace Cybex\Lodor\ChunkUploaders;

class ResumableJsChunkUploader extends ChunkUploader
{
    /**
     * Request key name that is used for the upload uuid.
     *
     * @var string
     */
    protected static $keyUuid = 'resumableIdentifier';

    /**
     * Request key name specifying the total number of chunks of the upload.
     *
     * @var string
     */
    protected static $keyChunkCount = 'resumableTotalChunks';

    /**
     * Request key name specifying the index of the currently transferred chunk.
     *
     * @var string
     */
    protected static $keyChunkIndex = 'resumableChunkNumber';

    /**
     * Request key name specifying the chunk size in bytes.
     *
     * @var string
     */
    protected static $keyChunkSize = 'resumableChunkSize';

    /**
     * Returns the index of the current chunk or null.
     * The index is not zero-based in ResumableJS.
     *
     * @return int|null
     */
    public function getChunkIndex(): ?int
    {
        $chunkIndex = $this->request->input(self::$keyChunkIndex);

        return $chunkIndex === null ? null : $chunkIndex - 1;
    }
}
