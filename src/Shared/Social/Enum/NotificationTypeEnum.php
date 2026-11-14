<?php

namespace App\Shared\Social\Enum;

use App\Shared\Translation\EnumTranslation;

enum NotificationTypeEnum: string
{
    use EnumTranslation;

    case FRIEND_REQUEST = 'friend_request';
    case FRIEND_ACCEPTED = 'friend_accepted';
    case ROOM_INVITE = 'room_invite';
    case MESSAGE = 'message';
    case EMAIL_CHANGED = 'email_changed';
    case PASSWORD_CHANGED = 'password_changed';
    case GAME_RELEASED = 'game_released';
    case SEASON_STARTED = 'season_started';
    case MAINTENANCE_PLANNED = 'maintenance_planned';
    case STUDIO_REVIEW = 'studio_review';
    case STUDIO_APPROVED = 'studio_approved';
    case STUDIO_REJECTED = 'studio_rejected';
    case STUDIO_LEAD = 'studio_lead';
    case SHOP_PAID = 'shop_paid';
    case SHOP_SHIPPED = 'shop_shipped';
    case SHOP_REFUNDED = 'shop_refunded';
    case SEASON_REWARD = 'season_reward';
    case REFERRAL_REWARD = 'referral_reward';
    case TOURNAMENT_REWARD = 'tournament_reward';
    case TOURNAMENT_QUALIFIED = 'tournament_qualified';
}
