<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\WalletRepository;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: WalletRepository::class)]
class Wallet
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(User::class, nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $coins = 0;

    #[ORM\Column]
    private int $livesUsed = 0;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $livesDay = null;

    #[ORM\Column]
    private int $bonusLives = 0;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTime $dailyDay = null;

    #[ORM\Column]
    private int $dailyStreak = 0;

    #[ORM\Column(type: 'json')]
    private array $challenges = [];

    public function getDailyDay(): ?DateTime
    {
        return $this->dailyDay;
    }

    public function setDailyDay(?DateTime $dailyDay): static
    {
        $this->dailyDay = $dailyDay;

        return $this;
    }

    public function getDailyStreak(): int
    {
        return $this->dailyStreak;
    }

    public function setDailyStreak(int $dailyStreak): static
    {
        $this->dailyStreak = $dailyStreak;

        return $this;
    }

    public function getChallenges(): array
    {
        return $this->challenges;
    }

    public function setChallenges(array $challenges): static
    {
        $this->challenges = $challenges;

        return $this;
    }


    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCoins(): int
    {
        return $this->coins;
    }

    public function setCoins(int $coins): static
    {
        $this->coins = $coins;

        return $this;
    }

    public function getLivesUsed(): int
    {
        return $this->livesUsed;
    }

    public function setLivesUsed(int $livesUsed): static
    {
        $this->livesUsed = $livesUsed;

        return $this;
    }

    public function getLivesDay(): ?DateTime
    {
        return $this->livesDay;
    }

    public function setLivesDay(?DateTime $livesDay): static
    {
        $this->livesDay = $livesDay;

        return $this;
    }

    public function getBonusLives(): int
    {
        return $this->bonusLives;
    }

    public function setBonusLives(int $bonusLives): static
    {
        $this->bonusLives = $bonusLives;

        return $this;
    }
}
