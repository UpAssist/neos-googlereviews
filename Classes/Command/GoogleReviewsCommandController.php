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
     * Search for a Google Place ID by business name and location.
     *
     * Example: ./flow googlereviews:findplaceid "Ewijk Solartechniek Zevenaar"
     *
     * @param string $query Business name and location (e.g. "Ewijk Solartechniek Zevenaar")
     */
    public function findPlaceIdCommand(string $query): void
    {
        $this->outputLine('Searching for "%s"...', [$query]);

        $results = $this->googleReviewsService->findPlaceId($query);

        if (isset($results['error'])) {
            $this->outputLine('<error>%s</error>', [$results['error']]);
            $this->quit(1);
        }

        if (empty($results)) {
            $this->outputLine('<error>No results found for "%s".</error>', [$query]);
            return;
        }

        $this->outputLine('');
        $this->outputLine('<success>Found %d result(s):</success>', [count($results)]);
        $this->outputLine('');

        foreach ($results as $index => $place) {
            $this->outputLine('  <b>%d. %s</b>', [$index + 1, $place['name']]);
            $this->outputLine('     Address:  %s', [$place['address']]);
            $this->outputLine('     Place ID: <success>%s</success>', [$place['placeId']]);
            $rating = $place['rating'] !== null ? $place['rating'] . '/5' : '-';
            $this->outputLine('     Rating:   %s (%d reviews)', [$rating, $place['ratingCount']]);
            $this->outputLine('');
        }

        $this->outputLine('Copy the Place ID and set it in the Neos backend (Homepage > Google Reviews tab)');
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

        $data = $this->googleReviewsService->fetchAndCacheReviews($placeId);
        $reviews = $data['reviews'] ?? [];

        if (empty($reviews)) {
            $this->outputLine('<error>No reviews found. Check your API key and Place ID.</error>');
            return;
        }

        $this->outputLine('<success>Cached %d review(s) — Rating: %s/5 (%d total reviews)</success>', [
            count($reviews),
            $data['rating'] ?? '-',
            $data['totalReviews'] ?? 0,
        ]);

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
