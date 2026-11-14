<?php

namespace App\Site\Room;

use App\Entity\Room;
use App\Shared\Room\Enum\RoomVisibilityEnum;

class RoomRequest
{
    private const PASSWORD_PATTERN = '/^\S{4,30}$/';

    public string $name = '';
    public int $maxPlayers = 8;
    public RoomVisibilityEnum $visibility = RoomVisibilityEnum::CODE;
    public string $password = '';
    public int $choices = 4;
    public int $voteDuration = 20;
    public bool $guests = true;
    public bool $spectators = true;
    public bool $permanent = false;

    public static function fromArray(array $data, bool $public = true): static
    {
        $request = new static();
        $request->name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 60);
        $request->maxPlayers = self::pick((int) ($data['max_players'] ?? 0), Room::SIZES, 8);
        $request->visibility = RoomVisibilityEnum::tryFrom((string) ($data['visibility'] ?? '')) ?? RoomVisibilityEnum::CODE;

        if (!$public && $request->visibility === RoomVisibilityEnum::PUBLIC) {
            $request->visibility = RoomVisibilityEnum::CODE;
        }

        $request->password = trim((string) ($data['password'] ?? ''));
        $request->choices = self::pick((int) ($data['choices'] ?? 0), Room::CHOICES, 4);
        $request->voteDuration = self::pick((int) ($data['vote_duration'] ?? 0), Room::DURATIONS, 20);
        $request->guests = isset($data['guests']);
        $request->spectators = isset($data['spectators']);
        $request->permanent = isset($data['permanent']);

        return $request;
    }

    public static function fromRoom(Room $room): static
    {
        $request = new static();
        $request->name = $room->getName();
        $request->maxPlayers = $room->getMaxPlayers();
        $request->visibility = $room->getVisibility();
        $request->password = (string) $room->getPassword();
        $request->choices = $room->getChoices();
        $request->voteDuration = $room->getVoteDuration();
        $request->guests = $room->allowsGuests();
        $request->spectators = $room->allowsSpectators();
        $request->permanent = $room->isPermanent();

        return $request;
    }

    public static function create(string $host): static
    {
        $request = new static();
        $request->name = mb_substr('Room de ' . $host, 0, 60);
        $request->password = substr(bin2hex(random_bytes(3)), 0, 6);

        return $request;
    }

    public function errors(): array
    {
        return array_filter([
            'name' => mb_strlen($this->name) < 3 ? 'room.name.too_short' : null,
            'password' => $this->visibility === RoomVisibilityEnum::PASSWORD && !preg_match(self::PASSWORD_PATTERN, $this->password) ? 'room.password.invalid' : null,
        ]);
    }

    public function applyTo(Room $room): Room
    {
        return $room
            ->setName($this->name)
            ->setMaxPlayers($this->maxPlayers)
            ->setVisibility($this->visibility)
            ->setPassword($this->visibility === RoomVisibilityEnum::PASSWORD ? $this->password : null)
            ->setChoices($this->choices)
            ->setVoteDuration($this->voteDuration)
            ->setGuests($this->guests)
            ->setSpectators($this->spectators)
            ->setPermanent($this->permanent);
    }

    protected static function pick(int $value, array $allowed, int $default): int
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
