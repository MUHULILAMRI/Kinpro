<?php
// Halaman Kelola Izin - Admin Only
if (!$isAdminUser) {
    header("Location: index.php?page=dashboard");
    exit;
}

$msg = '';
$msgType = 'success';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $id_izin = (int)($_POST['id_izin'] ?? 0);

        if ($action === 'proses_izin' && $id_izin > 0) {
            $status = sanitize($_POST['status']);
            $catatan = sanitize($_POST['catatan_admin'] ?? '');

            // Validate status whitelist
            if (!in_array($status, ['approved', 'rejected'])) {
                $msg = 'Status tidak valid.';
                $msgType = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE izin SET status=?, catatan_admin=?, tanggal_respon=NOW() WHERE id_izin=?");
                $stmt->bind_param('ssi', $status, $catatan, $id_izin);
                
                if ($stmt->execute()) {
                    $msg = 'Pengajuan izin berhasil diproses!';
                } else {
                    $msg = 'Gagal memproses pengajuan.';
                    $msgType = 'danger';
                }
            }
        }

        if ($action === 'reset_kuota') {
            $id_pegawai_reset = (int)($_POST['id_pegawai_reset'] ?? 0);
            $jumlah_reset = (int)($_POST['jumlah_reset'] ?? 4);
            $catatan_reset = sanitize($_POST['catatan_reset'] ?? '');
            $adminId = (int)$_SESSION['user_id'];
            $bulanReset = date('Y-m');

            if ($id_pegawai_reset <= 0 || $jumlah_reset < 1 || $jumlah_reset > 4) {
                $msg = 'Data reset tidak valid.';
                $msgType = 'danger';
            } else {
                $stmt = $db->prepare("INSERT INTO reset_kuota_izin (id_pegawai, bulan, jumlah_reset, catatan, direset_oleh) VALUES (?,?,?,?,?)");
                $stmt->bind_param('isisi', $id_pegawai_reset, $bulanReset, $jumlah_reset, $catatan_reset, $adminId);
                if ($stmt->execute()) {
                    $msg = 'Kuota izin berhasil direset!';
                } else {
                    $msg = 'Gagal mereset kuota.';
                    $msgType = 'danger';
                }
            }
        }
    }
}

// Filter with prepared statements
$filterStatus = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$params = [];
$types = '';
$where = "WHERE 1=1";

