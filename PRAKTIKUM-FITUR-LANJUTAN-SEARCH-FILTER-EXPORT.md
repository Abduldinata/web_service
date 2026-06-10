UNIVERSITAS DUTA BANGSA SURAKARTAWeb ServiceDosen: Ridwan Dwi Irawan, S. Kom., M. Kom.   Praktikum Fitur Lanjutan CRUD Google Sheets   Dokumen ini dipakai untuk menambahkan 4 perubahan pada project CRUD Google Sheets:   Layout dibuat lebih lebar atau full   Fitur pencarian data   Fitur filter status   Fitur export CSV   Dokumen ini ditulis agar mahasiswa bisa mengikuti langkah demi langkah dan langsung menyalin kode ke file yang benar.  Catatan untuk mahasiswa:Gunakan project masing-masing   Gunakan spreadsheet masing-masing   Gunakan service account masing-masing   Dokumen ini fokus pada penambahan fitur, bukan setup awal Google Sheets API   Tujuan PraktikumSetelah praktikum ini selesai, aplikasi akan memiliki kemampuan:   Menampilkan layout lebih lebar   Mencari data berdasarkan name, email, atau status   Memfilter data berdasarkan status   Mengunduh data hasil filter ke file CSV   File yang Akan DiubahFile yang diubah:   index.php (C:/xamppp/htdocs/webservice/index.php)   style.css (C:/xamppp/htdocs/webservice/style.css)   Gambaran PerubahanDi index.php, kita akan:   Menambah fungsi filter data   Menambah fungsi export CSV   Membaca parameter search dan status dari URL   Memfilter data sebelum ditampilkan   Menambahkan form pencarian dan filter   Menambahkan tombol export CSV   Di style.css, kita akan:   Mengubah wrapper utama agar lebih lebar   Menambah styling form filter   Menambah styling tombol export   Menambah styling keadaan data kosong   Langkah-Langkah PraktikumLangkah 1. Tambahkan Fungsi Filter dan Export CSVFile yang diedit: index.php   Letak kode: Cari fungsi getStatusLabel(), tambahkan fungsi baru tepat di bawah getStatusLabel().   Kode yang ditambahkan:PHPfunction filterSheetRows(array $rows, string $search, string $status): array [cite: 49]
[cite_start]{ [cite: 50]
    return array_values(array_filter($rows, static function (array $row) use ($search, $status): bool { [cite: 51, 52]
        $matchesSearch = true; [cite: 53]
        $matchesStatus = true; [cite: 54]
        
        if ($search !== '') { [cite: 55]
            $haystack = strtolower(trim((string)($row['name'] ?? '') . ' ' . (string)($row['email'] ?? '') . ' ' . (string)($row['status'] ?? ''))); [cite: 57, 58, 61, 63, 64]
            $matchesSearch = str_contains($haystack, strtolower($search)); [cite: 65]
        } [cite: 56]
        
        if ($status !== '' && $status !== 'all') { [cite: 66]
            $matchesStatus = strtolower(getStatusLabel($row)) === strtolower($status); [cite: 67]
        } [cite: 68]
        
        return $matchesSearch && $matchesStatus; [cite: 69]
    })); [cite: 70]
} [cite: 71]

function exportRowsAsCsv(array $rows): never [cite: 77]
[cite_start]{ [cite: 78]
    header('Content-Type: text/csv; charset=UTF-8'); [cite: 79]
    header('Content-Disposition: attachment; filename="google-sheets-data.csv"'); [cite: 80]
    
    $output = fopen('php://output', 'wb'); [cite: 81]
    if ($output === false) { 
        exit; [cite: 82]
    } [cite: 83]
    
    fwrite($output, "\xEF\xBB\xBF"); [cite: 84]
    fputcsv($output, ['id', 'name', 'email', 'status']); [cite: 84, 87, 88]
    
    foreach ($rows as $row) { [cite: 85]
        fputcsv($output, [ [cite: 86]
            (string) ($row['id'] ?? ''), [cite: 89, 90]
            (string) ($row['name'] ?? ''), [cite: 91, 92, 94]
            (string) ($row['email'] ?? ''), [cite: 94]
            (string) ($row['status'] ?? ''), [cite: 93, 94]
        ]); [cite: 95]
    } [cite: 96]
    
    fclose($output); [cite: 98]
    exit; [cite: 99]
} [cite: 97]
Penjelasan: * filterSheetRows() dipakai untuk menyaring data berdasarkan kata kunci dan status. * exportRowsAsCsv() dipakai untuk mengunduh data yang sedang tampil ke file CSV.   Langkah 2. Tambahkan Variabel Search dan FilterFile yang diedit: index.php   Letak kode: Cari bagian variabel awal: $flash, $flashType, $selectedItem, $detailItem, $requestMethod. Tambahkan variabel baru di bawah bagian itu.   Kode yang ditambahkan:PHP$filteredItems = []; [cite: 115]
$searchQuery = trim((string) ($_GET['search'] ?? '')); [cite: 116]
$statusFilter = trim((string) ($_GET['status'] ?? 'all')); [cite: 117]
$allowedStatuses = ['all', 'active', 'inactive', 'pending']; [cite: 118]

