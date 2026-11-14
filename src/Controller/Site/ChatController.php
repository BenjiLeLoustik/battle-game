<?php

namespace App\Controller\Site;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Shared\Social\ChatManager;
use App\Shared\Social\Exception\SocialActionException;
use App\Shared\Social\FriendManager;
use App\Shared\Social\PresenceManager;
use NeoPHP\Component\Controller\Contract\AbstractController;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\Security\Attribute\IsGranted;

#[Route('/chat', name: 'chat_')]
class ChatController extends AbstractController
{
    private const KEY = '[a-z0-9-]+';
    private const MEMBERS = 30;

    public function __construct(
        protected ChatManager $chat,
        protected FriendManager $friends,
        protected PresenceManager $presence
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $first = $this->chat->visible($this->viewer())[0] ?? null;

        return $first === null
            ? $this->render('@site/pages/chat/empty.html.twig')
            : $this->redirectToRoute('site_chat_channel', ['channel' => $first->getSlug()]);
    }

    #[Route('/{channel}', name: 'channel', methods: ['GET'], requirements: ['channel' => '[a-z0-9-]+'])]
    public function channel(string $channel): Response
    {
        $current = $this->chat->channel($channel);

        if ($current === null || !$this->chat->canRead($this->viewer(), $current)) {
            throw $this->createNotFoundException();
        }

        return $this->page($current->getSlug(), ['channel' => $current]);
    }

    #[Route('/dm/{username}', name: 'dm', methods: ['GET'], requirements: ['username' => '[A-Za-z0-9_]{3,30}'])]
    #[IsGranted('ROLE_USER')]
    public function direct(string $username, UserRepository $users): Response
    {
        $other = $users->findOneBy(['username' => $username]);

        if ($other === null || $other->isGuest() || $other->getId() === $this->getUser()->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->page(ChatManager::direct($this->getUser(), $other), [
            'partner' => $other,
            'partnerStatus' => $this->presence->status($other),
        ]);
    }

    #[Route('/{key}/messages', name: 'post', methods: ['POST'], requirements: ['key' => self::KEY])]
    #[IsGranted('ROLE_USER')]
    #[Csrf('chat')]
    public function post(string $key, Request $request): Response
    {
        $data = $request->toArray();
        $room = ($data['invite'] ?? false) === true ? $this->friends->currentRoom($this->getUser()) : null;

        if (($data['invite'] ?? false) === true && $room === null) {
            throw new SocialActionException();
        }

        $this->chat->post($this->getUser(), $key, (string) ($data['text'] ?? ''), $room);

        return $this->json(['ok' => true]);
    }

    #[Route('/{key}/read', name: 'read', methods: ['POST'], requirements: ['key' => self::KEY])]
    #[IsGranted('ROLE_USER')]
    #[Csrf('chat')]
    public function read(string $key): Response
    {
        $this->chat->markRead($this->getUser(), $key);

        return $this->json(['ok' => true]);
    }

    #[Route('/{key}/typing', name: 'typing', methods: ['POST'], requirements: ['key' => self::KEY])]
    #[IsGranted('ROLE_USER')]
    #[Csrf('chat')]
    public function typing(string $key): Response
    {
        $this->chat->typing($this->getUser(), $key);

        return $this->json(['ok' => true]);
    }

    protected function viewer(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    protected function page(string $key, array $parameters): Response
    {
        $user = $this->getUser();
        $member = $user instanceof User && !$user->isGuest();

        if ($member) {
            $this->chat->markRead($user, $key);
        }

        $friends = $member ? $this->friends->friends($user) : [];
        $friendIds = array_map(fn (User $friend): ?int => $friend->getId(), $friends);
        $online = array_filter($this->presence->online(self::MEMBERS), fn (User $other): bool => !$member || $other->getId() !== $user->getId());
        $entry = fn (User $other): array => ['user' => $other, 'status' => $this->presence->status($other)];

        return $this->render('@site/pages/chat/index.html.twig', $parameters + [
            'key' => $key,
            'channel' => null,
            'partner' => null,
            'channels' => $this->chat->visible($this->viewer()),
            'messages' => $this->chat->history($key, $member ? $user : null),
            'conversations' => $member ? array_map(fn (array $conversation): array => $conversation + ['online' => $this->presence->isOnline($conversation['user'])], $this->chat->conversations($user)) : [],
            'unread' => $member ? $this->chat->unread($user) : [],
            'canWrite' => $this->chat->canWrite($member ? $user : null, $key),
            'onlineFriends' => array_map($entry, array_values(array_filter($friends, fn (User $friend): bool => $this->presence->isOnline($friend)))),
            'online' => array_map($entry, array_values(array_filter($online, fn (User $other): bool => !in_array($other->getId(), $friendIds, true)))),
            'onlineCount' => $this->presence->countOnline(),
            'othersCount' => max(0, $this->presence->countOnline() - count(array_filter($friends, fn (User $friend): bool => $this->presence->isOnline($friend))) - ($member && $this->presence->isOnline($user) ? 1 : 0)),
            'room' => $member ? $this->friends->currentRoom($user) : null,
            'topic' => $this->chat->topic($key),
        ]);
    }
}
