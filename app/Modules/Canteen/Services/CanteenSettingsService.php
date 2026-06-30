<?php

namespace App\Modules\Canteen\Services;

use App\Models\User;
use App\Modules\Canteen\Models\Setting;
use App\Modules\Canteen\Support\CanteenSettingKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CanteenSettingsService
{
    public const CACHE_KEY = 'canteen.settings.runtime';

    /**
     * @var array<string, string>
     */
    private const CONFIG_FALLBACKS = [
        CanteenSettingKeys::DEFAULT_DAILY_LIMIT => 'canteen.defaults.daily_spending_limit',
        CanteenSettingKeys::LOW_STOCK_THRESHOLD => 'canteen.defaults.low_stock_threshold',
        CanteenSettingKeys::CURRENCY => 'canteen.defaults.currency',
        CanteenSettingKeys::MANAGER_USER_ID => 'canteen.teacher_staff.manager_user_id',
    ];

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => $this->loadFromDatabase());
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            $value = $this->normalizeValue($settings[$key]);

            return $value !== null ? $value : $default;
        }

        if ($default !== null) {
            return $default;
        }

        $configKey = self::CONFIG_FALLBACKS[$key] ?? null;

        return $configKey !== null ? config($configKey) : null;
    }

    public function defaultDailyLimit(): ?string
    {
        $value = $this->get(CanteenSettingKeys::DEFAULT_DAILY_LIMIT);

        return $value !== null ? (string) $value : null;
    }

    public function lowStockThreshold(): float
    {
        return (float) $this->get(
            CanteenSettingKeys::LOW_STOCK_THRESHOLD,
            config('canteen.defaults.low_stock_threshold', 10),
        );
    }

    public function currency(): string
    {
        return (string) $this->get(
            CanteenSettingKeys::CURRENCY,
            config('canteen.defaults.currency', 'EGP'),
        );
    }

    public function managerUserId(): ?int
    {
        $value = $this->get(CanteenSettingKeys::MANAGER_USER_ID);

        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $email = config('canteen.teacher_staff.manager_email');

        if (! is_string($email) || trim($email) === '') {
            return null;
        }

        $userId = User::query()
            ->where('email', trim($email))
            ->value('id');

        return $userId ? (int) $userId : null;
    }

    public function persistManagerUserId(int $userId): void
    {
        $this->setMany([CanteenSettingKeys::MANAGER_USER_ID => $userId]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                if ($value === null) {
                    continue;
                }

                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        $this->clearCache();
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadFromDatabase(): array
    {
        $settings = [];

        foreach (Setting::query()->get(['key', 'value']) as $row) {
            $settings[$row->key] = $this->decodeStoredValue($row->getRawOriginal('value'));
        }

        return $settings;
    }

    protected function decodeStoredValue(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            return json_decode($raw, true);
        }

        return $raw;
    }

    protected function normalizeValue(mixed $value): mixed
    {
        if (is_array($value) && array_is_list($value) && count($value) === 1) {
            return $value[0];
        }

        return $value;
    }
}
