<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Csv\Writer;

class ExportReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;
    public $backoff = [120]; // Retry after 2 minutes

    public function __construct(
        public string $reportType,
        public int $companyId,
        public ?string $filename = null,
    ) {}

    public function handle(): void
    {
        $filename = $this->filename ?? "report-{$this->reportType}-".now()->format('YmdHis').'.csv';
        $path = storage_path("app/exports/{$filename}");

        // Ensure directory exists
        \File::ensureDirectoryExists(dirname($path));

        // Dispatch based on report type
        match ($this->reportType) {
            'stock' => $this->exportStock($path),
            'receivables' => $this->exportReceivables($path),
            'payables' => $this->exportPayables($path),
            default => throw new \Exception("Unknown report type: {$this->reportType}"),
        };

        \Log::info('Report exported', ['type' => $this->reportType, 'path' => $path]);
    }

    private function exportStock(string $path): void
    {
        $csv = Writer::createFromPath($path, 'w');
        $csv->insertOne(['SKU', 'Name', 'Unit', 'Quantity', 'Cost', 'Total Value']);

        $items = \App\Models\Item::whereCompanyId($this->companyId)
            ->whereActive(true)
            ->get();

        foreach ($items as $item) {
            $csv->insertOne([
                $item->sku,
                $item->name,
                $item->unit,
                $item->quantity,
                $item->cost,
                round($item->quantity * $item->cost, 2),
            ]);
        }
    }

    private function exportReceivables(string $path): void
    {
        $csv = Writer::createFromPath($path, 'w');
        $csv->insertOne(['Customer', 'Invoice #', 'Date', 'Due Date', 'Total', 'Open', 'Days Overdue']);

        $invoices = \App\Models\Invoice::whereCompanyId($this->companyId)
            ->where('status', 'open')
            ->with('customer')
            ->get();

        foreach ($invoices as $invoice) {
            $daysOverdue = max(0, today()->diffInDays($invoice->due_date ?? $invoice->invoice_date));
            $csv->insertOne([
                $invoice->customer->name,
                $invoice->number,
                $invoice->invoice_date->format('Y-m-d'),
                $invoice->due_date?->format('Y-m-d') ?? '',
                $invoice->total,
                $invoice->open_balance ?? ($invoice->total - $invoice->amount_received),
                $daysOverdue,
            ]);
        }
    }

    private function exportPayables(string $path): void
    {
        $csv = Writer::createFromPath($path, 'w');
        $csv->insertOne(['Vendor', 'Bill #', 'Date', 'Due Date', 'Total', 'Open', 'Days Overdue']);

        $bills = \App\Models\VendorBill::whereCompanyId($this->companyId)
            ->where('status', 'open')
            ->with('vendor')
            ->get();

        foreach ($bills as $bill) {
            $daysOverdue = max(0, today()->diffInDays($bill->due_date ?? $bill->bill_date));
            $csv->insertOne([
                $bill->vendor->name,
                $bill->number,
                $bill->bill_date->format('Y-m-d'),
                $bill->due_date?->format('Y-m-d') ?? '',
                $bill->total,
                $bill->open_balance ?? ($bill->total - $bill->amount_paid),
                $daysOverdue,
            ]);
        }
    }
}
