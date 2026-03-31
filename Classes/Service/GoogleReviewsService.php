<?php

namespace UpAssist\Neos\GoogleReviews\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
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

        $apiVersion = $this->settings['apiVersion'] ?? 'legacy';

        if ($apiVersion === 'new') {
            return $this->fetchFromNewApi($placeId, $apiKey);
        }

        return $this->fetchFromLegacyApi($placeId, $apiKey);
    }

    protected function fetchFromLegacyApi(string $placeId, string $apiKey): array
    {
        $language = $this->settings['language'] ?? 'nl';
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/place/details/json?place_id=%s&fields=reviews,rating,user_ratings_total&language=%s&key=%s',
            urlencode($placeId),
            urlencode($language),
            urlencode($apiKey)
        );

        $response = $this->httpGet($url);
        if ($response === false) {
            return $this->getCachedFallback($placeId);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
            $errorMessage = $data['error_message'] ?? ($data['status'] ?? 'Unknown error');
            $this->logger->error('UpAssist.Neos.GoogleReviews: Legacy API error — ' . $errorMessage);
            return $this->getCachedFallback($placeId);
        }

        $maxReviews = (int)($this->settings['maxReviews'] ?? 5);
        $reviews = $data['result']['reviews'] ?? [];

        return [
            'rating' => (float)($data['result']['rating'] ?? 0),
            'totalReviews' => (int)($data['result']['user_ratings_total'] ?? 0),
            'reviews' => array_map(function (array $review) {
                return [
                    'authorName' => $review['author_name'] ?? '',
                    'rating' => (int)($review['rating'] ?? 0),
                    'text' => $review['text'] ?? '',
                    'relativePublishTime' => $review['relative_time_description'] ?? '',
                    'profilePhotoUrl' => $review['profile_photo_url'] ?? '',
                ];
            }, array_slice($reviews, 0, $maxReviews)),
        ];
    }

    protected function fetchFromNewApi(string $placeId, string $apiKey): array
    {
        $language = $this->settings['language'] ?? 'nl';
        $url = sprintf(
            'https://places.googleapis.com/v1/places/%s?fields=reviews,rating,userRatingCount&languageCode=%s',
            urlencode($placeId),
            urlencode($language)
        );

        $response = $this->httpGet($url, [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
        ]);

        if ($response === false) {
            return $this->getCachedFallback($placeId);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            $this->logger->error('UpAssist.Neos.GoogleReviews: New API error — ' . $errorMessage);
            return $this->getCachedFallback($placeId);
        }

        $maxReviews = (int)($this->settings['maxReviews'] ?? 5);
        $reviews = $data['reviews'] ?? [];

        return [
            'rating' => (float)($data['rating'] ?? 0),
            'totalReviews' => (int)($data['userRatingCount'] ?? 0),
            'reviews' => array_map(function (array $review) {
                return [
                    'authorName' => $review['authorAttribution']['displayName'] ?? '',
                    'rating' => (int)($review['rating'] ?? 0),
                    'text' => $review['text']['text'] ?? '',
                    'relativePublishTime' => $review['relativePublishTimeDescription'] ?? '',
                    'profilePhotoUrl' => $review['authorAttribution']['photoUri'] ?? '',
                ];
            }, array_slice($reviews, 0, $maxReviews)),
        ];
    }

    /**
     * Search for a Place ID by query text. Used by the CLI command.
     */
    public function findPlaceId(string $query): array
    {
        $apiKey = $this->settings['apiKey'] ?? '';
        if (empty($apiKey)) {
            return ['error' => 'No API key configured'];
        }

        $apiVersion = $this->settings['apiVersion'] ?? 'legacy';

        if ($apiVersion === 'new') {
            return $this->findPlaceIdNewApi($query, $apiKey);
        }

        return $this->findPlaceIdLegacyApi($query, $apiKey);
    }

    protected function findPlaceIdLegacyApi(string $query, string $apiKey): array
    {
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=%s&inputtype=textquery&fields=place_id,name,formatted_address,rating,user_ratings_total&key=%s',
            urlencode($query),
            urlencode($apiKey)
        );

        $response = $this->httpGet($url);
        if ($response === false) {
            return ['error' => 'Failed to connect to Google Places API'];
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
            return ['error' => $data['error_message'] ?? ($data['status'] ?? 'Unknown error')];
        }

        return array_map(function (array $place) {
            return [
                'name' => $place['name'] ?? 'Unknown',
                'address' => $place['formatted_address'] ?? '',
                'placeId' => $place['place_id'] ?? '',
                'rating' => $place['rating'] ?? null,
                'ratingCount' => $place['user_ratings_total'] ?? 0,
            ];
        }, $data['candidates'] ?? []);
    }

    protected function findPlaceIdNewApi(string $query, string $apiKey): array
    {
        $url = 'https://places.googleapis.com/v1/places:searchText';

        try {
            $response = $this->httpPost($url, ['textQuery' => $query], [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $apiKey,
                'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount',
            ]);
        } catch (GuzzleException $e) {
            $this->logger->error('UpAssist.Neos.GoogleReviews: ' . $e->getMessage());
            return ['error' => 'Failed to connect to Google Places API'];
        }

        if ($response === false) {
            return ['error' => 'Failed to connect to Google Places API'];
        }

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            return ['error' => $data['error']['message'] ?? 'Unknown error'];
        }

        return array_map(function (array $place) {
            return [
                'name' => $place['displayName']['text'] ?? 'Unknown',
                'address' => $place['formattedAddress'] ?? '',
                'placeId' => $place['id'] ?? '',
                'rating' => $place['rating'] ?? null,
                'ratingCount' => $place['userRatingCount'] ?? 0,
            ];
        }, $data['places'] ?? []);
    }

    protected function httpGet(string $url, array $headers = []): string|false
    {
        try {
            $client = new Client(['timeout' => 10]);
            $response = $client->get($url, [
                'headers' => $this->formatHeaders($headers),
            ]);
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            $this->logger->error('UpAssist.Neos.GoogleReviews: ' . $e->getMessage());
            return false;
        }
    }

    protected function httpPost(string $url, array $body, array $headers = []): string|false
    {
        $client = new Client(['timeout' => 10]);
        $response = $client->post($url, [
            'headers' => $headers,
            'json' => $body,
        ]);
        return $response->getBody()->getContents();
    }

    /**
     * Convert indexed header array ("Key: Value") to associative array ("Key" => "Value").
     */
    protected function formatHeaders(array $headers): array
    {
        $formatted = [];
        foreach ($headers as $header) {
            if (is_string($header) && str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $formatted[trim($key)] = trim($value);
            }
        }
        return $formatted;
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
