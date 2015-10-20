# Changelog

### v0.2.1

- Updated dependencies to require `icicleio/stream ^0.4` and `icicleio/socket ^0.4` and updated the appropriate components. Note that server request handlers should now expect a `Icicle\Socket\SocketInterface` object (renamed from `Icicle\Socket\Client\ClientInterface`).

---

### v0.2.0

- Changes
    - Added support for cookies to request and response messages.

---

### v0.1.0

- Initial release.
