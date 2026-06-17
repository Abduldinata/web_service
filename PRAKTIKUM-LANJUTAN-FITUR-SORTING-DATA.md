# Praktikum Lanjutan Fitur Sorting Data pada CRUD Google Sheets

Dokumen ini dipakai untuk menambahkan satu fitur lanjutan pada project CRUD Google Sheets, yaitu:

1. sorting data

Sorting data artinya pengguna bisa mengurutkan data berdasarkan:

1. `name`
2. `email`
3. `status`
4. `id`

serta bisa memilih arah urutan:

1. `asc`
2. `desc`

Catatan untuk mahasiswa:

- gunakan project masing-masing
- gunakan spreadsheet masing-masing
- gunakan service account masing-masing
- dokumen ini fokus pada penambahan fitur sorting, bukan setup awal project

## Tujuan Praktikum

Setelah praktikum ini selesai, aplikasi akan memiliki kemampuan:

1. mengurutkan data berdasarkan field tertentu
2. mengurutkan data dari kecil ke besar atau besar ke kecil
3. menggabungkan sorting dengan search dan filter status

## File yang Akan Diubah

File yang diubah:

1. [index.php](C:/xamppp/htdocs/webservice/index.php)
2. [style.css](C:/xamppp/htdocs/webservice/style.css)

## Gambaran Perubahan

Di [index.php](C:/xamppp/htdocs/webservice/index.php), kita akan:

1. menambah fungsi `sortSheetRows()`
2. membaca parameter `sort_by`
3. membaca parameter `sort_dir`
4. memvalidasi field sorting
5. menerapkan sorting ke data hasil filter
6. menambah input sorting pada form filter
7. memastikan export CSV mengikuti hasil sorting

Di [style.css](C:/xamppp/htdocs/webservice/style.css), kita akan:

1. mengubah grid form filter agar muat kontrol sorting tambahan

## Langkah 1. Tambahkan Fungsi Sorting

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari fungsi `filterSheetRows()`
- tambahkan fungsi baru tepat di bawah `filterSheetRows()`

Kode yang ditambahkan:

```php
function sortSheetRows(array $rows, string $sortBy, string $sortDirection): array
{
    usort($rows, static function (array $left, array $right) use ($sortBy, $sortDirection): int {
        $leftValue = match ($sortBy) {
            'id' => (int) ($left['id'] ?? 0),
            'email' => strtolower((string) ($left['email'] ?? '')),
            'status' => strtolower((string) ($left['status'] ?? '')),
            default => strtolower((string) ($left['name'] ?? '')),
        };

        $rightValue = match ($sortBy) {
            'id' => (int) ($right['id'] ?? 0),
            'email' => strtolower((string) ($right['email'] ?? '')),
            'status' => strtolower((string) ($right['status'] ?? '')),
            default => strtolower((string) ($right['name'] ?? '')),
        };

        $result = $leftValue <=> $rightValue;

        return $sortDirection === 'desc' ? -$result : $result;
    });

    return $rows;
}
```

Penjelasan:

1. `usort()` dipakai untuk mengurutkan array data
2. `sortBy` menentukan kolom mana yang diurutkan
3. `sortDirection` menentukan arah urutan

## Langkah 2. Tambahkan Variabel Sorting

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari bagian variabel:
  `$searchQuery`, `$statusFilter`, `$allowedStatuses`
- tambahkan variabel baru tepat di bawah bagian itu

Kode yang ditambahkan:

```php
$sortBy = trim((string) ($_GET['sort_by'] ?? 'name'));
$sortDirection = trim((string) ($_GET['sort_dir'] ?? 'asc'));
$allowedSortFields = ['id', 'name', 'email', 'status'];
$allowedSortDirections = ['asc', 'desc'];
```

Fungsi bagian ini:

1. `sortBy` menyimpan nama kolom yang dipakai untuk sorting
2. `sortDirection` menyimpan arah urutan
3. `allowedSortFields` membatasi field yang boleh dipakai
4. `allowedSortDirections` membatasi arah urutan

## Langkah 3. Validasi Input Sorting

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari bagian validasi `statusFilter`
- tambahkan validasi sorting tepat di bawahnya

Kode yang ditambahkan:

```php
if (!in_array($sortBy, $allowedSortFields, true)) {
    $sortBy = 'name';
}

if (!in_array($sortDirection, $allowedSortDirections, true)) {
    $sortDirection = 'asc';
}
```

Tujuan:

1. mencegah input `sort_by` tidak valid
2. mencegah input `sort_dir` tidak valid

## Langkah 4. Terapkan Sorting Setelah Filter

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari bagian:

```php
$filteredItems = filterSheetRows($items, $searchQuery, $statusFilter);
```

- tambahkan tepat di bawahnya:

```php
$filteredItems = sortSheetRows($filteredItems, $sortBy, $sortDirection);
```

