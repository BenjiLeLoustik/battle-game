<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\EmailCampaignRepository;
use App\Shared\Content\TranslatableInterface;
use DateTime;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: EmailCampaignRepository::class)]
class EmailCampaign implements TranslatableInterface
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\Column(length: 150)]
    private string $subject = '';

    #[ORM\Column(type: 'text')]
    private string $content = '';

    #[ORM\Column(length: 60)]
    private string $buttonLabel = '';

    #[ORM\Column(length: 255)]
    private string $buttonUrl = '';

    #[ORM\ManyToOne(MailingList::class, onDelete: 'SET NULL')]
    private ?MailingList $list = null;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTime $sentAt = null;

    #[ORM\Column]
    private int $recipients = 0;

    public static function getTranslatableFields(): array
    {
        return ['subject' => 'text', 'content' => 'area', 'buttonLabel' => 'text'];
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getButtonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function setButtonLabel(string $buttonLabel): static
    {
        $this->buttonLabel = $buttonLabel;

        return $this;
    }

    public function getButtonUrl(): string
    {
        return $this->buttonUrl;
    }

    public function setButtonUrl(string $buttonUrl): static
    {
        $this->buttonUrl = $buttonUrl;

        return $this;
    }

    public function getList(): ?MailingList
    {
        return $this->list;
    }

    public function setList(?MailingList $list): static
    {
        $this->list = $list;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getSentAt(): ?DateTime
    {
        return $this->sentAt;
    }

    public function getRecipients(): int
    {
        return $this->recipients;
    }

    public function isSent(): bool
    {
        return $this->sentAt !== null;
    }

    public function markSent(int $recipients): static
    {
        $this->sentAt = new DateTime();
        $this->recipients = $recipients;

        return $this;
    }
}
