<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class AuditObserver
{
    private const IGNORED_FIELDS = [
        'created_at',
        'updated_at',
        'remember_token',
        'password',
        'api_key',
        'token',
    ];

    private const MODULE_NAMES = [
        \App\Models\DurianVariety::class => 'Durian Varieties',
        \App\Models\Expense::class => 'Expenses',
        \App\Models\InventoryItem::class => 'Master Produk',
        \App\Models\Outlet::class => 'Outlets',
        \App\Models\ProductConversion::class => 'Product Conversions',
        \App\Models\ProductReturn::class => 'Product Returns',
        \App\Models\Production::class => 'Productions',
        \App\Models\Purchase::class => 'Purchases',
        \App\Models\Sale::class => 'Sales',
        \App\Models\SalesTarget::class => 'Sales Targets',
        \App\Models\Shipment::class => 'Shipments',
        \App\Models\StockOpname::class => 'Stock Opnames',
        \App\Models\WhatsappReport::class => 'WA Draft Reports',
        \App\Models\User::class => 'Users',
    ];

    public function created(Model $model): void
    {
        $this->log($model, 'created', [], $this->clean($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $newValues = $this->clean($model->getChanges());

        if ($newValues === []) {
            return;
        }

        $oldValues = [];

        foreach (array_keys($newValues) as $field) {
            $oldValues[$field] = $model->getOriginal($field);
        }

        $event = $this->hasStatusChange($newValues) ? 'status_changed' : 'updated';

        $this->log($model, $event, $this->clean($oldValues), $newValues);
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', $this->clean($model->getOriginal()), []);
    }

    private function log(Model $model, string $event, array $oldValues, array $newValues): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => self::MODULE_NAMES[$model::class] ?? class_basename($model),
                'event' => $event,
                'auditable_type' => $model::class,
                'auditable_id' => $model->getKey(),
                'summary' => $this->summary($model, $event, $oldValues, $newValues),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'ip_address' => app()->runningInConsole() ? null : request()?->ip(),
                'user_agent' => app()->runningInConsole() ? null : request()?->userAgent(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function clean(array $values): array
    {
        return collect($values)
            ->reject(fn ($value, string $key) => in_array($key, self::IGNORED_FIELDS, true))
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_UNICODE))
            ->all();
    }

    private function hasStatusChange(array $values): bool
    {
        return Arr::hasAny($values, [
            'status',
            'approval_status',
            'approved_at',
            'approved_by',
            'supplier_accepted_qty_kg',
            'refund_amount',
        ]);
    }

    private function summary(Model $model, string $event, array $oldValues, array $newValues): string
    {
        $module = self::MODULE_NAMES[$model::class] ?? class_basename($model);
        $label = $this->recordLabel($model);

        if ($event === 'status_changed') {
            $statusText = '';

            if (array_key_exists('status', $newValues)) {
                $statusText = " dari '{$oldValues['status']}' ke '{$newValues['status']}'";
            }

            return "Mengubah status {$module} {$label}{$statusText}";
        }

        return match ($event) {
            'created' => "Membuat {$module} {$label}",
            'updated' => "Mengubah {$module} {$label}",
            'deleted' => "Menghapus {$module} {$label}",
            default => Str::headline($event) . " {$module} {$label}",
        };
    }

    private function recordLabel(Model $model): string
    {
        foreach (['name', 'title', 'supplier_name', 'category', 'date'] as $field) {
            if ($model->getAttribute($field)) {
                return '#' . $model->getKey() . ' (' . $model->getAttribute($field) . ')';
            }
        }

        return '#' . $model->getKey();
    }
}
