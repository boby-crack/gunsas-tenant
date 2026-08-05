# PRD Gunsas Tenant

## Latar Belakang

Gunsas menjalankan operasional durian dan produk pendukung di beberapa outlet partner. Data bisnis berasal dari pembelian, shipment, penjualan, produksi, retur, stock opname, expense, dan laporan WhatsApp.

Sebelum sistem ini, data tersebar di chat, Excel, laporan outlet, dan catatan manual. Akibatnya sulit mengetahui profit, stok, loss, performa outlet, performa supplier, dan harga jual minimum.

## Tujuan Produk

Membangun sistem operasional dan analitik yang bisa:

- Mencatat semua data penting harian.
- Membaca laporan WhatsApp menjadi draft data.
- Mengimpor data dari Excel.
- Menghitung profit, margin, HPP, loss, recovery, stok, dan inventory valuation.
- Membantu keputusan harga jual dan target sales.
- Menyediakan laporan PDF yang mudah dibagikan.
- Menyediakan AI read-only sebagai konsultan bisnis berbasis data.

## Pengguna

### Super Admin

Bisa mengelola semua modul, user, audit log, import/export, dan konfigurasi master.

### Eksekutif

Fokus membaca Dashboard, Business Insights, PDF report, Supplier Performance, dan AI Gunsas.

### Staff Admin

Fokus input dan koreksi data operasional:

- Purchase
- Shipment
- Sales
- Production
- Product Return
- Stock Opname
- Expense
- WA Draft Report

## Sasaran Utama

1. Data operasional rapi dan bisa diaudit.
2. Profit dan margin bisa dibaca per periode, outlet, grup, produk, dan varian.
3. Stok sistem mengikuti alur nyata di lapangan.
4. Import Excel dan WA mengurangi input manual.
5. Sistem tetap aman: AI dan bot tidak mengubah data tanpa approval.

## Non-Goals

Untuk tahap ini sistem tidak ditujukan sebagai:

- POS kasir langsung.
- Accounting penuh.
- Payroll detail per karyawan.
- Warehouse management enterprise.
- Sistem pembayaran supplier otomatis.
- AI yang bebas mengubah data.

## Modul dan Kebutuhan

### 1. Outlet

Kebutuhan:

- Menyimpan nama outlet.
- Menyimpan grup outlet seperti TipTop, Total Buah, Top Buah, dan Puncak.
- Menyimpan alias WhatsApp.
- Menyimpan persentase bagi hasil partner.
- Menyimpan target sales per periode.

### 2. Master Produk

Kebutuhan:

- Menyimpan produk durian sistem.
- Menyimpan produk jualan non-durian.
- Menyimpan produk olahan durian.
- Menyimpan inventory operasional seperti thinwall, stiker, tissue, sarung tangan, dan lain-lain.
- Menyimpan satuan dan default unit cost.

### 3. Purchases

Kebutuhan:

- Mencatat pembelian buah durian.
- Mencatat supplier dan kode supplier.
- Kode supplier otomatis uppercase.
- Mendukung import/export update.
- Menampilkan total purchase, total kg, total butir, avg modal.

### 4. Shipments

Kebutuhan:

- Mencatat pengiriman gudang ke outlet.
- Mencatat pengiriman outlet ke gudang.
- Mendukung durian dan produk inventory/non-durian.
- Menghitung modal shipment dari modal per kg atau default unit cost.
- Menampilkan total kirim, total terima, avg berat, dan total modal sesuai filter.

### 5. Sales

Kebutuhan:

- Mencatat sales durian: buah utuh, fresh, frozen.
- Mencatat sales produk non-durian lewat sale items.
- Mencatat gross sales, discount, sales return, dan net sales.
- Mendukung laporan penjualan per produk.
- Mendukung import/export update.

### 6. Productions

Kebutuhan:

- Mencatat buah utuh menjadi kupas fresh dan olahan/reject.
- Membedakan production normal dan production dari buah return.
- Menghitung yield, susut, dan pengkali modal.
- Production normal mengurangi stok buah.
- Production return dipakai untuk recovery tracking dan tidak boleh double consume stok normal.

### 7. Product Conversions

Kebutuhan:

- Mencatat fresh menjadi frozen.
- Mencatat fresh busuk/olahan sebagai loss.
- Mendukung import/export update.

### 8. Product Returns

Kebutuhan:

- Mencatat retur outlet ke supplier.
- Mencatat supplier menerima/menolak.
- Mencatat refund.
- Status `no retur` diperlakukan sebagai ditolak supplier.
- Loss final dihitung dari barang yang tidak dikompensasi supplier.
- Mendukung export list terpilih untuk update massal.

### 9. Stock Opnames

Kebutuhan:

- Mencatat fisik buah, fresh, frozen, dan inventory item.
- SO malam hari menjadi anchor stok setelah transaksi hari tersebut.
- Sistem menghitung stok buku sampai tanggal cek.
- Selisih minus menjadi loss.
- Selisih plus menambah inventory valuation, bukan profit langsung.

### 10. Expenses

Kebutuhan:

- Mencatat biaya operasional.
- Mendukung expense langsung ke outlet.
- Mendukung expense global ke semua outlet.
- Mendukung expense scope grup, misalnya gaji hanya untuk TipTop.
- Kategori expense mencakup parkir, logistik/kurir, settlement, bensin/tol, listrik/air, gaji, sewa, packaging, dan lain-lain.

### 11. Business Insights

Kebutuhan:

- Menampilkan ringkasan omset, sales net, bagian Gunsas, profit, margin, expense, HPP, loss, recovery, inventory valuation.
- Bisa difilter berdasarkan grup, outlet, kategori produk, produk, varian, dan periode.
- Menampilkan laporan penjualan per produk.
- Menampilkan profit per outlet.
- Menampilkan peta loss.
- Menampilkan pergerakan stok outlet sebagai lampiran audit.
- Menyediakan PDF report.

### 12. Dashboard

Kebutuhan:

- Menampilkan ringkasan cepat.
- Menampilkan target outlet.
- Menampilkan grafik sales mingguan.
- Menampilkan grafik expense dan purchase secara ringkas.
- Tidak terlalu detail seperti Business Insights.

### 13. WhatsApp Bot

Kebutuhan:

- Menerima laporan return, rijek, production, conversion, fresh loss, dan stock opname.
- Membuat draft.
- Membalas ringkasan manusiawi.
- Mendukung command detail, list, approve, batal.
- Menghindari double draft dari pesan yang sama.

### 14. AI Gunsas

Kebutuhan:

- Menjawab pertanyaan bisnis.
- Membaca konteks data dari sistem.
- Memberi analisa profit, margin, stok, loss, supplier, outlet, harga, dan forecast.
- Menyimpan riwayat chat.
- Tidak boleh write database/code.

### 15. Audit Trail

Kebutuhan:

- Mencatat perubahan data penting.
- Menyimpan user yang melakukan perubahan.
- Menyimpan before/after bila memungkinkan.

## Acceptance Criteria Umum

- Semua modul utama memiliki search, filter, sort, import/export bila relevan.
- Semua angka Business Insights konsisten dengan PDF.
- Semua rumus stok menggunakan `StockSnapshotCalculator`.
- Semua rumus profit menggunakan `BusinessInsightsCalculator`.
- Import tidak boleh membuat data sebagian bila ada error kritis.
- Template Excel sesuai field terbaru.
- WA draft tidak boleh langsung mengubah data final tanpa approval.
