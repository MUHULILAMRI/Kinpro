<?php
// $db sudah tersedia dari index.php
$msg = '';
$msgType = 'success';

// HANDLE POST - disesuaikan dengan struktur tabel penilaian kemenpu2
// Kolom: nip, bulan, tahun, nilai_kedisiplinan, kinerja, sikap, kepemimpinan, loyalitas, it, masukan_atasan
// total_nilai dan rata_rata sudah auto-generate di database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        $namaBulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

        if ($action === 'tambah' || $action === 'edit') {
            $nip = sanitize($_POST['nip']);
            $bulanNum = (int)$_POST['bulan'];
            $bulan = $namaBulanArr[$bulanNum] ?? '';
            $tahun = (int)$_POST['tahun'];
            $nilai_kedisiplinan = max(0, min(100, (int)$_POST['nilai_kedisiplinan']));
            $kinerja = max(0, min(100, (int)$_POST['kinerja']));
            $sikap = max(0, min(100, (int)$_POST['sikap']));
            $kepemimpinan = max(0, min(100, (int)$_POST['kepemimpinan']));
            $loyalitas = max(0, min(100, (int)$_POST['loyalitas']));
            $it = max(0, min(100, (int)$_POST['it']));
            $masukan_atasan = sanitize($_POST['masukan_atasan']);

            if (empty($nip) || empty($bulan) || $tahun < 2000 || $tahun > 2100) {
                $msg = 'Data tidak valid. Pastikan NIP, bulan, dan tahun terisi dengan benar.';
                $msgType = 'danger';
            } elseif ($action === 'tambah') {
                $stmt = $db->prepare("INSERT INTO penilaian (nip, bulan, tahun, nilai_kedisiplinan, kinerja, sikap, kepemimpinan, loyalitas, it, masukan_atasan) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('ssiiiiiiis', $nip, $bulan, $tahun, $nilai_kedisiplinan, $kinerja, $sikap, $kepemimpinan, $loyalitas, $it, $masukan_atasan);
                if ($stmt->execute()) { $msg = 'Penilaian berhasil disimpan!'; }
                else { $msg = 'Gagal menyimpan penilaian.'; $msgType = 'danger'; }
            } else {
                $id = (int)$_POST['id_penilaian'];
                $stmt = $db->prepare("UPDATE penilaian SET nip=?, bulan=?, tahun=?, nilai_kedisiplinan=?, kinerja=?, sikap=?, kepemimpinan=?, loyalitas=?, it=?, masukan_atasan=? WHERE id_penilaian=?");
                $stmt->bind_param('ssiiiiiiisi', $nip, $bulan, $tahun, $nilai_kedisiplinan, $kinerja, $sikap, $kepemimpinan, $loyalitas, $it, $masukan_atasan, $id);
                if ($stmt->execute()) { $msg = 'Penilaian berhasil diperbarui!'; }
                else { $msg = 'Gagal memperbarui.'; $msgType = 'danger'; }
            }
        }

        if ($action === 'hapus') {
            $id = (int)$_POST['id_penilaian'];
            $stmt = $db->prepare("DELETE FROM penilaian WHERE id_penilaian = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Penilaian berhasil dihapus.';
            } else {
                $msg = 'Gagal menghapus penilaian.';
                $msgType = 'danger';
            }
        }
    }
}

$search = sanitize($_GET['search'] ?? '');
$filter_tahun = sanitize($_GET['tahun'] ?? '');
$filter_bulan = sanitize($_GET['bulan'] ?? '');

// Use prepared statements for search
$params = [];
$types = '';
$where = "WHERE 1=1";

if ($search) {
    $searchParam = '%' . $search . '%';
    $where .= " AND (p.nama_lengkap LIKE ? OR n.nip LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}
if ($filter_tahun) {
    $where .= " AND n.tahun = ?";
    $params[] = $filter_tahun;
    $types .= 's';
}
if ($filter_bulan) {
    $namaBulanFilter = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $bulanName = $namaBulanFilter[(int)$filter_bulan] ?? '';
    if ($bulanName) {
        $where .= " AND n.bulan = ?";
        $params[] = $bulanName;
        $types .= 's';
    }
}

$stmt = $db->prepare("SELECT n.*, p.nama_lengkap, p.jabatan, p.id_pegawai, p.foto_profil
    FROM penilaian n 
    JOIN pegawai p ON n.nip = p.nip
    $where ORDER BY n.tahun DESC, n.bulan DESC, n.id_penilaian DESC");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$penilaian = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$resultPegawai = $db->query("SELECT id_pegawai, nama_lengkap, nip FROM pegawai ORDER BY nama_lengkap");
$pegawaiList = $resultPegawai ? $resultPegawai->fetch_all(MYSQLI_ASSOC) : [];

$resultTahun = $db->query("SELECT DISTINCT tahun FROM penilaian ORDER BY tahun DESC");
$tahunList = $resultTahun ? $resultTahun->fetch_all(MYSQLI_ASSOC) : [];

$namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
?>

<style>
/* Modern Penilaian Styles */
.penilaian-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 24px;
    padding: 8px 0;
}

.penilaian-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    border: 1px solid rgba(0,0,0,0.04);
}

