# Changelog

## [0.3.0] - 2016-03-08
### Changed
- All interface names have been changed to remove the `Interface` suffix. Classes such as `Icicle\Http\Message\Request` have been renamed to `BasicRequest` since the interface now uses the non-suffixed name.
- The constructor of `Icicle\Http\Server\Server` now requires an instance of `Icicle\Http\Server\RequestHandler` instead of callbacks. The constructor also takes an instance of `Icicle\Log\Log` that can be used to log server actions as they occur. This parameter uses STDERR if no log is given. Turn off assertions in production (`zend.assertions = -1`) to skip most log messages for better performance.
- `Icicle\Http\Message\Request::getRequestTarget()` now returns an instance of `Icicle\Http\Message\Uri`.
- Renamed `Icicle\Http\Message\Message::getHeader()` to `Icicle\Http\Message\Message::getHeaderAsArray()` and renamed `Icicle\Http\Message\Message::getHeaderLine()` to `Icicle\Http\Message\Message::getHeader()`.

### Fixed
- Fixed issues with encoding certain characters in headers and URIs.
- Values of headers and URIs are now decoded (that is, no percent-encoded characters should be present header and URI values returned from methods such as `Icicle\Http\Message\Message::getHeader()`.


## [0.2.1] - 2015-09-20
### Changed
- Updated dependencies to require `icicleio/stream ^0.4` and `icicleio/socket ^0.4` and updated the appropriate components. Note that server request handlers should now expect a `Icicle\Socket\SocketInterface` object (renamed from `Icicle\Socket\Client\ClientInterface`).

## [0.2.0] - 2015-09-01
### Changed
- Added support for cookies to request and response messages.

## [0.1.0] - 2015-08-25
- Initial release.


[0.3.0]: https://github.com/icicleio/http/releases/tag/v0.3.0
[0.2.1]: https://github.com/icicleio/http/releases/tag/v0.2.1
[0.2.0]: https://github.com/icicleio/http/releases/tag/v0.2.0
[0.1.0]: https://github.com/icicleio/http/releases/tag/v0.1.0