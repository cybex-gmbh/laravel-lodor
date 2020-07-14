<?php

namespace Cybex\Lodor\Tests;

use Cybex\Lodor\ChunkUploaders\DropzoneChunkUploader;
use Cybex\Lodor\ChunkUploaders\ResumableJsChunkUploader;
use Cybex\Lodor\LodorFacade as Lodor;
use Cybex\Lodor\LodorServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Orchestra\Testbench\TestCase;

class ChunkUploadersTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LodorServiceProvider::class];
    }

    /**
     * @test
     * @dataProvider uploaderConfigProvider
     *
     * @param $uploaderClass
     * @param $requiredParameters
     */
    public function uploaderIsCreatedFromConfig($uploaderClass, $requiredParameters)
    {
        Config::set('lodor.merge_chunks.uploader', $uploaderClass);

        $request = new Request();

        // Without any request data, getChunkUploader() should return null,
        // because this is not a supported chunked upload request.
        $this->assertNull(Lodor::getChunkUploader($request));

        $request->merge($requiredParameters);

        $this->assertEquals($uploaderClass, get_class(Lodor::getChunkUploader($request)));
    }

    /**
     * @test
     * @dataProvider uploaderProvider
     *
     * @param $expectedUploader
     * @param $parameters
     */
    public function uploaderIsAutoDiscovered($expectedUploader, $parameters)
    {
        $request = new Request();
        $request->merge($parameters);

        $uploader = Lodor::detectChunkUploader($request);

        if ($expectedUploader === null) {
            $this->assertNull($uploader);
        } else {
            $this->assertEquals($expectedUploader, get_class($uploader));
        }
    }

    /**
     * @test
     * @dataProvider zeroIndexProvider
     *
     * @param $expectedUploader
     * @param $parameters
     */
    public function returnsZeroBasedChunkIndex($parameters)
    {
        $request = new Request();
        $request->merge($parameters);

        $uploader = Lodor::detectChunkUploader($request);

        $this->assertEquals(0, $uploader->getChunkIndex());
    }

    public function zeroIndexProvider()
    {
        // All of these request parameters should reflect a valid chunked upload request and the chunk index should be at the first chunk.
        return [
            "Zero-based Dropzone chunk index"   => [['dzuuid' => 'any-uuid', 'dztotalchunkcount' => 2, 'dzchunkindex' => 0]],
            "One-based ResumableJS chunk index" => [['resumableIdentifier' => 'any-uuid', 'resumableTotalChunks' => 10, 'resumableChunkNumber' => 1]],
        ];
    }

    public function uploaderConfigProvider()
    {
        return [
            "Dropzone config"    => [DropzoneChunkUploader::class, ['dzuuid' => 'any-uuid', 'dztotalchunkcount' => 2, 'dzchunkindex' => 1]],
            "ResumableJS config" => [ResumableJsChunkUploader::class, ['resumableIdentifier' => 'any-uuid', 'resumableTotalChunks' => 10, 'resumableChunkNumber' => 1]],
        ];
    }

    public function uploaderProvider()
    {
        return [
            'Unchunked upload'                                 => [null, []],
            'Incomplete dropzone parameter set'                => [null, ['dzuuid' => 'any-uuid']],
            'Incomplete ResumableJs parameter set'             => [null, ['resumableIdentifier' => 'any-uuid']],
            'Dropzone parameters indicating only one chunk'    => [null, ['dzuuid' => 'any-uuid', 'dztotalchunkcount' => 0, 'dzchunkindex' => 0]],
            'ResumableJS parameters indicating only one chunk' => [null, ['resumableIdentifier' => 'any-uuid', 'resumableTotalChunks' => 0, 'resumableChunkNumber' => 0]],
            'First chunk of dropzone chunked upload'           => [DropzoneChunkUploader::class, ['dzuuid' => 'any-uuid', 'dztotalchunkcount' => 10, 'dzchunkindex' => 0]],
            'Last chunk of dropzone chunked upload'            => [DropzoneChunkUploader::class, ['dzuuid' => 'any-uuid', 'dztotalchunkcount' => 2, 'dzchunkindex' => 1]],
            'First chunk of resumableJs chunked upload'        => [
                ResumableJsChunkUploader::class,
                ['resumableIdentifier' => 'any-uuid', 'resumableTotalChunks' => 10, 'resumableChunkNumber' => 1]
            ],
            'Last chunk of resumableJs chunked upload'         => [
                ResumableJsChunkUploader::class,
                ['resumableIdentifier' => 'any-uuid', 'resumableTotalChunks' => 21, 'resumableChunkNumber' => 21]
            ],
        ];
    }
}
