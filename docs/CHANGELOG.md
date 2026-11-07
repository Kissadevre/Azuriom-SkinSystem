# Changelog

## Unreleased

### Fixed

- Direct skin delivery failed with SkinsRestorer's "The skin URL or file format is invalid" when the web server gzip-compressed `image/png`. MineSkin validates the URL with a `HEAD` request and rejects responses without `Content-Length`; gzip removes that header. The public skin endpoint now sends `Content-Encoding: identity` so nginx skips compression. Administrators should also exclude `image/png` from `gzip_types` (or the equivalent CDN/proxy setting).
