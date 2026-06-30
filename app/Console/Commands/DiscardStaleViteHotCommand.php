<?php

namespace App\Console\Commands;

use App\Support\ViteHotFileGuard;
use Illuminate\Console\Command;

class DiscardStaleViteHotCommand extends Command
{
    protected $signature = 'vite:discard-stale-hot';

    protected $description = 'Remove public/hot when the Vite dev server is not running';

    public function handle(ViteHotFileGuard $guard): int
    {
        if ($guard->discardStaleHotFileIfNeeded()) {
            $this->info('Removed stale public/hot — Laravel will use public/build assets.');

            return self::SUCCESS;
        }

        if ($guard->hotFileActive()) {
            $this->comment('Vite dev server is running — public/hot kept.');

            return self::SUCCESS;
        }

        $this->comment('No stale public/hot file.');

        return self::SUCCESS;
    }
}
