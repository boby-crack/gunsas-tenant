# Business Rules Gunsas

Dokumen ini adalah sumber aturan bisnis utama. Jika ada perubahan rumus atau flow, update dokumen ini bersamaan dengan code.

## 1. Tanggal

### Tanggal transaksi

Setiap modul memakai tanggal kejadian bisnis:

- Purchase: tanggal pembelian atau tanggal nota/kedatangan pembelian.
- Shipment: tanggal barang dikirim/diterima outlet.
- Sales: tanggal laporan penjualan.
- Production: tanggal buah diproses.
- Product Return: tanggal retur diajukan/dibuka.
- Stock Opname: tanggal cek fisik.
- Expense: tanggal biaya terjadi.

### Stock opname malam hari

Stock opname tanggal tertentu dianggap dilakukan setelah aktivitas hari itu selesai.

Artinya:

- Sales tanggal yang sama tetap memotong stok.
- Production tanggal yang sama tetap memotong/menambah stok.
- Return tanggal yang sama tetap memotong stok.
- Shipment tanggal yang sama tetap masuk stok.
- Baru setelah itu fisik stock opname menjadi anchor.

## 2. Outlet dan Grup

Outlet memiliki grup:

- TipTop
- Total Buah
- Top Buah
- Puncak

Setiap outlet memiliki `partner_share_percent`.

Contoh:

- TipTop: umumnya 15 persen partner, berarti Gunsas 85 persen.
- Total Buah: bisa 20 persen partner, berarti Gunsas 80 persen.

Rumus:

```text
Bagian Gunsas = Sales Net x (100% - partner_share_percent)
```

Jika filter memilih banyak outlet dengan share berbeda, sistem memakai perhitungan per outlet lalu dijumlah, bukan memakai satu persentase global sembarangan.

## 3. Sales

Sales terdiri dari:

- Gross sales / omset kasir
- Discount
- Sales return
- Sales net
- Bagian Gunsas

Rumus:

```text
Sales Net = Gross Sales - Discount - Sales Return
Bagian Gunsas = Sales Net - Bagian Partner
```

Sales return dicatat agar data sales cocok dengan laporan outlet. Sales return tidak ditampilkan sebagai loss utama di laporan PDF kecuali dibutuhkan untuk audit.

## 4. HPP

HPP adalah biaya modal produk yang terjual.

Untuk durian:

- Buah utuh memakai modal buah avg.
- Fresh memakai modal fresh avg.
- Frozen memakai modal frozen avg.

Untuk produk non-durian:

- HPP memakai unit cost dari item atau default cost master produk.

HPP mengikuti rumus yang aktif di `BusinessInsightsCalculator`. Jika rumus modal berubah, semua halaman terkait harus ikut berubah:

- Business Insights
- Dashboard
- PDF report
- AI context
- Price Simulator

## 5. Purchase

Purchase durian mencatat:

- tanggal
- varian
- kode supplier
- supplier
- butir
- berat kg
- harga per kg
- total

Kode supplier selalu disimpan uppercase.

Rumus:

```text
Total Purchase = Berat KG x Harga per KG
Avg Purchase/KG = Total Purchase / Total KG
```

## 6. Shipment

Shipment bisa berupa:

- Gudang ke outlet
- Outlet ke gudang

Shipment bisa untuk:

- Buah durian
- Fresh/frozen
- Inventory item
- Produk jualan non-durian

Untuk durian:

```text
Avg Berat = Qty KG / Qty Butir
Total Modal = Qty KG x Modal per KG
```

Untuk inventory/non-durian:

```text
Total Modal = Qty x Unit Cost
```

## 7. Production Normal

Production normal adalah proses buah bagus menjadi fresh dan olahan/reject.

Efek stok:

```text
Buah Utuh berkurang sebesar qty_buah_kg
Kupas Fresh bertambah sebesar qty_kupas_kg
Olahan/Reject hanya dicatat sebagai output proses, tidak masuk inventory jualan kecuali nanti dibuat modul khusus.
```

Rumus produksi:

```text
Total Daging = Fresh KG + Olahan KG
Susut = Buah KG - Total Daging
Yield Daging = Total Daging / Buah KG
Pengkali Produksi Fisik = Buah KG / Total Daging
```

## 8. Production Dari Return

Production dari return dipakai untuk mencatat buah return yang masih diselamatkan menjadi fresh/olahan.

