<?php

namespace App\Site\Onboarding;

use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\GameResultRepository;
use App\Repository\UserRepository;

class OnboardingManager
{
    public function __construct(
        protected GameResultRepository $results,
        protected GameMatchRepository $matches,
        protected UserRepository $users,
    ) {
    }

    public function build(?User $user): ?array
    {
        if ($user === null || $user->isGuest()) {
            return null;
        }

        $preferences = $user->getPreferences();
        $state = $preferences->section('onboarding');

        if ($state['dismissed']) {
            return null;
        }

        $completed = array_values(array_intersect(
            ['profile', 'favorite', 'play'],
            (array) ($state['completed'] ?? []),
        ));

        $checks = [
            'profile' => $user->getAvatar() !== null || $user->getBio() !== null,
            'favorite' => $user->getFavoriteGame() !== null,
            'play' => in_array('play', $completed, true) || $this->hasPlayed($user),
        ];

        foreach ($checks as $key => $done) {
            if ($done && !in_array($key, $completed, true)) {
                $completed[] = $key;
            }
        }

        if ($completed !== ($state['completed'] ?? [])) {
            $user->setPreferences(
                $preferences->with('onboarding', array_replace(
                    $state,
                    ['completed' => $completed],
                ))
            );

            $this->users->save($user, true);
        }

        $steps = [];

        foreach (['profile', 'favorite', 'play'] as $key) {
            $steps[] = [
                'title' => "site.onboarding.steps.{$key}.title",
                'description' => "site.onboarding.steps.{$key}.description",
                'action' => "site.onboarding.steps.{$key}.action",
                'done' => in_array($key, $completed, true),
                'route' => $key === 'play'
                    ? 'site_home_index'
                    : 'site_settings_profile',
                'fragment' => $key === 'play' ? '#games' : '',
            ];
        }

        return [
            'steps' => $steps,
            'completed' => count($completed),
            'total' => count($steps),
            'finished' => count($completed) === count($steps),
        ];
    }

    protected function hasPlayed(User $user): bool
    {
        if ((int) $this->results->totals($user)['played'] > 0) {
            return true;
        }

        $found = $this->matches->getOrm()->getConnection()->fetchOne(
            "SELECT id
             FROM game_match
             WHERE mode = 'solo'
               AND status = 'finished'
               AND COALESCE(JSON_EXTRACT(timeline, '$.preview'), 0) <> 1
               AND JSON_SEARCH(
                   players, 'one', :player, NULL, '$[*].id'
               ) IS NOT NULL
             LIMIT 1",
            ['player' => (string) $user->getId()],
        );

        return $found !== false && $found !== null;
    }
}