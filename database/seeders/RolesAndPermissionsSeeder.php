<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Permissions
        $permissions = [
            // Users Group
            ['key' => 'manage-users', 'label' => 'Manage Users', 'group' => 'Users'],
            ['key' => 'manage-online-users', 'label' => 'Online Users', 'group' => 'Users'],
            ['key' => 'manage-invitations', 'label' => 'User Invitations', 'group' => 'Users'],
            ['key' => 'manage-profile-fields', 'label' => 'Profile Fields', 'group' => 'Users'],
            ['key' => 'manage-verification-requests', 'label' => 'Verification Requests', 'group' => 'Users'],

            // Access Control Group
            ['key' => 'manage-roles', 'label' => 'Manage Roles & Permissions', 'group' => 'Access Control'],

            // Manage Features Group
            ['key' => 'manage-articles', 'label' => 'Articles', 'group' => 'Manage Features'],
            ['key' => 'manage-blog-categories', 'label' => 'Blog Categories', 'group' => 'Manage Features'],
            ['key' => 'manage-events', 'label' => 'Events', 'group' => 'Manage Features'],
            ['key' => 'manage-forums', 'label' => 'Forums', 'group' => 'Manage Features'],
            ['key' => 'manage-forum-sections', 'label' => 'Forum Sections', 'group' => 'Manage Features'],
            ['key' => 'manage-funding', 'label' => 'Funding', 'group' => 'Manage Features'],
            ['key' => 'manage-posts', 'label' => 'Posts', 'group' => 'Manage Features'],
            ['key' => 'manage-post-reactions', 'label' => 'Post Reactions', 'group' => 'Manage Features'],
            ['key' => 'manage-user-stories', 'label' => 'User Stories', 'group' => 'Manage Features'],

            // Groups
            ['key' => 'manage-groups', 'label' => 'Groups', 'group' => 'Groups'],
            ['key' => 'manage-group-categories', 'label' => 'Group Categories', 'group' => 'Groups'],
            ['key' => 'manage-group-sub-categories', 'label' => 'Group Sub Topics', 'group' => 'Groups'],
            ['key' => 'manage-group-fields', 'label' => 'Group Fields', 'group' => 'Groups'],

            // Pages
            ['key' => 'manage-pages', 'label' => 'Pages', 'group' => 'Pages'],
            ['key' => 'manage-page-categories', 'label' => 'Page Categories', 'group' => 'Pages'],
            ['key' => 'manage-page-sub-categories', 'label' => 'Page Sub Categories', 'group' => 'Pages'],
            ['key' => 'manage-page-fields', 'label' => 'Page Fields', 'group' => 'Pages'],

            // Marketplace / Products
            ['key' => 'manage-products', 'label' => 'Products', 'group' => 'Marketplace'],
            ['key' => 'manage-product-categories', 'label' => 'Product Categories', 'group' => 'Marketplace'],
            ['key' => 'manage-product-sub-categories', 'label' => 'Product Sub Categories', 'group' => 'Marketplace'],
            ['key' => 'manage-product-fields', 'label' => 'Product Fields', 'group' => 'Marketplace'],
            ['key' => 'manage-reviews', 'label' => 'Product Reviews', 'group' => 'Marketplace'],
            ['key' => 'manage-orders', 'label' => 'Orders', 'group' => 'Marketplace'],

            // Jobs
            ['key' => 'manage-jobs', 'label' => 'Jobs', 'group' => 'Jobs'],
            ['key' => 'manage-job-categories', 'label' => 'Job Categories', 'group' => 'Jobs'],

            // Entertainment
            ['key' => 'manage-games', 'label' => 'Games', 'group' => 'Entertainment'],
            ['key' => 'manage-game-players', 'label' => 'Game Players', 'group' => 'Entertainment'],

            // Settings / Languages
            ['key' => 'manage-languages', 'label' => 'Languages', 'group' => 'Settings'],
            ['key' => 'manage-language-keys', 'label' => 'Language Keys', 'group' => 'Settings'],
        ];

        foreach ($permissions as $permission) {
            AdminPermission::firstOrCreate(
                ['key' => $permission['key']],
                ['label' => $permission['label'], 'group' => $permission['group']]
            );
        }

        // 2. Create Default Roles

        // Super Admin Role
        AdminRole::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'description' => 'Has access to everything. Can manage other admins.',
                'is_super_admin' => true,
            ]
        );

        // Moderator Role
        $moderator = AdminRole::firstOrCreate(
            ['name' => 'Moderator'],
            [
                'description' => 'Can manage content like posts, comments, reports, and users.',
                'is_super_admin' => false,
            ]
        );

        // Assign common moderator permissions
        $moderatorPermissions = AdminPermission::whereIn('key', [
            'manage-users',
            'manage-posts',
            'manage-articles',
            'manage-reports', // If report resource exists/added later
            'manage-verification-requests',
            'manage-user-stories',
            'manage-reviews'
        ])->get();
        
        $moderator->permissions()->syncWithoutDetaching($moderatorPermissions);

        // Editor Role
        $editor = AdminRole::firstOrCreate(
            ['name' => 'Editor'],
            [
                'description' => 'Can manage articles, blog categories, and content.',
                'is_super_admin' => false,
            ]
        );

        // Assign editor permissions
        $editorPermissions = AdminPermission::whereIn('key', [
            'manage-articles',
            'manage-blog-categories',
            'manage-pages',
            'manage-groups',
            'manage-events',
        ])->get();

        $editor->permissions()->syncWithoutDetaching($editorPermissions);
    }
}
