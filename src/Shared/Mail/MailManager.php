<?php

namespace App\Shared\Mail;

use App\Entity\User;
use App\Shared\Content\ContentTranslationManager;
use App\Shared\Setting\SettingManager;
use App\Shared\Translation\Translator;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Mailer\MailerManagerInterface;
use NeoPHP\Component\Mailer\Mime\Email;
use NeoPHP\Component\Routing\RoutingManagerInterface;
use NeoPHP\Package\Markdown\MarkdownManagerInterface;

class MailManager
{
    public const LAYOUT = 'email_layout';
    public const TOKENS = ['title', 'content', 'button', 'note', 'unsubscribe', 'site', 'year', 'lang'];
    private const BUTTON = 'display: inline-block; padding: 14px 22px; border-radius: 10px; background: #2EE6D6; color: #06121A; font-weight: 700; text-decoration: none';

    public function __construct(
        protected MailerManagerInterface $mailer,
        protected SettingManager $settings,
        protected RoutingManagerInterface $router,
        protected MarkdownManagerInterface $markdown,
        protected ContentTranslationManager $contents,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
        #[Autowire(env: 'APP_SECRET')]
        protected string $secret = '',
    ) {
    }

    public function layout(): string
    {
        return (string) ($this->settings->get(self::LAYOUT) ?: $this->defaultLayout());
    }

    public function defaultLayout(): string
    {
        return (string) file_get_contents($this->rootPath . '/templates/site/emails/layout.html');
    }

    public function saveLayout(?string $html): void
    {
        $this->settings->set(self::LAYOUT, $html === null || trim($html) === '' || trim($html) === trim($this->defaultLayout()) ? null : $html);
    }

    public function isCustom(): bool
    {
        return (bool) $this->settings->get(self::LAYOUT);
    }

    public function locale(User $user): string
    {
        $locale = $user->getPreferences()->get('game', 'locale');

        return in_array($locale, $this->contents->available(), true) ? $locale : ContentTranslationManager::REFERENCE;
    }

    public function render(string $title, string $content, ?array $button = null, string $note = '', ?string $unsubscribe = null, ?string $layout = null, string $lang = ContentTranslationManager::REFERENCE): string
    {
        return strtr($layout ?? $this->layout(), [
            '{lang}' => $lang,
            '{title}' => $this->escape($title),
            '{content}' => $content,
            '{button}' => $button ? sprintf('<a href="%s" style="%s">%s</a>', $this->escape($button['url']), self::BUTTON, $this->escape($button['label'])) : '',
            '{note}' => $this->escape($note),
            '{unsubscribe}' => $unsubscribe ? sprintf('<a href="%s" style="color: #8C93B5">%s</a>', $this->escape($unsubscribe), $this->escape(Translator::trans('site.emails.unsubscribe', [], 'messages', $lang))) : '',
            '{site}' => $this->router->generate('site_home_index', [], true),
            '{year}' => date('Y'),
        ]);
    }

    public function send(string $to, string $subject, string $html): void
    {
        $this->mailer->send((new Email())->to($to)->subject($subject)->html($html));
    }

    public function compose(string $to, string $subject, string $title, string $text, ?array $button = null, string $note = '', ?string $unsubscribe = null, string $lang = ContentTranslationManager::REFERENCE): void
    {
        $this->send($to, $subject, $this->render($title, nl2br($this->escape($text)), $button, $note, $unsubscribe, null, $lang));
    }

    public function markdown(string $text): string
    {
        return $this->markdown->toHtml($text);
    }

    public function unsubscribeUrl(User $user): string
    {
        return $this->router->generate('site_email_unsubscribe', ['id' => $user->getId(), 'token' => $this->token($user)], true);
    }

    public function token(User $user): string
    {
        return substr(hash_hmac('sha256', 'unsubscribe-' . $user->getId(), $this->secret), 0, 32);
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES);
    }
}