if (!in_array($statusFilter, $allowedStatuses, true)) { [cite: 119]
    $statusFilter = 'all'; [cite: 120]
} [cite: 121]
Fungsi bagian ini: * searchQuery menyimpan kata pencarian * statusFilter menyimpan filter status * allowedStatuses membatasi status yang valid   Langkah 3. Terapkan Filter Setelah Data DibacaFile yang diedit: index.php   Letak kode: Cari bagian $items = fetchSheetRows(); lalu ubah menjadi kode baru.   Kode yang ditambahkan/diubah:PHP$items = fetchSheetRows(); [cite: 133]
$filteredItems = filterSheetRows($items, $searchQuery, $statusFilter); [cite: 134]

if (isset($_GET['export']) && $_GET['export'] === 'csv') { [cite: 135]
    exportRowsAsCsv($filteredItems); [cite: 135]
} [cite: 136]
Penjelasan: * Semua data tetap dibaca dari sheet. * Data yang ditampilkan di layar menggunakan $filteredItems. * Jika URL berisi export=csv, maka data hasil filter akan diunduh.   Langkah 4. Tambahkan Fallback Saat ErrorFile yang diedit: index.php   Letak kode: Cari blok catch } catch (Throwable $exception) { ... } lalu ubah kodenya.   Kode yang ditambahkan/diubah:PHP} catch (Throwable $exception) { [cite: 155]
    $flashType = 'error'; [cite: 156]
    $flash = $exception->getMessage(); [cite: 157]
    $loadError = $exception->getMessage(); [cite: 158]
    $filteredItems = []; [cite: 159]
} [cite: 160]
Tujuan: Supaya jika terjadi error, variabel $filteredItems tetap aman digunakan.   Langkah 5. Ubah Perhitungan Statistik Agar Berdasarkan FilterFile yang diedit: index.php   Letak kode: Cari blok perhitungan statistik yang berisi $totalItems, $activeItems, $statusGroups, $performers, kemudian ganti.   Kode yang ditambahkan/diubah:PHP$totalItems = count($filteredItems); [cite: 175]
$allItemsCount = count($items); [cite: 176]
$activeItems = count(array_filter($filteredItems, static fn (array $item): bool => strtolower(getStatusLabel($item)) === 'active')); [cite: 177, 178]
$statusGroups = count(array_unique(array_map(static fn (array $item): string => strtolower(getStatusLabel($item)), $filteredItems))); [cite: 179, 180, 184]
$heroItem = $detailItem ?? ($items[0] ?? null); [cite: 181]
$performers = array_slice($filteredItems, 0, 3); [cite: 182]
$renderStamp = date('Y-m-d H:i:s'); [cite: 183]

