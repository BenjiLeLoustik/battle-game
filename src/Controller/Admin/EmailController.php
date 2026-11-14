<?php

namespace App\Controller\Admin;

use App\Admin\Mail\MailingManager;
use App\Entity\EmailCampaign;
use App\Entity\MailingList;
use App\Repository\CountryRepository;
use App\Repository\EmailCampaignRepository;
use App\Repository\GameRepository;
use App\Repository\MailingListRepository;
use App\Shared\Mail\Enum\MailingAudienceEnum;
use App\Shared\Mail\MailManager;
use NeoPHP\Component\Csrf\Attribute\Csrf;
use NeoPHP\Component\Http\Request\Request;
use NeoPHP\Component\Http\Response\Response;
use NeoPHP\Component\Routing\Attribute\Route;
use NeoPHP\Package\WebProfiler\WebProfilerManagerInterface;

#[Route('/emails', name: 'email_')]
class EmailController extends AbstractAdminController
{
    public function __construct(
        protected EmailCampaignRepository $campaigns,
        protected MailingListRepository $lists,
        protected MailingManager $mailing,
        protected MailManager $mail,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('@admin/pages/email/index.html.twig', [
            'tab' => 'campaigns',
            'campaigns' => $this->campaigns->findBy([], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Csrf('admin-emails')]
    public function new(Request $request): Response
    {
        return $this->handle($request, (new EmailCampaign())->setAuthor($this->getUser()));
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-emails')]
    public function edit(int $id, Request $request): Response
    {
        return $this->handle($request, $this->campaign($id));
    }

    #[Route('/{id}/send', name: 'send', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-emails')]
    public function send(int $id): Response
    {
        $campaign = $this->campaign($id);

        if (!$campaign->isSent() && $campaign->getList() !== null) {
            $count = $this->mailing->send($campaign);
            $this->audit('email.send', $campaign->getSubject() . ' · ' . $count);
            $this->addFlash('success', 'admin.emails.flash.sent');
        }

        return $this->redirectToRoute('admin_email_index');
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-emails')]
    public function delete(int $id): Response
    {
        $campaign = $this->campaign($id);
        $this->deleteTranslations($campaign);
        $this->campaigns->delete($campaign, true);
        $this->audit('email.delete', $campaign->getSubject());

        return $this->done('admin_email_index', [], 'admin.flash.deleted');
    }

    #[Route('/lists', name: 'lists', methods: ['GET'])]
    public function lists(): Response
    {
        return $this->render('@admin/pages/email/lists.html.twig', [
            'tab' => 'lists',
            'lists' => array_map(fn (MailingList $list): array => ['list' => $list, 'count' => count($this->mailing->recipients($list))], $this->lists->findBy([], ['name' => 'ASC'])),
        ]);
    }

    #[Route('/lists/new', name: 'list_new', methods: ['GET', 'POST'])]
    #[Csrf('admin-emails')]
    public function newList(Request $request, CountryRepository $countries, GameRepository $games): Response
    {
        return $this->handleList($request, new MailingList(), $countries, $games);
    }

    #[Route('/lists/{id}/edit', name: 'list_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-emails')]
    public function editList(int $id, Request $request, CountryRepository $countries, GameRepository $games): Response
    {
        return $this->handleList($request, $this->lists->find($id) ?? throw $this->createNotFoundException(), $countries, $games);
    }

    #[Route('/lists/{id}/delete', name: 'list_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[Csrf('admin-emails')]
    public function deleteList(int $id): Response
    {
        $list = $this->lists->find($id) ?? throw $this->createNotFoundException();
        $this->lists->delete($list, true);
        $this->audit('email.list_delete', $list->getName());

        return $this->done('admin_email_lists', [], 'admin.flash.deleted');
    }

    #[Route('/layout', name: 'layout', methods: ['GET', 'POST'])]
    #[Csrf('admin-emails')]
    public function layout(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            $this->mail->saveLayout($request->request->has('layout') ? (string) $request->request->get('layout') : null);
            $this->audit('email.layout');

            return $this->done('admin_email_layout');
        }

        return $this->render('@admin/pages/email/layout.html.twig', [
            'tab' => 'layout',
            'layout' => $this->mail->layout(),
            'custom' => $this->mail->isCustom(),
            'tokens' => MailManager::TOKENS,
        ]);
    }

    #[Route('/layout/preview', name: 'layout_preview', methods: ['POST'])]
    #[Csrf('admin-emails')]
    public function layoutPreview(Request $request, WebProfilerManagerInterface $profiler): Response
    {
        $profiler->disable();

        return new Response($this->mail->render(
            'Titre de l’email',
            '<p>Bonjour <strong>' . htmlspecialchars((string) $this->getUser()->getUsername(), ENT_QUOTES) . '</strong>, voici un aperçu du contenu.</p>',
            ['label' => 'Bouton', 'url' => '#'],
            'Petite note en bas de l’email.',
            '#',
            (string) $request->request->get('layout') ?: null,
        ));
    }

    protected function handle(Request $request, EmailCampaign $campaign): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST' && !$campaign->isSent()) {
            $data = $request->request->all();
            $campaign
                ->setSubject(mb_substr(trim((string) ($data['subject'] ?? '')), 0, 150))
                ->setContent((string) ($data['content'] ?? ''))
                ->setButtonLabel(mb_substr(trim((string) ($data['button_label'] ?? '')), 0, 60))
                ->setButtonUrl(mb_substr(trim((string) ($data['button_url'] ?? '')), 0, 255))
                ->setList($this->lists->find((int) ($data['list'] ?? 0)));
            $errors = array_filter([
                'subject' => $campaign->getSubject() === '' ? 'admin.emails.errors.subject' : null,
                'content' => trim($campaign->getContent()) === '' ? 'admin.emails.errors.content' : null,
                'button_url' => $campaign->getButtonUrl() !== '' && !preg_match('#^https?://#', $campaign->getButtonUrl()) ? 'admin.emails.errors.url' : null,
            ]);

            if ($errors === []) {
                $this->campaigns->save($campaign, true);
                $this->saveTranslations($campaign, $request);
                $this->audit('email.save', $campaign->getSubject());

                if (($data['intent'] ?? '') === 'test') {
                    $this->mailing->test($campaign, $this->getUser());
                    $this->addFlash('success', 'admin.emails.flash.test');
                } else {
                    $this->addFlash('success', 'admin.flash.saved');
                }

                return $this->redirectToRoute('admin_email_edit', ['id' => $campaign->getId()]);
            }
        }

        return $this->render('@admin/pages/email/form.html.twig', [
            'campaign' => $campaign,
            'errors' => $errors,
            'preview' => $campaign->getId() ? $this->mailing->render($campaign, $this->getUser()) : null,
            'lists' => array_map(fn (MailingList $list): array => ['list' => $list, 'count' => count($this->mailing->recipients($list))], $this->lists->findBy([], ['name' => 'ASC'])),
        ], $errors === [] ? 200 : 422);
    }

    protected function handleList(Request $request, MailingList $list, CountryRepository $countries, GameRepository $games): Response
    {
        $errors = [];

        if ($request->getMethod() === 'POST') {
            $data = $request->request->all();
            $audience = MailingAudienceEnum::tryFrom((string) ($data['audience'] ?? '')) ?? MailingAudienceEnum::ALL;
            $list
                ->setName(mb_substr(trim((string) ($data['name'] ?? '')), 0, 80))
                ->setDescription(mb_substr(trim((string) ($data['description'] ?? '')), 0, 160))
                ->setAudience($audience)
                ->setCountry($audience === MailingAudienceEnum::COUNTRY ? $countries->findByCode((string) ($data['country'] ?? ''))?->getCode() : null)
                ->setGame($audience === MailingAudienceEnum::GAME ? $games->find((int) ($data['game'] ?? 0)) : null)
                ->setOptIn(isset($data['opt_in']));
            $errors = array_filter([
                'name' => $list->getName() === '' ? 'admin.emails.errors.name' : null,
                'country' => $audience === MailingAudienceEnum::COUNTRY && $list->getCountry() === null ? 'admin.emails.errors.country' : null,
                'game' => $audience === MailingAudienceEnum::GAME && $list->getGame() === null ? 'admin.emails.errors.game' : null,
            ]);

            if ($errors === []) {
                $this->lists->save($list, true);
                $this->audit('email.list_save', $list->getName());

                return $this->done('admin_email_lists');
            }
        }

        return $this->render('@admin/pages/email/list_form.html.twig', [
            'list' => $list,
            'errors' => $errors,
            'audiences' => MailingAudienceEnum::cases(),
            'countries' => $countries->findOrdered(),
            'games' => $games->findBy([], ['name' => 'ASC']),
            'count' => $list->getId() ? count($this->mailing->recipients($list)) : null,
        ], $errors === [] ? 200 : 422);
    }

    protected function campaign(int $id): EmailCampaign
    {
        return $this->campaigns->find($id) ?? throw $this->createNotFoundException();
    }
}
