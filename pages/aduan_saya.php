<?php
// Halaman Pengaduan untuk User/Pegawai
$msg = '';
$msgType = 'success';
$userNip = $_SESSION['user_nip'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// HANDLE POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'tambah') {
            $jenis_laporan = sanitize($_POST['jenis_laporan']);
            $tanggal_kejadian = $_POST['tanggal_kejadian'] ?: date('Y-m-d');
            $keterangan = sanitize($_POST['keterangan']);

            // Handle file upload
            $bukti = null;
            if (isset($_FILES['bukti']) && $_FILES['bukti']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = safeUploadFile($_FILES['bukti'], 'uploads/pengaduan/', 'aduan');
                if (!$uploadResult['success']) {
                    $msg = $uploadResult['error'];
                    $msgType = 'danger';
                } else {
                    $bukti = $uploadResult['filename'];
                }
            }

            if ($msgType !== 'danger') {
                $stmt = $db->prepare("INSERT INTO pengaduan (nip, jenis_laporan, tanggal_kejadian, keterangan, bukti, status) VALUES (?,?,?,?,?,'pending')");
                $stmt->bind_param('sssss', $userNip, $jenis_laporan, $tanggal_kejadian, $keterangan, $bukti);
                if ($stmt->execute()) {
                    $msg = 'Pengaduan berhasil dikirim! Admin akan meninjau laporan Anda.';
                } else {
                    $msg = 'Gagal mengirim pengaduan.';
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Fetch user's own pengaduan
$filter_status = sanitize($_GET['status'] ?? '');
$params = [$userNip];
$types = 's';
$where = "WHERE a.nip = ?";

if ($filter_status) {
    $where .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$stmt = $db->prepare("SELECT a.* FROM pengaduan a $where ORDER BY a.id DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pengaduan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Stats for user's own pengaduan
$stats = [];
foreach (['pending','approved','rejected'] as $s) {
    $stmtStat = $db->prepare("SELECT COUNT(*) as c FROM pengaduan WHERE nip=? AND status=?");
    $stmtStat->bind_param('ss', $userNip, $s);
    $stmtStat->execute();
    $stats[$s] = $stmtStat->get_result()->fetch_assoc()['c'] ?? 0;
}

$statusConfig = [
    'pending' => ['label' => 'Menunggu', 'bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock'],
    'approved' => ['label' => 'Ditanggapi', 'bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-check-circle'],
    'rejected' => ['label' => 'Ditolak', 'bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle']
];

$totalAduan = array_sum($stats);
?>

<style>
.aduan-header {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-radius: 16px;
    padding: 28px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.aduan-header-info h2 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
}

.aduan-header-info p {
    opacity: 0.9;
    font-size: 14px;
}

.aduan-stats {
    display: flex;
    gap: 20px;
}

.aduan-stat {
    text-align: center;
    padding: 12px 20px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
}

.aduan-stat-value {
    font-size: 24px;
    font-weight: 800;
}

.aduan-stat-label {
    font-size: 11px;
    opacity: 0.9;
    margin-top: 2px;
}

.aduan-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .aduan-grid { grid-template-columns: 1fr; }
    .aduan-stats { flex-wrap: wrap; gap: 10px; }
    .aduan-stat { flex: 1; min-width: 80px; }
}

.aduan-form-card {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
    position: sticky;
    top: 80px;
}

.aduan-form-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.aduan-form-header i { color: #f59e0b; }

.aduan-form-header h3 {
    font-size: 15px;
    font-weight: 700;
}

.aduan-form-body { padding: 20px; }

.riwayat-aduan {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
}

.riwayat-aduan-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.riwayat-aduan-header h3 {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.aduan-item {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    transition: background 0.2s;
}

.aduan-item:last-child { border-bottom: none; }

.aduan-item:hover { background: var(--bg); }

.filter-tabs {
    display: flex;
    gap: 6px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
}

.filter-tab {
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    color: var(--muted);
    transition: all 0.2s;
}

.filter-tab:hover { border-color: var(--accent); color: var(--accent); }

.filter-tab.active {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}
</style>

<!-- HEADER -->
<div class="aduan-header">
    <div class="aduan-header-info">
        <h2><i class="fas fa-comment-dots"></i> Pengaduan</h2>
        <p>Sampaikan keluhan atau laporan Anda kepada Admin</p>
    </div>
    <div class="aduan-stats">
        <div class="aduan-stat">
            <div class="aduan-stat-value"><?= $totalAduan ?></div>
            <div class="aduan-stat-label">Total Laporan</div>
        </div>
        <div class="aduan-stat">
            <div class="aduan-stat-value"><?= $stats['pending'] ?></div>
            <div class="aduan-stat-label">Menunggu</div>
        </div>
        <div class="aduan-stat">
            <div class="aduan-stat-value"><?= $stats['approved'] ?></div>
            <div class="aduan-stat-label">Ditanggapi</div>
        </div>
    </div>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<div class="aduan-grid">
    <!-- FORM PENGADUAN -->
    <div class="aduan-form-card">
        <div class="aduan-form-header">
            <i class="fas fa-plus-circle"></i>
            <h3>Buat Pengaduan Baru</h3>
        </div>
        <div class="aduan-form-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="tambah">

                <div class="form-group">
                    <label>Jenis Laporan</label>
                    <select name="jenis_laporan" class="form-control" required>
                        <option value="">-- Pilih Jenis Laporan --</option>
                        <option value="Lupa Absen Masuk">Lupa Absen Masuk</option>
                        <option value="Lupa Absen Pulang">Lupa Absen Pulang</option>
                        <option value="Telat">Telat</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tanggal Kejadian</label>
                    <input type="date" name="tanggal_kejadian" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label>Detail Kejadian</label>
                    <textarea name="keterangan" class="form-control" rows="4" required placeholder="Jelaskan secara detail apa yang ingin Anda laporkan..."></textarea>
                </div>

                <div class="form-group">
                    <label>Bukti Pendukung (opsional)</label>
                    <input type="file" name="bukti" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <small style="color:var(--muted);font-size:11px">Format: JPG, PNG, PDF, DOC (maks 2MB)</small>
                </div>

                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:12px;margin-bottom:16px">
                    <p style="margin:0;font-size:11px;color:#92400e;line-height:1.5">
                        <i class="fas fa-shield-alt"></i> Laporan bersifat <strong>rahasia</strong> dan hanya dapat dilihat oleh Admin.
                    </p>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <i class="fas fa-paper-plane"></i> Kirim Pengaduan
                </button>
            </form>
        </div>
    </div>

    <!-- RIWAYAT PENGADUAN -->
    <div class="riwayat-aduan">
        <div class="riwayat-aduan-header">
            <h3><i class="fas fa-history"></i> Riwayat Pengaduan</h3>
            <span style="font-size:13px;color:var(--muted)"><?= $totalAduan ?> laporan</span>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="?page=aduan_saya" class="filter-tab <?= !$filter_status ? 'active' : '' ?>">Semua (<?= $totalAduan ?>)</a>
            <?php foreach ($statusConfig as $key => $cfg): ?>
            <a href="?page=aduan_saya&status=<?= $key ?>" class="filter-tab <?= $filter_status === $key ? 'active' : '' ?>"><?= $cfg['label'] ?> (<?= $stats[$key] ?>)</a>
            <?php endforeach; ?>
        </div>

        <?php if (empty($pengaduan)): ?>
        <div style="text-align:center;padding:50px 20px">
            <i class="fas fa-comment-slash" style="font-size:40px;color:var(--border);margin-bottom:14px;display:block"></i>
            <div style="color:var(--muted);font-size:14px">Belum ada pengaduan<?= $filter_status ? ' dengan status ini' : '' ?></div>
        </div>
        <?php else: ?>
        <?php foreach ($pengaduan as $a):
            $cfg = $statusConfig[$a['status']] ?? $statusConfig['pending'];
        ?>
        <div class="aduan-item">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;flex-wrap:wrap;gap:8px">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <span class="badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>">
                        <i class="fas <?= $cfg['icon'] ?>"></i> <?= $cfg['label'] ?>
                    </span>
                    <?php if ($a['jenis_laporan']): ?>
                    <span class="badge badge-average"><?= sanitize($a['jenis_laporan']) ?></span>
                    <?php endif; ?>
                </div>
                <span style="font-size:11px;color:var(--muted)">
                    <i class="fas fa-calendar"></i> <?= formatTanggal($a['tanggal_kejadian']) ?>
                </span>
            </div>

            <p style="margin:0 0 10px;font-size:13px;line-height:1.6;color:var(--text)"><?= nl2br(sanitize($a['keterangan'])) ?></p>

            <?php if ($a['bukti']): ?>
            <div style="margin-bottom:8px">
                <a href="<?= getBaseUrl() ?>uploads/pengaduan/<?= htmlspecialchars($a['bukti']) ?>" target="_blank" style="font-size:12px;color:var(--accent);text-decoration:none">
                    <i class="fas fa-paperclip"></i> Lihat Bukti
                </a>
            </div>
            <?php endif; ?>

            <?php if ($a['status'] === 'rejected' && $a['alasan_penolakan']): ?>
            <div style="background:#fee2e2;padding:10px 12px;border-radius:8px;margin-top:8px">
                <p style="margin:0;font-size:12px;color:#991b1b;line-height:1.5">
                    <i class="fas fa-exclamation-circle"></i> <strong>Ditolak:</strong> <?= sanitize($a['alasan_penolakan']) ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($a['status'] === 'approved'): ?>
            <div style="background:#d1fae5;padding:10px 12px;border-radius:8px;margin-top:8px">
                <p style="margin:0;font-size:12px;color:#065f46;line-height:1.5">
                    <i class="fas fa-check-circle"></i> Pengaduan telah ditanggapi oleh Admin
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
