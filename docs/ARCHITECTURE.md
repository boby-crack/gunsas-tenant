# Arsitektur Aplikasi Gunsas

Dokumen ini menjelaskan susunan teknis aplikasi Gunsas Tenant. Tujuannya agar pengembangan berikutnya tetap mengikuti pola yang sama dan tidak membuat rumus bisnis tersebar di banyak tempat.

## Ringkasan

Aplikasi ini adalah sistem operasional dan analitik bisnis untuk Gunsas. Sistem mencatat pembelian durian dan produk inventory, pengiriman ke outlet, penjualan, produksi, retur supplier, stock opname, expense, laporan WhatsApp, insight bisnis, laporan PDF, dan AI read-only untuk analisis.

Stack utama:

- Backend: Laravel 12
- Admin panel: Filament
- Interaksi real-time UI: Livewire
- Database: MySQL/MariaDB
- Import/export Excel: class import/export di `app/Imports` dan `app/Exports`
- WhatsApp integration: Fonnte webhook
- AI assistant: OpenAI/Gemini style provider melalui service AI read-only

## Lapisan Aplikasi

### 1. Model dan Database

Model Eloquent berada di `app/Models`. Model menjadi representasi data utama seperti:

- `Outlet`
- `DurianVariety`
- `InventoryItem`
- `Purchase`
- `Shipment`
- `Sale` dan `SaleItem`
- `Production`
- `ProductConversion`
- `ProductReturn`
- `StockOpname`
- `Expense`
- `WhatsappReport`
- `AuditLog`
- `AiChatSession` dan `AiChatMessage`

Migration berada di `database/migrations`. Setiap perubahan struktur data wajib lewat migration agar bisa diaudit dan diulang di environment lain.

### 2. Filament Resources

Resource Filament berada di `app/Filament/Resources`. Resource adalah pintu utama user mengelola data:

- Purchases
- Shipments
- Sales
- Productions
- Product Conversions
- Product Returns
- Stock Opnames
- Expenses
- Outlets
- Master Produk
- WA Draft Reports
- Users
- Audit Trail

Resource sebaiknya hanya mengatur form, table, action, filter, import, export, dan validasi UI. Rumus bisnis tidak boleh berat di Resource.

### 3. Service dan Calculator

Service berada di `app/Services`. Ini adalah lapisan terpenting untuk menjaga rumus tetap konsisten.

Service utama:

- `BusinessInsightsCalculator`: menghitung profit, margin, HPP, expense, retur, opname, inventory valuation, product mix, profit outlet, dan laporan operasional.
- `StockSnapshotCalculator`: menghitung posisi stok per tanggal dengan anchor stock opname.
- `PriceSimulatorCalculator`: simulasi harga, target margin, target omset, dan proyeksi kebutuhan KG.
- `SalesTargetCalculator`: menghitung target sales outlet.
- `SupplierPerformanceCalculator`: analisa performa supplier.
- `WhatsappReportParser`: membaca format laporan WhatsApp.
- `WhatsappReportApprover`: mengubah draft WA menjadi data operasional setelah approval.
- `GunsasBusinessDataContext`: menyusun data konteks bisnis untuk AI.
- `GunsasAiAnalyst`: interface AI read-only untuk analisa.
- `FonnteMessageSender`: pengiriman balasan WhatsApp.

Aturan arsitektur: rumus stok harus lewat `StockSnapshotCalculator`; rumus bisnis harus lewat `BusinessInsightsCalculator`.

### 4. Import dan Export

Import Excel berada di `app/Imports`.

Import yang tersedia:

- Purchases
- Shipments
- Sales
- Productions
- Product Conversions
- Product Returns
- Stock Opnames
- Expenses
- Inventory Items

Export berada di `app/Exports`.

Export yang tersedia:

- Template Excel
- Update records
- Product returns terpilih
- Laporan bisnis PDF/data

Prinsip import:

- Jika baris memiliki `id`, sistem memperbarui data lama.
- Jika `id` kosong, sistem membuat data baru.
- Data harus divalidasi sebelum masuk agar tidak ada sebagian data benar masuk dan sebagian salah tertinggal tanpa disadari.

### 5. WhatsApp Flow

Webhook masuk melalui `WhatsappWebhookController`.

Alur:

1. User mengirim atau forward laporan ke nomor bot.
2. Webhook menerima payload dari Fonnte.
3. `WhatsappReportParser` membaca isi pesan.
4. Sistem membuat `WhatsappReport` sebagai draft.
5. Bot membalas ringkasan dan ID draft.
6. Admin bisa approve/batal/detail/list dari chat.
7. `WhatsappReportApprover` membuat data final seperti retur, produksi return, conversion, atau stock opname.

WA hanya membuat draft dulu, kecuali aturan approval diubah secara sadar.

### 6. AI Gunsas

AI Gunsas adalah asisten read-only. Ia boleh:

- Membaca ringkasan data bisnis yang disediakan sistem.
- Menjawab pertanyaan bisnis.
- Membantu analisa profit, margin, stok, retur, expense, supplier, dan harga.
- Memberi saran strategi.

AI tidak boleh:

- Mengubah database.
- Mengubah code.
- Menjalankan import/export.
- Menghapus data.
- Menyetujui draft tanpa command eksplisit dari flow WA atau UI.

### 7. Laporan PDF

Laporan PDF dibuat dari data Business Insights. PDF harus berisi ringkasan bisnis yang mudah dikirim ke pihak manajemen:

- Omset kasir
- Sales net
- Bagian Gunsas
- Profit dan margin
- Beban profit
- Expense
- Shipment/modal masuk
- Penjualan per produk
- Profit per outlet
- Supplier performance bila relevan
- Pergerakan stok sebagai lampiran audit, bukan fokus utama

## Alur Data Utama

### Alur Durian Normal

```text
Purchase
  -> Shipment gudang ke outlet
  -> Sales buah utuh
  -> Production buah ke fresh/olahan
  -> Product Conversion fresh ke frozen / fresh loss
  -> Sales fresh/frozen
  -> Stock Opname
  -> Business Insights
```

### Alur Retur Supplier

```text
Product Return diajukan outlet
  -> Supplier menerima/menolak
  -> Refund dicatat
  -> Rejected/final loss masuk beban
  -> Jika masih diselamatkan, Production source_type=return mencatat recovery
  -> Business Insights menampilkan profit setelah estimasi recovery
```

### Alur Produk Non-Durian

```text
Inventory Item kategori produk jualan/olahan
  -> Purchase
  -> Shipment ke outlet
  -> SaleItem
  -> Stock Opname bila perlu
  -> Business Insights
```

### Alur Inventory Operasional

```text
Inventory Item packaging/stiker/operasional
  -> Purchase
  -> Shipment ke outlet
  -> Pemakaian diketahui dari Stock Opname
  -> Inventory terpakai menjadi beban profit
```

## Prinsip Pengembangan

- Jangan menaruh rumus penting di Blade.
- Jangan membuat rumus stok baru di Resource jika sudah ada `StockSnapshotCalculator`.
- Jangan membuat rumus profit baru di page jika sudah ada `BusinessInsightsCalculator`.
- Setiap perubahan rumus harus dicek dampaknya ke Dashboard, Business Insights, PDF, AI context, dan export.
- Setiap field baru yang bisa di-import harus masuk juga ke template, import, export update, table, dan dokumentasi.
