<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class UpdateExchangeRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange-rate:update {--from=USD} {--to=EGP}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rate from API';

    protected ExchangeRateService $exchangeRateService;

    public function __construct(ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
        $this->exchangeRateService = $exchangeRateService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $from = $this->option('from');
        $to = $this->option('to');

        $this->info("Updating exchange rate: {$from} to {$to}...");

        $success = $this->exchangeRateService->updateRate($from, $to);

        if ($success) {
            $rate = $this->exchangeRateService->getRate($from, $to);
            $this->info("✓ Exchange rate updated successfully: 1 {$from} = {$rate} {$to}");
            return Command::SUCCESS;
        }

        $this->error("✗ Failed to update exchange rate. Check logs for details.");
        return Command::FAILURE;
    }
}
