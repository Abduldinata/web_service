# Tutorial Coding CRUD Google Sheets dengan Native PHP

Dokumen ini berisi tutorial coding langkah demi langkah untuk membuat aplikasi CRUD sederhana dengan:

- PHP native
- Google Sheets API
- Service Account Google
- Tampilan dashboard modern

Project yang dibahas ada di folder:

```text
C:\xampp\htdocs\web_service2
```

Catatan untuk mahasiswa:
- Gunakan akun Google Cloud masing-masing.
- Gunakan service account masing-masing.
- Gunakan spreadsheet Google Sheets masing-masing.
- Bagian ID spreadsheet dan email service account di bawah ini hanya contoh format, bukan data yang wajib sama.

## 1. Tujuan Project

Kita ingin membuat aplikasi web yang bisa:

1. membaca data dari Google Sheets
2. menambah data baru
3. mengubah data
4. menghapus data

Data yang dipakai terdiri dari 3 kolom:

```text
name | email | status
```

## 2. Struktur File Project

Struktur file utama:

```text
web_service2/
|- index.php
|- style.css
|- config.php
|- google-service-account.json
|- google-service-account.example.json
|- LANGKAH-LANGKAH-SETUP-GOOGLE-SHEETS.md
`- TUTORIAL-CODING-CRUD-GOOGLE-SHEETS.md
```

Fungsi tiap file:

- `index.php`
  Logika utama aplikasi dan koneksi ke Google Sheets API
- `style.css`
  Tampilan dashboard
- `config.php`
  Konfigurasi spreadsheet dan file kredensial
- `google-service-account.json`
  Kredensial service account Google

## 3. Buat File Konfigurasi

File yang diedit:
- [config.php](config.php)

Letak kode:
- isi file ini dari atas sampai bawah dengan konfigurasi berikut

Kode:

```php
<?php
declare(strict_types=1);

return [
    'google_sheet_id' => 'GANTI_DENGAN_ID_SPREADSHEET_MASING_MASING',
    'google_sheet_name' => 'Sheet1',
    'google_service_account_file' => __DIR__ . '/google-service-account.json',
];
```

Penjelasan:

- `google_sheet_id`
  ID spreadsheet dari URL Google Sheets milik masing-masing
- `google_sheet_name`
  nama tab spreadsheet
- `google_service_account_file`
  lokasi file JSON service account milik masing-masing

## 4. Mulai File PHP Utama

File yang diedit:
- [index.php](index.php)

Letak kode:
- letakkan di bagian paling atas file
- tepat setelah tag `<?php`
- bagian ini menjadi pembuka file sebelum fungsi-fungsi lain

Kode:

```php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

$appConfig = [];
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    $loadedConfig = require $configPath;
    if (is_array($loadedConfig)) {
        $appConfig = $loadedConfig;
    }
}
```

Tujuan bagian ini:

1. memastikan tipe data ketat
2. membaca file konfigurasi
3. mencegah browser menampilkan cache lama

## 5. Definisikan Konstanta API

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah blok pembacaan config
- masih di bagian atas file
- sebelum deklarasi fungsi helper seperti `h()`

Kode:

```php
const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_SHEETS_API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