$exportUrl = 'index.php?export=csv&search=' . urlencode($searchQuery) . '&status=' . urlencode($statusFilter); [cite: 185, 186]
Penjelasan: * Statistik utama mengikuti hasil pencarian/filter. * allItemsCount menyimpan jumlah semua data asli. * exportUrl dipakai untuk tombol export CSV.   Langkah 6. Ubah Ringkasan Hero CardFile yang diedit: index.php   Letak kode: Cari bagian hero card yang menampilkan Status groups, lalu ganti kodenya.   Kode lama:HTML<small>Status groups</small> [cite: 201]
<strong><?= h((string) $statusGroups) ?></strong> [cite: 202]
Ganti menjadi:HTML<small>Total all rows</small> [cite: 203, 204]
<strong><?= h((string) $allItemsCount) ?></strong> [cite: 205]
Tujuan: Supaya user bisa melihat jumlah hasil filter dan jumlah data total.   Langkah 7. Tambahkan Tombol Export dan Reset FilterFile yang diedit: index.php   Letak kode: Cari bagian header tabel <div class="section-head table-head">. Ganti tombol lama <a href="index.php" class="ghost">Refresh Data</a> dengan struktur baru.   Ganti menjadi:HTML<div class="table-actions"> [cite: 217]
    <a href="<?= h($exportUrl) ?>" class="ghost">Export CSV</a> [cite: 218]
    <a href="index.php" class="ghost">Reset Filter</a> [cite: 218]
</div> [cite: 219]
Tujuan: * Export CSV untuk mengunduh data. * Reset Filter untuk menghapus pencarian dan filter.   Langkah 8. Tambahkan Form Search dan FilterFile yang diedit: index.php   Letak kode: Cari bagian tabel data, letakkan form ini tepat di atas <div class="table-wrap">.   Kode yang ditambahkan:HTML<form method="get" class="filter-form"> [cite: 236]
    <div class="filter-grid"> [cite: 237]
        <div> [cite: 238]
            <label for="search">Cari data</label> [cite: 239]
            <input type="text" id="search" name="search" value="<?= h($searchQuery) ?>" placeholder="Cari nama, email, atau status"> [cite: 239, 240, 241, 242, 243, 244, 245]
        </div> [cite: 246]
        <div> [cite: 247]
            <label for="status_filter">Filter status</label> [cite: 248]
            <select id="status_filter" name="status"> [cite: 249]
                <?php foreach ($allowedStatuses as $statusOption): ?> [cite: 250]
                    <option value="<?= h($statusOption) ?>" <?= $statusFilter === $statusOption ? [cite_start]'selected' : '' ?>> [cite: 251, 252]
                        <?= h(ucfirst($statusOption)) ?> [cite: 253]
                    </option> [cite: 254]
                <?php endforeach; ?> [cite: 255]
            </select> [cite: 256]
        </div> [cite: 257]
        <div class="filter-submit"> [cite: 258]
            <button type="submit" class="primary">Terapkan</button> [cite: 259]
        </div> [cite: 260]
    </div> [cite: 261]
</form> [cite: 262]
Fitur yang dihasilkan: * User bisa mencari data. * User bisa memilih status tertentu. * Filter diproses menggunakan method GET.   Langkah 9. Ganti Tabel Agar Menggunakan Data Hasil FilterFile yang diedit: index.php   Letak kode: Cari loop tabel <?php foreach ($items as $item): ?> lalu ganti.   Ganti menjadi:PHP<?php foreach ($filteredItems as $item): ?> [cite: 282]
Tujuan: Tabel hanya menampilkan data hasil pencarian dan filter.   Langkah 10. Tambahkan Pesan Saat Data Tidak DitemukanFile yang diedit: index.php   Letak kode: Taruh tepat setelah endforeach; di dalam <tbody>.   Kode yang ditambahkan:HTML<?php if ($filteredItems === []): ?> [cite: 294]
    <tr> [cite: 295]
        <td colspan="5" class="empty-cell">Tidak ada data yang cocok dengan pencarian atau filter.</td> [cite: 296, 297]
    </tr> [cite: 298]
<?php endif; ?> [cite: 299]
Tujuan: User mendapatkan informasi yang jelas saat hasil filter kosong.   Langkah 11. Ubah Layout Menjadi Full WidthFile yang diedit: style.css   Letak kode: Cari selector .shell, ganti bagian width, max-width, dan margin.   Kode lama:CSS.shell { [cite: 308, 309]
    width: min(1280px, calc(100% - 48px)); [cite: 310]
    margin: 44px auto; [cite: 310]
}
Kode baru:CSS.shell { [cite: 311, 312]
    width: calc(100% - 32px); [cite: 313]
    max-width: 1680px; [cite: 314]
    margin: 16px auto; [cite: 315]
}
Hasil: * Layout menjadi jauh lebih lebar. * Tampilan tidak terlalu sempit di tengah.   Langkah 12. Tambahkan Styling Filter dan ExportFile yang diedit: style.css   Letak kode: Cari bagian setelah .table-card, tambahkan blok berikut di bawahnya.   Kode yang ditambahkan:CSS.table-actions {
    display: flex;
    gap: 10px; [cite: 322]
    flex-wrap: wrap;
}

