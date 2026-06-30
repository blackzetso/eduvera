<?php

namespace App\Modules\Canteen\Support;

class CanteenMenuRegistry
{
    public static function menu(): array
    {
        return [
            'namespace' => config('canteen.module.namespace', 'canteen'),
            'label' => config('canteen.module.name', 'Canteen'),
            'label_ar' => config('canteen.module.name_ar', 'الكافتيريا'),
            'icon' => 'bi-shop',
            'base_url' => '/'.config('canteen.module.route_prefix', 'canteen'),
            'items' => [
                ['route' => 'canteen.dashboard', 'label' => 'Dashboard', 'label_ar' => 'لوحة التحكم', 'icon' => 'bi-speedometer2', 'permission' => 'canteen.dashboard.view'],
                ['route' => 'canteen.pos', 'label' => 'POS', 'label_ar' => 'نقطة البيع', 'icon' => 'bi-cart3', 'permission' => 'canteen.pos.access'],
                ['route' => 'canteen.products.index', 'label' => 'Products', 'label_ar' => 'المنتجات', 'icon' => 'bi-box-seam', 'permission' => 'canteen.products.view'],
                ['route' => 'canteen.categories.index', 'label' => 'Categories', 'label_ar' => 'التصنيفات', 'icon' => 'bi-tags', 'permission' => 'canteen.categories.manage'],
                ['route' => 'canteen.inventory.index', 'label' => 'Inventory', 'label_ar' => 'المخزون', 'icon' => 'bi-boxes', 'permission' => 'canteen.inventory.view'],
                ['route' => 'canteen.transactions.index', 'label' => 'Transactions', 'label_ar' => 'المعاملات', 'icon' => 'bi-receipt', 'permission' => 'canteen.transactions.view'],
                ['route' => 'canteen.student-limits.index', 'label' => 'Student Limits', 'label_ar' => 'حدود الطلاب', 'icon' => 'bi-person-lines-fill', 'permission' => 'canteen.student-limits.manage'],
                ['route' => 'canteen.reports.index', 'label' => 'Reports', 'label_ar' => 'التقارير', 'icon' => 'bi-bar-chart-line', 'permission' => 'canteen.reports.view'],
                ['route' => 'canteen.audit.index', 'label' => 'Audit Log', 'label_ar' => 'سجل التدقيق', 'icon' => 'bi-journal-text', 'permission' => 'canteen.audit.view'],
                ['route' => 'canteen.settings.index', 'label' => 'Settings', 'label_ar' => 'الإعدادات', 'icon' => 'bi-gear', 'permission' => 'canteen.settings.manage'],
            ],
        ];
    }
}