define('GOOGLE_SHEET_ID', (string) ($appConfig['google_sheet_id'] ?? ''));
define('GOOGLE_SHEET_NAME', (string) ($appConfig['google_sheet_name'] ?? 'Sheet1'));
define('GOOGLE_SERVICE_ACCOUNT_FILE', (string) ($appConfig['google_service_account_file'] ?? (__DIR__ . '/google-service-account.json')));
```

## 6. Buat Helper HTML Escape

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah konstanta API
- sebelum fungsi koneksi Google

Kode:

```php
function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
```

## 7. Baca File Service Account

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah helper `h()`
- masih di kelompok fungsi helper backend

Kode:

```php
function getGoogleServiceAccount(): array
{
    if (!file_exists(GOOGLE_SERVICE_ACCOUNT_FILE)) {
        throw new RuntimeException('File service account Google belum ditemukan.');
    }

    $decoded = json_decode((string) file_get_contents(GOOGLE_SERVICE_ACCOUNT_FILE), true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Isi file service account Google tidak valid.');
    }

    foreach (['client_email', 'private_key'] as $requiredKey) {
        if (empty($decoded[$requiredKey])) {
            throw new RuntimeException('Field ' . $requiredKey . ' belum ada di file service account.');
        }
    }

    return $decoded;
}
```

Fungsi ini penting untuk:

1. memeriksa file ada atau tidak
2. memeriksa JSON valid
3. memastikan `client_email` dan `private_key` tersedia

## 8. Buat JWT untuk Google OAuth

Google Service Account memakai JWT untuk mendapatkan access token.

### 8.1 Fungsi base64 URL safe

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah fungsi `getGoogleServiceAccount()`

Kode:

```php
function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
```

### 8.2 Fungsi ambil access token

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah fungsi `base64UrlEncode()`
- masih di kelompok fungsi autentikasi

Kode:

```php
function getGoogleAccessToken(): string
{
    static $cachedToken = null;
    static $expiresAt = 0;

    if ($cachedToken !== null && time() < $expiresAt - 60) {
        return $cachedToken;
    }

    $serviceAccount = getGoogleServiceAccount();
    $issuedAt = time();

    $jwtHeader = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtClaimSet = base64UrlEncode(json_encode([
        'iss' => $serviceAccount['client_email'],
        'scope' => 'https://www.googleapis.com/auth/spreadsheets',
        'aud' => GOOGLE_TOKEN_URL,
        'exp' => $issuedAt + 3600,
        'iat' => $issuedAt,
    ]));

    $unsignedJwt = $jwtHeader . '.' . $jwtClaimSet;

    $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
    $signature = '';
    openssl_sign($unsignedJwt, $signature, $privateKey, 'sha256');
    openssl_free_key($privateKey);

    $assertion = $unsignedJwt . '.' . base64UrlEncode($signature);

    $payload = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $assertion,
    ]);

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
    ]);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);
    if ($statusCode < 200 || $statusCode >= 300 || !is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('Google OAuth gagal.');
    }

    $cachedToken = (string) $decoded['access_token'];
    $expiresAt = $issuedAt + (int) ($decoded['expires_in'] ?? 3600);

    return $cachedToken;
}
```

## 9. Buat Fungsi Request ke Google Sheets API

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah `getGoogleAccessToken()`
- ini menjadi fungsi request umum yang dipakai semua operasi CRUD

Kode:

```php
function googleApiRequest(string $method, string $url, ?array $payload = null): array
{
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . getGoogleAccessToken(),
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string) $response, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new RuntimeException('Google Sheets API error (' . $statusCode . '): ' . $response);
    }

    return is_array($decoded) ? $decoded : [];
}
```

## 10. Buat Helper URL Range

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah `googleApiRequest()`
- masih di kelompok helper backend

Kode:

```php
function getSheetRange(string $range): string
{
    return GOOGLE_SHEETS_API_BASE . '/' . rawurlencode(GOOGLE_SHEET_ID) . '/values/' . rawurlencode($range);
}
```

## 11. Pastikan Header Spreadsheet Ada

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah `getSheetRange()`
- sebelum fungsi CRUD `fetch`, `append`, `update`, `delete`

Kode:

```php
function ensureSheetHeader(): void
{
    $range = GOOGLE_SHEET_NAME . '!A1:C1';
    $result = googleApiRequest('GET', getSheetRange($range));
    $values = $result['values'][0] ?? [];

    if ($values === ['name', 'email', 'status']) {
        return;
    }

    googleApiRequest('PUT', getSheetRange($range) . '?valueInputOption=RAW', [
        'range' => $range,
        'majorDimension' => 'ROWS',
        'values' => [['name', 'email', 'status']],
    ]);
}
```

## 12. Buat Fungsi Read

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah `ensureSheetHeader()`
- ini fungsi CRUD pertama

Kode:

```php
function fetchSheetRows(): array
{
    ensureSheetHeader();
    $range = GOOGLE_SHEET_NAME . '!A2:C';
    $result = googleApiRequest('GET', getSheetRange($range));
    $values = $result['values'] ?? [];
    $rows = [];

    foreach ($values as $index => $row) {
        $rows[] = [
            'id' => (string) ($index + 2),
            'name' => (string) ($row[0] ?? ''),
            'email' => (string) ($row[1] ?? ''),
            'status' => (string) ($row[2] ?? 'active'),
        ];
    }

    return $rows;
}
```

Penjelasan:

- data dibaca dari baris 2 karena baris 1 dipakai header
- `id` diambil dari nomor baris sheet

## 13. Buat Fungsi Create

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah `fetchSheetRows()`

Kode:

```php
function appendSheetRow(array $payload): void
{
    ensureSheetHeader();
    $range = GOOGLE_SHEET_NAME . '!A:C';

    googleApiRequest(
        'POST',
        getSheetRange($range) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
        [
            'values' => [[
                $payload['name'] ?? '',
                $payload['email'] ?? '',
                $payload['status'] ?? 'active',
            ]]
        ]
    );
}
```

Penting:
- endpoint `append` harus memakai `:append`
- ini sempat jadi sumber error waktu debugging

## 14. Buat Fungsi Update

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah `appendSheetRow()`

Kode:

```php
function updateSheetRow(string $rowId, array $payload): void
{
    $rowNumber = (int) $rowId;
    if ($rowNumber < 2) {
        throw new RuntimeException('ID baris tidak valid untuk update.');
    }

    $range = GOOGLE_SHEET_NAME . '!A' . $rowNumber . ':C' . $rowNumber;

    googleApiRequest('PUT', getSheetRange($range) . '?valueInputOption=RAW', [
        'range' => $range,
        'majorDimension' => 'ROWS',
        'values' => [[
            $payload['name'] ?? '',
            $payload['email'] ?? '',
            $payload['status'] ?? 'active',
        ]],
    ]);
}
```

## 15. Buat Fungsi Delete

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bawah `updateSheetRow()`
- ini fungsi CRUD terakhir

Kode:

```php
function deleteSheetRow(string $rowId): void
{
    $rowNumber = (int) $rowId;
    if ($rowNumber < 2) {
        throw new RuntimeException('ID baris tidak valid untuk delete.');
    }

    $sheetMeta = googleApiRequest('GET', GOOGLE_SHEETS_API_BASE . '/' . rawurlencode(GOOGLE_SHEET_ID));
    $sheetId = null;

    foreach (($sheetMeta['sheets'] ?? []) as $sheet) {
        if (($sheet['properties']['title'] ?? '') === GOOGLE_SHEET_NAME) {
            $sheetId = $sheet['properties']['sheetId'] ?? null;
            break;
        }
    }

    if ($sheetId === null) {
        throw new RuntimeException('Sheet tidak ditemukan.');
    }

    googleApiRequest('POST', GOOGLE_SHEETS_API_BASE . '/' . rawurlencode(GOOGLE_SHEET_ID) . ':batchUpdate', [
        'requests' => [[
            'deleteDimension' => [
                'range' => [
                    'sheetId' => (int) $sheetId,
                    'dimension' => 'ROWS',
                    'startIndex' => $rowNumber - 1,
                    'endIndex' => $rowNumber,
                ],
            ],
        ]],
    ]);
}
```

## 16. Tangani Form POST

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh setelah semua fungsi selesai
- ini awal blok logika runtime
- letaknya sebelum HTML `<!DOCTYPE html>`

Kode:

```php
$flash = null;
$flashType = 'info';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $formAction = $_POST['form_action'] ?? '';
    $rowId = trim((string) ($_POST['row_id'] ?? ''));
    $payload = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'active')),
    ];

    if ($formAction === 'create') {
        appendSheetRow($payload);
        $flashType = 'success';
        $flash = 'Baris baru berhasil ditambahkan ke Google Sheets.';
    }

    if ($formAction === 'update' && $rowId !== '') {
        updateSheetRow($rowId, $payload);
        $flashType = 'success';
        $flash = 'Baris berhasil diperbarui.';
    }

    if ($formAction === 'delete' && $rowId !== '') {
        deleteSheetRow($rowId);
        $flashType = 'success';
        $flash = 'Baris berhasil dihapus.';
    }
}
```

## 17. Ambil Data Setelah Request

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh tepat di bawah blok penanganan `POST`
- masih sebelum bagian HTML dimulai

Kode:

```php
$items = fetchSheetRows();
```

Lalu data ini ditampilkan ke tabel dan ringkasan dashboard.

## 18. Tampilkan Form HTML

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bagian HTML `<body>`
- di dalam card form
- biasanya di tengah layout dashboard

Contoh kode:

```php
<form method="post" class="form">
    <input type="hidden" name="form_action" value="<?= h($formMode) ?>">
    <input type="hidden" name="row_id" value="<?= h((string) ($selectedItem['id'] ?? '')) ?>">

    <label for="name">Name</label>
    <input type="text" id="name" name="name" value="<?= h($selectedItem['name'] ?? '') ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= h($selectedItem['email'] ?? '') ?>" required>

    <label for="status">Status</label>
    <select id="status" name="status" required>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="pending">Pending</option>
    </select>

    <button type="submit" class="primary">Simpan</button>
