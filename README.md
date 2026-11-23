# UpAssist.Neos.GoogleReviews

Fetch and display Google Place reviews in Neos CMS. Provides a content element, Eel helper, CLI commands, and automatic caching.

Supports both the legacy Google Maps API and the new Google Places API (v1).

## Requirements

- Neos CMS 8.3+ / 9.x
- PHP 8.1+
- A Google Cloud API key with the Places API enabled

## Installation

```bash
composer require upassist/neos-googlereviews
```

## Compatibility

| Package | Neos | PHP |
|---|---|---|
| 1.1 | 8.3, 8.4, 9.0, 9.1, 9.2 | 8.1+ |
| 1.0 | 7.3, 8.x | 8.1+ |

## Configuration

Add your API key and default Place ID in `Settings.yaml`:

```yaml
UpAssist:
  Neos:
    GoogleReviews:
      apiKey: 'YOUR_GOOGLE_API_KEY'
      defaultPlaceId: 'ChIJ...'
```

### All options

| Setting | Type | Default | Description |
|---|---|---|---|
| `apiVersion` | string | `'legacy'` | `'legacy'` for Maps API, `'new'` for Places API v1 |
| `apiKey` | string | `''` | Google Cloud API key (required) |
| `defaultPlaceId` | string | `''` | Fallback Place ID when not set on the document |
| `cacheLifetime` | integer | `86400` | Cache TTL in seconds (24 hours) |
| `maxReviews` | integer | `5` | Maximum number of reviews to fetch (Google API limit is 5) |
| `language` | string | `'nl'` | Language code for API responses |

## Usage

### Content element

Insert the **Google Reviews** content element in the Neos backend. It displays a rating summary and individual review cards in a responsive grid.

The element reads the Place ID from the site node (add the `GooglePlaceId` mixin to your homepage NodeType) or falls back to `defaultPlaceId` from settings.

Editor options in the inspector:

- **Show author photo** - Display reviewer avatars
- **Max displayed reviews** - Slider from 1 to 5
- **Show link to all reviews** - Link to Google reviews page

### Document mixin

Add the mixin to any document NodeType to enable per-page Place ID configuration:

```yaml
'Your.Package:Document.Page':
  superTypes:
    'UpAssist.Neos.GoogleReviews:Mixin.GooglePlaceId': true
```

This adds a "Google Reviews" tab in the inspector where editors can set the Place ID.

### Eel helper

Use the `GoogleReviews` helper directly in Fusion:

```
# Get all data (rating, totalReviews, reviews array)
data = ${GoogleReviews.getData(placeId)}

# Individual accessors
reviews = ${GoogleReviews.getReviews(placeId)}
rating = ${GoogleReviews.getRating(placeId)}
totalReviews = ${GoogleReviews.getTotalReviews(placeId)}
```

The `placeId` argument is optional; it falls back to `defaultPlaceId` from settings.

### CLI commands

**Find a Place ID:**
```bash
./flow googlereviews:findplaceid "Your Business Name City"
```

**Fetch and cache reviews:**
```bash
./flow googlereviews:fetch [placeId]
```

**Clear the cache:**
```bash
./flow googlereviews:clearcache [placeId]
```

## Styling

The package ships minimal structural CSS. Override styles in your site package for branding. All classes use the BEM convention with the `google-reviews` block:

```
.google-reviews
.google-reviews__summary
.google-reviews__grid
.google-reviews__card
.google-reviews__stars
.google-reviews__star--filled
.google-reviews__footer
.google-reviews__cta
```

The grid uses a `--reviews-count` CSS custom property and collapses to a single column below 768px.

## Caching

Reviews are cached using Flow's file-based cache backend and fetched again once they are older than `cacheLifetime`. On API errors, the last fetched reviews are served as a fallback, however old they are. Use the `clearcache` CLI command or wait for the TTL to expire. The content element is cached in the Neos content cache with the same `cacheLifetime` as its maximum lifetime, so the page picks up new reviews when the TTL expires, without editing the page.

## API versions

| Version | Endpoint | Auth method | Notes |
|---|---|---|---|
| `legacy` | `maps.googleapis.com` | Query string | Widely available, enable "Places API" |
| `new` | `places.googleapis.com/v1` | `X-Goog-Api-Key` header | Requires "Places API (New)" enablement |

## Translations

Includes English and Dutch translations out of the box. Add your own XLIFF files in the standard Neos translation path.

## License

MIT
