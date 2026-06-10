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

const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const GOOGLE_SHEETS_API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

define('GOOGLE_SHEET_ID', (string) ($appConfig['google_sheet_id'] ?? ''));
define('GOOGLE_SHEET_NAME', (string) ($appConfig['google_sheet_name'] ?? 'Sheet1'));
define('GOOGLE_SERVICE_ACCOUNT_FILE', (string) ($appConfig['google_service_account_file'] ?? (__DIR__ . '/google-service-account.json')));

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

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

function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

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

    $privateKey = @openssl_pkey_get_private($serviceAccount['private_key']);
    if ($privateKey === false) {
        throw new RuntimeException('Private key tidak bisa dibaca. Periksa format key di file service account.');
    }

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

function getSheetRange(string $range): string
{
    return GOOGLE_SHEETS_API_BASE . '/' . rawurlencode(GOOGLE_SHEET_ID) . '/values/' . rawurlencode($range);
}

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

function getStatusLabel(array $row): string
{
    return (string) ($row['status'] ?? '');
}

function filterSheetRows(array $rows, string $search, string $status): array
{
    return array_values(array_filter($rows, static function (array $row) use ($search, $status): bool {
        $matchesSearch = true;
        $matchesStatus = true;

        if ($search !== '') {
            $haystack = strtolower(trim((string) ($row['name'] ?? '') . ' ' . (string) ($row['email'] ?? '') . ' ' . (string) ($row['status'] ?? '')));
            $matchesSearch = str_contains($haystack, strtolower($search));
        }

        if ($status !== '' && $status !== 'all') {
            $matchesStatus = strtolower(getStatusLabel($row)) === strtolower($status);
        }

        return $matchesSearch && $matchesStatus;
    }));
}

function exportRowsAsCsv(array $rows): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="google-sheets-data.csv"');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
        exit;
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['id', 'name', 'email', 'status']);

    foreach ($rows as $row) {
        fputcsv($output, [
            (string) ($row['id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['email'] ?? ''),
            (string) ($row['status'] ?? ''),
        ]);
    }

    fclose($output);
    exit;
}

$flash = null;
$flashType = 'info';
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$allowedStatuses = ['all', 'active', 'inactive', 'pending'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $formAction = $_POST['form_action'] ?? '';
    $rowId = trim((string) ($_POST['row_id'] ?? ''));
    $payload = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'active')),
    ];

    try {
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
    } catch (\Throwable $e) {
        $flashType = 'error';
        $flash = 'Error: ' . $e->getMessage();
        $loadError = $e->getMessage();
        $filteredItems = [];
    }
}

$items = fetchSheetRows();
$filteredItems = filterSheetRows($items, $searchQuery, $statusFilter);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportRowsAsCsv($filteredItems);
}

$formMode = 'create';
$selectedItem = null;
$editId = trim((string) ($_GET['edit'] ?? ''));
if ($editId !== '') {
    foreach ($items as $item) {
        if ($item['id'] === $editId) {
            $selectedItem = $item;
            $formMode = 'update';
            break;
        }
    }
}

$totalItems = count($filteredItems);
$allItemsCount = count($items);
$activeItems = count(array_filter($filteredItems, static fn (array $item): bool => strtolower(getStatusLabel($item)) === 'active'));
$statusGroups = count(array_unique(array_map(static fn (array $item): string => strtolower(getStatusLabel($item)), $filteredItems)));
$heroItem = $items[0] ?? null;
$performers = array_slice($filteredItems, 0, 3);
$renderStamp = date('Y-m-d H:i:s');

