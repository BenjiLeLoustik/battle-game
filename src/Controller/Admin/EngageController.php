<?php

namespace App\Controller\Admin;

use App\Entity\GameEvent;
use App\Entity\PassPlan;
use App\Entity\Season;
use App\Entity\Tournament;
use App\Repository\ClanRepository;
use App\Repository\GameEventRepository;
use App\Repository\GameRepository;
use App\Repository\PassPlanRepository;
use App\Repository\SeasonPassRepository;
use App\Repository\SeasonRepository;
use App\Repository\ShopProductRepository;
use App\Repository\TournamentRepository;
use App\Shared\Clan\ClanManager;
use App\Shared\Engage\RatingManager;
use App\Shared\Engage\RewardManager;
use App\Shared\Engage\SeasonPassManager;
use App\Shared\Engage\TournamentManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Shop\Enum\ShopProductTypeEnum;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;
use DateTime;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/engage', name: 'engage_')]
class EngageController extends AbstractAdminController
{
    private const DATE = 'Y-m-d\TH:i';

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(GameEventRepository $events): Response
    {
        return $this->render('@admin/pages/engage/events.html.twig', ['events' => $events->findBy([], ['startsAt' => 'DESC']), 'now' => new DateTime()]);
    }

    #[Route('/events/new', name: 'event_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-engage')]
    public function newEvent(Request $request, GameEventRepository $events, GameRepository $games): Response
    {
        return $this->eventForm($request, (new GameEvent())->setStartsAt(new DateTime('+1 hour'))->setEndsAt(new DateTime('+3 days'))->setXpMultiplier(2), $events, $games);
    }

    #[Route('/events/{id}/edit', name: 'event_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function editEvent(int $id, Request $request, GameEventRepository $events, GameRepository $games): Response
    {
        return $this->eventForm($request, $events->find($id) ?? throw $this->createNotFoundException(), $events, $games);
    }

    #[Route('/events/{id}/delete', name: 'event_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function deleteEvent(int $id, GameEventRepository $events): Response
    {
        $event = $events->find($id) ?? throw $this->createNotFoundException();
        $events->delete($event, true);
        $this->audit('engage.event_delete', $event->getName());

        return $this->done('admin_engage_events', [], 'admin.flash.deleted');
    }

    #[Route('/seasons', name: 'seasons', methods: ['GET'])]
    public function seasons(SeasonRepository $seasons, SeasonPassRepository $passes): Response
    {
        $list = array_map(fn (Season $season): array => ['season' => $season, 'players' => $passes->count(['season' => $season])], $seasons->findBy([], ['startsAt' => 'DESC']));
        $current = $seasons->current();
        $next = current(array_filter(array_reverse($list), fn (array $item): bool => $item['season']->getState() === 'upcoming')) ?: null;

        return $this->render('@admin/pages/engage/seasons.html.twig', [
            'seasons' => $list,
            'current' => $current,
            'current_players' => $current !== null ? $passes->count(['season' => $current]) : 0,
            'next' => $next['season'] ?? null,
            'now' => time(),
        ]);
    }

    #[Route('/seasons/new', name: 'season_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-engage')]
    public function newSeason(Request $request, SeasonRepository $seasons, PassPlanRepository $plans, NotificationManager $notifications): Response
    {
        $last = $seasons->findOneBy([], ['endsAt' => 'DESC']);
        $starts = $last !== null && $last->getEndsAt() > new DateTime() ? clone $last->getEndsAt() : new DateTime('today');
        $season = (new Season())->setName('Saison ' . ($seasons->count() + 1))->setStartsAt($starts)->setEndsAt((clone $starts)->modify('+30 days'));

        return $this->seasonForm($request, $season, $seasons, $plans, $notifications);
    }

    #[Route('/seasons/{id}/edit', name: 'season_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function editSeason(int $id, Request $request, SeasonRepository $seasons, PassPlanRepository $plans, NotificationManager $notifications): Response
    {
        return $this->seasonForm($request, $seasons->find($id) ?? throw $this->createNotFoundException(), $seasons, $plans, $notifications);
    }

