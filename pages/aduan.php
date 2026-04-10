<?php
// $db sudah tersedia dari index.php
$msg = '';
$msgType = 'success';

// HANDLE POST - disesuaikan dengan struktur tabel pengaduan kemenpu2
// Kolom: nip, jenis_laporan, tanggal_kejadian, keterangan, bukti, status (pending/approved/rejected), alasan_penolakan

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'tambah') {
            $nip = sanitize($_POST['nip']);
            $jenis_laporan = sanitize($_POST['jenis_laporan']);
            $tanggal_kejadian = $_POST['tanggal_kejadian'] ?: date('Y-m-d');
            $keterangan = sanitize($_POST['keterangan']);
            
            // Handle file upload securely
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
                $stmt->bind_param('sssss', $nip, $jenis_laporan, $tanggal_kejadian, $keterangan, $bukti);
                if ($stmt->execute()) { $msg = 'Pengaduan berhasil ditambahkan!'; }
                else { $msg = 'Gagal menyimpan pengaduan.'; $msgType = 'danger'; }
            }
        }

        if ($action === 'update_status') {
            $id = (int)$_POST['id'];
            $status = sanitize($_POST['status']);
            $alasan_penolakan = sanitize($_POST['alasan_penolakan']);

            // Validate status whitelist
            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                $msg = 'Status tidak valid.';
                $msgType = 'danger';
            } else {
                $stmt = $db->prepare("UPDATE pengaduan SET status=?, alasan_penolakan=? WHERE id=?");
                $stmt->bind_param('ssi', $status, $alasan_penolakan, $id);
                if ($stmt->execute()) { $msg = 'Status pengaduan berhasil diperbarui!'; }
                else { $msg = 'Gagal memperbarui.'; $msgType = 'danger'; }
            }
        }

        if ($action === 'hapus') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM pengaduan WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Pengaduan berhasil dihapus.';
            } else {
                $msg = 'Gagal menghapus pengaduan.';
                $msgType = 'danger';
            }
        }
    }
}

$search = sanitize($_GET['search'] ?? '');
$filter_status = sanitize($_GET['status'] ?? '');
$filter_jenis = sanitize($_GET['jenis'] ?? '');

// Use prepared statements for search
$params = [];
$types = '';
$where = "WHERE 1=1";

if ($search) {
    $searchParam = '%' . $search . '%';
    $where .= " AND (a.keterangan LIKE ? OR p.nama_lengkap LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'ss';
}
if ($filter_status) {
    $where .= " AND a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_jenis) {
    $where .= " AND a.jenis_laporan = ?";
    $params[] = $filter_jenis;
    $types .= 's';
}

$stmt = $db->prepare("SELECT a.*, p.nama_lengkap, p.jabatan, p.id_pegawai
    FROM pengaduan a 
    JOIN pegawai p ON a.nip = p.nip
    $where ORDER BY FIELD(a.status,'pending','approved','rejected'), a.id DESC");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pengaduan = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pegawaiList = $db->query("SELECT id_pegawai, nama_lengkap, nip FROM pegawai ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);
$jenisList = $db->query("SELECT DISTINCT jenis_laporan FROM pengaduan WHERE jenis_laporan IS NOT NULL ORDER BY jenis_laporan")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = [];
foreach (['pending','approved','rejected'] as $s) {
    $stmtStat = $db->prepare("SELECT COUNT(*) as c FROM pengaduan WHERE status=?");
    $stmtStat->bind_param('s', $s);
    $stmtStat->execute();
    $stats[$s] = $stmtStat->get_result()->fetch_assoc()['c'] ?? 0;
}

// Status display config
$statusConfig = [
    'pending' => ['label' => 'Pending', 'bg' => '#fef3c7', 'color' => '#92400e', 'icon' => 'fa-clock'],
    'approved' => ['label' => 'Disetujui', 'bg' => '#d1fae5', 'color' => '#065f46', 'icon' => 'fa-check-circle'],
    'rejected' => ['label' => 'Ditolak', 'bg' => '#fee2e2', 'color' => '#991b1b', 'icon' => 'fa-times-circle']
];
?>

