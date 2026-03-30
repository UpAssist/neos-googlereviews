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

        $url = sprintf(
            'https://maps.googleapis.com/maps/api/place/findplacefromtext/json?input=%s&inputtype=textquery&fields=place_id,name,formatted_address,rating,user_ratings_total&key=%s',
            urlencode($query),
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
            $this->outputLine('<error>Failed to connect to Google Places API.</error>');
            $this->quit(1);
        }

        $data = json_decode($response, true);

        if (!is_array($data) || ($data['status'] ?? '') !== 'OK') {
            $errorMessage = $data['error_message'] ?? ($data['status'] ?? 'Unknown error');
            $this->outputLine('<error>API error: %s</error>', [$errorMessage]);
            $this->quit(1);
        }

        if (empty($data['candidates'])) {
            $this->outputLine('<error>No results found for "%s".</error>', [$query]);
            return;
        }

        $this->outputLine('');
        $this->outputLine('<success>Found %d result(s):</success>', [count($data['candidates'])]);
        $this->outputLine('');

        foreach ($data['candidates'] as $index => $place) {
            $name = $place['name'] ?? 'Unknown';
            $address = $place['formatted_address'] ?? '';
            $placeId = $place['place_id'] ?? '';
            $rating = $place['rating'] ?? '-';
            $ratingCount = $place['user_ratings_total'] ?? 0;

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
