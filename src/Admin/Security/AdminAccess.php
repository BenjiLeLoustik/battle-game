<?php

namespace App\Admin\Security;

use App\Entity\User;

class AdminAccess
{
    public const PERMISSIONS = [
        'steering' => ['dash.view', 'games.install', 'games.state'],
        'studio' => ['studio.view', 'studio.create', 'studio.edit', 'studio.publish', 'studio.team'],
        'community' => ['users.view', 'users.edit', 'users.suspend', 'users.delete', 'rooms.view', 'rooms.close', 'chat.manage'],
        'moderation' => ['mod.view', 'mod.sanction', 'mod.words'],
        'shop' => ['shop.view', 'shop.manage', 'shop.orders'],
        'engage' => ['engage.manage'],
        'system' => ['sys.general', 'sys.pages', 'sys.maintenance', 'sys.langs', 'sys.mercure', 'sys.emails', 'sys.admins', 'sys.reset'],
    ];

    public const SECTIONS = [
        'dashboard' => ['admin_dashboard_index', 'dash.view', '/admin/dashboard'],
        'games' => ['admin_game_index', 'games.install', '/admin/games'],
        'categories' => ['admin_category_index', 'games.state', '/admin/categories'],
        'studio' => ['admin_studio_index', 'studio.view', '/admin/studio'],
        'rooms' => ['admin_room_index', 'rooms.view', '/admin/rooms'],
        'banners' => ['admin_banner_index', 'sys.general', '/admin/banners'],
        'badges' => ['admin_badge_index', 'games.state', '/admin/badges'],
        'shop_products' => ['admin_shop_products', 'shop.manage', '/admin/shop/products'],
        'shop_orders' => ['admin_shop_orders', 'shop.orders', '/admin/shop/orders'],
        'shop_vouchers' => ['admin_shop_vouchers', 'shop.manage', '/admin/shop/vouchers'],
        'shop_subscriptions' => ['admin_shop_subscriptions', 'shop.view', '/admin/shop/subscriptions'],
        'shop_settings' => ['admin_shop_settings', 'shop.manage', '/admin/shop/settings'],
        'shop_coins' => ['admin_shop_settings', 'shop.manage', '/admin/shop/coins'],
        'shop_players' => ['admin_shop_settings', 'shop.manage', '/admin/shop/players'],
        'engage_seasons' => ['admin_engage_seasons', 'engage.manage', '/admin/engage/seasons'],
        'engage_ranks' => ['admin_engage_ranks', 'engage.manage', '/admin/engage/ranks'],
        'engage_events' => ['admin_engage_events', 'engage.manage', '/admin/engage/events'],
        'engage_pass' => ['admin_engage_pass', 'engage.manage', '/admin/engage/pass'],
        'engage_tournaments' => ['admin_engage_tournaments', 'engage.manage', '/admin/engage/tournaments'],
        'engage_clans' => ['admin_engage_clans', 'engage.manage', '/admin/engage/clans'],
        'users' => ['admin_user_index', 'users.view', '/admin/users'],
        'channels' => ['admin_channel_index', 'chat.manage', '/admin/channels'],
        'moderation' => ['admin_moderation_index', 'mod.view', '/admin/moderation'],
        'general' => ['admin_system_index', 'sys.general', '/admin/system'],
        'pages' => ['admin_page_index', 'sys.pages', '/admin/pages'],
        'maintenance' => ['admin_maintenance_index', 'sys.maintenance', '/admin/maintenance'],
        'languages' => ['admin_language_index', 'sys.langs', '/admin/languages'],
        'levels' => ['admin_level_index', 'engagne.manage', 'admin/levels'],
        'emails' => ['admin_email_index', 'sys.emails', '/admin/emails'],
        'realtime' => ['admin_mercure_index', 'sys.mercure', '/admin/realtime'],
        'admins' => ['admin_admin_index', 'sys.admins', '/admin/admins'],
        'roles' => ['admin_role_index', 'sys.admins', '/admin/roles'],
        'reset' => ['admin_reset_index', 'sys.reset', '/admin/reset'],
    ];

    public static function all(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    public function can(?User $user, string $permission): bool
    {
        if ($user === null || !$user->isAdmin()) {
            return false;
        }

        $role = $user->getAdminRole();

        return $role === null || $role->allows($permission);
    }

    public function sections(?User $user): array
    {
        return array_filter(self::SECTIONS, fn (array $section): bool => $this->can($user, $section[1]));
    }

    public function permissionFor(string $path): ?string
    {
        foreach (self::SECTIONS as [, $permission, $prefix]) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $permission;
            }
        }

        return null;
    }

    public function home(?User $user): ?string
    {
        return array_values($this->sections($user))[0][0] ?? null;
    }
}
