<?php

namespace App\Site\Onboarding;

use App\Entity\User;
use App\Repository\GameResultRepository;

class OnboardingManager
{
    public function __construct(
        protected GameResultRepository $results,
    ) {
    }

    public function build(?User $user): ?array
    {
        if ($user === null || $user->isGuest() || $user->getPreferences()->get('onboarding', 'dismissed')) {
            return null;
        }

        $totals = $this->results->totals($user);

        $steps = [
            [
                'title' => 'site.onboarding.steps.profile.title',
                'description' => 'site.onboarding.steps.profile.description',
                'done' => $user->getAvatar() !== null
                    || $user->getDiscordAvatar() !== null
                    || $user->getBio() !== null,
                'route' => 'site_settings_profile',
                'fragment' => '',
                'action' => 'site.onboarding.steps.profile.action',
            ],
            [
                'title' => 'site.onboarding.steps.favorite.title',
                'description' => 'site.onboarding.steps.favorite.description',
                'done' => $user->getFavoriteGame() !== null,
                'route' => 'site_settings_profile',
                'fragment' => '',
                'action' => 'site.onboarding.steps.favorite.action',
            ],
            [
                'title' => 'site.onboarding.steps.play.title',
                'description' => 'site.onboarding.steps.play.description',
                'done' => (int) $totals['played'] > 0,
                'route' => 'site_home_index',
                'fragment' => '#games',
                'action' => 'site.onboarding.steps.play.action',
            ],
        ];

        $completed = count(array_filter(
            $steps,
            static fn (array $step): bool => $step['done'],
        ));

        return [
            'steps' => $steps,
            'completed' => $completed,
            'total' => count($steps),
            'finished' => $completed === count($steps),
        ];
    }
}