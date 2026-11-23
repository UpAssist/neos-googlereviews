# Changelog

All notable changes to this project are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.1.2] - 2026-11-23

### Fixed

- Stale fallback: when the Google API fails, the last fetched reviews are shown again. Before, the fallback only returned entries that had not expired yet, so after `cacheLifetime` a failed fetch left an empty block. Reviews are now cached without expiry and refreshed once they are older than `cacheLifetime`. Entries cached by 1.1.1 and earlier are refreshed on first use.
- The "all reviews" link now also shows when only `defaultPlaceId` is set (no `googlePlaceId` on the site node).

## [1.1.1] - 2026-11-19

### Fixed

- The rendered reviews element now expires from the Neos content cache after `cacheLifetime`. Before, the page kept showing the reviews it first rendered, or an empty block after one failed fetch, until the page was edited or the caches were flushed.

## [1.1.0] - 2026-11-19

### Added

- Neos 9 support (tested on 9.0, 9.1 and 9.2).

### Changed

- Constraints: `neos/neos` and `neos/flow` are now `^8.3 || ^9.0` (tested on 8.3, 8.4, 9.0, 9.1 and 9.2). Neos 7.3 and 8.0–8.2 stay on 1.0.

### Fixed

- `guzzlehttp/guzzle` and `psr/log` are now required explicitly. Before, Guzzle was only there when another package happened to require it.
- Review text, author name and relative date are now HTML-escaped. Before, HTML in a Google review was rendered as markup in the page (XSS).
- PHP 8.4: the Eel helper's `$placeId` parameters are explicitly nullable. The implicit-nullable deprecation notice was printed into the page and cut off the response.
- README: the content element reads the Place ID from the site node, not the nearest document node.
