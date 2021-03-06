<?php

namespace Cybex\Lodor\Tests;

use Cybex\Lodor\Events\FileUploaded;
use Cybex\Lodor\Events\UploadFailed;
use Cybex\Lodor\Events\UploadFinished;
use Cybex\Lodor\LodorFacade as Lodor;
use Cybex\Lodor\LodorServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Ramsey\Uuid\Uuid;
use voku\helper\ASCII;

class FileUploadedTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LodorServiceProvider::class];
    }

    /**
     * @test
     */
    public function fileUploadedEventFires()
    {
        Event::fake();
        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create('test.jpg', 20);
        $this->uploadFile($imageFile);
        Event::assertDispatched(FileUploaded::class,
            function ($event) use ($fakeUploadStorage) {
                return Uuid::isValid($event->uuid) &&
                    $event->metadata['chunked'] == false &&
                    $event->metadata['chunkCount'] === 0 &&
                    $event->metadata['originalFilename'] === 'test.jpg' &&
                    $event->metadata['fileSize'] === 20 * 1024 &&
                    $event->metadata['uploadFilename'] = $fakeUploadStorage->path('test.jpg');
            });
    }

    /**
     * @test
     * @dataProvider uploadFileProvider
     */
    public function singleFileUploadSucceeds($filename, $filesize)
    {
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create($filename, $filesize);

        $postTestResponse = $this->uploadFile($imageFile);
        $postTestResponse->assertStatus(200)->assertJson(['success' => true], true);

        // Create empty request in order to be able to call getUploadFilenameFromRequest().
        $request = new Request();

        $fakeUploadStorage->assertExists(Lodor::getUploadFilenameFromRequest($postTestResponse->json('uuid'), $request, $imageFile));
    }

    /**
     * @test
     * @dataProvider maliciousFileProvider
     */
    public function maliciousSingleFileUploadFails($filename, $filesize)
    {
        Config::set('lodor.auto_cleanup', false);

        Event::fake();

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create($filename, $filesize);

        $postTestResponse = $this->uploadFile($imageFile);
        $postTestResponse->assertStatus(500)->assertJson(['success' => false], true);

        // Create empty request in order to be able to call getUploadFilenameFromRequest().
        $request = new Request();

        if (ASCII::clean($filename)) {
            $fakeUploadStorage->assertExists(Lodor::getUploadFilenameFromRequest($postTestResponse->json('uuid'), $request, $imageFile));
        }

        Event::assertDispatched(UploadFailed::class);
    }

    /**
     * @test
     */
    public function targetFilenameWithoutExtensionIsSubstitutedWithOriginalExtension()
    {
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create('image.jpg', 42);

        $this->uploadFile($imageFile, ['lodor_filename' => 'fancy']);
        $fakeUploadStorage->assertExists('fancy.jpg');
    }

    /**
     * @test
     */
    public function targetFilenameWithExtensionIsUsed()
    {
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create('image.jpg', 42);

        $this->uploadFile($imageFile, ['lodor_filename' => 'fancy.png']);
        $fakeUploadStorage->assertExists('fancy.png');
    }

    /**
     * @test
     */
    public function targetFilenameEndingWithDotStaysWithoutExtension()
    {
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create('image.jpg', 42);

        $this->uploadFile($imageFile, ['lodor_filename' => 'fancy.']);
        $fakeUploadStorage->assertExists('fancy.');
    }

    /**
     * @test
     */
    public function targetFilenamePathsAreStripped()
    {
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->create('image.jpg', 42);

        $this->uploadFile($imageFile, ['lodor_filename' => '../malicious.php']);
        $fakeUploadStorage->assertExists('malicious.php');
    }

    /**
     * @test
     */
    public function fileNameBindingWorks()
    {
        Config::set('lodor.filename', 'class');
        App::instance('lodorFilename', new LodorFilenameSanitizer());
        $filename = Lodor::getUploadFilename('test-uuid', '', '', '');

        $this->assertEquals('test-uuid-binding', $filename);
    }

    /**
     * @test
     */
    public function existingFilesAreOverwritten()
    {
        Config::set('lodor.file_exists_strategy', 'overwrite');
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->image('image.jpg', 800, 600)->size(120);
        $this->uploadFile($imageFile);
        $fakeUploadStorage->assertExists('image.jpg');

        $imageSize = getimagesize($fakeUploadStorage->path('image.jpg'));
        $this->assertEquals(800, $imageSize[0]);

        $imageFile = UploadedFile::fake()->image('image.jpg', 1024, 768)->size(80);
        $this->uploadFile($imageFile);

        $imageSize = getimagesize($fakeUploadStorage->path('image.jpg'));
        $this->assertEquals(1024, $imageSize[0]);
    }

    /**
     * @test
     */
    public function existingFilesAreRenamed()
    {
        Config::set('lodor.file_exists_strategy', 'rename');
        Config::set('lodor.auto_cleanup', false);

        $fakeUploadStorage = TestHelper::fakeUploadStorage();
        $imageFile         = UploadedFile::fake()->image('image.jpg', 800, 600)->size(120);
        $this->uploadFile($imageFile);

        // Check if the file was not auto-deleted.
        $fakeUploadStorage->assertExists('image.jpg');

        Config::set('lodor.auto_cleanup', true);

        $metadata = [];
        $uuid     = '';

        // Registering a FileUploaded Event will prevent Lodor from triggering the UploadFinished event on its own,
        // giving us the opportunity to check if the file exists before it is auto-removed.
        Event::listen(
            FileUploaded::class,
            function ($event) use ($fakeUploadStorage, &$uuid, &$metadata) {
                $uuid = $event->uuid;
                $metadata = $event->metadata;
            }
        );

        $imageFile = UploadedFile::fake()->image('image.jpg', 1024, 768)->size(80);
        $this->uploadFile($imageFile);

        $fakeUploadStorage->assertExists('image_0001.jpg');
        $imageSize = getimagesize($fakeUploadStorage->path('image_0001.jpg'));
        $this->assertEquals(1024, $imageSize[0]);
        $this->assertEquals($fakeUploadStorage->path('image_0001.jpg'), $metadata['finalFilename'], 'FinalFilename is incorrect.');
        $this->assertEquals(
            $fakeUploadStorage->path('image_0001.jpg'),
            Lodor::getUploadDestinationFilename($uuid),
            'GetUploadDestinationFilename returns incorrect final filename.'
        );

        // Fire UploadFinished event to trigger cleanup.
        event(new UploadFinished($uuid, $metadata));

        // Check if the file was auto-deleted.
        $fakeUploadStorage->assertMissing('image_0001.jpg');
    }

    /**
     * @test
     */
    public function existingFilesFailUpload()
    {
        Config::set('lodor.file_exists_strategy', '');
        Event::fake();

        $imageFile = UploadedFile::fake()->image('image.jpg', 800, 600)->size(120);
        $this->uploadFile($imageFile);

        $imageFile        = UploadedFile::fake()->image('image.jpg', 1024, 768)->size(80);
        $postTestResponse = $this->uploadFile($imageFile);
        $postTestResponse->assertStatus(500)->assertJson(['success' => false, 'error' => 'The destination file image.jpg already exists.'], true);

        Event::assertDispatched(UploadFailed::class);
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
}
