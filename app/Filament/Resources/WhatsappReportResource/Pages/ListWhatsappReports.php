<?php

namespace App\Filament\Resources\WhatsappReportResource\Pages;

use App\Filament\Resources\Concerns\HasListSummaryHeader;
use App\Filament\Resources\WhatsappReportResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListWhatsappReports extends ListRecords
{
    use HasListSummaryHeader;

    protected static string $resource = WhatsappReportResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn () => $this->summaryHeader($this->getWhatsappReportSummaryItems()));
    }

    protected function getWhatsappReportSummaryItems(): array
    {
        $row = $this->filteredSummaryQuery()
            ->selectRaw("
                COUNT(*) as total_records,
                COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END), 0) as pending_count,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) as approved_count,
                COALESCE(SUM(CASE WHEN status = 'needs_review' THEN 1 ELSE 0 END), 0) as review_count,
                COALESCE(AVG(confidence), 0) as avg_confidence
            ")
            ->first();

        return [
            ['label' => 'Total Draft', 'value' => $this->whole((float) ($row->total_records ?? 0), 'draft')],
            ['label' => 'Pending Approval', 'value' => $this->whole((float) ($row->pending_count ?? 0), 'draft')],
            ['label' => 'Approved', 'value' => $this->whole((float) ($row->approved_count ?? 0), 'draft')],
            ['label' => 'Needs Review', 'value' => $this->whole((float) ($row->review_count ?? 0), 'draft')],
            ['label' => 'Avg Confidence', 'value' => $this->percent((float) ($row->avg_confidence ?? 0))],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