$exportUrl = 'index.php?export=csv&search=' . urlencode($searchQuery) . '&status=' . urlencode($statusFilter);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Google Sheets CRUD</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                </div>
                <p class="brand-name">CRUD</p>
                <p class="brand-sub">Google Sheets</p>
            </div>
            <nav class="side-nav">
                <a href="index.php" class="side-link active">
                    <span class="side-icon">&#9783;</span>
                    Dashboard
                </a>
            </nav>
            <div class="profile-chip">
                <div class="avatar">M</div>
                <strong>Mahasiswa</strong>
                <p>Web Service</p>
            </div>
        </aside>
        <main class="main-panel">
            <div class="topbar">
                <div class="topbar-left">
                    <a href="index.php" class="back-link">&#8592; Kembali</a>
                    <nav class="top-menu">
                        <a href="index.php" class="current">Dashboard</a>
                    </nav>
                </div>
            </div>

            <?php if ($flash !== null): ?>
                <div class="flash <?= h($flashType) ?>"><?= h($flash) ?></div>
            <?php endif; ?>

            <div class="dashboard">
                <div class="hero-panel">
                    <div class="hero-card">
                        <div class="hero-copy">
                            <p class="eyebrow">Google Sheets CRUD</p>
                            <h1>Data</h1>
                            <div class="metric-list">
                                <div class="metric-item">
                                    <span class="metric-icon">&#128203;</span>
                                    <div>
                                        <small>Total Data</small>
                                        <strong><?= h((string) $totalItems) ?></strong>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-icon">&#10003;</span>
                                    <div>
                                        <small>Active</small>
                                        <strong><?= h((string) $activeItems) ?></strong>
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-icon">&#10007;</span>
                                    <div>
                                        <small>Total All</small>
                                        <strong><?= h((string) $allItemsCount) ?></strong>
                                    </div>
                                </div>
                            </div>
                            <a href="#form-section" class="cta-link">Tambah Data</a>
                        </div>
                        <div class="hero-illustration">
                            <div class="orbit orbit-a"></div>
                            <div class="orbit orbit-b"></div>
                            <div class="desk"></div>
                            <div class="figure">
                                <div class="figure-head"></div>
                                <div class="figure-body"></div>
                                <div class="figure-leg left"></div>
                                <div class="figure-leg right"></div>
                            </div>
                        </div>
                    </div>
                    <div class="rate-card">
                        <div class="rate-score">
                            <strong><?= h((string) $totalItems) ?></strong>
                            <span>total</span>
                        </div>
                        <div class="gauge">
                            <div class="gauge-ring"></div>
                            <div class="gauge-needle"></div>
                        </div>
                        <div class="sheet-meta">
                            <p>
                                Status
                                <span><?= $totalItems > 0 ? 'Terisi' : 'Kosong' ?></span>
                            </p>
                            <p>
                                Spreadsheet
                                <span><?= h(GOOGLE_SHEET_ID) ?></span>
                            </p>
                        </div>
                        <div class="tip-box">
                            <span class="tip-icon">&#9432;</span>
                            <p>Data dikelola langsung dari Google Sheets.</p>
                        </div>
                    </div>
                </div>

                <div class="content-grid">
                    <div id="form-section" class="dashboard-card">
                        <div class="card-heading">
                            <h2><?= $formMode === 'create' ? 'Tambah Data Baru' : 'Edit Data' ?></h2>
                            <?php if ($formMode === 'update'): ?>
                                <a href="index.php" class="ghost">&#10005; Batal</a>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="form">
                            <input type="hidden" name="form_action" value="<?= h($formMode) ?>">
                            <input type="hidden" name="row_id" value="<?= h((string) ($selectedItem['id'] ?? '')) ?>">

                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?= h($selectedItem['name'] ?? '') ?>" required>

                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= h($selectedItem['email'] ?? '') ?>" required>

                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="active" <?= (($selectedItem['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (($selectedItem['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                <option value="pending" <?= (($selectedItem['status'] ?? '') === 'pending') ? 'selected' : '' ?>>Pending</option>
                            </select>

                            <div class="actions">
                                <button type="submit" class="primary"><?= $formMode === 'create' ? 'Tambah ke Sheet' : 'Update Sheet' ?></button>
                            </div>
                        </form>
                    </div>

                    <div class="dashboard-card detail-box">
                        <h3>Cara Pakai</h3>
                        <p>Isi form untuk menambah data baru. Data akan langsung dikirim ke Google Sheets.</p>
                        <p>Klik <strong>Edit</strong> pada tabel untuk mengubah data. Klik <strong>Delete</strong> untuk menghapus.</p>
                        <p style="margin-bottom:0;">Pastikan file <code>google-service-account.json</code> sudah ada dan akses spreadsheet sudah dibagikan ke service account.</p>
                    </div>
                </div>

                <div class="table-card">
                    <div class="table-head">
                        <h3>Data dari Google Sheets</h3>
                        <div class="table-actions">
                            <span class="soft-badge"><?= h((string) $totalItems) ?></span>
                            <a href="<?= h($exportUrl) ?>" class="ghost">Export CSV</a>
                            <a href="index.php" class="ghost">Reset Filter</a>
                        </div>
                    </div>
                    <form method="get" class="filter-form">
                        <div class="filter-grid">
                            <div>
                                <label for="search">Cari data</label>
                                <input type="text" id="search" name="search" value="<?= h($searchQuery) ?>" placeholder="Cari nama, email, atau status">
                            </div>
                            <div>
                                <label for="status_filter">Filter status</label>
                                <select id="status_filter" name="status">
                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                        <option value="<?= h($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>>
                                            <?= h(ucfirst($statusOption)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-submit">
                                <button type="submit" class="primary">Terapkan</button>
                            </div>
                        </div>
                    </form>
                    <div class="table-wrap">
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
                                <?php foreach ($filteredItems as $item): ?>
                                    <tr>
                                        <td><?= h((string) $item['id']) ?></td>
                                        <td><?= h($item['name']) ?></td>
                                        <td><?= h($item['email']) ?></td>
                                        <td><span class="status-pill"><?= h($item['status']) ?></span></td>
                                        <td class="action-cell">
                                            <a href="index.php?edit=<?= h((string) $item['id']) ?>" class="ghost">Edit</a>
                                            <form method="post" onsubmit="return confirm('Hapus data ini?')">
                                                <input type="hidden" name="form_action" value="delete">
                                                <input type="hidden" name="row_id" value="<?= h((string) $item['id']) ?>">
                                                <button type="submit" class="danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($filteredItems === []): ?>
                                    <tr>
                                        <td colspan="5" class="empty-cell">Tidak ada data yang cocok dengan pencarian atau filter.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
