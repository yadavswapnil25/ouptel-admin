<?php

return [
    'default_filesystem_disk' => env('FILAMENT_FILESYSTEM_DISK', 'public'),
    'default_avatar_provider' => \Filament\AvatarProviders\UiAvatarsProvider::class,
    'default_theme_mode' => 'light',
    'layout' => [
        'actions' => [
            'modal' => [
                'actions' => [
                    'alignment' => 'left',
                ],
            ],
        ],
        'forms' => [
            'actions' => [
                'alignment' => 'left',
            ],
            'have_inline_labels' => false,
        ],
        'footer' => [
            'should_show_logo' => true,
        ],
        'max_content_width' => null,
        'notifications' => [
            'vertical_alignment' => 'top',
            'alignment' => 'center',
        ],
        'sidebar' => [
            'is_collapsible_on_desktop' => true,
            'groups' => [
                'are_collapsible' => true,
            ],
            'navigation' => [
                'should_register_navigation_sort' => true,
            ],
        ],
        'tables' => [
            'actions' => [
                'alignment' => 'left',
            ],
            'bulk_actions' => [
                'alignment' => 'left',
            ],
        ],
    ],
    'dark_mode' => [
        'enabled' => true,
        'media_query' => '(prefers-color-scheme: dark)',
    ],
    'discover_resources' => [
        'enabled' => true,
        'paths' => [
            app_path('Filament/Resources'),
        ],
    ],
    'discover_widgets' => [
        'enabled' => true,
        'paths' => [
            app_path('Filament/Widgets'),
        ],
    ],
    'discover_pages' => [
        'enabled' => true,
        'paths' => [
            app_path('Filament/Pages'),
        ],
    ],
    'middleware' => [
        'auth' => [
            \Filament\Http\Middleware\Authenticate::class,
        ],
        'base' => [
            \Filament\Http\Middleware\DisableBladeIconComponents::class,
            \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
        ],
        'guest' => [
            \Filament\Http\Middleware\RedirectIfAuthenticated::class,
        ],
    ],
    'pages' => [
        'namespace' => 'App\\Filament\\Pages',
        'path' => app_path('Filament/Pages'),
        'register' => [],
    ],
    'resources' => [
        'namespace' => 'App\\Filament\\Resources',
        'path' => app_path('Filament/Resources'),
        'register' => [],
    ],
    'widgets' => [
        'namespace' => 'App\\Filament\\Widgets',
        'path' => app_path('Filament/Widgets'),
        'register' => [],
    ],
    'livewire' => [
        'loading_state_path' => null,
        'persistent_route_parameter' => false,
    ],
    'user_menu' => [
        'account' => false,
        'logout' => true,
        'profile' => false,
    ],
    'global_search' => [
        'enabled' => true,
        'include_models' => [],
        'exclude_models' => [],
    ],
    'tenant' => [
        'enabled' => false,
    ],
    'broadcasting' => [
        'enabled' => false,
    ],
    'navigation' => [
        'should_register_navigation_sort' => true,
    ],
    'unsaved_changes_alerts' => [
        'enabled' => true,
    ],
    'spa_mode' => [
        'enabled' => false,
    ],
    'render_hooks' => [
        'panels::auth.login.form.after' => [],
        'panels::content.start' => [],
        'panels::content.end' => [],
        'panels::footer' => [],
        'panels::global-search.after' => [],
        'panels::global-search.before' => [],
        'panels::head.end' => [],
        'panels::head.start' => [],
        'panels::notifications' => [],
        'panels::sidebar.nav.end' => [],
        'panels::sidebar.nav.start' => [],
        'panels::sidebar.user-menu.before' => [],
        'panels::sidebar.user-menu.after' => [],
        'panels::styles.after' => [],
        'panels::styles.before' => [],
        'panels::tenant-menu' => [],
        'panels::topbar.end' => [],
        'panels::topbar.start' => [],
        'panels::user-menu.account.before' => [],
        'panels::user-menu.account.after' => [],
        'panels::user-menu.logout.before' => [],
        'panels::user-menu.logout.after' => [],
        'panels::user-menu.profile.before' => [],
        'panels::user-menu.profile.after' => [],
        'panels::user-menu.start' => [],
        'panels::user-menu.end' => [],
    ],
];

