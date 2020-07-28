# Changelog

All notable changes to `laravel-lodor` will be documented in this file

## 0.4.1 - 2020-07-28

- Automatic cleanup can now be switched off by setting the config value lodor.auto_cleanup or the LODOR_AUTO_CLEANUP env setting to false.
- Lodor will now determine if an upload is chunked or not if the upload config cache entry is missing by looking at the chunk storage disk.

## 0.3.0 - 2020-07-09

- The upload and polling routes are now guarded by a configurable array of middlewares. For security, web and auth are applied by default.
- Dependencies are now updated to work with a Laravel 6.x environment and PHPUnit 8.x.
- Added a Bitbucket pipeline for automated testing.

## 0.2.0 - 2020-07-09

- With auto merging disabled, the UploadFinished event did not fire after server upload.

## 0.0.2 - 2020-07-07

- Fixed type hinting for mergeChunkedFile method 

## 0.0.1 - 2020-06-22

- Initial release
