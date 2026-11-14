<?php

namespace App\Shared\Setting;

use App\Entity\Setting;
use App\Repository\SettingRepository;

class SettingManager
{
    public const DEFAULTS = [
        'guests' => true,
        'registration' => true,
        'public_rooms' => true,
        'filtered_words' => [],
        'shop_enabled' => true,
        'referral_referrer' => 300,
        'referral_referee' => 150,
        'ranked_tiers' => [],
        'ranked_step' => 150,
        'pass_tracks' => [['key' => 'premium', 'name' => 'Premium', 'color' => '#FFC94A']],
        'admin_2fa_required' => false,
        'daily_rewards' => '10,15,20,25,30,40,60',
        'challenge_play' => 20,
        'challenge_win' => 30,
        'challenge_variety' => 25,
        'season_rewards' => '1000,600,400,250,250,150,150,100,100,100',
        'backup_auto' => true,
        'backup_files' => true,
        'backup_keep' => 7,
        'legal_name' => '',
        'legal_form' => '',
        'legal_address' => '',
        'legal_siret' => '',
        'legal_vat_number' => '',
        'legal_email' => '',
        'vat_rate' => 20,
        'vat_exempt' => true,
        'coins_per_match' => 5,
        'coins_per_win' => 15,
        'lives_free' => 5,
    ];

    protected ?array $values = null;

    public function __construct(protected SettingRepository $settings)
    {
    }

    public function get(string $name): mixed
    {
        if ($this->values === null) {
            $this->values = self::DEFAULTS;

            foreach ($this->settings->findAll() as $setting) {
                $this->values[$setting->getName()] = $setting->getValue()['value'] ?? null;
            }
        }

        return $this->values[$name] ?? self::DEFAULTS[$name] ?? null;
    }

    public function set(string $name, mixed $value): void
    {
        $setting = $this->settings->findOneBy(['name' => $name]) ?? (new Setting())->setName($name);
        $this->settings->save($setting->setValue(['value' => $value]), true);
        $this->values = null;
    }

    public function filter(string $text): string
    {
        $words = array_filter((array) $this->get('filtered_words'), fn (mixed $word): bool => is_string($word) && $word !== '');

        if ($words === []) {
            return $text;
        }

        $pattern = '/\b(' . implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), $words)) . ')\b/iu';

        return (string) preg_replace_callback($pattern, fn (array $match): string => str_repeat('*', mb_strlen($match[0])), $text);
    }
}