</form>
```

## 19. Tampilkan Data ke Tabel

File yang diedit:
- [index.php](index.php)

Letak kode:
- taruh di bagian HTML bawah
- setelah card form dan card ringkasan
- di section tabel data

Contoh kode:

```php
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= h((string) $item['id']) ?></td>
                <td><?= h($item['name']) ?></td>
                <td><?= h($item['email']) ?></td>
                <td><?= h($item['status']) ?></td>
                <td>
                    <a href="index.php?edit=<?= h((string) $item['id']) ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

## 20. Styling Dashboard

File yang diedit:
- [style.css](style.css)

Letak kode:
- isi styling dari atas file CSS
- kelompokkan dari variable, layout, card, form, lalu tabel

Beberapa bagian penting:

1. layout wrapper
2. sidebar
3. hero card
4. summary card
5. form styling
6. table styling

Contoh root variable:

```css
:root {
    --bg: #476375;
    --surface: #fffdfb;
    --teal: #2990ad;
    --teal-strong: #1f7691;
    --danger: #b74141;
}
```

## 21. Susunan Letak Kode di `index.php`

Agar tidak bingung, urutan isi file [index.php](index.php) sebaiknya seperti ini:

1. pembuka PHP dan `declare(strict_types=1);`
2. anti-cache dan baca config
3. konstanta API
4. helper sederhana seperti `h()`
5. fungsi service account
6. fungsi JWT dan access token
7. fungsi request API
8. helper range
9. fungsi CRUD
10. proses `POST`
11. ambil data sheet
12. siapkan variabel tampilan
13. HTML halaman

