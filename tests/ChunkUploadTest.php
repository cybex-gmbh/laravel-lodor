<?php

namespace Cybex\Lodor\Tests;

use Cybex\Lodor\Events\ChunkedFileUploaded;
use Cybex\Lodor\Events\ChunkUploaded;
use Cybex\Lodor\Events\FileUploaded;
use Cybex\Lodor\Events\UploadFailed;
use Cybex\Lodor\Events\UploadFinished;
use Cybex\Lodor\Listeners\MergeChunks;
use Cybex\Lodor\LodorFacade as Lodor;
use Cybex\Lodor\LodorServiceProvider;
use Illuminate\Contracts\Filesystem\FileExistsException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Orchestra\Testbench\TestCase;
use Ramsey\Uuid\Uuid;

class ChunkUploadTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LodorServiceProvider::class];
    }

    /**
     * @test
     */
    public function chunkedFileUploadEventsFire()
    {
        Event::fake();
        $fakeChunkStorage = TestHelper::fakeChunkStorage();
        $uploadUuid       = Uuid::uuid4()->toString();
        $chunkNumber      = 0;

        TestHelper::createFileChunks('test.mpg', 4000, 2)->each(function ($chunkFile) use (&$chunkNumber, $uploadUuid, $fakeChunkStorage) {
            $this->uploadFile($chunkFile, TestHelper::getChunkParametersForDropzone(2, $chunkNumber, $uploadUuid));

            Event::assertDispatched(ChunkUploaded::class,
                function ($event) use ($fakeChunkStorage, $chunkNumber, $uploadUuid) {
                    return $event->uuid === $uploadUuid &&
                        $event->metadata['chunked'] == true &&
                        $event->metadata['chunkCount'] === 2 &&
                        $event->metadata['metadata']['dzchunkindex'] === $chunkNumber &&
                        $event->metadata['originalFilename'] === 'test.mpg' &&
                        $event->metadata['uploadFilename'] = $fakeChunkStorage->path($uploadUuid . DIRECTORY_SEPARATOR . sprintf('chunk_%03d', $chunkNumber));
                });

            $chunkNumber++;
        });

        Event::assertDispatched(ChunkedFileUploaded::class,
            function ($event) use ($uploadUuid) {
                return $event->uuid === $uploadUuid;
            });

    }

    /**
     * @test
     * @dataProvider uploadFileProvider
     */
    public function chunkedFileUploadSucceeds($filename, $filesize)
    {
        Event::fake();

        $fakeChunkStorage = TestHelper::fakeChunkStorage();

        $imageFiles = TestHelper::createFileChunks($filename, $filesize, 4);
        $uploadUuid = Uuid::uuid4()->toString();

        // Upload chunks in mixed order to ensure sequential transfer is irrelevant.
        foreach ([2, 3, 0, 1] as $chunkNumber) {
            $imageFile = $imageFiles->get($chunkNumber);

            $postTestResponse = $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $postTestResponse->assertStatus(200)->assertJson(['success' => true], true);

            $fakeChunkStorage->assertExists(sprintf('%s%schunk_%03d', $uploadUuid, DIRECTORY_SEPARATOR, $chunkNumber));

            Event::assertDispatched(ChunkUploaded::class,
                function ($event) use ($uploadUuid, $chunkNumber) {
                    return $event->uuid === $uploadUuid && $event->chunkIndex === $chunkNumber;
                });
        }

        Event::assertDispatched(ChunkedFileUploaded::class,
            function ($event) use ($uploadUuid) {
                return $event->uuid === $uploadUuid;
            });
    }

    /**
     * @test
     */
    public function extraChunksFailToUploadAndAreOtherwiseIgnored()
    {
        Event::fake();

        $fakeChunkStorage = TestHelper::fakeChunkStorage();

        $imageFiles = TestHelper::createFileChunks('test.mp4', 8000, 4);
        $uploadUuid = Uuid::uuid4()->toString();
        $chunkCount = 0;

        foreach ([2, 3, 0, 1, 2] as $chunkNumber) {
            $chunkCount++;
            $imageFile = $imageFiles->get($chunkNumber);

            $postTestResponse = $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));

            if ($chunkCount === 5) {
                $postTestResponse->assertStatus(500)->assertJson(['success' => false, 'message' => 'The upload already finished.'], true);
                Event::assertNotDispatched(UploadFailed::class);
            } else {
                $postTestResponse->assertStatus(200)->assertJson(['success' => true], true);
                $fakeChunkStorage->assertExists(sprintf('%s%schunk_%03d', $uploadUuid, DIRECTORY_SEPARATOR, $chunkNumber));
            }
        }

        Event::assertDispatchedTimes(ChunkedFileUploaded::class, 1);
    }

    /**
     * @test
     */
    public function duplicateChunksAreSilentlyOverwritten()
    {
        Event::fake();

        $fakeChunkStorage = TestHelper::fakeChunkStorage();

        $imageFiles = TestHelper::createFileChunks('test.mp4', 8000, 4);
        $uploadUuid = Uuid::uuid4()->toString();

        foreach ([2, 2, 0, 3, 0, 1] as $chunkNumber) {
            $imageFile = $imageFiles->get($chunkNumber);

            $postTestResponse = $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $postTestResponse->assertStatus(200)->assertJson(['success' => true], true);

            $fakeChunkStorage->assertExists(sprintf('%s%schunk_%03d', $uploadUuid, DIRECTORY_SEPARATOR, $chunkNumber));
        }

        Event::assertDispatchedTimes(ChunkedFileUploaded::class, 1);
    }

    /**
     * @test
     */
    public function fileChunksAreMergedSynchronously()
    {
        Queue::fake();

        Config::set('lodor.merge_chunks.run_async', false);

        [, $fakeUploadStorage] = $this->uploadChunkedFile('syncvideo.avi', 5000, 4);

        $this->assertFileExists($fakeUploadStorage->path('syncvideo.avi'), 'The merged file was not found.');

        Queue::assertNotPushed(CallQueuedListener::class, function ($listener) {
            return $listener->class === MergeChunks::class;
        });
    }

    /**
     * @test
     */
    public function fileChunksAreNotMergedAutomatically()
    {
        Queue::fake();

        Config::set('lodor.merge_chunks.run_async', false);
        Config::set('lodor.merge_chunks.auto_merge_chunks', false);

        [, $fakeUploadStorage] = $this->uploadChunkedFile('automerge.avi', 5000, 4);

        $this->assertFileDoesNotExist($fakeUploadStorage->path('automerge.avi'), 'The file was merged despite auto_merge_chunks was set to false.');

        Queue::assertNotPushed(CallQueuedListener::class, function ($listener) {
            return $listener->class === MergeChunks::class;
        });
    }


    /**
     * @test
     */
    public function fileChunksAreMergedAsynchronously()
    {
        Queue::fake();
        Config::set('lodor.merge_chunks.run_async', true);

        $queue = config('lodor.merge_chunks.default_queue', 'uploads');

        $this->uploadChunkedFile('video.avi', 5000, 4);

        Queue::assertPushedOn($queue,
            CallQueuedListener::class,
            function ($listener) {
                return $listener->class === MergeChunks::class;
            });

        $listenerCollection = Queue::pushed(CallQueuedListener::class,
            function ($listener) {
                return $listener->class === MergeChunks::class;
            });

        $this->assertCount(1, $listenerCollection, 'The MergeChunks listener was queued more than once.');
    }

    /**
     * @test
     */
    public function fileChunksAreProperlyCleanedUpAfterUpload()
    {
        // We want FileUploaded and ChunkFileUploaded to fire in order to invoke MergeChunks
        // and CleanupUpload, so only fake the events that should not explicitly be called.
        Event::fake([
            FileUploaded::class,
            UploadFailed::class,
        ]);

        Queue::fake();

        Config::set('lodor.merge_chunks.run_async', false);

        [$fakeChunkStorage, $fakeUploadStorage, $uploadUuid] = $this->uploadChunkedFile('cleanupvideo.avi', 5000, 4);

        $this->assertFileExists($fakeUploadStorage->path('cleanupvideo.avi'), 'The merged file was not found.');
        $this->assertDirectoryDoesNotExist($fakeChunkStorage->path($uploadUuid), 'The chunks have not been cleaned up.');

        Event::assertDispatched(FileUploaded::class, 1);
    }

    /**
     * @test
     */
    public function existingFileThrowsExceptionAfterMerge()
    {
        $this->expectException(FileExistsException::class);

        Event::fake();

        Config::set('lodor.file_exists_strategy', '');

        $fakeUploadStorage = TestHelper::fakeUploadStorage();

        $imageFiles  = TestHelper::createFileChunks('video.avi', 5000, 4);
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);

        $this->assertFileExists($fakeUploadStorage->path('video.avi'), 'The merged file was not found.');

        // Set new uuid and upload the same file again.
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);
    }

    /**
     * @test
     */
    public function existingFileIsRenamedAfterMerge()
    {
        Event::fake();

        Config::set('lodor.file_exists_strategy', 'rename');

        $fakeUploadStorage = TestHelper::fakeUploadStorage();

        $imageFiles  = TestHelper::createFileChunks('video.avi', 5000, 4);
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);

        $this->assertFileExists($fakeUploadStorage->path('video.avi'), 'The merged file was not found.');

        // Set new uuid and upload the same file again.
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);

        $this->assertFileExists($fakeUploadStorage->path('video_0001.avi'), 'The file was not renamed.');
    }

    /**
     * @test
     */
    public function existingFileIsOverwrittenAfterMerge()
    {
        Event::fake();

        Config::set('lodor.file_exists_strategy', 'overwrite');

        $fakeUploadStorage = TestHelper::fakeUploadStorage();

        $imageFiles  = TestHelper::createFileChunks('image.jpg', 5000, 4, 800, 600);
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);

        $this->assertFileExists($fakeUploadStorage->path('image.jpg'), 'The merged file was not found.');

        // Set new uuid and upload the same file again.
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        $imageFiles = TestHelper::createFileChunks('image.jpg', 2000, 2, 1024, 768);

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(2, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);

        $imageSize = getimagesize($fakeUploadStorage->path('image.jpg'));
        $this->assertEquals(1024, $imageSize[0]);
    }

    /**
     * @test
     */
    public function MissingChunksThrowFileNotFoundExceptionWhenMerging()
    {
        Event::fake();

        $this->expectException(FileNotFoundException::class);

        Config::set('lodor.file_exists_strategy', 'overwrite');

        $fakeUploadStorage = TestHelper::fakeUploadStorage();

        $imageFiles  = TestHelper::createFileChunks('image.jpg', 5000, 2, 800, 600);
        $uploadUuid  = Uuid::uuid4()->toString();
        $chunkNumber = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        Lodor::mergeChunkedFile($uploadUuid);
    }

    /**
     * Uploads the specified file with optional parameters to the default upload route.
     *
     * @param       $imageFile
     * @param array $additionalParameters
     *
     * @return mixed
     */
    protected function uploadFile($imageFile, $additionalParameters = [])
    {
        return $this->post(Lodor::getUploadRoute(), array_merge($additionalParameters, ['file' => $imageFile]));
    }

    /**
     * Provides valid filenames and -sizes.
     *
     * @return mixed
     */
    public function uploadFileProvider()
    {
        return [
            'Regular image name'                    => ['testimage.jpg', 128],
            'Regular video name'                    => ['testvideo.mp4', 1024 * 200],
            // Paths should be stripped out and upload should work.
            'Path in original filename'             => ['../../../danger.php', 12],
            'Chinese UTF-8 characters in filename'  => ['測試.jpg', 80],
            // Null-byte should be cleaned and upload should still work.
            'Null-byte in filename'                 => ['測' . chr(0) . '試.jpg', 80],
            'Hindi UTF-8 characters in filename'    => ['परीक्षा.jpg', 80],
            'Cyrillic UTF-8 characters in filename' => ['тестовое задание.jpg', 80],
            'RTL character in filename'             => ['מִבְחָן.jpg', 80],
            'Reserved characters in filename'       => ['lots:of\reserved/characters?.<jpg>', 30],
        ];
    }

    /**
     * Provides invalid filenames and -sizes.
     *
     * @return mixed
     */
    public function maliciousFileProvider()
    {
        return [
            'Empty filename'       => ['', 50],
            'Nullbyte as filename' => [chr(0), null],
        ];
    }

    /**
     * Helper method to fake a chunked file upload.
     *
     * @param string $uploadUuid
     * @param string $filename
     * @param int    $fileSize
     * @param int    $numChunks
     *
     * @return array
     */
    protected function uploadChunkedFile(string $filename, int $fileSize, int $numChunks)
    {
        $fakeChunkStorage  = TestHelper::fakeChunkStorage();
        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $uploadUuid        = Uuid::uuid4()->toString();
        $imageFiles        = TestHelper::createFileChunks($filename, $fileSize, $numChunks);
        $chunkNumber       = 0;

        foreach ($imageFiles as $imageFile) {
            $this->uploadFile($imageFile, TestHelper::getChunkParametersForDropzone(4, $chunkNumber, $uploadUuid));
            $chunkNumber++;
        }

        return [$fakeChunkStorage, $fakeUploadStorage, $uploadUuid];

    }
}

