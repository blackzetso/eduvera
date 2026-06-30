<?php

namespace App\Console\Commands;

use App\Services\Admission\AdmissionMigrationAuditService;
use Illuminate\Console\Command;

class AdmissionsMigrationReadinessReport extends Command
{
    protected $signature = 'admissions:migration-readiness-report
                            {--json : Output as JSON only}
                            {--save= : Save JSON report to storage path relative to storage/app}';

    protected $description = 'Generate Admissions Migration Readiness Report (no migration)';

    public function handle(AdmissionMigrationAuditService $audit): int
    {
        $report = $audit->report();

        if ($path = $this->option('save')) {
            $fullPath = storage_path('app/'.$path);
            $dir = dirname($fullPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($fullPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Report saved to storage/app/{$path}");
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->info('Admissions Migration Readiness Report');
        $this->line('Generated: '.$report['generated_at']);
        $this->line('Total records: '.$report['total_records']);
        $this->newLine();

        $this->table(
            ['Type', 'Count', '%'],
            collect($report['labels'])->map(fn ($label, $key) => [
                $label,
                $report['counts'][$key] ?? 0,
                ($report['percentages'][$key] ?? 0).'%',
            ])->values()->all(),
        );

        if (! empty($report['data_quality_issues'])) {
            $this->newLine();
            $this->warn('Data Quality Issues');
            foreach ($report['data_quality_issues'] as $issue => $count) {
                if ($count > 0) {
                    $this->line("  • {$issue}: {$count}");
                }
            }
        }

        if (! empty($report['migration_risks'])) {
            $this->newLine();
            $this->error('Migration Risks');
            foreach ($report['migration_risks'] as $risk) {
                $this->line("  • {$risk}");
            }
        }

        if (! empty($report['recommendations'])) {
            $this->newLine();
            $this->info('Recommendations');
            foreach ($report['recommendations'] as $rec) {
                $this->line("  • {$rec}");
            }
        }

        return self::SUCCESS;
    }
}
