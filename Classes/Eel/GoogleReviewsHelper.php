<?php

namespace UpAssist\Neos\GoogleReviews\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use UpAssist\Neos\GoogleReviews\Service\GoogleReviewsService;

/**
 * @Flow\Scope("singleton")
 */
class GoogleReviewsHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var GoogleReviewsService
     */
    protected $googleReviewsService;

    public function getData(string $placeId = null): array
    {
        return $this->googleReviewsService->getReviews((string)$placeId) ?: [];
    }

    public function getReviews(string $placeId = null): array
    {
        $data = $this->getData($placeId);
        return $data['reviews'] ?? [];
    }

    public function getRating(string $placeId = null): float
    {
        $data = $this->getData($placeId);
        return (float)($data['rating'] ?? 0);
    }

    public function getTotalReviews(string $placeId = null): int
    {
        $data = $this->getData($placeId);
        return (int)($data['totalReviews'] ?? 0);
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
