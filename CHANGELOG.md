# Changelog

All notable changes to `laravel-lodor` will be documented in this file

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
