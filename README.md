# Gunsas Tenant

Backoffice Laravel + Filament untuk operasional tenant durian Gunsas.

## Tujuan Aplikasi

Aplikasi ini dipakai untuk mencatat dan memantau alur bisnis durian dari pembelian supplier sampai laporan laba rugi outlet. Fokus utamanya adalah:

- Pembelian durian dari supplier.
- Distribusi/pengiriman buah ke outlet.
- Produksi buah utuh menjadi kupas fresh dan olahan/reject.
- Konversi kupas fresh menjadi durpas frozen.
- Penjualan buah utuh, kupas fresh, dan durpas frozen.
- Retur buah rusak ke supplier beserta refund dan losses.
- Stock opname fisik outlet dibandingkan stok sistem.
- Biaya operasional outlet atau global.
- Dashboard stok, omset, HPP, losses, inventory, dan laba bersih.

## Teknologi

- PHP 8.2+
- Laravel 12
- Filament 3
- SQLite/MySQL sesuai konfigurasi `.env`
- Vite + Tailwind

## Modul Utama

- `PurchaseResource`: nota pembelian dari supplier.
- `ShipmentResource`: pengiriman durian ke outlet.
- `ProductionResource`: proses kupas dan perhitungan yield.
- `ProductConversionResource`: pemindahan fresh ke frozen.
- `SaleResource`: pencatatan penjualan per kategori produk.
- `ProductReturnResource`: klaim retur supplier dan refund.
- `StockOpnameResource`: audit stok sistem vs fisik.
- `ExpenseResource`: beban operasional.
- `Dashboard`: ringkasan operasional dan finansial.

## Akses

Panel admin Filament tersedia di:

```text
/admin
```

Route publik yang tersedia:

```text
/
/test-ai
```

`/test-ai` memakai `App\Services\BotIntelligence` untuk uji parsing pesan retur sederhana.

## Setup Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Jalankan server:

```bash
php artisan serve
```

## Catatan Pengembangan

- Seeder default hanya membuat user contoh `test@example.com`.
- Beberapa migration retur merupakan tambahan bertahap untuk field supplier, status klaim, dan refund.
- Perubahan angka bisnis sebaiknya selalu dicek di dashboard dan resource terkait karena beberapa metrik saling bergantung.
