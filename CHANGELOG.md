# Changelog

All notable changes to `laravel-lodor` will be documented in this file

## 0.3.0 - 2020-07-09

- the upload and polling routes are now guarded by a configurable array of middlewares. For security, web and auth are applied by default.

## 0.2.0 - 2020-07-09

-  with auto merging disabled, the UploadFinished event did not fire after server upload.

## 0.0.2 - 2020-07-07

- fixed type hinting for mergeChunkedFile method 

## 0.0.1 - 2020-06-22

- initial release
