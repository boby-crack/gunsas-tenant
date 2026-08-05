# Schema Data Gunsas

Dokumen ini menjelaskan struktur data utama aplikasi. Detail kolom mengikuti migration terbaru di `database/migrations`.

## Entity Relationship Ringkas

```text
users
  -> audit_logs
  -> ai_chat_sessions

outlets
  -> sales_targets
  -> purchases
  -> shipments
  -> sales
  -> productions
  -> product_returns
  -> stock_opnames
  -> expenses

durian_varieties
  -> purchases
  -> shipments
  -> sales
  -> productions
  -> product_returns
  -> stock_opnames
  -> inventory_items

inventory_items
  -> sale_items
  -> shipments
  -> stock_opnames

sales
  -> sale_items

whatsapp_reports
  -> created operational record after approval
```

## Tabel Utama

### users

Menyimpan akun login.

Kolom penting:

- `name`
- `email`
- `password`
- `role`

Role yang digunakan:

- `super_admin`
- `executive`
- `admin_staff`

### audit_logs

Mencatat perubahan data penting.

Kolom penting:

- `user_id`
- `event`
- `auditable_type`
- `auditable_id`
- `old_values`
- `new_values`
- `ip_address`
- `user_agent`

### outlets

Master outlet.

Kolom penting:

- `name`
- `group_name`
- `location`
- `aliases`
- `partner_share_percent`

Grup outlet:

- `tiptop`
- `total_buah`
- `top_buah`
- `puncak`

### sales_targets

Target sales outlet per periode.

Kolom penting:

- `outlet_id`
- `period_type`
- `period_start`
- `period_end`
- `target_amount`
- `notes`

### durian_varieties

Master varian durian.

Kolom penting:

- `name`
- `description`

Contoh:

- MONTHONG
- LOKAL PREMIUM
- MASMUAR
- BAWOR
- BLACKTHORN LOKAL
- MUSANGKING LOKAL

### inventory_items

Master produk dan inventory.

Kolom penting:

- `name`
- `sku`
- `category`
- `unit`
- `durian_variety_id`
- `default_unit_cost`
- `is_active`
- `is_sellable`
- `notes`

Kategori umum:

- `buah_utuh`
- `kupas_fresh`
- `durpas_frozen`
- `produk_jualan`
- `produk_olahan`
- `packaging`
- `stiker`
- `bahan_baku`
- `operasional`
- `lainnya`

### purchases

Mencatat pembelian.

Kolom penting:

- `outlet_id`
- `durian_variety_id`
- `date`
- `supplier_code`
- `supplier_name`
- `qty_butir`
- `qty_kg`
- `price_per_kg`
- `total_amount`
- `notes`

Catatan:

- `supplier_code` disimpan uppercase.
- Untuk durian, modal utama berasal dari `qty_kg` dan `price_per_kg`.

### shipments

Mencatat pengiriman barang.

Kolom penting:

- `outlet_id`
- `inventory_item_id`
- `shipment_mode`
- `shipment_direction`
- `product_type`
- `durian_variety_id`
- `date`
- `modal_price`
- `qty_sent_butir`
- `qty_received_butir`
- `qty_sent_kg`
- `qty_received_kg`
- `generic_qty_sent`
- `generic_qty_received`
- `generic_unit`
- `generic_unit_cost`
- `generic_total_amount`
- `average_weight`
- `value_purchase`
- `notes`

Direction:

- Gudang ke outlet
- Outlet ke gudang

Mode:

- Durian
- Inventory item

### sales

Header sales per outlet/tanggal/varian.

Kolom penting:

- `outlet_id`
- `durian_variety_id`
- `date`
- `buah_sold_kg`
- `fresh_sold_kg`
- `frozen_sold_kg`
- `grand_total_revenue`
- `discount_amount`
- `sales_return_amount`
- `net_sales`
- `notes`

### sale_items

Detail sales produk non-durian atau item tambahan.

Kolom penting:

- `sale_id`
- `inventory_item_id`
- `item_name`
- `category`
- `unit`
- `quantity`
- `unit_price`
- `gross_sales`
- `discount_amount`
- `sales_return_amount`
- `net_sales`
- `unit_cost`
- `total_cost`
- `notes`

### productions

Mencatat proses buah menjadi fresh/olahan.

Kolom penting:

- `outlet_id`
- `durian_variety_id`
- `date`
- `source_type`
- `qty_buah_butir`
- `qty_buah_kg`
- `qty_kupas_pack`
- `qty_kupas_kg`
- `qty_olahan_pack`
- `qty_olahan_kg`
- `total_usable_meat_kg`
- `shrinkage_percentage`
- `multiplier_factor`
- `notes`

Source type:

- `normal`
- `return`

### product_conversions

Mencatat perpindahan produk, terutama fresh ke frozen dan fresh loss.

Kolom penting:

- `outlet_id`
- `durian_variety_id`
- `date`
- `conversion_type`
- `source_qty_kg`
- `result_qty_kg`
- `loss_qty_kg`
- `notes`

Conversion type:

- Fresh ke frozen
- Fresh loss / olahan / busuk

### product_returns

Mencatat retur supplier.

Kolom penting:

- `outlet_id`
- `durian_variety_id`
- `shipment_id`
- `return_type`
- `supplier_code`
- `paint_color`
- `date`
- `reason`
- `qty_butir`
- `qty_kg`
- `supplier_status`
- `accepted_by_supplier_butir`
- `accepted_by_supplier_kg`
- `refund_amount`
- `notes`

Status:

- Menunggu QC
- Diterima
- Ditolak
- No Retur

### stock_opnames

Mencatat cek fisik stok.

Kolom penting:

- `outlet_id`
- `inventory_item_id`
- `durian_variety_id`
- `date`
- `stock_opname_type`
- `product_type`
- `system_qty_kg`
- `physical_qty_kg`
- `difference_qty_kg`
- `generic_unit`
- `generic_consumed_qty`
- `generic_unit_cost`
- `generic_consumed_amount`
- `notes`

Jenis:

- Produk Durian
- Produk Inventory

### expenses

Mencatat biaya operasional.

Kolom penting:

- `date`
- `outlet_id`
- `allocation_scope`
- `allocation_group`
- `category`
- `amount`
- `notes`

Allocation scope:

- Outlet langsung
- Global
- Grup outlet

Kategori:

- Parkir
- Logistik / Kurir
- Settlement
- Bensin & Tol
- Listrik & Air
- Gaji / Lemburan Staff
- Sewa Tempat / Tenant
- Perlengkapan & Packaging
- Lain-lain

### whatsapp_reports

Draft laporan dari WhatsApp.

Kolom penting:

- `sender`
- `message`
- `report_type`
- `status`
- `confidence`
- `parsed_payload`
- `error_notes`
- `approved_at`
- `approved_by`

Status:

- `pending_approval`
- `needs_review`
- `approved`
- `cancelled`

### ai_chat_sessions

Session chat AI Gunsas.

Kolom penting:

- `user_id`
- `title`
- `last_message_at`

### ai_chat_messages

Isi percakapan AI Gunsas.

Kolom penting:

- `ai_chat_session_id`
- `role`
- `content`
- `metadata`

Role:

- `user`
- `assistant`
- `system`

## Catatan Integritas Data

- Semua module yang memakai outlet harus memakai `outlet_id`.
- Semua data durian harus memakai `durian_variety_id` bila relevan.
- Produk non-durian harus memakai `inventory_item_id`.
- Jangan mengandalkan nama bebas jika sudah ada master data.
- Import Excel harus melakukan lookup alias outlet dan validasi master produk.
- Semua perubahan besar harus tercatat di audit log.
