# Laravel Lodor

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cybex/laravel-lodor.svg?style=flat-square)](https://packagist.org/packages/cybex/laravel-lodor)
[![Total Downloads](https://img.shields.io/packagist/dt/cybex/laravel-lodor.svg?style=flat-square)](https://packagist.org/packages/cybex/laravel-lodor)

This Laravel package provides an easy, zero-conf way to implement simple and chunked uploading from frontend libraries like DropzoneJS or ResumableJS and implement custom asynchronous post-processing through listeners thanks to its use of Laravel Events.

## Installation

You can install the package via composer:

```bash
composer require cybex/laravel-lodor
```

## Usage

To get started with a simple HTML file upload, the only thing you really have to do is to set the action of your file upload form to the _Lodor_ upload route: 

``` html
<form id="upload-form" enctype="multipart/form-data" method="post" action="{{ Lodor::getUploadRoute() }}">
    <label for="file">Upload a file with Lodor:</label>
    <input type="file" name="file" id="file-input" multiple />
    <input type="submit">
</form>
```

By default, _Lodor_ registers a POST route at `/uploadmedia`, and all simple uploads go straight to the `lodor/uploads` directory in the storage path of your Laravel application.  

The HTML form above will upload the file to your storage directory and, by default, return a JSON with a success indicator and uuid like:

    {"success":true,"uuid":"ffb3dfe7-9029-4b9a-abfe-5e7485592561"}
    
This setup is useful for asynchronous uploads using Javascripts, particularly when using Javascript libraries like [Dropzone.js](https://www.dropzonejs.com) or [Resumable.js](http://www.resumablejs.com).

### Redirecting to a Controller after Upload
 
If you want to process the form yourself instead after the upload completed, you may define a named route by the name of `lodor_uploaded` like this:

    Route::post('/uploaded')->uses('SomeController@uploaded')->name('lodor_uploaded');

If this named route exists, Lodor will automatically redirect the request to the specified controller action instead of returning a JSON response. The controller method should be declared as follows:

```php
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

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email info@lupinitylabs.com instead of using the issue tracker.

## Credits

- [Oliver Matla](https://github.com/lupinitylabs)
- [Cybex GmbH](https://bitbucket.org/cybexgmbh)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com) (thanks, Marcel!).
