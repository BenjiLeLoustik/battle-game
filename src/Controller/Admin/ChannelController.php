<?php

namespace App\Controller\Admin;

use App\Entity\ChatChannel;
use App\Repository\ChatChannelRepository;
use App\Shared\Social\ChatManager;
use App\Shared\Social\Enum\ChatAccessEnum;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;

#[Route('/channels', name: 'channel_')]
class ChannelController extends AbstractAdminController
{
    private const RESERVED = ['dm'];

    public function __construct(
        protected ChatChannelRepository $channels,
        protected ChatManager $chat,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/channel/index.html.twig', [
            'channels' => array_map(fn (ChatChannel $channel): array => ['channel' => $channel, 'messages' => $this->chat->count($channel)], $this->channels->findOrdered()),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-channels')]
    public function new(Request $request): Response
    {
        return $this->handle($request, (new ChatChannel())->setPosition(count($this->channels->findAll())));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-channels')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->find($id));
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-channels')]
    public function toggle(int $id): Response
    {
        $channel = $this->find($id);
        $this->channels->save($channel->setActive(!$channel->isActive()), true);
        $this->audit('channel.save', $channel->getSlug());

        return $this->done('admin_channel_index');
    }

    #[Route('/{id}/purge', name: 'purge', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-channels')]
    public function purge(int $id): Response
    {
        $channel = $this->find($id);
        $this->chat->purge($channel);
        $this->audit('channel.purge', $channel->getSlug());

        return $this->done('admin_channel_index', [], 'admin.channels.purged');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-channels')]
    public function delete(int $id): Response
    {
        $channel = $this->find($id);
        $this->chat->purge($channel);
        $this->deleteTranslations($channel);
        $this->channels->delete($channel, true);
        $this->audit('channel.delete', $channel->getSlug());

        return $this->done('admin_channel_index', [], 'admin.flash.deleted');
    }

    protected function handle(Request $request, ChatChannel $channel): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 60);
            $slug = $channel->getId() ? $channel->getSlug() : $this->slugify((string) (($data['slug'] ?? '') ?: $name));
            $read = ChatAccessEnum::tryFrom((string) ($data['read'] ?? ''));
            $write = ChatAccessEnum::tryFrom((string) ($data['write'] ?? ''));
            $other = $this->channels->findBySlug($slug);

            $channel
                ->setSlug($slug)
                ->setName($name)
                ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 160))
                ->setReadAccess(in_array($read, ChatAccessEnum::readable(), true) ? $read : ChatAccessEnum::EVERYONE)
                ->setWriteAccess(in_array($write, ChatAccessEnum::writable(), true) ? $write : ChatAccessEnum::MEMBERS)
                ->setCooldown(max(1, min(3600, (int) ($data['cooldown'] ?? 1))))
                ->setPosition((int) ($data['position'] ?? 0))
                ->setActive(isset($data['active']));

            $errors = array_filter([
                'name' => $name === '' ? 'admin.channels.errors.name' : null,
                'slug' => $slug === '' || in_array($slug, self::RESERVED, true) || str_starts_with($slug, 'dm-') ? 'admin.channels.errors.slug' : ($other !== null && $other !== $channel ? 'admin.channels.errors.slug_taken' : null),
            ]);

            if ($errors === []) {
                $this->channels->save($channel, true);
                $this->saveTranslations($channel, $request);
                $this->audit('channel.save', $channel->getSlug());

                return $this->done('admin_channel_index');
            }
        }

        return $this->render('@admin/pages/channel/form.html.twig', [
            'channel' => $channel,
            'errors' => $errors,
            'reads' => ChatAccessEnum::readable(),
            'writes' => ChatAccessEnum::writable(),
        ], $errors === [] ? 200 : 422);
    }

    protected function slugify(string $value): string
    {
        $value = trim((string) preg_replace('/[^a-z0-9]+/', '-', (string) transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value)), '-');

        return substr($value, 0, 40);
    }

    protected function find(int $id): ChatChannel
    {
        return $this->channels->find($id) ?? throw $this->createNotFoundException();
    }
}