.filter-form {
    margin-top: 18px;
}

.filter-grid {
    display: grid;
    grid-template-columns: minmax(260px, 1.6fr) minmax(180px, 0.8fr) auto; [cite: 323]
    gap: 14px;
    align-items: end;
}

.filter-submit {
    display: flex;
    align-items: end;
} [cite: 324]

.empty-cell {
    text-align: center;
    color: var(--muted);
    padding: 28px 14px; 
}
Fungsi: * .table-actions untuk tombol export dan reset. * .filter-form untuk jarak form filter. * .filter-grid untuk layout input filter. * .empty-cell untuk tampilan data kosong.   Langkah 13. Tambahkan Responsive untuk FilterFile yang diedit: style.css   Letak kode: Cari media query @media (max-width: 760px) { lalu tambahkan beberapa selector berikut di dalam media query tersebut.   Kode yang ditambahkan:CSS.table-actions,
.topbar,
.topbar-left,
.members,
.table-head,
.section-head,
.card-heading,
.money-line {
    flex-direction: column;
    align-items: flex-start; [cite: 327]
}

.filter-grid {
    grid-template-columns: 1fr;
}

.filter-submit,
.filter-submit .primary {
    width: 100%;
} [cite: 328]
Catatan: Jika di file sudah ada selector lain dalam grup ini, cukup tambahkan .table-actions pada grup dan tambahkan juga blok berikut:   CSS.filter-grid {
    grid-template-columns: 1fr;
} [cite: 329]

.filter-submit,
.filter-submit .primary {
    width: 100%;
}
Tujuan: Form filter tetap rapi di layar kecil.   Langkah 14. Uji Hasil PraktikumSetelah semua perubahan selesai dilakukan:Simpan file index.php   Simpan file style.css   Reload halaman web dengan menekan kombinasi Ctrl + F5   Lalu lakukan serangkaian pengujian fitur berikut:   Uji Search   Ketik nama pada kolom pencarian.   Klik Terapkan.   Pastikan hanya data yang cocok yang tampil di dalam tabel.   Uji Filter Status   Pilih salah satu status: Active, Inactive, atau Pending.   Klik Terapkan.   Pastikan hanya data dengan status tersebut yang muncul.   Uji Export CSV   Lakukan pencarian atau pemfilteran data terlebih dahulu.   Klik tombol Export CSV.   Buka file CSV hasil unduhan dan pastikan isinya sesuai dengan data yang tampil di tabel web.   Uji Layout   Lihat pada tampilan desktop, pastikan dashboard menjadi lebih lebar.   Lihat pada tampilan mobile, pastikan susunan form filter turun ke bawah dan tetap rapi.   Langkah 15. Validasi Sintaks PHPJalankan perintah ini di terminal/command prompt:Bashphp -l index.php
Jika implementasi kode benar dan tidak ada kesalahan ketik, maka hasilnya adalah:PlaintextNo syntax errors detected in index.php
Ringkasan PerubahanDi index.php:Tambah fungsi filterSheetRows()   Tambah fungsi exportRowsAsCsv()   Tambah variabel search, status, dan allowedStatuses   Tambah proses filter data setelah fetchSheetRows()   Tambah tombol Export CSV   Tambah form pencarian dan filter status   Ganti loop tabel data dari menggunakan $items menjadi $filteredItems   Tambah pesan informatif jika data kosong / tidak ditemukan   Di style.css:Ubah class .shell agar tampilannya menjadi lebih lebar   Tambah styling untuk .table-actions   Tambah styling untuk .filter-form   Tambah styling untuk .filter-grid   Tambah styling untuk .filter-submit   Tambah styling untuk .empty-cell   Tambah rule responsive media query untuk filter   INSTITUSI TERAKREDITASI UNGGUL
