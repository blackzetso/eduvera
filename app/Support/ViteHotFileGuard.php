<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Drops public/hot when the Vite dev server is not running so Laravel
 * falls back to the production build in public/build.
 */
class ViteHotFileGuard
{
    public function discardStaleHotFileIfNeeded(): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        $hotPath = public_path('hot');

        if (! is_file($hotPath)) {
            return false;
        }

        $hotUrl = trim((string) file_get_contents($hotPath));

        if ($hotUrl !== '' && $this->devServerReachable($hotUrl)) {
            return false;
        }

        if (! @unlink($hotPath)) {
            return false;
        }

        Log::debug('Removed stale public/hot — Vite dev server unreachable; using public/build assets.');

        return true;
    }

    public function hotFileActive(): bool
    {
        $hotPath = public_path('hot');

        if (! is_file($hotPath)) {
            return false;
        }

        $hotUrl = trim((string) file_get_contents($hotPath));

        return $hotUrl !== '' && $this->devServerReachable($hotUrl);
    }

    protected function devServerReachable(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? '127.0.0.1';
        $port = (int) ($parts['port'] ?? 5173);

        foreach ($this->hostsToProbe($host) as $probeHost) {
            $socket = @fsockopen($probeHost, $port, $errno, $errstr, 0.25);

            if ($socket !== false) {
                fclose($socket);

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function hostsToProbe(string $host): array
    {
        $aliases = [
            'localhost' => ['localhost', '127.0.0.1', '[::1]'],
            '127.0.0.1' => ['127.0.0.1', 'localhost', '[::1]'],
            '[::1]' => ['[::1]', 'localhost', '127.0.0.1'],
        ];

        $candidates = $aliases[$host] ?? [$host, '127.0.0.1', 'localhost', '[::1]'];

        return array_values(array_unique($candidates));
    }
}
