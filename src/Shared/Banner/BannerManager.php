<?php

namespace App\Shared\Banner;

use App\Entity\Banner;
use App\Entity\BannerList;
use App\Entity\User;
use App\Repository\BannerListRepository;
use App\Repository\BannerRepository;
use App\Shared\Banner\Enum\BannerAudienceEnum;
use App\Shared\Banner\Enum\BannerPagesEnum;
use DateTime;

class BannerManager
{
    public function __construct(
        protected BannerRepository $banners,
        protected BannerListRepository $lists,
    ) {
    }

    public function liveList(): ?BannerList
    {
        $now = new DateTime();
        $due = array_values(array_filter($this->lists->findAll(), fn (BannerList $list): bool => $list->getScheduledAt() !== null && $list->getScheduledAt() <= $now));

        if ($due !== []) {
            usort($due, fn (BannerList $a, BannerList $b): int => $a->getScheduledAt() <=> $b->getScheduledAt());

            foreach ($due as $list) {
                $list->setScheduledAt(null);
            }

            $this->publish(end($due));
        }

        return $this->lists->findOneBy(['live' => true]);
    }

    public function schedule(BannerList $list, ?DateTime $date): void
    {
        $this->lists->save($list->setScheduledAt($date), true);
    }

    public function forList(BannerList $list): array
    {
        return $this->banners->findBy(['list' => $list], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function visible(?User $user, ?string $route): array
    {
        $list = $this->liveList();

        if ($list === null) {
            return [];
        }

        return array_values(array_filter($this->forList($list), fn (Banner $banner): bool => $banner->isLive()
            && $this->matchesAudience($banner, $user)
            && $this->matchesPage($banner, (string) $route)));
    }

    public function publish(BannerList $list): void
    {
        foreach ($this->lists->findAll() as $other) {
            $this->lists->save($other->setLive($other === $list));

            if ($other === $list) {
                $other->setScheduledAt(null);
            }
        }

        $this->lists->getOrm()->flush();
    }

    public function move(Banner $banner, string $direction): void
    {
        $list = $this->forList($banner->getList());
        $index = array_search($banner, $list, true);
        $swap = $index + ($direction === 'up' ? -1 : 1);

        if (isset($list[$swap])) {
            [$list[$index], $list[$swap]] = [$list[$swap], $list[$index]];
        }

        foreach ($list as $position => $item) {
            $this->banners->save($item->setPosition($position));
        }

        $this->banners->getOrm()->flush();
    }

    protected function matchesAudience(Banner $banner, ?User $user): bool
    {
        return match ($banner->getAudience()) {
            BannerAudienceEnum::ALL => true,
            BannerAudienceEnum::MEMBERS => $user !== null && !$user->isGuest(),
            BannerAudienceEnum::GUESTS => $user === null || $user->isGuest(),
        };
    }

    protected function matchesPage(Banner $banner, string $route): bool
    {
        return match ($banner->getPages()) {
            BannerPagesEnum::ALL => true,
            BannerPagesEnum::HOME => $route === 'site_home_index',
            BannerPagesEnum::GAMES => str_starts_with($route, 'site_game_'),
        };
    }
}
