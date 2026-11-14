<?php

namespace App\Controller\Site;

use App\Entity\Room;
use App\Repository\RoomRepository;
use App\Shared\Room\Enum\RoomStatusEnum;
use App\Shared\Room\Enum\RoomVisibilityEnum;
use App\Shared\Room\RoomManager;
use App\Shared\Setting\SettingManager;
use App\Shared\User\UserManager;
use App\Site\Room\RoomRequest;
use App\Site\Security\Form\GuestForm;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Component\Session\SessionManagerInterface;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route(name: 'room_')]
class RoomController extends AbstractController
{
    private const CODE = '[A-Za-z0-9]{3}-?[A-Za-z0-9]{3}';
    private const ACCESS = '_battlegame.room_access';
    private const ATTEMPTS = '_battlegame.room_attempts';
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        protected RoomManager $rooms,
        protected SessionManagerInterface $session,
        protected SettingManager $settings
    ) {
    }

    #[Route('/rooms', name: 'index', methods: ['GET'])]
    public function index(RoomRepository $repository): Response
    {
        $user = $this->getUser();

        return $this->render('@site/pages/room/index.html.twig', [
            'mine' => $user === null ? [] : array_map($this->rooms->summary(...), $repository->findJoinedBy($user)),
            'public' => $this->settings->get('public_rooms') ? array_map($this->rooms->summary(...), $repository->findPublic()) : [],
        ]);
    }

    #[Route('/rooms/new', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-new')]
    public function new(Request $request): Response
    {
        $data = $request->getMethod() === 'POST'
            ? RoomRequest::fromArray($request->request->all(), $this->settings->get('public_rooms'))
            : RoomRequest::create($this->getUser()->getUsername());
        $errors = $request->getMethod() === 'POST' ? $data->errors() : [];

        if ($request->getMethod() === 'POST' && $errors === []) {
            $room = $this->rooms->create($data->applyTo(new Room()), $this->getUser());

            return $this->redirectToRoute('site_room_invite', ['code' => $room->getCode()]);
        }

        return $this->render('@site/pages/room/new.html.twig', [
            'data' => $data,
            'errors' => $errors,
            'visibilities' => array_filter(RoomVisibilityEnum::cases(), fn (RoomVisibilityEnum $visibility): bool => $visibility !== RoomVisibilityEnum::PUBLIC || $this->settings->get('public_rooms')),
            'sizes' => Room::SIZES,
            'choices' => Room::CHOICES,
            'durations' => Room::DURATIONS,
        ], $errors === [] ? 200 : 422);
    }

    #[Route('/rooms/join', name: 'join', methods: ['GET', 'POST'])]
    public function join(Request $request, UserManager $users): Response
    {
        $code = RoomManager::normalize((string) ($request->request->get('code') ?? $request->query->get('code') ?? ''));
        $room = $code === '' ? null : $this->rooms->find($code);
        $guest = $this->getUser() === null && $this->settings->get('guests') ? $this->createForm(GuestForm::class) : null;

        if ($this->getUser() === null && $guest === null) {
            return $this->redirectToRoute('site_security_login');
        }
        $error = $code !== '' && $room === null ? 'site.room.join.not_found' : null;

        if ($request->getMethod() === 'POST' && $room !== null) {
            if ($guest !== null) {
                $guest->handleRequest($request);

                if (!$guest->isSubmitted() || !$guest->isValid()) {
                    return $this->renderJoin($code, $room, $guest, null);
                }

                $this->loginUser($users->createGuest($guest->get('username')->getData()), null, true);
            }

            if ($room->isProtected() && !$this->isUnlocked($room)) {
                $error = $this->unlock($room, (string) $request->request->get('password'));
            }
        }

        if ($room !== null && $error === null && $this->getUser() !== null && (!$room->isProtected() || $this->isUnlocked($room) || $this->rooms->isMember($room, $this->getUser()))) {
            return $this->redirectToRoute('site_room_show', ['code' => $room->getCode()]);
        }

        return $this->renderJoin($code, $room, $guest, $error);
    }

    #[Route('/r/{code}', name: 'short', methods: ['GET'], requirements: ['code' => self::CODE])]
    public function short(string $code): Response
    {
        return $this->redirectToRoute('site_room_join', ['code' => RoomManager::normalize($code)]);
    }

    #[Route('/rooms/{code}', name: 'show', methods: ['GET'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function show(string $code): Response
    {
        $room = $this->find($code);

        if ($room->isProtected() && !$this->isUnlocked($room) && !$this->rooms->isMember($room, $this->getUser())) {
            return $this->redirectToRoute('site_room_join', ['code' => $room->getCode()]);
        }

        $member = $this->rooms->join($room, $this->getUser());

        if ($room->getStatus() === RoomStatusEnum::PLAYING && !$member->isSpectator() && $room->getMatchCode() !== null) {
            return $this->redirectToRoute('site_play_show', ['code' => $room->getMatchCode()]);
        }

        return $this->render('@site/pages/room/show.html.twig', [
            'room' => $room,
            'snapshot' => $this->rooms->snapshot($room),
            'messages' => $this->rooms->messages($room),
            'topic' => $this->rooms->topic($room),
        ]);
    }

    #[Route('/rooms/{code}/invite', name: 'invite', methods: ['GET'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function invite(string $code): Response
    {
        $room = $this->find($code);

        if (!$room->isHost($this->getUser())) {
            return $this->redirectToRoute('site_room_show', ['code' => $room->getCode()]);
        }

        return $this->render('@site/pages/room/invite.html.twig', [
            'room' => $room,
            'link' => $this->generateUrl('site_room_short', ['code' => $room->getCode()], true),
        ]);
    }

    #[Route('/rooms/{code}/ping', name: 'ping', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function ping(string $code): Response
    {
        $room = $this->find($code);
        $this->rooms->ping($room, $this->getUser());

        return $this->json($this->rooms->snapshot($room));
    }

    #[Route('/rooms/{code}/vote', name: 'vote', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function vote(string $code, Request $request): Response
    {
        $this->rooms->vote($this->find($code), $this->getUser(), (int) ($request->toArray()['game'] ?? 0));

        return $this->json(['ok' => true]);
    }

    #[Route('/rooms/{code}/configure', name: 'configure', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function configure(string $code, Request $request): Response
    {
        $data = $request->toArray();
        $this->rooms->configure($this->find($code), $this->getUser(), (array) ($data['settings'] ?? []), ($data['defaults'] ?? false) === true);

        return $this->json(['ok' => true]);
    }

    #[Route('/rooms/{code}/launch', name: 'launch', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function launch(string $code): Response
    {
        $this->rooms->launchNow($this->find($code), $this->getUser());

        return $this->json(['ok' => true]);
    }

    #[Route('/rooms/{code}/messages', name: 'message', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function message(string $code, Request $request): Response
    {
        $this->rooms->say($this->find($code), $this->getUser(), (string) ($request->toArray()['text'] ?? ''));

        return $this->json(['ok' => true]);
    }

    #[Route('/rooms/{code}/leave', name: 'leave', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[IsGranted('IS_AUTHENTICATED')]
    #[Csrf('room-{code}')]
    public function leave(string $code): Response
    {
        $this->rooms->leave($this->find($code), $this->getUser());

        return $this->redirectToRoute('site_room_index');
    }

    protected function renderJoin(string $code, ?Room $room, mixed $guest, ?string $error): Response
    {
        return $this->render('@site/pages/room/join.html.twig', [
            'code' => $code,
            'room' => $room,
            'summary' => $room === null ? null : $this->rooms->summary($room),
            'guest' => $guest,
            'error' => $error,
            'attempts' => $room === null ? 0 : self::MAX_ATTEMPTS - ($this->session->get(self::ATTEMPTS, [])[$room->getCode()] ?? 0),
        ], $error === null ? 200 : 422);
    }

    protected function unlock(Room $room, string $password): ?string
    {
        $attempts = $this->session->get(self::ATTEMPTS, []);
        $count = $attempts[$room->getCode()] ?? 0;

        if ($count >= self::MAX_ATTEMPTS) {
            return 'site.room.join.locked';
        }

        if (!hash_equals((string) $room->getPassword(), $password)) {
            $attempts[$room->getCode()] = $count + 1;
            $this->session->set(self::ATTEMPTS, $attempts);

            return 'site.room.join.wrong_password';
        }

        $this->session->set(self::ACCESS, [...$this->session->get(self::ACCESS, []), $room->getCode()]);

        return null;
    }

    protected function isUnlocked(Room $room): bool
    {
        return in_array($room->getCode(), $this->session->get(self::ACCESS, []), true);
    }

    protected function find(string $code): Room
    {
        return $this->rooms->find($code) ?? throw $this->createNotFoundException();
    }
}
