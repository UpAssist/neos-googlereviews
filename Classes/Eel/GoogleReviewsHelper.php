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

    public function getReviews(string $placeId = null): array
    {
        return $this->googleReviewsService->getReviews((string)$placeId) ?: [];
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
