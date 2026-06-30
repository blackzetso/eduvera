<?php

namespace App\Modules\Canteen\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function toExcel(string $type, array $report): StreamedResponse
    {
        [$headers, $rows] = $this->tabularData($type, $report);
        $filename = sprintf('canteen-%s-report-%s.csv', $type, now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    protected function tabularData(string $type, array $report): array
    {
        return match ($type) {
            'sales' => [
                ['Date', 'Sale Number', 'Student', 'Cashier', 'Payment Method', 'Total', 'Status'],
                collect($report['rows'] ?? [])->map(fn ($r) => [
                    $r['date'], $r['sale_number'], $r['student'], $r['cashier'],
                    $r['payment_method'], $r['total'], $r['status'],
                ])->all(),
            ],
            'products' => [
                ['Product', 'Quantity Sold', 'Revenue', 'Cost', 'Profit'],
                collect($report['rows'] ?? [])->map(fn ($r) => [
                    $r['product'], $r['quantity_sold'], $r['revenue'], $r['cost'], $r['profit'],
                ])->all(),
            ],
            'inventory' => [
                ['Product', 'SKU', 'Category', 'Current Stock', 'Minimum Stock', 'Low Stock', 'Out of Stock'],
                collect($report['rows'] ?? [])->map(fn ($r) => [
                    $r['product'], $r['sku'], $r['category'], $r['current_stock'],
                    $r['minimum_stock'], $r['is_low_stock'] ? 'Yes' : 'No', $r['is_out_of_stock'] ? 'Yes' : 'No',
                ])->all(),
            ],
            'students' => [
                ['Student ID', 'Student', 'Grade', 'Total Purchases', 'Total Spent', 'Average Spend'],
                collect($report['rows'] ?? [])->map(fn ($r) => [
                    $r['student_id_ref'], $r['student'], $r['grade'],
                    $r['total_purchases'], $r['total_spent'], $r['average_spend'],
                ])->all(),
            ],
            'categories' => [
                ['Category', 'Quantity Sold', 'Revenue', '% of Total Sales'],
                collect($report['rows'] ?? [])->map(fn ($r) => [
                    $r['category'], $r['quantity_sold'], $r['revenue'], $r['percentage_of_total'],
                ])->all(),
            ],
            default => [[], []],
        };
    }
}