<div class="page-header">
    <div>
        <h2>Pengaduan Karyawan</h2>
        <p>Kelola dan tanggapi pengaduan dari karyawan</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').classList.add('show')">
        <i class="fas fa-plus"></i> Input Pengaduan
    </button>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- STATUS QUICK STATS -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:24px">
    <?php foreach ($stats as $s => $count):
        $cfg = $statusConfig[$s];
    ?>
    <a href="?page=aduan&status=<?= $s ?>" style="text-decoration:none">
        <div style="background:<?= $cfg['bg'] ?>;border-radius:14px;padding:18px;display:flex;align-items:center;gap:14px;border:2px solid <?= $filter_status === $s ? $cfg['color'] : 'transparent' ?>">
            <i class="fas <?= $cfg['icon'] ?>" style="font-size:22px;color:<?= $cfg['color'] ?>"></i>
            <div>
                <div style="font-size:24px;font-weight:800;color:<?= $cfg['color'] ?>"><?= $count ?></div>
                <div style="font-size:12px;font-weight:600;color:<?= $cfg['color'] ?>"><?= $cfg['label'] ?></div>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- FILTERS -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="page" value="aduan">
            <div style="flex:2;min-width:200px" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Cari keterangan atau nama pegawai..." value="<?= $search ?>">
            </div>
            <div style="flex:1;min-width:130px">
                <select name="status" class="form-control">
                    <option value="">Semua Status</option>
                    <?php foreach ($statusConfig as $key => $cfg): ?>
                    <option value="<?= $key ?>" <?= $filter_status === $key ? 'selected' : '' ?>><?= $cfg['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:130px">
                <select name="jenis" class="form-control">
                    <option value="">Semua Jenis</option>
                    <?php foreach ($jenisList as $j): ?>
                    <option value="<?= $j['jenis_laporan'] ?>" <?= $filter_jenis === $j['jenis_laporan'] ? 'selected' : '' ?>><?= $j['jenis_laporan'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="?page=aduan" class="btn btn-outline"><i class="fas fa-rotate"></i> Reset</a>
        </form>
    </div>
</div>

<!-- PENGADUAN CARDS -->
<?php if (empty($pengaduan)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px">
        <i class="fas fa-comment-slash" style="font-size:40px;color:var(--border);margin-bottom:14px;display:block"></i>
        <div style="color:var(--muted);font-size:15px">Tidak ada pengaduan ditemukan</div>
    </div>
</div>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($pengaduan as $a):
        $cfg = $statusConfig[$a['status']] ?? $statusConfig['pending'];
    ?>
    <div class="card" style="border-left:4px solid <?= $cfg['color'] ?>">
        <div style="padding:20px">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:14px">
                <div style="flex:1">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <div class="avatar" style="background:<?= getAvatarColor($a['id_pegawai']) ?>;width:34px;height:34px;font-size:12px">
                            <?= getInitials($a['nama_lengkap']) ?>
                        </div>
                        <div>
                            <div style="font-weight:600"><?= sanitize($a['nama_lengkap']) ?></div>
                            <div style="font-size:12px;color:var(--muted)"><?= sanitize($a['jabatan'] ?: '-') ?> • <?= sanitize($a['nip']) ?></div>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap">
                        <span class="badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>">
                            <i class="fas <?= $cfg['icon'] ?>"></i> <?= $cfg['label'] ?>
                        </span>
                        <?php if ($a['jenis_laporan']): ?>
                        <span class="badge badge-average"><?= sanitize($a['jenis_laporan']) ?></span>
                        <?php endif; ?>
                        <span style="font-size:12px;color:var(--muted)">
                            <i class="fas fa-calendar"></i> <?= formatTanggal($a['tanggal_kejadian']) ?>
                        </span>
                    </div>

                    <div style="background:var(--bg);padding:14px;border-radius:10px;margin-bottom:10px">
                        <p style="margin:0;font-size:14px;line-height:1.6"><?= nl2br(sanitize($a['keterangan'])) ?></p>
                    </div>

                    <?php if ($a['bukti']): ?>
                    <div style="margin-bottom:10px">
                        <a href="<?= getBaseUrl() ?>uploads/pengaduan/<?= htmlspecialchars($a['bukti']) ?>" target="_blank" class="btn btn-outline btn-sm">
                            <i class="fas fa-file"></i> Lihat Bukti
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($a['status'] === 'rejected' && $a['alasan_penolakan']): ?>
                    <div style="background:#fee2e2;padding:12px;border-radius:8px;margin-top:10px">
                        <div style="font-size:12px;font-weight:600;color:#991b1b;margin-bottom:4px">
                            <i class="fas fa-exclamation-circle"></i> Alasan Penolakan:
                        </div>
                        <p style="margin:0;font-size:13px;color:#991b1b"><?= sanitize($a['alasan_penolakan']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="display:flex;flex-direction:column;gap:6px">
                    <?php if ($a['status'] === 'pending'): ?>
                    <button class="btn btn-primary btn-sm" onclick="updateStatus(<?= $a['id'] ?>,'approved')">
                        <i class="fas fa-check"></i> Setujui
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="showReject(<?= $a['id'] ?>)">
                        <i class="fas fa-times"></i> Tolak
                    </button>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Hapus pengaduan ini?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="page" value="aduan">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL TAMBAH -->
<div class="modal" id="modalTambah">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-comment-medical"></i> Input Pengaduan</h3>
            <button class="close-btn" onclick="document.getElementById('modalTambah').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="aduan">
            <input type="hidden" name="action" value="tambah">
            
            <div class="form-group">
                <label>Pegawai <span style="color:#ef4444">*</span></label>
                <select name="nip" class="form-control" required>
                    <option value="">-- Pilih Pegawai --</option>
                    <?php foreach ($pegawaiList as $p): ?>
                    <option value="<?= $p['nip'] ?>"><?= sanitize($p['nama_lengkap']) ?> (<?= $p['nip'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Jenis Laporan</label>
                <select name="jenis_laporan" class="form-control">
                    <option value="">-- Pilih Jenis --</option>
                    <option value="Pelanggaran Disiplin">Pelanggaran Disiplin</option>
                    <option value="Perilaku Tidak Profesional">Perilaku Tidak Profesional</option>
                    <option value="Penyalahgunaan Wewenang">Penyalahgunaan Wewenang</option>
                    <option value="Konflik Kepentingan">Konflik Kepentingan</option>
                    <option value="Pelecehan">Pelecehan</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Tanggal Kejadian</label>
                <input type="date" name="tanggal_kejadian" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            
            <div class="form-group">
                <label>Keterangan <span style="color:#ef4444">*</span></label>
                <textarea name="keterangan" class="form-control" rows="4" required placeholder="Jelaskan detail kejadian..."></textarea>
            </div>
            
            <div class="form-group">
                <label>Bukti (Opsional)</label>
                <input type="file" name="bukti" class="form-control" accept="image/*,.pdf,.doc,.docx">
                <small style="color:var(--muted)">Format: gambar, PDF, atau dokumen Word</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalTambah').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REJECT -->
<div class="modal" id="modalReject">
    <div class="modal-content" style="max-width:450px">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle" style="color:#ef4444"></i> Tolak Pengaduan</h3>
            <button class="close-btn" onclick="document.getElementById('modalReject').classList.remove('show')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="aduan">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="reject_id">
            <input type="hidden" name="status" value="rejected">
            
            <div class="form-group">
                <label>Alasan Penolakan <span style="color:#ef4444">*</span></label>
                <textarea name="alasan_penolakan" class="form-control" rows="4" required placeholder="Jelaskan alasan penolakan..."></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalReject').classList.remove('show')">Batal</button>
                <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> Tolak Pengaduan</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden form for approve -->
<form id="formApprove" method="POST" style="display:none">
    <?= csrfField() ?>
    <input type="hidden" name="page" value="aduan">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="id" id="approve_id">
    <input type="hidden" name="status" id="approve_status">
    <input type="hidden" name="alasan_penolakan" value="">
</form>

<script>
function updateStatus(id, status) {
    if (confirm('Setujui pengaduan ini?')) {
        document.getElementById('approve_id').value = id;
        document.getElementById('approve_status').value = status;
        document.getElementById('formApprove').submit();
    }
}

function showReject(id) {
    document.getElementById('reject_id').value = id;
    document.getElementById('modalReject').classList.add('show');
}
</script>