.penilaian-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,0,0,0.12);
}

.penilaian-card-header {
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    position: relative;
}

.penilaian-photo {
    width: 72px;
    height: 72px;
    border-radius: 16px;
    overflow: hidden;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border: 3px solid white;
}

.penilaian-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.penilaian-photo .avatar-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    color: white;
}

.penilaian-info h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
}

.penilaian-info .nip {
    font-family: 'Space Mono', monospace;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
}

.penilaian-info .jabatan {
    font-size: 11px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 4px;
}

.periode-badge {
    position: absolute;
    top: 16px;
    right: 16px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.penilaian-card-body {
    padding: 20px 24px;
}

.nilai-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.nilai-item {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    padding: 14px 12px;
    border-radius: 12px;
    text-align: center;
    transition: all 0.3s ease;
}

.nilai-item:hover {
    background: linear-gradient(135deg, #e0e7ff, #eef2ff);
    transform: scale(1.02);
}

.nilai-item .label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
    font-weight: 600;
}

.nilai-item .value {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
}

.rata-rata-section {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    padding: 16px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    border: 1px solid #a7f3d0;
}

.rata-rata-section.excellent {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    border-color: #a7f3d0;
}

.rata-rata-section.good {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    border-color: #93c5fd;
}

.rata-rata-section.average {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-color: #fcd34d;
}

.rata-rata-section.poor {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    border-color: #fca5a5;
}

.rata-rata-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rata-rata-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.rata-rata-section.excellent .rata-rata-icon {
    background: #10b981;
    color: white;
}

.rata-rata-section.good .rata-rata-icon {
    background: #3b82f6;
    color: white;
}

.rata-rata-section.average .rata-rata-icon {
    background: #f59e0b;
    color: white;
}

.rata-rata-section.poor .rata-rata-icon {
    background: #ef4444;
    color: white;
}

.rata-rata-text h5 {
    margin: 0;
    font-size: 12px;
    color: #374151;
    font-weight: 600;
}

.rata-rata-text p {
    margin: 2px 0 0 0;
    font-size: 10px;
    color: #6b7280;
}

.rata-rata-value {
    text-align: right;
}

.rata-rata-number {
    font-size: 32px;
    font-weight: 800;
    line-height: 1;
}

.rata-rata-section.excellent .rata-rata-number { color: #059669; }
.rata-rata-section.good .rata-rata-number { color: #2563eb; }
.rata-rata-section.average .rata-rata-number { color: #d97706; }
.rata-rata-section.poor .rata-rata-number { color: #dc2626; }

.rata-rata-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 4px;
    color: white;
}

.rata-rata-section.excellent .rata-rata-badge { background: #10b981; }
.rata-rata-section.good .rata-rata-badge { background: #3b82f6; }
.rata-rata-section.average .rata-rata-badge { background: #f59e0b; }
.rata-rata-section.poor .rata-rata-badge { background: #ef4444; }

.progress-bar-container {
    height: 6px;
    background: rgba(0,0,0,0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.rata-rata-section.excellent .progress-bar-fill { background: linear-gradient(90deg, #34d399, #10b981); }
.rata-rata-section.good .progress-bar-fill { background: linear-gradient(90deg, #60a5fa, #3b82f6); }
.rata-rata-section.average .progress-bar-fill { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.rata-rata-section.poor .progress-bar-fill { background: linear-gradient(90deg, #f87171, #ef4444); }

.penilaian-card-footer {
    padding: 16px 24px;
    background: #fafbfc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.masukan-preview {
    font-size: 12px;
    color: #64748b;
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: flex;
    align-items: center;
    gap: 6px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.action-btn {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.action-btn.edit {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4f46e5;
}

.action-btn.edit:hover {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    color: white;
    transform: scale(1.1);
}

.action-btn.delete {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
}

.action-btn.delete:hover {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    transform: scale(1.1);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.06);
}

.empty-state-icon {
    width: 80px;
    height: 80px;
    border-radius: 20px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 32px;
    color: #94a3b8;
}

.empty-state h3 {
    margin: 0 0 8px 0;
    color: #374151;
    font-size: 18px;
}

.empty-state p {
    margin: 0;
    color: #94a3b8;
    font-size: 14px;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.stat-card {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.05);
    text-align: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 20px;
}

.stat-card.total .stat-icon {
    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
    color: #2563eb;
}

.stat-card.excellent .stat-icon {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #059669;
}

.stat-card.good .stat-icon {
    background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
    color: #4f46e5;
}

.stat-card.average .stat-icon {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #d97706;
}

.stat-value {
    font-size: 28px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 500;
}

/* View Toggle */
.view-toggle {
    display: flex;
    gap: 4px;
    padding: 4px;
    background: #f1f5f9;
    border-radius: 10px;
}

.view-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #64748b;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.view-btn.active {
    background: white;
    color: #1e293b;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.view-btn:hover:not(.active) {
    background: rgba(255,255,255,0.5);
}
</style>

<div class="page-header">
    <div>
        <h2>Penilaian Kinerja</h2>
        <p>Tambah dan kelola penilaian kinerja pegawai (6 kriteria)</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').classList.add('show')">
        <i class="fas fa-plus"></i> Tambah Penilaian
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- FILTERS -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="page" value="penilaian">
            <div style="flex:2;min-width:200px" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Cari nama pegawai atau NIP..." value="<?= $search ?>">
            </div>
            <div style="flex:1;min-width:130px">
                <select name="tahun" class="form-control">
                    <option value="">Semua Tahun</option>
                    <?php foreach ($tahunList as $t): ?>
                    <option value="<?= $t['tahun'] ?>" <?= $filter_tahun == $t['tahun'] ? 'selected' : '' ?>><?= $t['tahun'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:130px">
                <select name="bulan" class="form-control">
                    <option value="">Semua Bulan</option>
                    <?php for ($b = 1; $b <= 12; $b++): ?>
                    <option value="<?= $b ?>" <?= $filter_bulan == $b ? 'selected' : '' ?>><?= $namaBulan[$b] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="?page=penilaian" class="btn btn-outline"><i class="fas fa-rotate"></i> Reset</a>
        </form>
    </div>
</div>

<?php
// Calculate stats
$totalPenilaian = count($penilaian);
$istimewa = 0; $sangatBaik = 0; $baik = 0; $cukup = 0;
foreach ($penilaian as $p) {
    if ($p['rata_rata'] >= 86) $istimewa++;
    elseif ($p['rata_rata'] >= 71) $sangatBaik++;
    elseif ($p['rata_rata'] >= 51) $baik++;
    else $cukup++;
}
?>

<!-- STATS SUMMARY -->
<div class="stats-summary">
    <div class="stat-card total">
        <div class="stat-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="stat-value"><?= $totalPenilaian ?></div>
        <div class="stat-label">Total Penilaian</div>
    </div>
    <div class="stat-card excellent">
        <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        <div class="stat-value"><?= $istimewa ?></div>
        <div class="stat-label">Istimewa</div>
    </div>
    <div class="stat-card good">
        <div class="stat-icon"><i class="fas fa-star"></i></div>
        <div class="stat-value"><?= $sangatBaik ?></div>
        <div class="stat-label">Sangat Baik</div>
    </div>
    <div class="stat-card average">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value"><?= $baik + $cukup ?></div>
        <div class="stat-label">Baik/Cukup</div>
    </div>
</div>

<!-- PENILAIAN GRID -->
<?php if (empty($penilaian)): ?>
<div class="empty-state">
    <div class="empty-state-icon"><i class="fas fa-star"></i></div>
    <h3>Belum Ada Data Penilaian</h3>
    <p>Klik tombol "Tambah Penilaian" untuk mulai menilai kinerja pegawai</p>
</div>
<?php else: ?>
<div class="penilaian-grid">
    <?php foreach ($penilaian as $n):
        $pred = getPredikat($n['rata_rata']);
        $rataClass = 'average';
        if ($n['rata_rata'] >= 86) $rataClass = 'excellent';
        elseif ($n['rata_rata'] >= 71) $rataClass = 'good';
        elseif ($n['rata_rata'] < 51) $rataClass = 'poor';
    ?>
    <div class="penilaian-card">
        <div class="penilaian-card-header">
            <div class="penilaian-photo">
                <?php if ($n['foto_profil'] && $n['foto_profil'] !== 'default.jpg'): ?>
                <img src="<?= getBaseUrl() ?>uploads/<?= $n['foto_profil'] ?>" alt="<?= sanitize($n['nama_lengkap']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="avatar-fallback" style="display:none;background:<?= getAvatarColor($n['id_pegawai']) ?>">
                    <?= getInitials($n['nama_lengkap']) ?>
                </div>
                <?php else: ?>
                <div class="avatar-fallback" style="background:<?= getAvatarColor($n['id_pegawai']) ?>">
                    <?= getInitials($n['nama_lengkap']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="penilaian-info">
                <h4><?= sanitize($n['nama_lengkap']) ?></h4>
                <div class="nip"><i class="fas fa-id-card"></i> <?= sanitize($n['nip']) ?></div>
                <div class="jabatan"><i class="fas fa-briefcase"></i> <?= sanitize($n['jabatan'] ?? 'Pegawai') ?></div>
            </div>
            <div class="periode-badge">
                <i class="fas fa-calendar-alt"></i>
                <?= $n['bulan'] ?> <?= $n['tahun'] ?>
            </div>
        </div>
        
        <div class="penilaian-card-body">
            <div class="nilai-grid">
                <div class="nilai-item">
                    <div class="label">Disiplin</div>
                    <div class="value"><?= $n['nilai_kedisiplinan'] ?></div>
                </div>
                <div class="nilai-item">
                    <div class="label">Kinerja</div>
                    <div class="value"><?= $n['kinerja'] ?></div>
                </div>
                <div class="nilai-item">
                    <div class="label">Sikap</div>
                    <div class="value"><?= $n['sikap'] ?></div>
                </div>
                <div class="nilai-item">
                    <div class="label">Pimpinan</div>
                    <div class="value"><?= $n['kepemimpinan'] ?></div>
                </div>
                <div class="nilai-item">
                    <div class="label">Loyalitas</div>
                    <div class="value"><?= $n['loyalitas'] ?></div>
                </div>
                <div class="nilai-item">
                    <div class="label">IT</div>
                    <div class="value"><?= $n['it'] ?></div>
                </div>
            </div>
            
            <div class="rata-rata-section <?= $rataClass ?>">
                <div class="rata-rata-left">
                    <div class="rata-rata-icon">
                        <?php if ($rataClass == 'excellent'): ?>
                        <i class="fas fa-trophy"></i>
                        <?php elseif ($rataClass == 'good'): ?>
                        <i class="fas fa-star"></i>
                        <?php elseif ($rataClass == 'average'): ?>
                        <i class="fas fa-thumbs-up"></i>
                        <?php else: ?>
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php endif; ?>
                    </div>
                    <div class="rata-rata-text">
                        <h5>Rata-rata Nilai</h5>
                        <p>Dari 6 aspek penilaian</p>
                    </div>
                </div>
                <div class="rata-rata-value">
                    <div class="rata-rata-number"><?= number_format($n['rata_rata'], 1) ?></div>
                    <div class="rata-rata-badge"><?= $pred['label'] ?></div>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?= min($n['rata_rata'], 100) ?>%"></div>
            </div>
        </div>
        
        <div class="penilaian-card-footer">
            <div class="masukan-preview">
                <?php if ($n['masukan_atasan']): ?>
                <i class="fas fa-comment"></i>
                <?= sanitize(substr($n['masukan_atasan'], 0, 30)) ?><?= strlen($n['masukan_atasan']) > 30 ? '...' : '' ?>
                <?php else: ?>
                <i class="fas fa-comment-slash"></i>
                <span style="color:#94a3b8">Tidak ada masukan</span>
                <?php endif; ?>
            </div>
            <div class="action-buttons">
                <button class="action-btn edit" onclick="editPenilaian(<?= htmlspecialchars(json_encode($n)) ?>)" title="Edit">
                    <i class="fas fa-pen"></i>
                </button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Hapus penilaian ini?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="page" value="penilaian">
                    <input type="hidden" name="action" value="hapus">
                    <input type="hidden" name="id_penilaian" value="<?= $n['id_penilaian'] ?>">
                    <button type="submit" class="action-btn delete" title="Hapus">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL TAMBAH -->
<div class="modal" id="modalTambah">
    <div class="modal-content" style="max-width:650px">
        <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border-radius: 16px 16px 0 0; padding: 20px 24px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-star"></i>
                </div>
                <div>
                    <h3 style="font-size: 17px; margin: 0;">Tambah Penilaian</h3>
                    <p style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Input nilai kinerja pegawai</p>
                </div>
            </div>
            <button class="close-btn" style="background: rgba(255,255,255,0.2); color: white; border: none;" onclick="document.getElementById('modalTambah').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" style="padding: 28px;">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="penilaian">
            <input type="hidden" name="action" value="tambah">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-user" style="color: #f59e0b; font-size: 12px;"></i>
                    Pegawai <span style="color:#ef4444">*</span>
                </label>
                <select name="nip" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                    <option value="">-- Pilih Pegawai --</option>
                    <?php foreach ($pegawaiList as $p): ?>
                    <option value="<?= $p['nip'] ?>"><?= sanitize($p['nama_lengkap']) ?> (<?= $p['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px; margin-bottom: 24px;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-calendar" style="color: #f59e0b; font-size: 12px;"></i>
                        Bulan <span style="color:#ef4444">*</span>
                    </label>
                    <select name="bulan" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                        <?php for ($b = 1; $b <= 12; $b++): ?>
                        <option value="<?= $b ?>" <?= $b == date('n') ? 'selected' : '' ?>><?= $namaBulan[$b] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-calendar-alt" style="color: #f59e0b; font-size: 12px;"></i>
                        Tahun <span style="color:#ef4444">*</span>
                    </label>
                    <input type="number" name="tahun" class="form-control" value="<?= date('Y') ?>" required min="2020" max="2099" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #fef3c7, #fffbeb); padding: 24px; border-radius: 14px; margin-bottom: 24px; border: 1px solid #fcd34d;">
                <h4 style="margin: 0 0 20px 0; font-size: 14px; color: #92400e; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-chart-line"></i> Nilai Kriteria (0-100)
                </h4>
                <!-- Kedisiplinan (DIS) - input data kehadiran -->
                <div style="background:linear-gradient(135deg,#fef3c7,#fffbeb);padding:20px;border-radius:12px;margin-bottom:20px;border-left:4px solid #f59e0b">
                    <h5 style="margin:0 0 16px;font-size:13px;color:#92400e;font-weight:700">Kedisiplinan (DIS) — input data kehadiran</h5>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#78350f;margin-bottom:6px">Jumlah Hari Efektif</label>
                            <input type="number" id="add_hari_efektif" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungKedisiplinanTambah()">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#78350f;margin-bottom:6px">Jumlah Hari Kerja</label>
                            <input type="number" id="add_hari_kerja" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungDariHariKerjaTambah()">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#78350f;margin-bottom:6px">Lupa Absen</label>
                            <input type="number" id="add_lupa_absen" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungKedisiplinanTambah()">
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:12px 16px;background:rgba(255,255,255,0.7);border-radius:10px">
                        <label style="font-size:11px;color:#78350f;font-weight:600">Hasil Nilai Kedisiplinan (DIS)</label>
                        <div id="add_kedisiplinan_display" style="font-size:24px;font-weight:800;color:#92400e;margin-top:4px">0.00</div>
                        <p style="margin:4px 0 0;font-size:10px;color:#a16207">Otomatis: (Hari Efektif - Lupa Absen = Hari Kerja), atau isi Hari Kerja manual &rarr; (Hari Kerja / Hari Efektif) &times; 100</p>
                    </div>
                    <input type="hidden" name="nilai_kedisiplinan" id="add_kedisiplinan" value="0">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #78350f; margin-bottom: 6px;">Kinerja</label>
                        <input type="number" name="kinerja" id="add_kinerja" class="form-control nilai-input" min="0" max="100" value="80" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataTambah()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #78350f; margin-bottom: 6px;">Sikap</label>
                        <input type="number" name="sikap" id="add_sikap" class="form-control nilai-input" min="0" max="100" value="80" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataTambah()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #78350f; margin-bottom: 6px;">Kepemimpinan</label>
                        <input type="number" name="kepemimpinan" id="add_kepemimpinan" class="form-control nilai-input" min="0" max="100" value="80" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataTambah()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #78350f; margin-bottom: 6px;">Loyalitas</label>
                        <input type="number" name="loyalitas" id="add_loyalitas" class="form-control nilai-input" min="0" max="100" value="80" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataTambah()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #78350f; margin-bottom: 6px;">IT</label>
                        <input type="number" name="it" id="add_it" class="form-control nilai-input" min="0" max="100" value="80" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataTambah()">
                    </div>
                </div>
            </div>

            <!-- RATA-RATA AKHIR LIVE -->
            <div style="background: linear-gradient(135deg, #d1fae5, #ecfdf5); padding: 20px; border-radius: 14px; margin-bottom: 24px; border: 2px solid #10b981;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #10b981; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 13px; color: #065f46;">Rata-rata Akhir (Otomatis)</h4>
                            <p style="margin: 2px 0 0 0; font-size: 11px; color: #047857;">Rata-rata dari 6 aspek penilaian</p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div id="rataRataTambahValue" style="font-size: 32px; font-weight: 800; color: #059669;">80.00</div>
                        <div id="rataRataTambahPredikat" style="font-size: 12px; padding: 4px 12px; border-radius: 20px; background: #10b981; color: white; display: inline-block; margin-top: 4px;">Sangat Baik</div>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <div style="height: 8px; background: #d1fae5; border-radius: 4px; overflow: hidden;">
                        <div id="rataRataTambahBar" style="height: 100%; width: 80%; background: linear-gradient(90deg, #10b981, #059669); transition: width 0.3s ease;"></div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-comment" style="color: #f59e0b; font-size: 12px;"></i>
                    Masukan Atasan
                </label>
                <textarea name="masukan_atasan" class="form-control" rows="3" placeholder="Catatan atau masukan dari atasan..." style="padding: 14px 16px; font-size: 14px; border-radius: 10px;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline" style="padding: 12px 24px;" onclick="document.getElementById('modalTambah').classList.remove('show')">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-save"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal" id="modalEdit">
    <div class="modal-content" style="max-width:650px">
        <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; border-radius: 16px 16px 0 0; padding: 20px 24px;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-edit"></i>
                </div>
                <div>
                    <h3 style="font-size: 17px; margin: 0;">Edit Penilaian</h3>
                    <p style="font-size: 12px; opacity: 0.9; margin-top: 2px;">Perbarui nilai kinerja pegawai</p>
                </div>
            </div>
            <button class="close-btn" style="background: rgba(255,255,255,0.2); color: white; border: none;" onclick="document.getElementById('modalEdit').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" style="padding: 28px;">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="penilaian">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_penilaian" id="edit_id">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-user" style="color: #6366f1; font-size: 12px;"></i>
                    Pegawai <span style="color:#ef4444">*</span>
                </label>
                <select name="nip" id="edit_nip" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                    <?php foreach ($pegawaiList as $p): ?>
                    <option value="<?= $p['nip'] ?>"><?= sanitize($p['nama_lengkap']) ?> (<?= $p['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px; margin-bottom: 24px;">
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-calendar" style="color: #6366f1; font-size: 12px;"></i>
                        Bulan <span style="color:#ef4444">*</span>
                    </label>
                    <select name="bulan" id="edit_bulan" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                        <?php for ($b = 1; $b <= 12; $b++): ?>
                        <option value="<?= $b ?>"><?= $namaBulan[$b] ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-calendar-alt" style="color: #6366f1; font-size: 12px;"></i>
                        Tahun <span style="color:#ef4444">*</span>
                    </label>
                    <input type="number" name="tahun" id="edit_tahun" class="form-control" required min="2020" max="2099" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
            </div>

            <div style="background: linear-gradient(135deg, #ede9fe, #f5f3ff); padding: 24px; border-radius: 14px; margin-bottom: 24px; border: 1px solid #c4b5fd;">
                <h4 style="margin: 0 0 20px 0; font-size: 14px; color: #5b21b6; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-chart-line"></i> Nilai Kriteria (0-100)
                </h4>
                <!-- Kedisiplinan (DIS) - input data kehadiran (EDIT) -->
                <div style="background:linear-gradient(135deg,#ede9fe,#f5f3ff);padding:20px;border-radius:12px;margin-bottom:20px;border-left:4px solid #8b5cf6">
                    <h5 style="margin:0 0 16px;font-size:13px;color:#5b21b6;font-weight:700">Kedisiplinan (DIS) — input data kehadiran</h5>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#4c1d95;margin-bottom:6px">Jumlah Hari Efektif</label>
                            <input type="number" id="edit_hari_efektif" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungKedisiplinanEdit()">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#4c1d95;margin-bottom:6px">Jumlah Hari Kerja</label>
                            <input type="number" id="edit_hari_kerja" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungDariHariKerjaEdit()">
                        </div>
                        <div class="form-group" style="margin:0">
                            <label style="font-size:12px;font-weight:600;color:#4c1d95;margin-bottom:6px">Lupa Absen</label>
                            <input type="number" id="edit_lupa_absen" class="form-control" min="0" max="366" value="0" style="padding:12px 16px;font-size:14px;border-radius:10px" oninput="hitungKedisiplinanEdit()">
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:12px 16px;background:rgba(255,255,255,0.7);border-radius:10px">
                        <label style="font-size:11px;color:#4c1d95;font-weight:600">Hasil Nilai Kedisiplinan (DIS)</label>
                        <div id="edit_kedisiplinan_display" style="font-size:24px;font-weight:800;color:#5b21b6;margin-top:4px">0.00</div>
                        <p style="margin:4px 0 0;font-size:10px;color:#6d28d9">Otomatis: (Hari Efektif - Lupa Absen = Hari Kerja), atau isi Hari Kerja manual &rarr; (Hari Kerja / Hari Efektif) &times; 100</p>
                    </div>
                    <input type="hidden" name="nilai_kedisiplinan" id="edit_kedisiplinan" value="0">
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #4c1d95; margin-bottom: 6px;">Kinerja</label>
                        <input type="number" name="kinerja" id="edit_kinerja" class="form-control edit-nilai" min="0" max="100" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataEdit()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #4c1d95; margin-bottom: 6px;">Sikap</label>
                        <input type="number" name="sikap" id="edit_sikap" class="form-control edit-nilai" min="0" max="100" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataEdit()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #4c1d95; margin-bottom: 6px;">Kepemimpinan</label>
                        <input type="number" name="kepemimpinan" id="edit_kepemimpinan" class="form-control edit-nilai" min="0" max="100" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataEdit()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #4c1d95; margin-bottom: 6px;">Loyalitas</label>
                        <input type="number" name="loyalitas" id="edit_loyalitas" class="form-control edit-nilai" min="0" max="100" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataEdit()">
                    </div>
                    <div class="form-group" style="margin:0">
                        <label style="font-size: 12px; font-weight: 600; color: #4c1d95; margin-bottom: 6px;">IT</label>
                        <input type="number" name="it" id="edit_it" class="form-control edit-nilai" min="0" max="100" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="hitungRataRataEdit()">
                    </div>
                </div>
            </div>

            <!-- RATA-RATA AKHIR LIVE (EDIT) -->
            <div style="background: linear-gradient(135deg, #e0e7ff, #eef2ff); padding: 20px; border-radius: 14px; margin-bottom: 24px; border: 2px solid #6366f1;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #6366f1; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px;">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div>
                            <h4 style="margin: 0; font-size: 13px; color: #3730a3;">Rata-rata Akhir (Otomatis)</h4>
                            <p style="margin: 2px 0 0 0; font-size: 11px; color: #4f46e5;">Rata-rata dari 6 aspek penilaian</p>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div id="rataRataEditValue" style="font-size: 32px; font-weight: 800; color: #4f46e5;">0.00</div>
                        <div id="rataRataEditPredikat" style="font-size: 12px; padding: 4px 12px; border-radius: 20px; background: #6366f1; color: white; display: inline-block; margin-top: 4px;">-</div>
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <div style="height: 8px; background: #e0e7ff; border-radius: 4px; overflow: hidden;">
                        <div id="rataRataEditBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #6366f1, #4f46e5); transition: width 0.3s ease;"></div>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-comment" style="color: #6366f1; font-size: 12px;"></i>
                    Masukan Atasan
                </label>
                <textarea name="masukan_atasan" id="edit_masukan" class="form-control" rows="3" style="padding: 14px 16px; font-size: 14px; border-radius: 10px;"></textarea>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-outline" style="padding: 12px 24px;" onclick="document.getElementById('modalEdit').classList.remove('show')">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px;"><i class="fas fa-save"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function getBulanNumber(bulanName) {
    const bulanArr = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    const idx = bulanArr.indexOf(bulanName);
    return idx > 0 ? idx : 1;
}

// Fungsi untuk mendapatkan predikat berdasarkan nilai
function getPredikatJS(nilai) {
    if (nilai >= 86) return { label: 'Istimewa', class: 'badge-success', color: '#10b981' };
    if (nilai >= 71) return { label: 'Sangat Baik', class: 'badge-info', color: '#3b82f6' };
    if (nilai >= 51) return { label: 'Baik', class: 'badge-primary', color: '#6366f1' };
    if (nilai >= 31) return { label: 'Cukup', class: 'badge-warning', color: '#f59e0b' };
    return { label: 'Kurang', class: 'badge-danger', color: '#ef4444' };
}

// Hitung Kedisiplinan dari Hari Efektif, Hari Kerja, Lupa Absen
function hitungKedisiplinanTambah() {
    const hariEfektif = parseFloat(document.getElementById('add_hari_efektif').value) || 0;
    const lupaAbsen = parseFloat(document.getElementById('add_lupa_absen').value) || 0;
    const hariKerja = Math.max(0, hariEfektif - lupaAbsen);
    document.getElementById('add_hari_kerja').value = hariKerja;
    updateNilaiKedisiplinanTambah(hariKerja, hariEfektif);
}

function hitungDariHariKerjaTambah() {
    const hariEfektif = parseFloat(document.getElementById('add_hari_efektif').value) || 0;
    const hariKerja = parseFloat(document.getElementById('add_hari_kerja').value) || 0;
    updateNilaiKedisiplinanTambah(hariKerja, hariEfektif);
}

function updateNilaiKedisiplinanTambah(hariKerja, hariEfektif) {
    let nilai = 0;
    if (hariEfektif > 0) {
        nilai = (hariKerja / hariEfektif) * 100;
        nilai = Math.min(100, Math.max(0, Math.round(nilai * 100) / 100));
    }
    document.getElementById('add_kedisiplinan').value = Math.round(nilai);
    document.getElementById('add_kedisiplinan_display').textContent = nilai.toFixed(2);
    hitungRataRataTambah();
}

function hitungKedisiplinanEdit() {
    const hariEfektif = parseFloat(document.getElementById('edit_hari_efektif').value) || 0;
    const lupaAbsen = parseFloat(document.getElementById('edit_lupa_absen').value) || 0;
    const hariKerja = Math.max(0, hariEfektif - lupaAbsen);
    document.getElementById('edit_hari_kerja').value = hariKerja;
    updateNilaiKedisiplinanEdit(hariKerja, hariEfektif);
}

function hitungDariHariKerjaEdit() {
    const hariEfektif = parseFloat(document.getElementById('edit_hari_efektif').value) || 0;
    const hariKerja = parseFloat(document.getElementById('edit_hari_kerja').value) || 0;
    updateNilaiKedisiplinanEdit(hariKerja, hariEfektif);
}

function updateNilaiKedisiplinanEdit(hariKerja, hariEfektif) {
    let nilai = 0;
    if (hariEfektif > 0) {
        nilai = (hariKerja / hariEfektif) * 100;
        nilai = Math.min(100, Math.max(0, Math.round(nilai * 100) / 100));
    }
    document.getElementById('edit_kedisiplinan').value = Math.round(nilai);
    document.getElementById('edit_kedisiplinan_display').textContent = nilai.toFixed(2);
    hitungRataRataEdit();
}

// Hitung rata-rata untuk Modal Tambah
function hitungRataRataTambah() {
    const kedisiplinan = parseFloat(document.getElementById('add_kedisiplinan').value) || 0;
    const kinerja = parseFloat(document.getElementById('add_kinerja').value) || 0;
    const sikap = parseFloat(document.getElementById('add_sikap').value) || 0;
    const kepemimpinan = parseFloat(document.getElementById('add_kepemimpinan').value) || 0;
    const loyalitas = parseFloat(document.getElementById('add_loyalitas').value) || 0;
    const it = parseFloat(document.getElementById('add_it').value) || 0;
    
    const total = kedisiplinan + kinerja + sikap + kepemimpinan + loyalitas + it;
    const rataRata = total / 6;
    
    const predikat = getPredikatJS(rataRata);
    
    document.getElementById('rataRataTambahValue').textContent = rataRata.toFixed(2);
    document.getElementById('rataRataTambahBar').style.width = rataRata + '%';
    document.getElementById('rataRataTambahPredikat').textContent = predikat.label;
    document.getElementById('rataRataTambahPredikat').style.background = predikat.color;
}

// Hitung rata-rata untuk Modal Edit
function hitungRataRataEdit() {
    const kedisiplinan = parseFloat(document.getElementById('edit_kedisiplinan').value) || 0;
    const kinerja = parseFloat(document.getElementById('edit_kinerja').value) || 0;
    const sikap = parseFloat(document.getElementById('edit_sikap').value) || 0;
    const kepemimpinan = parseFloat(document.getElementById('edit_kepemimpinan').value) || 0;
    const loyalitas = parseFloat(document.getElementById('edit_loyalitas').value) || 0;
    const it = parseFloat(document.getElementById('edit_it').value) || 0;
    
    const total = kedisiplinan + kinerja + sikap + kepemimpinan + loyalitas + it;
    const rataRata = total / 6;
    
    const predikat = getPredikatJS(rataRata);
    
    document.getElementById('rataRataEditValue').textContent = rataRata.toFixed(2);
    document.getElementById('rataRataEditBar').style.width = rataRata + '%';
    document.getElementById('rataRataEditPredikat').textContent = predikat.label;
    document.getElementById('rataRataEditPredikat').style.background = predikat.color;
}

function editPenilaian(n) {
    document.getElementById('edit_id').value = n.id_penilaian;
    document.getElementById('edit_nip').value = n.nip;
    document.getElementById('edit_bulan').value = getBulanNumber(n.bulan);
    document.getElementById('edit_tahun').value = n.tahun;
    document.getElementById('edit_kedisiplinan').value = n.nilai_kedisiplinan;
    document.getElementById('edit_kedisiplinan_display').textContent = parseFloat(n.nilai_kedisiplinan).toFixed(2);
    // Reset hari fields (user can re-input)
    document.getElementById('edit_hari_efektif').value = '';
    document.getElementById('edit_hari_kerja').value = '';
    document.getElementById('edit_lupa_absen').value = 0;
    document.getElementById('edit_kinerja').value = n.kinerja;
    document.getElementById('edit_sikap').value = n.sikap;
    document.getElementById('edit_kepemimpinan').value = n.kepemimpinan;
    document.getElementById('edit_loyalitas').value = n.loyalitas;
    document.getElementById('edit_it').value = n.it;
    document.getElementById('edit_masukan').value = n.masukan_atasan || '';
    document.getElementById('modalEdit').classList.add('show');
    
    // Hitung rata-rata setelah data diisi
    hitungRataRataEdit();
}

// Inisialisasi rata-rata saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    hitungKedisiplinanTambah();
});
</script>
