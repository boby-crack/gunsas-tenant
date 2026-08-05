# Design System dan UX Gunsas

Dokumen ini menjelaskan arah tampilan dan pengalaman pengguna aplikasi Gunsas.

## Tujuan Desain

Aplikasi ini dipakai untuk pekerjaan operasional harian. Desain harus terasa cepat, rapi, mudah dibaca, dan tidak seperti landing page. Fokus utama adalah input data, koreksi data, audit angka, dan membaca ringkasan bisnis.

Prinsip utama:

- Padat tapi tetap jelas.
- Angka penting harus mudah ditemukan.
- Tabel boleh detail, tapi ringkasan harus selalu tersedia.
- Filter tidak boleh membuat halaman terasa berat.
- Bahasa harus operasional dan mudah dipahami tim.

## Tema Visual

Identitas visual mengikuti logo Gunsas:

- Warna utama: oranye Gunsas.
- Warna aksen: hijau/biru dari logo bila dibutuhkan.
- Background admin: gelap.
- Kartu dan tabel: kontras cukup, border sederhana, radius kecil.
- Warna merah hanya untuk rugi, minus, error, atau warning penting.
- Warna hijau untuk profit, approved, diterima, atau positif.
- Warna kuning/oranye untuk pending, review, atau perhatian.

## Navigasi

Sidebar adalah navigasi utama. Modul yang sering dipakai harus mudah dijangkau:

- Dashboard
- Business Insights
- Purchases
- Shipments
- Sales
- Productions
- Product Returns
- Product Conversions
- Stock Opnames
- Expenses
- WA Draft Reports
- Master Produk
- Outlets

Menu sistem seperti Users dan Audit Trail dikelompokkan di bagian System.

## Dashboard

Dashboard adalah halaman kesimpulan cepat. Jangan terlalu teknis.

Yang ideal tampil:

- Sales net periode
- Profit bersih
- Margin bersih
- Outlet capai target
- Grafik sales mingguan
- Grafik expense dan purchase ringkas
- Target sales per outlet
- Alert outlet minus atau margin negatif

Dashboard bukan tempat audit rumus detail. Audit detail masuk ke Business Insights atau Stock Snapshot.

## Business Insights

Business Insights adalah halaman analisis bisnis mendalam.

Susunan ideal:

1. Filter analisis
2. Ringkasan periode
3. Omset dan bagian Gunsas
4. Profit dan margin
5. Beban, stok, dan barang masuk
6. Penjualan per produk
7. Profit per outlet
8. Expense terbesar
9. Supplier performance
10. Peta loss
11. Lampiran pergerakan stok outlet

Bagian teknis seperti pergerakan stok sebaiknya berada di bawah karena dipakai untuk audit, bukan bacaan utama.

## Filter

Filter yang memicu query berat sebaiknya memakai tombol `Terapkan`, bukan update otomatis setiap perubahan.

Filter umum:

- Grup outlet
- Outlet
- Kategori produk
- Produk
- Varian
- Tanggal mulai
- Tanggal akhir

Jika memungkinkan, filter boleh multi-select untuk outlet, varian, dan kategori.

## Tabel

Aturan tabel:

- ID ditampilkan untuk modul yang sering di-update lewat Excel.
- Header bisa sort asc/desc.
- Search tersedia.
- Filter tersedia.
- Bulk delete tersedia untuk modul operasional yang aman dihapus.
- Bulk export/update tersedia untuk modul yang sering dikoreksi.
- Summary total tampil di atas tabel atau footer.

Kolom angka harus rata kanan. Kolom nama/label rata kiri.

## Form

Form harus mengikuti konteks data:

- Durian: butir, kg, varian, modal per kg.
- Produk non-durian: produk, unit, qty, unit cost.
- Expense: tanggal, alokasi, scope, kategori, nominal, keterangan.
- Stock opname: jenis opname, outlet, produk/varian, tanggal cek fisik, stok sistem, fisik, selisih.

Field yang dihitung otomatis sebaiknya read-only atau diberi label jelas.

## Import Excel

UX import ideal:

1. User upload file.
2. Sistem validasi seluruh baris.
3. Jika ada error, tampilkan daftar baris dan masalah.
4. Tidak ada data yang masuk sebelum semua valid.
5. Jika semua valid, baru import sukses.

Template harus mengikuti field terbaru. Jangan ada field lama yang sudah tidak dipakai.

## AI Gunsas

AI Gunsas adalah asisten mengambang yang tidak boleh mengganggu klik user.

Perilaku ideal:

- Saat tertutup hanya tampil tab kecil di pojok.
- Klik tab membuka label AI Gunsas.
- Klik label membuka panel chat.
- Panel bisa ditutup.
- Riwayat chat tersimpan.
- Enter mengirim pesan.
- Balasan mendukung format rapi seperti bold, daftar, dan angka.

AI tetap read-only dan tidak boleh mengubah database.

## PDF Laporan

PDF bukan salinan mentah halaman web. PDF harus lebih ringkas dan mudah dibaca.

PDF harus mengutamakan:

- Ringkasan angka utama.
- Profit dan margin.
- Beban besar.
- Produk terlaris.
- Outlet terbaik/terburuk.
- Loss dan retur.
- Lampiran detail bila perlu.

Tabel panjang boleh dipotong atau dipindah ke lampiran agar halaman awal tetap jelas.