if ($filterStatus) {
    $where .= " AND i.status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($search) {
    $searchParam = '%' . $search . '%';
    $where .= " AND (p.nama_lengkap LIKE ? OR p.nip LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}

// Get all izin with pegawai data
$stmt = $db->prepare("SELECT i.*, p.nama_lengkap, p.nip, p.jabatan, p.foto_profil 
          FROM izin i 
          JOIN pegawai p ON i.id_pegawai = p.id_pegawai 
          $where 
          ORDER BY i.status = 'pending' DESC, i.tanggal_pengajuan DESC");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$izinList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung kuota izin per pegawai bulan ini
$bulanIni = date('Y-m');
$kuotaPerPegawai = [];
$stmtKuota = $db->prepare("SELECT id_pegawai, COUNT(*) as total FROM izin WHERE DATE_FORMAT(tanggal_pengajuan, '%Y-%m') = ? GROUP BY id_pegawai");
$stmtKuota->bind_param('s', $bulanIni);
$stmtKuota->execute();
$kuotaResult = $stmtKuota->get_result();
while ($row = $kuotaResult->fetch_assoc()) {
    $kuotaPerPegawai[$row['id_pegawai']] = (int)$row['total'];
}

// Hitung total reset kuota per pegawai bulan ini
$resetPerPegawai = [];
$stmtReset = $db->prepare("SELECT id_pegawai, SUM(jumlah_reset) as total_reset FROM reset_kuota_izin WHERE bulan = ? GROUP BY id_pegawai");
$stmtReset->bind_param('s', $bulanIni);
$stmtReset->execute();
$resetResult = $stmtReset->get_result();
while ($row = $resetResult->fetch_assoc()) {
    $resetPerPegawai[$row['id_pegawai']] = (int)$row['total_reset'];
}
$batasIzinPerBulan = 4;

// Daftar pegawai untuk dropdown reset kuota
$pegawaiListReset = $db->query("SELECT id_pegawai, nama_lengkap, nip FROM pegawai ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = [
    'total' => count($izinList),
    'pending' => count(array_filter($izinList, fn($i) => $i['status'] === 'pending')),
    'disetujui' => count(array_filter($izinList, fn($i) => $i['status'] === 'approved')),
    'ditolak' => count(array_filter($izinList, fn($i) => $i['status'] === 'rejected')),
];

// Jenis izin mapping
$jenisIzinLabels = [
    'cuti_tahunan' => ['label' => 'Cuti Tahunan', 'icon' => 'umbrella-beach', 'color' => '#3b82f6'],
    'sakit' => ['label' => 'Sakit', 'icon' => 'briefcase-medical', 'color' => '#ef4444'],
    'izin_khusus' => ['label' => 'Izin Khusus', 'icon' => 'calendar-day', 'color' => '#8b5cf6'],
    'dinas_luar' => ['label' => 'Dinas Luar', 'icon' => 'plane', 'color' => '#10b981'],
];

$statusLabels = [
    'pending' => ['label' => 'Menunggu', 'class' => 'badge-average', 'icon' => 'clock'],
    'approved' => ['label' => 'Disetujui', 'class' => 'badge-excellent', 'icon' => 'check-circle'],
    'rejected' => ['label' => 'Ditolak', 'class' => 'badge-poor', 'icon' => 'times-circle'],
];
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

.stat-card-mini {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon-mini {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.stat-info-mini h4 {
    font-size: 22px;
    font-weight: 800;
    line-height: 1;
}

.stat-info-mini p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 4px;
}

.izin-table-card {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
}

.table-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.filter-tabs {
    display: flex;
    gap: 6px;
}

.filter-tab {
    padding: 8px 14px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    color: var(--muted);
    background: white;
    transition: all 0.2s;
}

.filter-tab:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.filter-tab.active {
    background: var(--accent);
    border-color: var(--accent);
    color: white;
}

.izin-row {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border-light);
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 16px;
    align-items: center;
}

.izin-row:last-child {
    border-bottom: none;
}

.izin-row:hover {
    background: var(--bg);
}

.pegawai-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.pegawai-avatar {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
    overflow: hidden;
}

.pegawai-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pegawai-detail h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.pegawai-detail p {
    font-size: 12px;
    color: var(--muted);
}

.izin-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

.izin-periode {
    font-size: 13px;
}

.izin-periode .dates {
    font-weight: 600;
    color: var(--text);
}

.izin-periode .duration {
    font-size: 11px;
    color: var(--muted);
    margin-top: 2px;
}

.action-buttons {
    display: flex;
    gap: 6px;
}

.modal-body-izin {
    padding: 20px;
}

.izin-detail-card {
    background: var(--bg);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 16px;
}

.izin-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border);
}

.izin-detail-row:last-child {
    border-bottom: none;
}

.izin-detail-label {
    font-size: 13px;
    color: var(--muted);
}

.izin-detail-value {
    font-size: 13px;
    font-weight: 600;
    text-align: right;
}

.reason-box {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
    font-size: 13px;
    line-height: 1.6;
    color: var(--text);
}

.action-tabs {
    display: flex;
    gap: 8px;
    margin-top: 16px;
}

