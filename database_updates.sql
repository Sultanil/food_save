-- ========================================================
-- 🌿 SQL UPDATE DATABASE FOODSAVE
-- ========================================================
-- Petunjuk: 
-- 1. Buka phpMyAdmin Anda.
-- 2. Pilih database "foodsave" (atau nama database yang Anda gunakan).
-- 3. Klik tab "SQL" di bagian atas.
-- 4. Salin (Copy) seluruh kode di bawah ini, tempel (Paste) ke kolom SQL phpMyAdmin.
-- 5. Klik tombol "Kirim" (Go) di pojok kanan bawah.
-- ========================================================

-- 1. Memperbarui tabel `penjual` untuk verifikasi NIK, KTP, dan Kode Pos Toko
ALTER TABLE `penjual` 
  ADD COLUMN `nik` VARCHAR(16) NULL AFTER `no_telp`,
  ADD COLUMN `foto_ktp` VARCHAR(255) NULL AFTER `nik`,
  ADD COLUMN `kode_pos` VARCHAR(5) NULL AFTER `foto_ktp`,
  ADD COLUMN `status_verifikasi` ENUM('pending', 'disetujui', 'ditolak') DEFAULT 'pending' AFTER `kode_pos`,
  ADD COLUMN `alasan_penolakan` TEXT NULL AFTER `status_verifikasi`;

-- 2. Memperbarui tabel `transaksi` untuk bukti pembayaran, resi, pembatalan, dan pengiriman batch
ALTER TABLE `transaksi` 
  ADD COLUMN `bukti_pembayaran` VARCHAR(255) NULL AFTER `kode_voucher`,
  ADD COLUMN `no_resi` VARCHAR(255) NULL AFTER `bukti_pembayaran`,
  ADD COLUMN `alasan_pembatalan` TEXT NULL AFTER `no_resi`,
  ADD COLUMN `checkout_batch_id` VARCHAR(255) NULL AFTER `alasan_pembatalan`,
  ADD COLUMN `shipping_status` VARCHAR(50) NULL AFTER `checkout_batch_id`;
