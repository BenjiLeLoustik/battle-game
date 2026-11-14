<?php

namespace App\Admin\Mail;

use App\Entity\EmailCampaign;
use App\Entity\MailingList;
use App\Entity\User;
use App\Repository\EmailCampaignRepository;
use App\Repository\UserRepository;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Mail\Enum\MailingAudienceEnum;
use App\Shared\Mail\MailManager;
use App\Shared\Translation\Translator;

class MailingManager
{
    public function __construct(
        protected UserRepository $users,
        protected EmailCampaignRepository $campaigns,
        protected MailManager $mail,
        protected ContentTranslationManager $contents,
    ) {
    }

    public function recipients(MailingList $list): array
    {
        $players = $list->getAudience() === MailingAudienceEnum::GAME && $list->getGame() !== null
            ? array_map('intval', array_column($this->users->getOrm()->getConnection()->fetchAllAssociative(
                'SELECT DISTINCT user_id FROM game_result WHERE game_id = :game',
                ['game' => $list->getGame()->getId()],
            ), 'user_id'))
            : [];

        return array_values(array_filter($this->users->findBy(['guest' => false]), fn (User $user): bool => $user->getEmail() !== null
            && $user->getDeletionRequestedAt() === null
            && (!$list->isOptIn() || $user->getPreferences()->wants('events', 'email'))
            && match ($list->getAudience()) {
                MailingAudienceEnum::ALL => true,
                MailingAudienceEnum::ADMINS => $user->isAdmin(),
                MailingAudienceEnum::COUNTRY => $user->getCountry() === $list->getCountry(),
                MailingAudienceEnum::GAME => in_array($user->getId(), $players, true),
            }));
    }

    public function subject(EmailCampaign $campaign, User $user): string
    {
        return (string) $this->contents->localize($campaign, 'subject', $this->mail->locale($user));
    }

    public function render(EmailCampaign $campaign, User $user): string
    {
        $locale = $this->mail->locale($user);
        $content = str_replace('{username}', htmlspecialchars((string) $user->getUsername(), ENT_QUOTES), $this->mail->markdown((string) $this->contents->localize($campaign, 'content', $locale)));
        $label = (string) $this->contents->localize($campaign, 'buttonLabel', $locale);
        $button = $label !== '' && $campaign->getButtonUrl() !== '' ? ['label' => $label, 'url' => $campaign->getButtonUrl()] : null;

        return $this->mail->render($this->subject($campaign, $user), $content, $button, Translator::trans('site.emails.campaign_note', [], 'messages', $locale), $this->mail->unsubscribeUrl($user), null, $locale);
    }

    public function test(EmailCampaign $campaign, User $user): void
    {
        $this->mail->send((string) $user->getEmail(), '[Test] ' . $this->subject($campaign, $user), $this->render($campaign, $user));
    }

    public function send(EmailCampaign $campaign): int
    {
        $recipients = $campaign->getList() ? $this->recipients($campaign->getList()) : [];

        foreach ($recipients as $user) {
            $this->mail->send((string) $user->getEmail(), $this->subject($campaign, $user), $this->render($campaign, $user));
        }

        $this->campaigns->save($campaign->markSent(count($recipients)), true);

        return count($recipients);
    }
}