## 22. Bagian yang Wajib Diganti Mahasiswa

Sebelum project dijalankan, setiap mahasiswa wajib mengganti bagian berikut:

1. `google_sheet_id` di file `config.php`
2. file `google-service-account.json` dengan file milik sendiri
3. spreadsheet target dengan file Google Sheets milik sendiri
4. akses share spreadsheet ke `client_email` service account milik sendiri

Contoh yang boleh sama:
- nama file `config.php`
- nama file `index.php`
- nama kolom `name`, `email`, `status`

Contoh yang tidak boleh sama:
- ID spreadsheet
- isi file JSON service account
- email service account

## 23. Error yang Pernah Terjadi Saat Coding

### 1. Spreadsheet masih format Excel

Error:

```text
The document must not be an Office file
```

Penyebab:
- file masih `.xlsx`

Solusi:
- `Save as Google Sheets`

### 2. Service account belum punya akses

Gejala:
- read atau write gagal

Solusi:
- share spreadsheet ke `client_email` service account sebagai `Editor`

### 3. Endpoint append salah

Gejala:
- request create gagal
- muncul halaman HTML error Google

Penyebab:
- request diarahkan ke endpoint value biasa

Solusi:
- gunakan endpoint:

```text
/values/{range}:append
```

### 4. Halaman lama masih tercache

Gejala:
- kode terbaru tidak tampil di browser

Solusi:
- tambahkan:

```php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
```

- lakukan `Ctrl + F5`

## 24. Cara Test

### Test Read

1. buka halaman
2. pastikan data di tabel sesuai isi spreadsheet

### Test Create

1. isi form
2. submit
3. cek data masuk ke spreadsheet

### Test Update

1. klik `Edit`
2. ubah data
3. submit
4. cek perubahan di spreadsheet

### Test Delete

1. klik `Delete`
2. konfirmasi
3. cek data hilang dari spreadsheet

## 25. Validasi yang Bisa Dipakai

Anda bisa cek sintaks PHP dengan:

```powershell
php -l index.php
php -l config.php
```

## 26. Kesimpulan

Dengan pendekatan ini, kita bisa membuat CRUD sederhana tanpa database tradisional. Google Sheets dipakai sebagai penyimpanan data, sementara PHP native menangani:

1. autentikasi service account
2. komunikasi ke Google Sheets API
3. proses CRUD
4. tampilan dashboard

## 27. Ringkasan Alur Coding

Urutan implementasi:

1. buat `config.php`
2. tambahkan pembuka dan load config di `index.php`
3. tambahkan konstanta API
4. buat helper sederhana
5. baca service account JSON
6. buat JWT
7. ambil access token Google
8. buat helper request API
9. buat fungsi `fetchSheetRows`
10. buat fungsi `appendSheetRow`
11. buat fungsi `updateSheetRow`
12. buat fungsi `deleteSheetRow`
13. proses request form
14. render form dan tabel ke halaman

## 28. Referensi File di Project

- [index.php](index.php)
- [config.php](config.php)
- [style.css](style.css)
- [LANGKAH-LANGKAH-SETUP-GOOGLE-SHEETS.md](LANGKAH-LANGKAH-SETUP-GOOGLE-SHEETS.md)
