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
            'https://places.googleapis.com/v1/places/%s?fields=reviews&languageCode=%s',
            urlencode($placeId),
            urlencode($language)
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Content-Type: application/json',
                    'X-Goog-Api-Key: ' . $apiKey,
                ],
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

        if (!is_array($data) || isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            $this->logger->error('UpAssist.Neos.GoogleReviews: API error — ' . $errorMessage);
            return $this->getCachedFallback($placeId);
        }

        if (!isset($data['reviews']) || !is_array($data['reviews'])) {
            return [];
        }

        $maxReviews = (int)($this->settings['maxReviews'] ?? 5);

        return array_map(function (array $review) {
            return [
                'authorName' => $review['authorAttribution']['displayName'] ?? '',
                'rating' => (int)($review['rating'] ?? 0),
                'text' => $review['text']['text'] ?? '',
                'relativePublishTime' => $review['relativePublishTimeDescription'] ?? '',
                'profilePhotoUrl' => $review['authorAttribution']['photoUri'] ?? '',
            ];
        }, array_slice($data['reviews'], 0, $maxReviews));
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
