<?php

namespace App\Modules\Canteen\Console\Commands;

use App\Models\User;
use App\Models\UserWallet;
use App\Modules\Canteen\CanteenModule;
use App\Support\Student\StudentStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CanteenSeedWalletsCommand extends Command
{
    protected $signature = 'canteen:seed-wallets
                            {--amount=200 : Target balance (EGP) per student after seeding}
                            {--student-id=* : Only these users.id (repeatable)}
                            {--min-balance= : Top up only if current balance is below this amount}
                            {--dry-run : Preview without writing}';

    protected $description = 'Seed student user_wallets with test balance for canteen POS checkout';

    public function handle(): int
    {
        if (! CanteenModule::enabled()) {
            $this->error('Canteen module is disabled (CANTEEN_ENABLED=false).');

            return self::FAILURE;
        }

        $target = (float) $this->option('amount');
        $minBalance = $this->option('min-balance');
        $minBalance = $minBalance !== null && $minBalance !== '' ? (float) $minBalance : null;
        $dryRun = (bool) $this->option('dry-run');
        $studentIds = array_filter(array_map('intval', $this->option('student-id') ?? []));

        if ($target <= 0) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        $query = User::query()
            ->where('user_type', 'student')
            ->where('student_status', StudentStatus::ACTIVE)
            ->whereHas('currentStudentEnrollment')
            ->orderBy('id');

        if ($studentIds !== []) {
            $query->whereIn('id', $studentIds);
        }

        $students = $query->get(['id', 'name', 'student_code']);

        if ($students->isEmpty()) {
            $this->warn('No matching active enrolled students found.');

            return self::FAILURE;
        }

        $rows = [];
        $credited = 0;
        $skipped = 0;

        foreach ($students->chunk(100) as $chunk) {
            DB::transaction(function () use ($chunk, $target, $minBalance, $dryRun, &$rows, &$credited, &$skipped) {
                foreach ($chunk as $student) {
                    $wallet = UserWallet::query()->firstOrCreate(
                        ['user_id' => $student->id],
                        ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0],
                    );

                    $current = (float) $wallet->balance;
                    $threshold = $minBalance ?? $target;

                    if ($current >= $threshold) {
                        $skipped++;
                        $rows[] = [
                            $student->id,
                            $student->name,
                            $student->student_code ?? '—',
                            number_format($current, 2, '.', ''),
                            '0.00',
                            number_format($current, 2, '.', ''),
                            'skipped',
                        ];

                        continue;
                    }

                    $topUp = round($target - $current, 2);

                    if (! $dryRun) {
                        $wallet->credit($topUp, 'Canteen test wallet seed');
                    }

                    $credited++;
                    $rows[] = [
                        $student->id,
                        $student->name,
                        $student->student_code ?? '—',
                        number_format($current, 2, '.', ''),
                        number_format($topUp, 2, '.', ''),
                        number_format($current + $topUp, 2, '.', ''),
                        $dryRun ? 'dry-run' : 'credited',
                    ];
                }
            });
        }

        $this->info('Canteen wallet seed '.($dryRun ? '(dry run) ' : '').'completed.');
        $this->line('Wallet adapter: '.config('canteen.integration.wallet_adapter')
            .' — set CANTEEN_WALLET_ADAPTER=user_wallet for real POS debits.');
        $this->newLine();
        $this->table(
            ['ID', 'Name', 'Code', 'Before', 'Top-up', 'After', 'Status'],
            $rows,
        );
        $this->line("Credited: {$credited} | Skipped (already sufficient): {$skipped}");

        return self::SUCCESS;
    }
}