Aturan:

- Tidak boleh double consume stok buah normal jika return sudah memotong stok lewat Product Return.
- Recovery value boleh ditampilkan sebagai pengurang dampak loss retur.
- Sales eksternal tidak bisa membedakan mana barang dari return dan mana barang normal, jadi recovery bersifat estimasi bisnis.

## 9. Product Conversion

Product conversion dipakai untuk:

- Fresh menjadi frozen.
- Fresh menjadi olahan/busuk/loss.

Fresh ke frozen:

```text
Kupas Fresh berkurang
Durpas Frozen bertambah
```

Fresh busuk/olahan:

```text
Kupas Fresh berkurang
Loss bertambah
Tidak masuk inventory jualan
```

## 10. Product Return

Return adalah barang bermasalah yang diajukan outlet ke supplier.

Status utama:

- Menunggu QC
- Diterima
- Ditolak
- No Retur

Aturan:

- `No Retur` diperlakukan sebagai ditolak supplier.
- Supplier accepted kg mengurangi loss.
- Refund mengurangi kerugian final.
- KG ditolak menjadi loss langsung.

Rumus umum:

```text
Ditolak KG = Retur KG - Diterima Supplier KG
Loss Retur Final = Nilai retur ditolak - Refund terkait
```

## 11. Stock Opname

Stock opname adalah anchor stok fisik.

Sistem menghitung stok buku sampai tanggal cek. User mengisi stok fisik.

```text
Selisih = Fisik - Sistem
```

Jika selisih minus:

- Menjadi loss opname.
- Mengurangi profit.

Jika selisih plus:

- Menambah inventory valuation.
- Tidak langsung dianggap profit.

## 12. Inventory Valuation

Inventory valuation adalah nilai stok tersisa, bukan laba bersih.

```text
Profit + Inventory = Profit Bersih + Inventory Valuation
```

Angka ini dipakai untuk melihat posisi aset, bukan uang profit yang sudah aman.

## 13. Expense

Expense bisa dialokasikan:

- Langsung ke outlet.
- Global ke semua outlet.
- Ke grup outlet tertentu.

Aturan:

- Expense outlet langsung hanya membebani outlet tersebut.
- Expense global dibagi proporsional sesuai basis yang disepakati sistem.
- Expense grup hanya dibagi ke outlet dalam grup tersebut.
- Jika alokasi tidak diisi, default dianggap global.

Kategori umum:

- Parkir
- Logistik / Kurir
- Settlement
- Bensin & Tol
- Listrik & Air
- Gaji / Lemburan Staff
- Sewa Tempat / Tenant
- Perlengkapan & Packaging
- Lain-lain

## 14. Inventory Operasional

Barang seperti thinwall, stiker, tissue, dan sarung tangan biasanya tidak ter-track saat dipakai harian.

Aturan:

- Purchase menambah stok gudang.
- Shipment memindahkan stok ke outlet.
- Stock opname membaca fisik.
- Selisih pemakaian dihitung dari movement dan SO.
- Nilai terpakai menjadi beban profit.

## 15. Produk Non-Durian

Produk seperti pancake, brulee, mochi, kopi, atau es krim dicatat sebagai `InventoryItem` kategori produk jualan/produk olahan.

Aturan:

- Bisa dikirim lewat shipment.
- Bisa dijual lewat sale items.
- HPP memakai unit cost.
- Tidak memakai rumus durian kg/butir.

## 16. Import Excel

Aturan import:

- Baris pertama adalah header.
- Header harus mengikuti template terbaru.
- Jika ada `id`, update record.
- Jika `id` kosong, create record baru.
- Sistem sebaiknya validasi seluruh baris lebih dulu.
- Jika ada error kritis, tidak ada data yang masuk.

## 17. WhatsApp Draft

WA parser hanya membuat draft.

Command umum:

- `detail #ID`
- `approve #ID`
- `batal #ID`
- `list pending`
- `list review`

Draft yang sama tidak boleh tercipta dua kali dari pesan yang sama.

## 18. AI Gunsas

AI hanya read-only.

AI boleh:

- Menganalisa data.
- Menjelaskan rumus.
- Memberi saran bisnis.
- Membantu forecast dan simulasi harga.

AI tidak boleh:

- Insert/update/delete database.
- Approve draft.
- Menjalankan import.
- Mengubah code.