    #[Route('/seasons/{id}/delete', name: 'season_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function deleteSeason(int $id, SeasonRepository $seasons): Response
    {
        $season = $seasons->find($id) ?? throw $this->createNotFoundException();
        $seasons->delete($season, true);
        $this->audit('engage.season_delete', $season->getName());

        return $this->done('admin_engage_seasons', [], 'admin.flash.deleted');
    }

    #[Route('/ranks', name: 'ranks', methods: ['GET', 'POST'])]
    #[Csrf('admin-engage')]
    public function ranks(Request $request, SettingManager $settings, RatingManager $ratings): Response
    {
        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $tiers = [];

            foreach ((array) ($data['tiers'] ?? []) as $row) {
                $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 30);

                if ($name !== '') {
                    $tiers[] = ['name' => $name, 'min' => max(0, (int) ($row['min'] ?? 0)), 'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($row['color'] ?? '')) ? strtoupper($row['color']) : '#8A93B5'];
                }
            }

            usort($tiers, fn (array $a, array $b): int => $a['min'] <=> $b['min']);

            if ($tiers !== []) {
                $tiers[0]['min'] = 0;
            }

            $settings->set('ranked_tiers', $tiers);
            $settings->set('ranked_step', max(10, (int) ($data['step'] ?? 150)));
            $this->audit('engage.ranks', count($tiers) . ' grades');

            return $this->done('admin_engage_ranks');
        }

