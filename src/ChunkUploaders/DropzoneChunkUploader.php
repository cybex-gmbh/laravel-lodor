<?php

namespace Cybex\Lodor\ChunkUploaders;

class DropzoneChunkUploader extends ChunkUploader
{
    /**
     * Request key name that is used for the upload uuid.
     *
     * @var string
     */
    protected static $keyUuid = 'dzuuid';

    /**
     * Request key name specifying the total number of chunks of the upload.
     *
     * @var string
     */
    protected static $keyChunkCount = 'dztotalchunkcount';

    /**
     * Request key name specifying the index of the currently transferred chunk.
     *
     * @var string
     */
    protected static $keyChunkIndex = 'dzchunkindex';

    /**
     * Request key name specifying the chunk size in bytes.
     *
     * @var string
     */
    protected static $keyChunkSize = 'dzchunksize';
}
