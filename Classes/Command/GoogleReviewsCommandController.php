<?php

namespace UpAssist\Neos\GoogleReviews\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use UpAssist\Neos\GoogleReviews\Service\GoogleReviewsService;

class GoogleReviewsCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var GoogleReviewsService
     */
    protected $googleReviewsService;

    /**
     * @Flow\InjectConfiguration(package="UpAssist.Neos.GoogleReviews")
     * @var array
     */
    protected $settings;

    /**
     * Search for a Google Place ID by business name and location.
     *
     * Example: ./flow googlereviews:findplaceid "Ewijk Solartechniek Zevenaar"
     *
     * @param string $query Business name and location (e.g. "Ewijk Solartechniek Zevenaar")
     */
    public function findPlaceIdCommand(string $query): void
    {
        $apiKey = $this->settings['apiKey'] ?? '';
        if (empty($apiKey)) {
            $this->outputLine('<error>No API key configured. Set GOOGLE_PLACES_API_KEY in your .env file.</error>');
            $this->quit(1);
        }

        $this->outputLine('Searching for "%s"...', [$query]);

        $url = 'https://places.googleapis.com/v1/places:searchText';
        $payload = json_encode(['textQuery' => $query]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'X-Goog-Api-Key: ' . $apiKey,
                    'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.rating,places.userRatingCount',
                ]),
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $this->outputLine('<error>Failed to connect to Google Places API.</error>');
            $this->quit(1);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || isset($data['error'])) {
            $errorMessage = $data['error']['message'] ?? 'Unknown error';
            $this->outputLine('<error>API error: %s</error>', [$errorMessage]);
            $this->quit(1);
        }

        if (empty($data['places'])) {
            $this->outputLine('<error>No results found for "%s".</error>', [$query]);
            return;
        }

        $this->outputLine('');
        $this->outputLine('<success>Found %d result(s):</success>', [count($data['places'])]);
        $this->outputLine('');

        foreach ($data['places'] as $index => $place) {
            $name = $place['displayName']['text'] ?? 'Unknown';
            $address = $place['formattedAddress'] ?? '';
            $placeId = $place['id'] ?? '';
            $rating = $place['rating'] ?? '-';
            $ratingCount = $place['userRatingCount'] ?? 0;

            $this->outputLine('  <b>%d. %s</b>', [$index + 1, $name]);
            $this->outputLine('     Address:  %s', [$address]);
            $this->outputLine('     Place ID: <success>%s</success>', [$placeId]);
            $this->outputLine('     Rating:   %s/5 (%d reviews)', [$rating, $ratingCount]);
            $this->outputLine('');
        }

        $this->outputLine('Copy the Place ID and set it in the Neos backend (Homepage → Google Reviews tab)');
        $this->outputLine('or in Settings.GoogleReviews.yaml under defaultPlaceId.');
    }

    /**
     * Fetch and cache Google Reviews for a Place ID.
     *
     * @param string $placeId Optional Place ID (uses default from settings if empty)
     */
    public function fetchCommand(string $placeId = ''): void
    {
        $this->outputLine('Fetching Google Reviews...');

        $reviews = $this->googleReviewsService->fetchAndCacheReviews($placeId);

        if (empty($reviews)) {
            $this->outputLine('<error>No reviews found. Check your API key and Place ID.</error>');
            return;
        }

        $this->outputLine('<success>Cached %d review(s) successfully.</success>', [count($reviews)]);

        foreach ($reviews as $review) {
            $this->outputLine(
                '  %s — %d/5 — %s',
                [$review['authorName'], $review['rating'], mb_substr($review['text'], 0, 80) . '...']
            );
        }
    }

    /**
     * Clear the Google Reviews cache.
     *
     * @param string $placeId Optional specific Place ID to clear (clears all if empty)
     */
    public function clearCacheCommand(string $placeId = ''): void
    {
        $this->googleReviewsService->clearCache($placeId);
        $this->outputLine('<success>Google Reviews cache cleared.</success>');
    }
}
