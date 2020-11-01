# Laravel Lodor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cybex/laravel-lodor.svg)](https://packagist.org/packages/cybex/laravel-lodor)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cybex/laravel-lodor.svg)](https://packagist.org/packages/cybex/laravel-lodor)
![Github build status](https://github.com/cybex-gmbh/laravel-lodor/workflows/tests/badge.svg)
[![GitHub pull-requests](https://img.shields.io/github/issues-pr/cybex-gmbh/laravel-lodor.svg)](https://github.com/cybex-gmbh/laravel-lodor/pull/)
[![GitHub issues](https://img.shields.io/github/issues/cybex-gmbh/laravel-lodor.svg)](https://github.com/cybex-gmbh/laravel-lodor/issues/)
[![GitHub contributors](https://img.shields.io/github/contributors/cybex-gmbh/laravel-lodor.svg)](https://github.com/cybex-gmbh/laravel-lodor/graphs/contributors/)
[![Laravel Version](https://img.shields.io/badge/Minimum_Laravel_Version-6.x-red.svg)](https://laravel.com/docs/6.x)

This package for Laravel 6.x or newer provides an easy way to implement simple as well as chunked uploading from frontend libraries like DropzoneJS or ResumableJS and implement custom synchronous or asynchronous post-processing through (queued) listeners thanks to its use of Laravel Events.

The package is not available for older Laravel versions because the support for versions below 6.0.0 has run out. 

## Installation

You can install the package via composer:

```bash
composer require cybex/laravel-lodor
```

## Security

Please note that by default, the upload and polling routes are protected by web and auth middleware for security reasons. This means that _Lodor_ will only work on authenticated routes and when called with a CSRF token. Otherwise you will get a HTTP 401 response when trying to upload or poll.

Please refer to the [Laravel Documentation on CSRF protection](https://laravel.com/docs/csrf) and [Authentication](https://laravel.com/docs/authentication) for further reading.

### Customizing the Route Middleware

It is encouraged to leave the web middleware active at all times to ensure protection against CSRF. However, there might be times when you want to allow uploads by users without logging in to your site, e.g. on contact forms.

You can customize the middleware that is applied to the routes registered by _Lodor_ by adjusting the `route_middleware` array in the [Configuration](#Configuration).

## Usage

To get started with a simple HTML file upload, the only thing you really have to do is to set the action of your file upload form to the _Lodor_ upload route: 

``` html
<form id="upload-form" enctype="multipart/form-data" method="post" action="{{ Lodor::getUploadRoute() }}">
    @csrf
    <label for="file-input">Upload a file with Lodor:</label>
    <input type="file" name="file" id="file-input" multiple />
    <input type="submit">
</form>
```

By default, _Lodor_ registers a POST route at `/uploadmedia`, and all simple uploads go straight to the `lodor/uploads` directory in the storage path of your Laravel application.  

The HTML form above will upload the file to your storage directory and, by default, return a JSON with a success indicator and uuid like:

    {"success":true,"uuid":"ffb3dfe7-9029-4b9a-abfe-5e7485592561"}
    
This setup is useful for asynchronous uploads using Javascript, particularly when using libraries like [Dropzone.js](https://www.dropzonejs.com) or [Resumable.js](http://www.resumablejs.com).

### Redirecting to a Controller after Upload
   
If you want to process the form yourself instead after the upload completed, you may define a named route by the name of `lodor_uploaded` like this:

``` php
Route::post('/uploaded')->uses('SomeController@uploaded')->name('lodor_uploaded');
```

If this named route exists, Lodor will automatically redirect the request to the specified controller action instead of returning a JSON response. The controller method should be declared as follows:

``` php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SomeController extends Controller 
{
   function uploaded(Request $request, bool $success, string $uuid, array $metadata, string $errorMessage = null) {
        // Do something here and handle the request returning some response, view or redirect.
    }
}
```
* `$request` contains all request data of the file upload form.
* `$success` is `true` if the upload succeeded, and `false` if not.
* `$uuid` contains the unique id of the upload.
* `$metadata` is an array containing detail info about the uploaded file.
* `$errormessage` contains the error message if the upload failed or is null otherwise.

### Chunked uploads

_Lodor_ automatically merges upload chunks back into a single file. To prevent interruptions due to exceeding the maximum execution time for PHP scripts, _Lodor_ uses worker queues by default. If you cannot or do not wish to use workers, you should set the `LODOR_MERGE_ASYNC=false` environment variable or set the `merge_chunks.run_async` config setting to false (see [Configuration](#Configuration) for details). 

### Configuration

_Lodor_ was created with a setup in mind that works out of the box for most situations. However, you can publish its configuration file to your application's config directory to customize the settings to your needs.
You can publish the config using the following command:

```bash
php artisan vendor:publish --provider="Cybex\Lodor\LodorServiceProvider" --tag=config
```

Most of the settings can also be adjusted by environment settings that you can put in your .env file as needed.

The available options with their corresponding env settings and defaults are:

| Option                         | Env                          | Default         | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| :---                           | :---                         | :---            | :---                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| upload_route_path              | LODOR_URL_UPLOAD             | uploadmedia     | Default POST route endpoint for file uploads.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| poll_route_path                | LODOR_URL_POLL               | uploadpolling   | Default POST route endpoint for polling requests.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| route_middleware               |                              | ['web', 'auth'] | Array of middleware to be applied to _Lodor_ routes.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| filename                       | LODOR_FILE_NAMING            | original        | Defines how finished uploads shall be named. `original` uses the original name of the uploaded file, `uuid` uses the unique identifier that was generated during the upload process, and `uuid_ext` adds the original extension to the unique identifier. The file naming can also be overridden by defining a hidden parameter by the name of `lodor_filename`  in the file upload form.                                                                                                                                                |
| file_exists_strategy           | LODOR_FILE_EXISTS_STRATEGY   | rename          | Determines what should be done if an upload with the same filename already exists in the upload folder. `rename` will add an incrementing number part to the original filename, `overwrite` will overwrite the existing file, any other or no value will throw a `FileExistsException`.                                                                                                                                                                                                                                                  |
| states                         |                              |                 | Specifies pre-defined states in the upload process. `progress` specifies the percentage at the beginning of that state, `state` specifies the upload state (`waiting`, `processing`, `done` or `error`), `status` is a status message and `info` may have more details.                                                                                                                                                                                                                                                                  |
| disks                          |                              |                 | Defines the details of the disks that _Lodor_ uses for the temporary chunks and for the final uploads. Usually you would not change settings here but instead create your own disks and specify them in the `disk_chunked` and `disk_uploads` config.                                                                                                                                                                                                                                                                                    |
| disk_uploads                   | LODOR_DISK_UPLOADS           | lodor_uploads   | Name of the disk to be used for storing the final uploads.                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| disk_chunked                   | LODOR_DISK_CHUNKED           | lodor_chunked   | Name of the disk to use for storing upload chunks.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| event_channel_name             | LODOR_EVENT_CHANNEL          | upload          | Name of the channel to use for events.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| upload_cleanup_timeout         | LODOR_UPLOAD_CLEANUP_TIMEOUT | 600             | Amount of seconds to wait after the last status update of an upload before it is considered safe to be deleted by the cleanup (currently not in use).                                                                                                                                                                                                                                                                                                                                                                                    |
| cache                          |                              |                 | `upload_config` specifies the `prefix` and default `ttl` (time to live) for the details on the uploaded file that are saved in the Cache. `upload_status` specifies the same for the status entries that are used to provide the polling functionality.                                                                                                                                                                                                                                                                                  |
| merge_chunks                   |                              |                 | Settings for the process of merging the chunks of an upload into a single file.                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| merge_chunks.uploader          |                              | null            | You can force a specific class for handling chunked uploads using this setting. This comes in handy if you want to write your own handler by extending the `Cybex\Lodor\ChunkUploaders\ChunkUploader` class. The default setting `null` will auto-detect the appropriate uploader by analyzing the request data.                                                                                                                                                                                                                         |
| merge_chunks.auto_merge_chunks | LODOR_AUTO_MERGE_CHUNKS      | true            | If set to `true`, _Lodor_ will automatically merge the chunks back to one single file and store it in the `disk_uploads` disk. If set to `false`, the upload process will finish after uploading all chunks to the server. This is useful if you want to re-use the chunks, e.g. for forwarding them to a different server or if you want to implement your own merge algorithm by registering a listener for the `FileUploaded` event.                                                                                                  |
| merge_chunks.run_async         | LODOR_MERGE_ASYNC            | true            | If set to `true`, the merge process will queue on the `merge_chunks.default_queue` and will wait for the worker to process the job. If you don't want to use worker queues, you can set this to `false` to merge the chunks immediately after uploading. __Warning__: this merges all chunks of an upload immediately after uploading the last chunk. For large chunks or slow transfers, this may exceed the maximum execution time for script execution. You should only set this option to false if you are not concerned about this. |
| merge_chunks.default_queue     | LODOR_DEFAULT_MERGE_QUEUE    | default         | You may set a different queue name for the merge jobs.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| auto_cleanup                   | LODOR_AUTO_CLEANUP           | false           | Specifies if Lodor should automatically delete the finished upload files after the UploadFinished event is triggered. (see [Cleanup](#Cleanup) for details).                                                                                                                                                                                                                                                                                                                                                                             |
| auto_cleanup_chunks            | LODOR_AUTO_CLEANUP_CHUNKS    | true            | Defines if Lodor should automatically delete the temporary chunks folder for chunked uploads (see [Cleanup](#Cleanup) for details).                                                                                                                                                                                                                                                                                                                                                                                                      |

### Cleanup

`Lodor` automatically runs a cleanup if the UploadFailed event is triggered after an upload fails or the UploadFinished event is triggered after successful completion.
In case of a failing upload, all files - chunks and merged - are always forcibly deleted regardless of your configuration settings.

In case of a successfully finished upload, the behavior depends on a number of configuration settings. By default, `Lodor` will delete all existing file chunks during cleanup, but not the merged files in the upload disk.
 
To change this default behavior, you can set `lodor.auto_cleanup_chunks` to `false` to leave the chunks in place after uploading, and `lodor.auto_cleanup` to `true` to always delete the finished uploads once they are completed (as indicated by the `UploadFinished` event).

#### Using auto-cleanup

In a basic setup with no further event listeners registered, Lodor always triggers the `UploadFinished` event once the non-chunked upload succeeded or the chunked upload was successfully merged.
Therefore, `auto_cleanup` is set to `false` by default. If you set it to `true`, your uploads would otherwise be gone the second they are finished.

If you want to use `auto_cleanup`, you need to make sure that you register a listener for the `FileUploaded` event, usually by adding it to your EventServiceProvider (see [Registering Events & Listeners](https://laravel.com/docs/7.x/events#registering-events-and-listeners) in the Laravel docs for details).

You can simply use Laravel's generator to create a listener class for you, e.g.

``` bash
artisan make:listener -e "\Cybex\Lodor\Events\FileUploaded" -- FileUploadedListener
```

Then include it in your EventServiceProvider $listener property:

``` php
use App\Listeners\FileUploadedListener;
use Cybex\Lodor\Events\FileUploaded;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        FileUploaded::class => [
            FileUploadedListener::class,
        ],
    ];
``` 

Inside your `FileUploadedListener` class, you should then process the file as needed in the `handle()` method and trigger the `UploadFinished` event to indicate that you are done processing and the files can be cleaned up:

``` php
    /**
     * Handle the event.
     *
     * @param  FileUploaded  $event
     * @return void
     */
    public function handle(FileUploaded $event)
    {
        $uuid     = $event->uuid;
        $metadata = $event->metadata;

        // You are also responsible to post updates on the status of the process using Lodor::setUploadStatus()
        // to keep the frontend up to date on the progress and any info you want to publish along with it.
        // After the server upload finishes, the upload is put in "waiting" state until the listener(s)
        // process(es) the upload and set the status to "done" state.
        Lodor::setUploadStatus($event->uuid,
            'done',
            __('Server upload finished.'),
            __('Upload complete.', ['uuid' => $uuid]),
            100,
            $metadata);

        // Then fire the UploadFinished event to signalize that the upload has completed processing.
        event(new UploadFinished($event->uuid, $event->metadata));
    }
``` 

#### Cleaning up manually

You may also choose to keep `auto_cleanup` disabled and do the cleanup yourself. You can do so by following the steps above and add

``` php
Lodor::cleanupUpload($event->uuid, true);
```

to your listener's `handle()` method. The second parameter specifies if all files should be forcibly deleted, regardless of the config settings.

#### Caveats

Sometimes, files might not be cleaned up at all, either because the cache info of the upload was deleted, your listener(s) are crashing or if you use queued event listeners and your job queue is failing or not running at all.
To make sure leftover files are cleaned up, you may want to schedule a cron job that deletes old files from the `lodor_chunked` and `lodor_uploads` storage disks and the according info from the cache periodically.
In future versions of `Lodor`, it is planned to implement both a Listener and an artisan command to clean up periodically.   


### Testing

``` bash
composer test
```

### To do

- Cleanup Command for leftover uploads.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email info@lupinitylabs.com instead of using the issue tracker.

## Credits

- [Cybex GmbH](https://github.com/cybex-gmbh)
- [Oliver Matla](https://github.com/lupinitylabs)


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com) (thanks, Marcel!).
