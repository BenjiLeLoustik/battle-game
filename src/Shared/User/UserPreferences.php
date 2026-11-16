<?php

namespace App\Shared\User;

class UserPreferences
{
    public const AUDIENCES = ['everyone', 'friends', 'nobody'];
    public const ACCENTS = ['cyan' => '#2EE6D6', 'magenta' => '#FF3DCB', 'violet' => '#A78BFA', 'amber' => '#FFC94A', 'green' => '#4ADE80'];
    public const EVENTS = ['room_invite', 'message', 'friend_request', 'shop', 'events', 'security'];
    public const CHANNELS = ['site', 'email'];

    public const DEFAULTS = [
        'onboarding' => [
            'dismissed' => false,
            'completed' => [],
        ],
        'privacy' => [
            'public' => true,
            'online' => true,
            'leaderboard' => true,
            'invite' => 'friends',
            'message' => 'friends',
            'friend_request' => 'everyone',
        ],
        'game' => [
            'sound' => 70,
            'music' => 35,
            'fullscreen' => false,
            'ping' => true,
            'vibrations' => true,
            'stay_in_room' => true,
            'accent' => 'cyan',
            'reduce_motion' => false,
            'contrast' => false,
            'locale' => null,
        ],
        'notifications' => [
            'room_invite' => ['site' => true, 'email' => false],
            'message' => ['site' => true, 'email' => false],
            'friend_request' => ['site' => true, 'email' => true],
            'events' => ['site' => true, 'email' => false],
            'security' => ['site' => true, 'email' => true],
            'studio' => ['site' => true, 'email' => true],
            'shop' => ['site' => true, 'email' => true],
            'dnd' => ['enabled' => false, 'from' => '23:00', 'to' => '08:00'],
        ],
    ];

    protected array $values;

    public function __construct(array $values = [])
    {
        $this->values = array_replace_recursive(self::DEFAULTS, $values);
    }

    public function all(): array
    {
        return $this->values;
    }

    public function get(string $section, string $key): mixed
    {
        return $this->values[$section][$key] ?? null;
    }

    public function section(string $section): array
    {
        return $this->values[$section];
    }

    public function with(string $section, array $values): static
    {
        return new static([...$this->values, $section => array_replace_recursive(self::DEFAULTS[$section], $values)]);
    }

    public function accent(): string
    {
        return self::ACCENTS[$this->get('game', 'accent')] ?? self::ACCENTS['cyan'];
    }

    public function wants(string $event, string $channel): bool
    {
        return (bool) ($this->values['notifications'][$event][$channel] ?? false);
    }
}