Penjelasan:

Urutan proses sekarang menjadi:

1. baca semua data
2. lakukan pencarian
3. lakukan filter status
4. lakukan sorting

## Langkah 5. Ubah URL Export CSV

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari bagian:

```php
$exportUrl = 'index.php?export=csv&search=' . urlencode($searchQuery) . '&status=' . urlencode($statusFilter);
```

- ganti menjadi:

```php
$exportUrl = 'index.php?export=csv&search=' . urlencode($searchQuery) . '&status=' . urlencode($statusFilter) . '&sort_by=' . urlencode($sortBy) . '&sort_dir=' . urlencode($sortDirection);
```

Tujuan:

agar file CSV hasil export mengikuti hasil sorting yang sedang aktif.

## Langkah 6. Tambahkan Input Sorting pada Form Filter

File yang diedit:
- [index.php](C:/xamppp/htdocs/webservice/index.php)

Letak kode:
- cari form filter yang sudah berisi input pencarian dan filter status
- tambahkan 2 blok berikut sebelum tombol `Terapkan`

Kode yang ditambahkan:

```php
<div>
    <label for="sort_by">Urutkan berdasarkan</label>
    <select id="sort_by" name="sort_by">
        <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Name</option>
        <option value="email" <?= $sortBy === 'email' ? 'selected' : '' ?>>Email</option>
        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
        <option value="id" <?= $sortBy === 'id' ? 'selected' : '' ?>>ID</option>
    </select>
</div>
<div>
    <label for="sort_dir">Arah urutan</label>
    <select id="sort_dir" name="sort_dir">
        <option value="asc" <?= $sortDirection === 'asc' ? 'selected' : '' ?>>A-Z / Kecil-Besar</option>
        <option value="desc" <?= $sortDirection === 'desc' ? 'selected' : '' ?>>Z-A / Besar-Kecil</option>
    </select>
</div>
```

Fungsi:

1. dropdown pertama memilih kolom sorting
2. dropdown kedua memilih arah sorting

## Langkah 7. Ubah Grid CSS untuk Form Filter

File yang diedit:
- [style.css](C:/xamppp/htdocs/webservice/style.css)

Letak kode:
- cari selector `.filter-grid`

Kode lama:

```css
.filter-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1.6fr) minmax(180px, 0.8fr) auto;
    gap: 14px;
    align-items: end;
}
```

Ganti menjadi:

```css
.filter-grid {
    display: grid;
    grid-template-columns: minmax(240px, 1.5fr) minmax(170px, 0.8fr) minmax(170px, 0.8fr) minmax(170px, 0.8fr) auto;
    gap: 14px;
    align-items: end;
}
```

Tujuan:

agar form filter sekarang bisa memuat:

1. input search
2. filter status
3. sort by
4. sort direction
5. tombol submit

## Langkah 8. Uji Hasil Praktikum

Setelah semua perubahan selesai:

1. simpan `index.php`
2. simpan `style.css`
3. reload halaman dengan `Ctrl + F5`

Lalu uji:

### Uji Sorting Berdasarkan Name

1. pilih `Urutkan berdasarkan = Name`
2. pilih `Arah urutan = asc`
3. klik `Terapkan`
4. pastikan data urut A-Z

### Uji Sorting Berdasarkan Email

1. pilih `Urutkan berdasarkan = Email`
2. pilih `Arah urutan = desc`
3. klik `Terapkan`
4. pastikan data urut Z-A

### Uji Sorting Berdasarkan Status

1. pilih `Urutkan berdasarkan = Status`
2. pilih `Arah urutan = asc`
3. klik `Terapkan`
4. pastikan data diurutkan berdasarkan status

### Uji Sorting Bersama Search dan Filter

1. isi pencarian
2. pilih status tertentu
3. pilih kolom sorting
4. pilih arah sorting
5. klik `Terapkan`
6. pastikan hasil tetap konsisten

### Uji Export CSV

1. terapkan sorting tertentu
2. klik `Export CSV`
3. pastikan isi file CSV mengikuti urutan yang tampil di tabel

## Langkah 9. Validasi Sintaks PHP

Jalankan:

```powershell
php -l index.php
```

Jika benar, hasilnya:

```text
No syntax errors detected in index.php
```

## Ringkasan Perubahan

Di [index.php](C:/xamppp/htdocs/webservice/index.php):

1. tambah `sortSheetRows()`
2. tambah variabel `sortBy`
3. tambah variabel `sortDirection`
4. tambah validasi field sorting
5. tambah penerapan sorting setelah filter
6. tambah parameter sorting ke `exportUrl`
7. tambah input `sort_by`
8. tambah input `sort_dir`

Di [style.css](C:/xamppp/htdocs/webservice/style.css):

1. ubah `.filter-grid` agar muat kontrol sorting tambahan