.action-tab {
    flex: 1;
    padding: 12px;
    border: 2px solid var(--border);
    border-radius: 10px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.action-tab:hover {
    border-color: var(--accent);
}

.action-tab.approve {
    border-color: var(--success);
    background: rgba(16, 185, 129, 0.05);
}

.action-tab.reject {
    border-color: var(--danger);
    background: rgba(239, 68, 68, 0.05);
}

.action-tab input {
    display: none;
}

.action-tab input:checked + .action-content {
    transform: scale(1.02);
}

.action-tab.approve input:checked ~ .action-content {
    color: var(--success);
}

.action-tab.reject input:checked ~ .action-content {
    color: var(--danger);
}

.action-content i {
    font-size: 24px;
    margin-bottom: 6px;
}

.action-content span {
    display: block;
    font-size: 13px;
    font-weight: 600;
}
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="stats-grid">
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background:#dbeafe;color:#2563eb">
            <i class="fas fa-list"></i>
        </div>
        <div class="stat-info-mini">
            <h4><?= $stats['total'] ?></h4>
            <p>Total Pengajuan</p>
        </div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background:#fef3c7;color:#d97706">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info-mini">
            <h4><?= $stats['pending'] ?></h4>
            <p>Menunggu</p>
        </div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background:#d1fae5;color:#059669">
            <i class="fas fa-check"></i>
        </div>
        <div class="stat-info-mini">
            <h4><?= $stats['disetujui'] ?></h4>
            <p>Disetujui</p>
        </div>
    </div>
    <div class="stat-card-mini">
        <div class="stat-icon-mini" style="background:#fee2e2;color:#dc2626">
            <i class="fas fa-times"></i>
        </div>
        <div class="stat-info-mini">
            <h4><?= $stats['ditolak'] ?></h4>
            <p>Ditolak</p>
        </div>
    </div>
</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
    <button class="btn btn-primary" onclick="document.getElementById('modalResetKuota').classList.add('show')">
        <i class="fas fa-redo-alt"></i> Reset Kuota Pegawai
    </button>
</div>

<!-- Table -->
<div class="izin-table-card">
    <div class="table-header">
        <div class="filter-tabs">
            <a href="?page=kelola_izin" class="filter-tab <?= !$filterStatus ? 'active' : '' ?>">Semua</a>
            <a href="?page=kelola_izin&status=pending" class="filter-tab <?= $filterStatus === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="?page=kelola_izin&status=approved" class="filter-tab <?= $filterStatus === 'approved' ? 'active' : '' ?>">Disetujui</a>
            <a href="?page=kelola_izin&status=rejected" class="filter-tab <?= $filterStatus === 'rejected' ? 'active' : '' ?>">Ditolak</a>
        </div>
        <form method="GET" style="display:flex;gap:8px">
            <input type="hidden" name="page" value="kelola_izin">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Cari pegawai..." value="<?= $search ?>" style="padding-left:36px">
            </div>
            <button type="submit" class="btn btn-outline"><i class="fas fa-filter"></i></button>
        </form>
    </div>

    <?php if (empty($izinList)): ?>
    <div style="padding:60px;text-align:center">
        <i class="fas fa-inbox" style="font-size:48px;color:var(--border);margin-bottom:16px"></i>
        <h4 style="font-size:16px;margin-bottom:6px">Tidak Ada Pengajuan</h4>
        <p style="font-size:13px;color:var(--muted)">Belum ada pengajuan izin yang sesuai filter.</p>
    </div>
    <?php else: ?>
    <?php foreach ($izinList as $izin): 
        $jenisInfo = $jenisIzinLabels[$izin['jenis_izin']] ?? ['label' => $izin['jenis_izin'], 'icon' => 'calendar', 'color' => '#64748b'];
        $statusInfo = $statusLabels[$izin['status']] ?? ['label' => $izin['status'], 'class' => '', 'icon' => 'question'];
        $start = new DateTime($izin['tanggal_mulai']);
        $end = new DateTime($izin['tanggal_selesai']);
        $duration = $start->diff($end)->days + 1;
        $kuotaTerpakai = $kuotaPerPegawai[$izin['id_pegawai']] ?? 0;
        $totalReset = $resetPerPegawai[$izin['id_pegawai']] ?? 0;
        $kuotaEfektif = max(0, $kuotaTerpakai - $totalReset);
        $kuotaSisa = max(0, $batasIzinPerBulan - $kuotaEfektif);
    ?>
    <div class="izin-row">
        <div class="pegawai-info">
            <div class="pegawai-avatar">
                <?php if ($izin['foto_profil'] && file_exists("uploads/{$izin['foto_profil']}")): ?>
                <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($izin['foto_profil']) ?>" alt="">
                <?php else: ?>
                <?= getInitials($izin['nama_lengkap']) ?>
                <?php endif; ?>
            </div>
            <div class="pegawai-detail">
                <h4><?= htmlspecialchars($izin['nama_lengkap']) ?></h4>
                <p><?= htmlspecialchars($izin['jabatan'] ?? $izin['nip']) ?></p>
                <span style="font-size:10px;font-weight:600;color:<?= $kuotaSisa <= 0 ? '#dc2626' : ($kuotaSisa <= 1 ? '#d97706' : '#059669') ?>"><i class="fas fa-ticket-alt"></i> Kuota: <?= $kuotaEfektif ?>/<?= $batasIzinPerBulan ?> bulan ini<?= $totalReset > 0 ? ' (reset +'.$totalReset.')' : '' ?></span>
            </div>
        </div>
        
        <div>
            <span class="izin-type-badge" style="background:<?= $jenisInfo['color'] ?>">
                <i class="fas fa-<?= $jenisInfo['icon'] ?>"></i>
                <?= $jenisInfo['label'] ?>
            </span>
        </div>

        <div class="izin-periode">
            <div class="dates"><?= date('d M', strtotime($izin['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($izin['tanggal_selesai'])) ?></div>
            <div class="duration"><?= $duration ?> hari</div>
        </div>

        <div>
            <span class="badge <?= $statusInfo['class'] ?>">
                <i class="fas fa-<?= $statusInfo['icon'] ?>"></i>
                <?= $statusInfo['label'] ?>
            </span>
        </div>

        <div class="action-buttons">
            <button class="btn btn-outline btn-sm" onclick="showDetailModal(<?= htmlspecialchars(json_encode($izin)) ?>, <?= htmlspecialchars(json_encode($jenisInfo)) ?>)">
                <i class="fas fa-eye"></i>
            </button>
            <?php if ($izin['status'] === 'pending'): ?>
            <button class="btn btn-primary btn-sm" onclick="showProcessModal(<?= htmlspecialchars(json_encode($izin)) ?>, <?= htmlspecialchars(json_encode($jenisInfo)) ?>)">
                <i class="fas fa-check"></i> Proses
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Detail Modal -->
<div id="modalDetail" class="modal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Detail Pengajuan</h3>
            <button class="close-btn" onclick="document.getElementById('modalDetail').classList.remove('show')">&times;</button>
        </div>
        <div class="modal-body-izin" id="detailContent">
            <!-- Content will be filled by JS -->
        </div>
    </div>
</div>

<!-- Process Modal -->
<div id="modalProcess" class="modal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-tasks"></i> Proses Pengajuan</h3>
            <button class="close-btn" onclick="document.getElementById('modalProcess').classList.remove('show')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="proses_izin">
            <input type="hidden" name="id_izin" id="processIzinId">
            <div class="modal-body-izin" id="processContent">
                <!-- Content will be filled by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalProcess').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function showDetailModal(izin, jenisInfo) {
    const start = new Date(izin.tanggal_mulai);
    const end = new Date(izin.tanggal_selesai);
    const duration = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
    
    const statusLabels = {
        'pending': '<span class="badge badge-average"><i class="fas fa-clock"></i> Menunggu</span>',
        'approved': '<span class="badge badge-excellent"><i class="fas fa-check-circle"></i> Disetujui</span>',
        'rejected': '<span class="badge badge-poor"><i class="fas fa-times-circle"></i> Ditolak</span>'
    };

    document.getElementById('detailContent').innerHTML = `
        <div class="izin-detail-card">
            <div class="izin-detail-row">
                <span class="izin-detail-label">Pegawai</span>
                <span class="izin-detail-value">${izin.nama_lengkap}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">NIP</span>
                <span class="izin-detail-value">${izin.nip}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Jenis Izin</span>
                <span class="izin-detail-value"><i class="fas fa-${jenisInfo.icon}" style="color:${jenisInfo.color}"></i> ${jenisInfo.label}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Periode</span>
                <span class="izin-detail-value">${formatDate(izin.tanggal_mulai)} - ${formatDate(izin.tanggal_selesai)}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Durasi</span>
                <span class="izin-detail-value">${duration} hari</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Status</span>
                <span class="izin-detail-value">${statusLabels[izin.status]}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Diajukan</span>
                <span class="izin-detail-value">${formatDateTime(izin.tanggal_pengajuan)}</span>
            </div>
        </div>
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:8px;display:block">Keterangan</label>
            <div class="reason-box">${izin.keterangan || '-'}</div>
        </div>
        ${izin.catatan_admin ? `
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:8px;display:block">Catatan Admin</label>
            <div class="reason-box" style="background:#fef3c7;border-color:#fcd34d">${izin.catatan_admin}</div>
        </div>
        ` : ''}
        ${izin.dokumen ? `
        <a href="<?= getBaseUrl() ?>uploads/pengaduan/${izin.dokumen}" target="_blank" class="btn btn-outline" style="width:100%">
            <i class="fas fa-paperclip"></i> Lihat Dokumen
        </a>
        ` : ''}
    `;
    document.getElementById('modalDetail').classList.add('show');
}

function showProcessModal(izin, jenisInfo) {
    document.getElementById('processIzinId').value = izin.id_izin;
    
    const start = new Date(izin.tanggal_mulai);
    const end = new Date(izin.tanggal_selesai);
    const duration = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

    document.getElementById('processContent').innerHTML = `
        <div class="izin-detail-card">
            <div class="izin-detail-row">
                <span class="izin-detail-label">Pegawai</span>
                <span class="izin-detail-value">${izin.nama_lengkap}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Jenis Izin</span>
                <span class="izin-detail-value"><i class="fas fa-${jenisInfo.icon}" style="color:${jenisInfo.color}"></i> ${jenisInfo.label}</span>
            </div>
            <div class="izin-detail-row">
                <span class="izin-detail-label">Periode</span>
                <span class="izin-detail-value">${formatDate(izin.tanggal_mulai)} - ${formatDate(izin.tanggal_selesai)} (${duration} hari)</span>
            </div>
        </div>
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:8px;display:block">Keterangan</label>
            <div class="reason-box">${izin.keterangan || '-'}</div>
        </div>
        <div class="form-group">
            <label style="font-weight:600;margin-bottom:8px;display:block">Keputusan</label>
            <div class="action-tabs">
                <label class="action-tab approve">
                    <input type="radio" name="status" value="approved" required>
                    <div class="action-content">
                        <i class="fas fa-check-circle"></i>
                        <span>Setujui</span>
                    </div>
                </label>
                <label class="action-tab reject">
                    <input type="radio" name="status" value="rejected" required>
                    <div class="action-content">
                        <i class="fas fa-times-circle"></i>
                        <span>Tolak</span>
                    </div>
                </label>
            </div>
        </div>
        <div class="form-group">
            <label>Catatan (opsional)</label>
            <textarea name="catatan_admin" class="form-control" rows="2" placeholder="Tambahkan catatan jika perlu..."></textarea>
        </div>
    `;
    document.getElementById('modalProcess').classList.add('show');
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Close modal on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('show');
        }
    });
});
</script>

