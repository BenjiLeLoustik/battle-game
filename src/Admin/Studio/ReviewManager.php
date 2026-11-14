<?php

namespace App\Admin\Studio;

use App\Admin\Studio\Enum\GameReviewStatusEnum;
use App\Entity\Game;
use App\Entity\GameMember;
use App\Entity\GameReview;
use App\Entity\User;
use App\Repository\GameMemberRepository;
use App\Repository\GameReviewRepository;
use App\Shared\Social\Enum\NotificationTypeEnum;
use App\Shared\Social\NotificationManager;

class ReviewManager
{
    public function __construct(
        protected GameReviewRepository $reviews,
        protected GameMemberRepository $members,
        protected EditorManager $editor,
        protected NotificationManager $notifications,
    ) {
    }

    public function request(Game $game, User $user, ?string $message): GameReview
    {
        $review = $this->reviews->findPending($game);

        if ($review !== null) {
            return $review;
        }

        $review = (new GameReview())->setGame($game)->setRequester($user)->setMessage($message === null ? null : mb_substr($message, 0, 1000));
        $this->reviews->save($review, true);

        foreach ($this->leads($game) as $lead) {
            if ($lead->getId() !== $user->getId()) {
                $this->notifications->notify($lead, NotificationTypeEnum::STUDIO_REVIEW, $this->data($game, $user));
            }
        }

        return $review;
    }

    public function approve(GameReview $review, User $reviewer): ?string
    {
        $game = $review->getGame();
        $error = $this->editor->validate($game);

        if ($error !== null) {
            return $error;
        }

        $version = $this->editor->publish($game)->getVersion();
        $this->reviews->save($review->close(GameReviewStatusEnum::APPROVED, $reviewer, null, $version), true);
        $this->answer($review, $reviewer, NotificationTypeEnum::STUDIO_APPROVED, ['version' => $version]);

        return null;
    }

    public function reject(GameReview $review, User $reviewer, string $comment): void
    {
        $this->reviews->save($review->close(GameReviewStatusEnum::REJECTED, $reviewer, mb_substr($comment, 0, 2000)), true);
        $this->answer($review, $reviewer, NotificationTypeEnum::STUDIO_REJECTED, []);
    }

    public function published(Game $game, User $reviewer, string $version): void
    {
        $review = $this->reviews->findPending($game);

        if ($review !== null) {
            $this->reviews->save($review->close(GameReviewStatusEnum::APPROVED, $reviewer, null, $version), true);
            $this->answer($review, $reviewer, NotificationTypeEnum::STUDIO_APPROVED, ['version' => $version]);
        }
    }

    public function leads(Game $game): array
    {
        return array_map(fn (GameMember $member): User => $member->getUser(), array_filter($this->members->findBy(['game' => $game]), fn (GameMember $member): bool => $member->isLead()));
    }

    protected function answer(GameReview $review, User $reviewer, NotificationTypeEnum $type, array $extra): void
    {
        $requester = $review->getRequester();

        if ($requester !== null && $requester->getId() !== $reviewer->getId()) {
            $this->notifications->notify($requester, $type, $this->data($review->getGame(), $reviewer) + $extra);
        }
    }

    protected function data(Game $game, User $user): array
    {
        return [
            'name' => $user->getUsername(),
            'game' => $game->getName(),
            'slug' => $game->getSlug(),
            'initials' => $user->getInitials(),
            'avatar' => $user->getAvatar(),
        ];
    }
}
