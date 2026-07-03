<?php

namespace App\Imports;

use App\Models\DurianVariety;
use App\Models\Outlet;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

abstract class BaseExcelImport implements ToModel, WithHeadingRow
{
    protected int $imported = 0;

    protected int $rowNumber = 1;

    protected array $errors = [];

    public function model(array $row): ?Model
    {
        $this->rowNumber++;
        $row = $this->normalizeRow($row);

        if ($this->isEmptyRow($row)) {
            return null;
        }

        try {
            $model = $this->makeModel($row);

            if ($model) {
                $this->imported++;
            }

            return $model;
        } catch (Throwable $exception) {
            $this->errors[] = "Baris {$this->rowNumber}: {$exception->getMessage()}";

            return null;
        }
    }

    abstract protected function makeModel(array $row): ?Model;

    public function importedCount(): int
    {
        return $this->imported;
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function summary(): string
    {
        $summary = "{$this->imported} baris berhasil diimpor.";

        if ($this->errors !== []) {
            $summary .= "\n{$this->errorCount()} baris dilewati.";
            $summary .= "\n" . implode("\n", array_slice($this->errors, 0, 5));
        }

        return $summary;
    }

    protected function value(array $row, array|string $keys, mixed $default = null): mixed
    {
        foreach ((array) $keys as $key) {
            $normalizedKey = $this->normalizeKey($key);

            if (array_key_exists($normalizedKey, $row) && $row[$normalizedKey] !== null && $row[$normalizedKey] !== '') {
                return $row[$normalizedKey];
            }
        }

        return $default;
    }

    protected function text(array $row, array|string $keys, ?string $default = null): ?string
    {
        $value = $this->value($row, $keys, $default);

        if ($value === null || $value === '') {
            return $default;
        }

        return trim((string) $value);
    }

    protected function number(array $row, array|string $keys, float|int|null $default = 0): float|int|null
    {
        $value = $this->value($row, $keys);

        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $value = strtolower(trim((string) $value));
        $value = str_replace(['rp', 'idr', 'kg', 'butir', 'btr', 'pack', 'pcs', ' '], '', $value);
        $value = preg_replace('/[^0-9,\.\-]/', '', $value) ?: '';

        if ($value === '' || $value === '-') {
            return $default;
        }

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $value = $lastComma > $lastDot
                ? str_replace('.', '', str_replace(',', '.', $value))
                : str_replace(',', '', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '.', $value);
        } elseif (preg_match('/^\d{1,3}\.\d{3}$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? $value + 0 : $default;
    }

    protected function integer(array $row, array|string $keys, int $default = 0): int
    {
        return (int) round((float) $this->number($row, $keys, $default));
    }

    protected function date(array $row, array|string $keys, ?string $default = null): string
    {
        $value = $this->value($row, $keys, $default);

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new \InvalidArgumentException('tanggal wajib diisi');
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y'] as $format) {
            $date = Carbon::createFromFormat($format, $value);

            if ($date !== false) {
                return $date->toDateString();
            }
        }

        return Carbon::parse($value)->toDateString();
    }

    protected function resolveOutletId(mixed $value, bool $required = true): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                throw new \InvalidArgumentException('outlet wajib diisi');
            }

            return null;
        }

        if (is_numeric($value) && Outlet::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        $needle = $this->normalizeLookup($value);

        foreach (Outlet::all() as $outlet) {
            $names = [$outlet->name, ...preg_split('/[\n,;|]+/', (string) $outlet->aliases)];

            foreach ($names as $name) {
                if ($this->normalizeLookup($name) === $needle) {
                    return $outlet->id;
                }
            }
        }

        throw new \InvalidArgumentException("outlet '{$value}' tidak ditemukan");
    }

    protected function resolveDurianVarietyId(mixed $value, bool $required = true): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            $singleVariety = DurianVariety::query()->count() === 1
                ? DurianVariety::query()->value('id')
                : null;

            if ($singleVariety) {
                return $singleVariety;
            }

            if ($required) {
                throw new \InvalidArgumentException('varian durian wajib diisi');
            }

            return null;
        }

        if (is_numeric($value) && DurianVariety::whereKey((int) $value)->exists()) {
            return (int) $value;
        }

        $needle = $this->normalizeLookup($value);

        $variety = DurianVariety::all()->first(function (DurianVariety $variety) use ($needle) {
            $name = $this->normalizeLookup($variety->name);

            return $name === $needle || str_contains($name, $needle) || str_contains($needle, $name);
        });

        if (! $variety) {
            throw new \InvalidArgumentException("varian '{$value}' tidak ditemukan");
        }

        return $variety->id;
    }

    protected function normalizeStatus(?string $status): string
    {
        $status = $this->normalizeLookup($status ?? '');

        return match (true) {
            str_contains($status, 'approve'), str_contains($status, 'diterima'), str_contains($status, 'selesai') => 'approved_by_supplier',
            str_contains($status, 'reject'), str_contains($status, 'tolak') => 'rejected_by_supplier',
            default => 'pending',
        };
    }

    protected function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalized[$this->normalizeKey($key)] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    protected function normalizeKey(string|int $key): string
    {
        $key = Str::ascii((string) $key);
        $key = strtolower($key);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?: '';

        return trim($key, '_');
    }

    protected function normalizeLookup(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii((string) $value))) ?: '';
    }

    protected function isEmptyRow(array $row): bool
    {
        return collect($row)
            ->filter(fn ($value) => $value !== null && trim((string) $value) !== '')
            ->isEmpty();
    }
}
