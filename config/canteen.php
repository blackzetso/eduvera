<?php

return [

    'enabled' => env('CANTEEN_ENABLED', false),

    'module' => [
        'id' => 'canteen',
        'name' => 'Canteen',
        'name_ar' => 'الكافتيريا',
        'version' => '1.0.0',
        'route_prefix' => 'canteen',
        'namespace' => 'canteen',
    ],

    'defaults' => [
        'currency' => 'EGP',
        'daily_spending_limit' => null,
        'low_stock_threshold' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Integration adapters (switch via .env without code changes)
    |--------------------------------------------------------------------------
    |
    | student: core = EDUVERA User + enrollment | local = canteen_student_profiles
    | wallet:  pending = local queue (default) | user_wallet = UserWallet debit
    | guardian: core = EDUVERA guardian_student pivot | local = profile metadata snapshot
    | parent:  queued = local visibility queue | eduvera = EDUVERA notifications
    | finance: noop = no ledger writes (default) | eduvera = student/family finance entries
    |
    */
    'integration' => [
        'student_adapter' => env('CANTEEN_STUDENT_ADAPTER', 'core'),
        'wallet_adapter' => env('CANTEEN_WALLET_ADAPTER', 'pending'),
        'guardian_adapter' => env('CANTEEN_GUARDIAN_ADAPTER', 'core'),
        'parent_adapter' => env('CANTEEN_PARENT_ADAPTER', 'queued'),
        'finance_adapter' => env('CANTEEN_FINANCE_ADAPTER', 'noop'),
    ],

    'notifications' => [
        'whatsapp_enabled' => env('CANTEEN_NOTIFY_WHATSAPP', true),
        'admin_failures_enabled' => env('CANTEEN_NOTIFY_ADMIN_FAILURES', true),
        'admin_email' => env('CANTEEN_NOTIFY_ADMIN_EMAIL'),
    ],

    'guardian' => [
        'require_linked_guardian' => env('CANTEEN_REQUIRE_LINKED_GUARDIAN', false),
        'default_household_daily_limit' => env('CANTEEN_GUARDIAN_HOUSEHOLD_DAILY_LIMIT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Full sync (php artisan canteen:full-sync)
    |--------------------------------------------------------------------------
    |
    | staff: array of ['user_id' => 1, 'role' => 'manager'] or ['email' => '...', 'role' => 'cashier']
    | staff_env: comma-separated "identifier:role" pairs (same format as --staff option)
    |
    */
    'full_sync' => [
        'staff' => [],
        'staff_env' => env('CANTEEN_FULL_SYNC_STAFF', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teacher staff registration (php artisan canteen:register-teachers)
    |--------------------------------------------------------------------------
    |
    | default_role: fallback when no mapping matches the teacher id/email
    | roles_env: optional comma-separated identifier:role pairs (same as CANTEEN_FULL_SYNC_STAFF)
    |
    */
    'teacher_staff' => [
        'default_role' => env('CANTEEN_TEACHER_DEFAULT_ROLE', 'cashier'),
        'roles_env' => env('CANTEEN_TEACHER_STAFF_ROLES', ''),
        'manager_user_id' => env('CANTEEN_MANAGER_USER_ID'),
        'manager_email' => env('CANTEEN_MANAGER_EMAIL'),
    ],

    'adapters' => [
        'student' => [
            'core' => \App\Modules\Canteen\Integration\Adapters\CoreStudentIdentityAdapter::class,
            'local' => \App\Modules\Canteen\Integration\Adapters\LocalSnapshotStudentAdapter::class,
        ],
        'wallet' => [
            'pending' => \App\Modules\Canteen\Integration\Adapters\PendingWalletSettlementAdapter::class,
            'user_wallet' => \App\Modules\Canteen\Integration\Adapters\UserWalletSettlementAdapter::class,
        ],
        'guardian' => [
            'core' => \App\Modules\Canteen\Integration\Adapters\CoreGuardianIntegrationAdapter::class,
            'local' => \App\Modules\Canteen\Integration\Adapters\LocalSnapshotGuardianAdapter::class,
        ],
        'parent' => [
            'queued' => \App\Modules\Canteen\Integration\Adapters\QueuedParentNotificationAdapter::class,
            'eduvera' => \App\Modules\Canteen\Integration\Adapters\EduveraParentNotificationAdapter::class,
        ],
        'finance' => [
            'noop' => \App\Modules\Canteen\Integration\Adapters\NoopFinanceIntegrationAdapter::class,
            'eduvera' => \App\Modules\Canteen\Integration\Adapters\EduveraFinanceIntegrationAdapter::class,
        ],
    ],

    'roles' => [
        'manager' => [
            'canteen.dashboard.view',
            'canteen.pos.access',
            'canteen.products.view',
            'canteen.products.manage',
            'canteen.categories.manage',
            'canteen.inventory.view',
            'canteen.inventory.manage',
            'canteen.transactions.view',
            'canteen.transactions.void',
            'canteen.student-limits.manage',
            'canteen.student-limits.override',
            'canteen.reports.view',
            'canteen.reports.export',
            'canteen.audit.view',
        ],
        'cashier' => [
            'canteen.dashboard.view',
            'canteen.pos.access',
            'canteen.products.view',
            'canteen.transactions.view',
        ],
        'parent' => [
            'canteen.parent.transactions.view',
            'canteen.parent.limits.manage',
        ],
    ],

    'permissions' => [
        'canteen.dashboard.view',
        'canteen.pos.access',
        'canteen.products.view',
        'canteen.products.manage',
        'canteen.categories.manage',
        'canteen.inventory.view',
        'canteen.inventory.manage',
        'canteen.transactions.view',
        'canteen.transactions.void',
        'canteen.student-limits.manage',
        'canteen.student-limits.override',
        'canteen.reports.view',
        'canteen.reports.export',
        'canteen.audit.view',
        'canteen.settings.manage',
        'canteen.staff.manage',
        'canteen.parent.transactions.view',
        'canteen.parent.limits.manage',
    ],

];
