<?php

namespace App\Controller\Admin;

use App\Entity\Room;
use App\Repository\GameMatchRepository;
use App\Repository\RoomMessageRepository;
use App\Repository\RoomRepository;
use App\Repository\UserRepository;
use App\Shared\Room\Enum\RoomStatusEnum;
use App\Shared\Room\Enum\RoomVisibilityEnum;
use App\Shared\Room\RoomManager;
use App\Shared\Translation\Translator;
use App\Site\Room\RoomRequest;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/rooms', name: 'room_')]
class RoomController extends AbstractAdminController
{
    private const ACCESS = ['all', 'private', 'public', 'official'];
    private const CODE = '[A-Z0-9]{3}-[A-Z0-9]{3}';

    public function __construct(
        protected RoomRepository $repository,
        protected RoomManager $rooms
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $access = in_array($request->query->get('access'), self::ACCESS, true) ? $request->query->get('access') : 'all';
        $status = RoomStatusEnum::tryFrom((string) $request->query->get('status'));
        $search = mb_strtolower(trim((string) $request->query->get('q')));
        $rows = array_filter(array_map($this->rooms->summary(...), $this->repository->findBy([], ['updatedAt' => 'DESC'], 300)), fn (array $row): bool => match ($access) {
            'private' => $row['room']->getVisibility() !== RoomVisibilityEnum::PUBLIC,
            'public' => $row['room']->getVisibility() === RoomVisibilityEnum::PUBLIC,
            'official' => $row['room']->isOfficial(),
            default => true,
        } && ($status === null || $row['room']->getStatus() === $status)
            && ($search === '' || str_contains(mb_strtolower($row['room']->getName() . ' ' . $row['room']->getCode()), $search)));

        return $this->render('@admin/pages/room/index.html.twig', [
            'rows' => $rows,
            'filters' => ['access' => $access, 'status' => $status?->value, 'q' => $search],
            'accesses' => self::ACCESS,
            'statuses' => RoomStatusEnum::cases(),
            'counts' => [
                'total' => count($rows),
                'playing' => count(array_filter($rows, fn (array $row): bool => $row['room']->getStatus() === RoomStatusEnum::PLAYING)),
            ],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-rooms')]
    public function new(Request $request): Response
    {
        $this->allow('rooms.close');

        return $this->handle($request, (new Room())->setOfficial(true)->setVisibility(RoomVisibilityEnum::PUBLIC)->setPermanent(true));
    }

    #[Route('/{code}', name: 'show', methods: ['GET'], requirements: ['code' => self::CODE])]
    public function show(string $code, RoomMessageRepository $messages, GameMatchRepository $matches): Response
    {
        $room = $this->find($code);

        return $this->render('@admin/pages/room/show.html.twig', $this->rooms->summary($room) + [
            'members' => $this->rooms->members($room),
            'now' => (int) floor(microtime(true) * 1000),
            'messages' => $messages->findLatest($room, 50),
            'matches' => $matches->findBy(['room' => $room], ['id' => 'DESC'], 10),
            'topic' => $this->rooms->topic($room),
        ]);
    }

    #[Route('/{code}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['code' => self::CODE])]
    #[Csrf('admin-rooms')]
    public function edit(string $code, Request $request): Response
    {
        $this->allow('rooms.close');

        return $this->handle($request, $this->find($code));
    }

    #[Route('/{code}/close', name: 'close', methods: ['POST'], requirements: ['code' => self::CODE])]
    #[Csrf('admin-rooms')]
    public function close(string $code, Request $request): Response
    {
        $this->allow('rooms.close');
        $room = $this->find($code);
        $message = mb_substr(trim((string) $request->request->get('message')), 0, 200) ?: Translator::trans('admin.rooms.closed_default', [], 'admin');
        $this->rooms->close($room, $message);
        $this->audit('room.close', $code . ' · ' . $room->getName());

        return $this->done('admin_room_index', [], 'admin.flash.deleted');
    }

    #[Route('/{code}/kick/{user}', name: 'kick', methods: ['POST'], requirements: ['code' => self::CODE, 'user' => '\d+'])]
    #[Csrf('admin-rooms')]
    public function kick(string $code, int $user, UserRepository $users): Response
    {
        $this->allow('rooms.close');
        $member = $users->find($user) ?? throw $this->createNotFoundException();
        $this->rooms->kick($this->find($code), $member);
        $this->audit('room.kick', $code . ' · ' . $member->getUsername());

        return $this->done('admin_room_show', ['code' => $code]);
    }

    protected function handle(Request $request, Room $room): Response
    {
        $creating = $room->getId() === null;
        $data = $request->getMethod() === 'POST' ? RoomRequest::fromArray($request->request->all()) : RoomRequest::fromRoom($room);
        $errors = $request->getMethod() === 'POST' ? $data->errors() : [];

        if ($request->getMethod() === 'POST' && $errors === []) {
            $official = $request->request->get('official') !== null;
            $data->applyTo($room)->setOfficial($official);

            if ($official) {
                $room->setPermanent(true);
            }

            if ($creating) {
                $this->rooms->create($room, $this->getUser());
            } else {
                $this->repository->save($room, true);
            }

            $this->audit($creating ? 'room.create' : 'room.update', $room->getCode() . ' · ' . $room->getName());

            return $this->done('admin_room_show', ['code' => $room->getCode()]);
        }

        return $this->render('@admin/pages/room/form.html.twig', [
            'room' => $room,
            'creating' => $creating,
            'data' => $data,
            'errors' => $errors,
            'visibilities' => RoomVisibilityEnum::cases(),
            'sizes' => Room::SIZES,
            'choices' => Room::CHOICES,
            'durations' => Room::DURATIONS,
        ], $errors === [] ? 200 : 422);
    }

    protected function find(string $code): Room
    {
        return $this->repository->findByCode($code) ?? throw $this->createNotFoundException();
    }
}
