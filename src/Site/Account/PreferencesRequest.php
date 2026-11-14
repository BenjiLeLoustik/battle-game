<?php

namespace App\Site\Account;

use App\Shared\User\UserPreferences;

class PreferencesRequest
{
    private const TIME_PATTERN = '/^([01]\d|2[0-3]):[0-5]\d$/';

    public static function fromArray(string $section, array $data, array $locales = []): array
    {
        return match ($section) {
            'privacy' => self::privacy($data),
            'game' => self::game($data, $locales),
            'notifications' => self::notifications($data),
        };
    }

    protected static function privacy(array $data): array
    {
        $values = self::switches($data, ['public', 'online', 'leaderboard']);

        foreach (['invite', 'message', 'friend_request'] as $key) {
            $values[$key] = in_array($data[$key] ?? '', UserPreferences::AUDIENCES, true) ? $data[$key] : UserPreferences::DEFAULTS['privacy'][$key];
        }

        return $values;
    }

    protected static function game(array $data, array $locales): array
    {
        return self::switches($data, ['fullscreen', 'ping', 'vibrations', 'stay_in_room', 'reduce_motion', 'contrast']) + [
            'sound' => max(0, min(100, (int) ($data['sound'] ?? 70))),
            'music' => max(0, min(100, (int) ($data['music'] ?? 35))),
            'accent' => array_key_exists($data['accent'] ?? '', UserPreferences::ACCENTS) ? $data['accent'] : 'cyan',
            'locale' => in_array($data['locale'] ?? '', $locales, true) ? $data['locale'] : null,
        ];
    }

    protected static function notifications(array $data): array
    {
        $values = [];

        foreach (UserPreferences::EVENTS as $event) {
            foreach (UserPreferences::CHANNELS as $channel) {
                $values[$event][$channel] = isset($data[$event][$channel]);
            }
        }

        $values['dnd'] = [
            'enabled' => isset($data['dnd']['enabled']),
            'from' => preg_match(self::TIME_PATTERN, (string) ($data['dnd']['from'] ?? '')) ? $data['dnd']['from'] : '23:00',
            'to' => preg_match(self::TIME_PATTERN, (string) ($data['dnd']['to'] ?? '')) ? $data['dnd']['to'] : '08:00',
        ];

        return $values;
    }

    protected static function switches(array $data, array $keys): array
    {
        return array_combine($keys, array_map(fn (string $key): bool => isset($data[$key]), $keys));
    }
}
