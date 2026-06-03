# Langkah-Langkah Setup CRUD Google Sheets

Panduan ini menjelaskan cara menghubungkan aplikasi PHP di project ini ke Google Sheets agar fitur CRUD bisa langsung membaca, menambah, mengubah, dan menghapus data.

**Catatan untuk mahasiswa:**
- Setiap mahasiswa harus memakai akun Google Cloud masing-masing.
- Setiap mahasiswa harus membuat service account masing-masing.
- Setiap mahasiswa harus memakai spreadsheet Google Sheets milik masing-masing.
- Jangan memakai ID spreadsheet atau email service account milik teman.

## 1. Siapkan Google Cloud Project

1. Buka [Google Cloud Console](https://console.cloud.google.com/).
2. Buat project baru atau gunakan project yang sudah ada.
3. Pastikan project yang aktif adalah project yang akan dipakai untuk integrasi ini.

## 2. Aktifkan Google Sheets API

1. Buka menu **APIs & Services**.
2. Klik **Library**.
3. Cari **Google Sheets API**.
4. Buka hasilnya.
5. Klik **Enable**.

## 3. Buat Service Account

1. Buka menu **IAM & Admin**.
2. Klik **Service Accounts**.
3. Klik **Create Service Account**.
4. Isi nama sesuai kebutuhan masing-masing, misalnya `webservice-sheets`.
5. Klik **Create and Continue**.
6. Role boleh dilewati.
7. Klik **Done**.

## 4. Buat JSON Key

1. Klik service account yang baru dibuat.
2. Buka tab **Keys**.
3. Klik **Add Key**.
4. Pilih **Create new key**.
5. Pilih **JSON**.
6. Klik **Create**.
7. File `.json` akan terdownload.

## 5. Letakkan File JSON di Project

1. Rename file download tadi menjadi:

   ```
   google-service-account.json
   ```

2. Simpan file itu ke folder project:

   ```text
   C:\xampp\htdocs\web_service2\google-service-account.json
   ```

3. Pastikan file [config.php](config.php) menunjuk ke file ini:

   ```php
   'google_service_account_file' => __DIR__ . '/google-service-account.json',
   ```

## 6. Buat atau Pakai Google Sheet Native

**Penting:**
- Jangan gunakan file Excel `.xlsx` yang hanya dibuka di Google Sheets.
- Gunakan file Google Sheets asli.

Kalau file Anda masih Excel:
1. Buka file di Google Sheets.
2. Klik **File**.
3. Pilih **Save as Google Sheets** / **Simpan sebagai Google Spreadsheet**.
4. Gunakan file baru hasil konversi itu.

## 7. Share Spreadsheet ke Service Account

1. Buka file Google Sheets yang akan dipakai.
2. Klik **Share** / **Bagikan**.
3. Buka file `google-service-account.json`.
4. Copy nilai `client_email`.
5. Paste email itu ke kolom tambah pengguna di popup share.
6. Set role menjadi **Editor**.
7. Klik **Send** / **Kirim**.

Contoh format email service account:

```text
nama-service-account@nama-project.iam.gserviceaccount.com
```

**Catatan:**
- `Anyone with the link` tidak cukup.
- Service account harus ditambahkan langsung sebagai user yang punya akses.

## 8. Masukkan ID Spreadsheet ke Config

Buka file [config.php](config.php), lalu isi spreadsheet ID yang benar:

```php
return [
    'google_sheet_id' => 'GANTI_DENGAN_ID_SPREADSHEET_MASING_MASING',
    'google_sheet_name' => 'Sheet1',
    'google_service_account_file' => __DIR__ . '/google-service-account.json',
];
```

Penjelasan:
- `google_sheet_id`: ID spreadsheet dari URL Google Sheets.
- `google_sheet_name`: nama tab sheet, misalnya `Sheet1`.

## 9. Struktur Kolom yang Dipakai

Aplikasi ini memakai 3 kolom:

```text
name | email | status
```

Header akan dibuat otomatis oleh kode bila belum ada.

## 10. Jalankan Aplikasi

1. Pastikan Apache di XAMPP aktif.
2. Buka:

   ```text
   http://127.0.0.1:8080/web_service2/
   ```

3. Refresh halaman dengan `Ctrl + F5`.

## 11. Uji CRUD

### Tambah Data

1. Isi **Name**.
2. Isi **Email**.
3. Pilih **Status**.
4. Klik **Tambah ke Sheet**.

### Edit Data

1. Klik tombol **Edit** pada tabel.
2. Ubah data.
3. Klik **Update Sheet**.

### Hapus Data

1. Klik **Delete**.
2. Konfirmasi hapus.

## 12. Tanda Kalau Setup Sudah Benar

Kalau berhasil:
- Warning file kredensial hilang.
- Error Google Sheets hilang.
- Data baru masuk ke spreadsheet.
- Tabel di halaman menampilkan data dari sheet.

## 13. Error yang Pernah Muncul dan Artinya

### `The document must not be an Office file`

**Artinya:**
- Spreadsheet masih file Excel, belum Google Sheets native.

**Solusi:**
- Gunakan **Save as Google Sheets**.

### `Halaman Tidak Ditemukan`

**Artinya:**
- Request ke endpoint Google Sheets salah atau file belum valid.

**Solusi:**
- Pastikan kode terbaru dipakai.
- Pastikan spreadsheet ID benar.
- Pastikan service account punya akses.

### Spreadsheet tidak bisa dibaca/ditulis

Cek:
1. File `google-service-account.json` ada.
2. `client_email` sudah dibagikan sebagai **Editor**.
3. Spreadsheet ID di config benar.
4. Nama tab `Sheet1` benar.

## 14. Catatan Penggunaan untuk Mahasiswa

Setiap mahasiswa harus mengganti bagian berikut dengan data milik sendiri:

1. `google_sheet_id` di [config.php](config.php)
2. File `google-service-account.json`
3. Email `client_email` yang dibagikan ke spreadsheet
4. Spreadsheet target yang dipakai untuk CRUD

**Jangan menyalin mentah:**
- ID spreadsheet dosen
- ID spreadsheet teman
- File JSON service account teman
- Email service account teman

## 15. File Penting di Project

- [index.php](index.php) — Logika CRUD dan koneksi ke Google Sheets API.
- [config.php](config.php) — Konfigurasi spreadsheet dan file service account.
- [style.css](style.css) — Tampilan dashboard.
- [google-service-account.json](google-service-account.json) — Kredensial service account Google (jangan di-share).
- [google-service-account.example.json](google-service-account.example.json) — Contoh struktur file JSON service account.

## 16. Ringkasan Singkat

Urutan setup paling singkat:

1. Enable **Google Sheets API**.
2. Buat **Service Account**.
3. Download **JSON Key**.
4. Taruh file di project sebagai `google-service-account.json`.
5. Share spreadsheet ke `client_email` service account.
6. Pastikan spreadsheet adalah Google Sheets native.
7. Isi `google_sheet_id` di [config.php](config.php).
8. Buka `http://127.0.0.1:8080/web_service2/`.
9. Tes tambah data.