<!-- Modal Reset Kuota -->
<div id="modalResetKuota" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header" style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);color:white;border-radius:16px 16px 0 0;padding:24px">
            <div style="display:flex;align-items:center;gap:14px">
                <div style="width:50px;height:50px;border-radius:12px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:22px">
                    <i class="fas fa-redo-alt"></i>
                </div>
                <div>
                    <h3 style="font-size:17px;margin-bottom:4px">Reset Kuota Izin</h3>
                    <p style="font-size:12px;opacity:0.9">Tambah kuota izin pegawai untuk bulan <?= date('F Y') ?></p>
                </div>
            </div>
            <button class="close-btn" style="background:rgba(255,255,255,0.2);color:white;border:none" onclick="document.getElementById('modalResetKuota').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" style="padding:24px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_kuota">
            
            <div class="form-group" style="margin-bottom:18px">
                <label style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-user" style="color:#d97706;font-size:12px"></i> Pegawai <span style="color:#ef4444">*</span>
                </label>
                <select name="id_pegawai_reset" class="form-control" required style="padding:12px 16px;font-size:14px;border-radius:10px">
                    <option value="">-- Pilih Pegawai --</option>
                    <?php foreach ($pegawaiListReset as $peg): ?>
                    <option value="<?= $peg['id_pegawai'] ?>"><?= htmlspecialchars($peg['nama_lengkap']) ?> (<?= htmlspecialchars($peg['nip']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:18px">
                <label style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-plus-circle" style="color:#d97706;font-size:12px"></i> Jumlah Kuota Ditambahkan <span style="color:#ef4444">*</span>
                </label>
                <select name="jumlah_reset" class="form-control" required style="padding:12px 16px;font-size:14px;border-radius:10px">
                    <option value="1">+1 kuota</option>
                    <option value="2">+2 kuota</option>
                    <option value="3">+3 kuota</option>
                    <option value="4" selected>+4 kuota (reset penuh)</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:18px">
                <label style="font-size:13px;font-weight:600;color:#374151;margin-bottom:8px;display:flex;align-items:center;gap:6px">
                    <i class="fas fa-comment" style="color:#d97706;font-size:12px"></i> Catatan (opsional)
                </label>
                <textarea name="catatan_reset" class="form-control" rows="2" placeholder="Alasan reset kuota..." style="padding:12px 16px;font-size:14px;border-radius:10px"></textarea>
            </div>
            
            <div style="background:#fef3c7;border-radius:10px;padding:12px 14px;margin-bottom:20px;display:flex;align-items:center;gap:10px">
                <i class="fas fa-exclamation-triangle" style="color:#d97706"></i>
                <span style="font-size:12px;color:#92400e">Reset kuota akan menambah jatah pengajuan izin pegawai di bulan <?= date('F Y') ?>.</span>
            </div>
            
            <div style="display:flex;gap:12px;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalResetKuota').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-primary" style="background:#d97706;border-color:#d97706"><i class="fas fa-redo-alt"></i> Reset Kuota</button>
            </div>
        </form>
    </div>
</div>
