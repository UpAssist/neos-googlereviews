<?php

namespace UpAssist\Neos\GoogleReviews\Service;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

/**
 * @Flow\Scope("singleton")
 */
class GoogleReviewsService
{
    /**
     * @var VariableFrontend
     */
    protected $cache;

    /**
     * @Flow\InjectConfiguration(package="UpAssist.Neos.GoogleReviews")
     * @var array
     */
    protected $settings;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    public function getReviews(string $placeId = ''): array
    {
        $placeId = $placeId ?: ($this->settings['defaultPlaceId'] ?? '');
        if (empty($placeId)) {
            return [];
        }

        $cacheIdentifier = $this->getCacheIdentifier($placeId);

        if ($this->cache->has($cacheIdentifier)) {
            return $this->cache->get($cacheIdentifier);
        }

        return $this->fetchAndCacheReviews($placeId);
    }

    public function fetchAndCacheReviews(string $placeId): array
    {
        $placeId = $placeId ?: ($this->settings['defaultPlaceId'] ?? '');
        if (empty($placeId)) {
            return [];
        }

        $reviews = $this->fetchFromGoogleApi($placeId);
        $cacheIdentifier = $this->getCacheIdentifier($placeId);

        if (!empty($reviews)) {
            $lifetime = (int)($this->settings['cacheLifetime'] ?? 86400);
            $this->cache->set($cacheIdentifier, $reviews, [], $lifetime);
        }

        return $reviews;
    }

    protected function fetchFromGoogleApi(string $placeId): array
    {
        $apiKey = $this->settings['apiKey'] ?? '';
        if (empty($apiKey)) {
            $this->logger->warning('UpAssist.Neos.GoogleReviews: No API key configured');
            return [];
        }

        $language = $this->settings['language'] ?? 'nl';
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/place/details/json?place_id=%s&fields=reviews&language=%s&key=%s',
            urlencode($placeId),
            urlencode($language),
            urlencode($apiKey)
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->logger->error('UpAssist.Neos.GoogleReviews: Failed to connect to Google Places API');
            return $this->getCachedFallback($placeId);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
            $errorMessage = $data['error_message'] ?? ($data['status'] ?? 'Unknown error');
            $this->logger->error('UpAssist.Neos.GoogleReviews: API error — ' . $errorMessage);
            return $this->getCachedFallback($placeId);
        }

        $reviews = $data['result']['reviews'] ?? [];
        if (empty($reviews)) {
            return [];
        }

        $maxReviews = (int)($this->settings['maxReviews'] ?? 5);

        return array_map(function (array $review) {
            return [
                'authorName' => $review['author_name'] ?? '',
                'rating' => (int)($review['rating'] ?? 0),
                'text' => $review['text'] ?? '',
                'relativePublishTime' => $review['relative_time_description'] ?? '',
                'profilePhotoUrl' => $review['profile_photo_url'] ?? '',
            ];
        }, array_slice($reviews, 0, $maxReviews));
    }

    protected function getCachedFallback(string $placeId): array
    {
        $cacheIdentifier = $this->getCacheIdentifier($placeId);
        if ($this->cache->has($cacheIdentifier)) {
            $this->logger->info('UpAssist.Neos.GoogleReviews: Serving stale cached reviews as fallback');
            return $this->cache->get($cacheIdentifier);
        }
        return [];
    }

    public function clearCache(string $placeId = ''): void
    {
        if (!empty($placeId)) {
            $this->cache->remove($this->getCacheIdentifier($placeId));
        } else {
            $this->cache->flush();
        }
    }

    protected function getCacheIdentifier(string $placeId): string
    {
        return 'reviews_' . md5($placeId);
    }
}