        return $this->render('@admin/pages/engage/ranks.html.twig', ['tiers' => $ratings->tiers(), 'step' => $ratings->step()]);
    }

    #[Route('/pass', name: 'pass', methods: ['GET'])]
    public function passes(PassPlanRepository $plans, SeasonRepository $seasons, SeasonPassManager $passes, ShopProductRepository $products): Response
    {
        $linked = [];

        foreach ($seasons->findBy([], ['startsAt' => 'DESC']) as $season) {
            if ($season->getPass() !== null) {
                $linked[$season->getPass()->getId()][] = $season;
            }
        }

        return $this->render('@admin/pages/engage/passes.html.twig', [
            'plans' => $plans->findBy([], ['id' => 'DESC']),
            'linked' => $linked,
            'tracks' => $passes->tracks(),
            'offers' => $this->trackOffers($products),
            'current' => $seasons->current(),
        ]);
    }

    #[Route('/pass/tracks', name: 'pass_tracks', methods: ['POST'])]
    #[Csrf('admin-engage')]
    public function passTracks(Request $request, SettingManager $settings): Response
    {
        $tracks = [];

        foreach ((array) ($request->request->all()['tracks'] ?? []) as $row) {
            $key = strtolower((string) ($row['key'] ?? ''));
            $name = mb_substr(trim((string) ($row['name'] ?? '')), 0, 40);

            if ($name === '' || !preg_match('/^[a-z0-9_]{1,20}$/', $key) || in_array($key, [SeasonPassManager::FREE, 'xp'], true) || isset($tracks[$key])) {
                continue;
            }

            $tracks[$key] = ['key' => $key, 'name' => $name, 'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($row['color'] ?? '')) ? strtoupper($row['color']) : '#FFC94A'];
        }

        $settings->set('pass_tracks', array_values($tracks));
        $this->audit('engage.pass_tracks', (count($tracks) + 1) . ' pistes');

        return $this->done('admin_engage_pass');
    }

    #[Route('/pass/new', name: 'pass_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-engage')]
    public function newPass(Request $request, PassPlanRepository $plans, SeasonRepository $seasons, SeasonPassManager $passes, ShopProductRepository $products): Response
    {
        $plan = (new PassPlan())->setName('Pass ' . ($plans->count() + 1));
        $source = $plans->find((int) $request->query->get('from'));

        if ($source !== null) {
            $plan->setName($source->getName() . ' (copie)')->setTiers($source->getTiers());
        }

        return $this->passForm($request, $plan, $plans, $seasons, $passes, $products);
    }

    #[Route('/pass/{id}', name: 'pass_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function editPass(int $id, Request $request, PassPlanRepository $plans, SeasonRepository $seasons, SeasonPassManager $passes, ShopProductRepository $products): Response
    {
        return $this->passForm($request, $plans->find($id) ?? throw $this->createNotFoundException(), $plans, $seasons, $passes, $products);
    }

    #[Route('/pass/{id}/delete', name: 'pass_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function deletePass(int $id, PassPlanRepository $plans): Response
    {
        $plan = $plans->find($id) ?? throw $this->createNotFoundException();
        $plans->delete($plan, true);
        $this->audit('engage.pass_delete', $plan->getName());

        return $this->done('admin_engage_pass', [], 'admin.flash.deleted');
    }

    #[Route('/tournaments', name: 'tournaments', methods: ['GET'])]
    public function tournaments(TournamentRepository $tournaments, TournamentManager $manager): Response
    {
        return $this->render('@admin/pages/engage/tournaments.html.twig', [
            'tournaments' => array_map(fn (Tournament $tournament): array => ['tournament' => $tournament, 'count' => $manager->count($tournament)], $tournaments->findBy([], ['startsAt' => 'DESC'])),
        ]);
    }

    #[Route('/tournaments/new', name: 'tournament_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-engage')]
    public function newTournament(Request $request, TournamentRepository $tournaments, GameRepository $games, RewardManager $rewards, TournamentManager $manager, ShopProductRepository $products): Response
    {
        $tournament = (new Tournament())->setStartsAt(new DateTime('+1 day 18:00'))->setEndsAt(new DateTime('+3 days 22:00'))->setRewards([['place' => 1, 'type' => 'coins', 'value' => 1000], ['place' => 2, 'type' => 'coins', 'value' => 500], ['place' => 3, 'type' => 'coins', 'value' => 250]]);

        return $this->tournamentForm($request, $tournament, $tournaments, $games, $rewards, $manager, $products);
    }

    #[Route('/tournaments/{id}/edit', name: 'tournament_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function editTournament(int $id, Request $request, TournamentRepository $tournaments, GameRepository $games, RewardManager $rewards, TournamentManager $manager, ShopProductRepository $products): Response
    {
        return $this->tournamentForm($request, $tournaments->find($id) ?? throw $this->createNotFoundException(), $tournaments, $games, $rewards, $manager, $products);
    }

    #[Route('/tournaments/{id}/qualify', name: 'tournament_qualify', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function qualifyTournament(int $id, TournamentRepository $tournaments, TournamentManager $manager, NotificationManager $notifications): Response
    {
        $tournament = $tournaments->find($id) ?? throw $this->createNotFoundException();
        $added = $manager->registerQualified($tournament);

        foreach ($added as $user) {
            $notifications->notify($user, NotificationTypeEnum::TOURNAMENT_QUALIFIED, ['tournament' => $tournament->getName(), 'id' => $tournament->getId()]);
        }

        $this->audit('engage.tournament_qualify', $tournament->getName() . ' · ' . count($added));

        return $this->done('admin_engage_tournament_edit', ['id' => $id], 'admin.engage.tournaments.qualify_done');
    }

    #[Route('/tournaments/{id}/delete', name: 'tournament_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function deleteTournament(int $id, TournamentRepository $tournaments): Response
    {
        $tournament = $tournaments->find($id) ?? throw $this->createNotFoundException();
        $tournaments->delete($tournament, true);
        $this->audit('engage.tournament_delete', $tournament->getName());

        return $this->done('admin_engage_tournaments', [], 'admin.flash.deleted');
    }

    #[Route('/clans', name: 'clans', methods: ['GET'])]
    public function clans(ClanManager $clans): Response
    {
        return $this->render('@admin/pages/engage/clans.html.twig', ['clans' => $clans->ranking(500)]);
    }

    #[Route('/clans/{id}/delete', name: 'clan_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-engage')]
    public function deleteClan(int $id, ClanRepository $repository, ClanManager $clans): Response
    {
        $clan = $repository->find($id) ?? throw $this->createNotFoundException();
        $clans->disband($clan);
        $this->audit('engage.clan_delete', $clan->getName());

        return $this->done('admin_engage_clans', [], 'admin.flash.deleted');
    }

    protected function seasonForm(Request $request, Season $season, SeasonRepository $seasons, PassPlanRepository $plans, NotificationManager $notifications): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $starts = DateTime::createFromFormat(self::DATE, (string) ($data['starts_at'] ?? ''));
            $ends = DateTime::createFromFormat(self::DATE, (string) ($data['ends_at'] ?? ''));
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);
            $overlap = $starts && $ends ? array_filter($seasons->findAll(), fn (Season $other): bool => $other->getId() !== $season->getId() && $other->getStartsAt() < $ends && $other->getEndsAt() > $starts) : [];

            if ($name === '' || !$starts || !$ends || $ends <= $starts) {
                $error = 'admin.engage.seasons.invalid';
            } elseif ($overlap !== []) {
                $error = 'admin.engage.seasons.overlap';
            } else {
                $created = $season->getId() === null;
                $seasons->save($season->setName($name)->setStartsAt($starts)->setEndsAt($ends)->setPass($plans->find((int) ($data['pass'] ?? 0))), true);
                $this->audit('engage.season', $name);

                if ($created && $season->getState() === 'live') {
                    $notifications->broadcast(NotificationTypeEnum::SEASON_STARTED, ['season' => $name]);
                }

                return $this->done('admin_engage_seasons');
            }
        }

        return $this->render('@admin/pages/engage/season.html.twig', ['season' => $season, 'plans' => $plans->findBy([], ['id' => 'DESC']), 'error' => $error], $error === null ? 200 : 422);
    }

    protected function passForm(Request $request, PassPlan $plan, PassPlanRepository $plans, SeasonRepository $seasons, SeasonPassManager $passes, ShopProductRepository $products): Response
    {
        $keys = $passes->keys();

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $tiers = [];

            foreach ((array) ($data['tiers'] ?? []) as $row) {
                $xp = (int) ($row['xp'] ?? 0);

                if ($xp <= 0) {
                    continue;
                }

                $tier = ['xp' => $xp];

                foreach ($keys as $key) {
                    $cell = (array) ($row[$key] ?? []);
                    $type = (string) ($cell['type'] ?? '');
                    $value = $type === 'product' ? (int) ($cell['product'] ?? 0) : (int) ($cell['value'] ?? 0);
                    $tier[$key] = in_array($type, SeasonPassManager::TYPES, true) && $value > 0 ? ['type' => $type, 'value' => $value] : null;
                }

                $tiers[] = $tier;
            }

            usort($tiers, fn (array $a, array $b): int => $a['xp'] <=> $b['xp']);
            $plans->save($plan->setName(mb_substr(trim((string) ($data['name'] ?? '')), 0, 80) ?: 'Pass')->setTiers($tiers), true);

            foreach ((array) ($data['seasons'] ?? []) as $id) {
                $season = $seasons->find((int) $id);

                if ($season !== null) {
                    $seasons->save($season->setPass($plan), true);
                }
            }

            $this->audit('engage.pass', $plan->getName() . ' · ' . count($tiers) . ' paliers');

            return $this->done('admin_engage_pass_edit', ['id' => $plan->getId()]);
        }

        return $this->render('@admin/pages/engage/pass.html.twig', [
            'plan' => $plan,
            'tiers' => $plan->getTiers(),
            'tracks' => $passes->tracks(),
            'keys' => $keys,
            'seasons' => $seasons->findBy([], ['startsAt' => 'DESC']),
            'products' => array_merge($products->findBy(['type' => ShopProductTypeEnum::COSMETIC], ['name' => 'ASC']), $products->findBy(['type' => ShopProductTypeEnum::BADGE], ['name' => 'ASC'])),
        ]);
    }

    protected function trackOffers(ShopProductRepository $products): array
    {
        $offers = [];

        foreach ($products->findBy(['type' => ShopProductTypeEnum::SUBSCRIPTION], ['position' => 'ASC']) as $offer) {
            $chosen = (array) $offer->option('pass_tracks', []);

            if ($chosen === [] && $offer->option('season_pass') === true) {
                $chosen = ['premium'];
            }

            foreach ($chosen as $key) {
                $offers[$key][] = $offer->getName();
            }
        }

        return $offers;
    }

    protected function eventForm(Request $request, GameEvent $event, GameEventRepository $events, GameRepository $games): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $starts = DateTime::createFromFormat(self::DATE, (string) ($data['starts_at'] ?? ''));
            $ends = DateTime::createFromFormat(self::DATE, (string) ($data['ends_at'] ?? ''));
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);

            if ($name === '' || !$starts || !$ends || $ends <= $starts) {
                $error = 'admin.engage.events.invalid';
            } else {
                $events->save($event
                    ->setName($name)
                    ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 300))
                    ->setGame($games->find((int) ($data['game'] ?? 0)))
                    ->setStartsAt($starts)
                    ->setEndsAt($ends)
                    ->setXpMultiplier(max(1, min(5, (float) str_replace(',', '.', (string) ($data['xp'] ?? '1')))))
                    ->setCoinsMultiplier(max(1, min(5, (float) str_replace(',', '.', (string) ($data['coins'] ?? '1')))))
                    ->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($data['color'] ?? '')) ? strtoupper($data['color']) : '#FFC94A')
                    ->setActive(isset($data['active'])), true);
                $this->audit('engage.event', $name);

                return $this->done('admin_engage_events');
            }
        }

        return $this->render('@admin/pages/engage/event.html.twig', ['event' => $event, 'games' => $games->findLive(), 'error' => $error], $error === null ? 200 : 422);
    }

    protected function tournamentForm(Request $request, Tournament $tournament, TournamentRepository $tournaments, GameRepository $games, RewardManager $rewards, TournamentManager $manager, ShopProductRepository $products): Response
    {
        $error = null;

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $starts = DateTime::createFromFormat(self::DATE, (string) ($data['starts_at'] ?? ''));
            $ends = DateTime::createFromFormat(self::DATE, (string) ($data['ends_at'] ?? ''));
            $game = $games->find((int) ($data['game'] ?? 0));
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 80);

            if ($name === '' || $game === null || !$starts || !$ends || $ends <= $starts) {
                $error = 'admin.engage.tournaments.invalid';
            } else {
                $tournaments->save($tournament
                    ->setName($name)
                    ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 2000))
                    ->setGame($game)
                    ->setStartsAt($starts)
                    ->setEndsAt($ends)
                    ->setMaxPlayers(trim((string) ($data['max_players'] ?? '')) === '' ? null : max(2, (int) $data['max_players']))
                    ->setRewards($rewards->normalize((array) ($data['rewards'] ?? [])))
                    ->setQualifier(in_array($data['qualifier'] ?? '', TournamentManager::QUALIFIERS, true) ? $data['qualifier'] : 'open')
                    ->setQualifierCount(max(1, (int) ($data['qualifier_count'] ?? 10)))
                    ->setQualifierPeriod(in_array($data['qualifier_period'] ?? '', TournamentManager::PERIODS, true) ? $data['qualifier_period'] : 'season')
                    ->setPublished(isset($data['published'])), true);
                $this->audit('engage.tournament', $name);

                return $this->done('admin_engage_tournament_edit', ['id' => $tournament->getId()]);
            }
        }

        $entries = $tournament->getId() !== null ? array_map('intval', $tournaments->getOrm()->getConnection()->fetchFirstColumn('SELECT user_id FROM tournament_entry WHERE tournament_id = :id', ['id' => $tournament->getId()])) : [];

        return $this->render('@admin/pages/engage/tournament.html.twig', [
            'tournament' => $tournament,
            'games' => $games->findLive(),
            'error' => $error,
            'items' => array_merge($products->findBy(['type' => ShopProductTypeEnum::COSMETIC], ['name' => 'ASC']), $products->findBy(['type' => ShopProductTypeEnum::BADGE], ['name' => 'ASC'])),
            'offers' => $products->findBy(['type' => ShopProductTypeEnum::SUBSCRIPTION], ['position' => 'ASC']),
            'types' => RewardManager::TYPES,
            'qualified' => $tournament->getId() !== null ? $manager->qualified($tournament) : [],
            'entries' => $entries,
        ], $error === null ? 200 : 422);
    }
}
