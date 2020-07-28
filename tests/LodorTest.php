<?php

namespace Cybex\Lodor\Tests;

use Cybex\Lodor\LodorFacade as Lodor;
use Cybex\Lodor\LodorServiceProvider;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Class LodorTest
 * General isolated unit tests of methods from the Lodor facade.
 *
 * @package Cybex\Lodor\Tests
 */
class LodorTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LodorServiceProvider::class];
    }

    /**
     * @test
     */
    public function isChunkedWorksWithoutUploadConfig()
    {
        $uuid = Uuid::uuid4()->toString();

        Lodor::setUploadConfig($uuid, ['chunked' => true]);

        $this->assertTrue(Lodor::isChunked($uuid), 'isChunked returned false although upload config reports chunked => true.');
    }

    /**
     * @test
     */
    public function isChunkedWorksFromUploadConfig()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->assertFalse(Lodor::isChunked($uuid), 'isChunked returned true although matching directory does not exist in storage disk.');
        $this->addChunkedFolder($uuid);
        $this->assertTrue(Lodor::isChunked($uuid), 'isChunked returned false although matching directory exists in storage disk.');
    }

    /**
     * @test
     */
    public function nonExistingUploadHasNoUploadConfig()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->assertFalse(Lodor::hasUploadConfig($uuid), 'hasUploadConfig reported true when no config existed.');
    }

    /**
     * @test
     */
    public function uploadConfigCanBeCreatedAndRetrieved()
    {
        $uuid = Uuid::uuid4()->toString();

        Lodor::setUploadConfig($uuid, ['tested' => true]);

        $this->assertTrue(Lodor::hasUploadConfig($uuid), 'hasUploadConfig did not find the config that was set using setUploadConfig.');
        $this->assertSame(['tested' => true], Lodor::getUploadConfig($uuid), 'Upload config was not retrieved correctly.');
    }

    /**
     * @test
     */
    public function getUploadConfigThrowsWhenUploadConfigMissing()
    {
        $this->expectException('InvalidArgumentException');
        Lodor::getUploadConfig('testing');
    }

    /**
     * @test
     */
    public function cleanupUploadRemovesUploadConfig()
    {
        $uuid = Uuid::uuid4()->toString();

        Lodor::setUploadConfig($uuid, ['tested' => true]);
        Lodor::cleanupUpload($uuid);

        $this->assertFalse(Lodor::hasUploadConfig($uuid), 'cleanupUpload did not remove the upload config.');
    }

    /**
     * @test
     */
    public function cleanupUploadRemovesChunkedFolderOnly()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->addSingleUploadFile($uuid);
        $this->addChunkedFolder($uuid);

        Lodor::cleanupUpload($uuid);

        $storageDiskChunked = $this->getChunkedDisk();
        $storageDiskSingle  = $this->getSingleDisk();

        $storageDiskChunked->assertMissing($uuid);
        $storageDiskSingle->assertExists($uuid);
    }

    /**
     * @test
     */
    public function cleanupUploadRemovesSingleUploadFile()
    {
        $uuid = Uuid::uuid4()->toString();

        $this->addSingleUploadFile($uuid);
        Lodor::cleanupUpload($uuid);

        $storageDiskSingle = $this->getSingleDisk();
        $storageDiskSingle->assertMissing($uuid);
    }

    /**
     * @param string $uuid
     */
    protected function addChunkedFolder(string $uuid): void
    {
        $storageDiskChunked = $this->getChunkedDisk();
        $storageDiskChunked->makeDirectory($uuid);
    }

    /**
     * @param string $uuid
     */
    protected function addSingleUploadFile(string $uuid): void
    {
        $storageDiskSingle = $this->getSingleDisk();
        $storageDiskSingle->put($uuid, 'Test file');
    }

    /**
     * @return Filesystem|FilesystemAdapter
     */
    protected function getChunkedDisk()
    {
        return Storage::disk(Lodor::getChunkedUploadDiskName());
    }

    /**
     * @return Filesystem|FilesystemAdapter
     */
    protected function getSingleDisk()
    {
        return Storage::disk(Lodor::getSingleUploadDiskName());
    }

}
