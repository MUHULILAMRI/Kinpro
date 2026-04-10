<?php
// Halaman Pengajuan Izin Pegawai
$msg = '';
$msgType = 'success';
$userId = (int)$_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'ajukan_izin') {
            $jenis_izin = sanitize($_POST['jenis_izin']);
            $tanggal_mulai = sanitize($_POST['tanggal_mulai']);
            $tanggal_selesai = sanitize($_POST['tanggal_selesai']);
            $keterangan = sanitize($_POST['keterangan'] ?? $_POST['alasan'] ?? '');

            // Validate dates
            if (strtotime($tanggal_selesai) < strtotime($tanggal_mulai)) {
                $msg = 'Tanggal selesai tidak boleh sebelum tanggal mulai!';
                $msgType = 'danger';
            } else {
                // Handle document upload securely
                $dokumen = null;
                if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadResult = safeUploadFile($_FILES['dokumen'], 'uploads/pengaduan/', 'izin');
                    if (!$uploadResult['success']) {
                        $msg = $uploadResult['error'];
                        $msgType = 'danger';
                    } else {
                        $dokumen = $uploadResult['filename'];
                    }
                }

                if ($msgType !== 'danger') {
                    // Cek kuota izin bulan ini (maks 4 kali per bulan)
                    $bulanCek = date('Y-m');
                    $stmtCek = $db->prepare("SELECT COUNT(*) as total FROM izin WHERE id_pegawai = ? AND DATE_FORMAT(tanggal_pengajuan, '%Y-%m') = ?");
                    $stmtCek->bind_param('is', $userId, $bulanCek);
                    $stmtCek->execute();
                    $totalBulanIni = (int)$stmtCek->get_result()->fetch_assoc()['total'];

                    // Hitung reset dari admin
                    $stmtCekReset = $db->prepare("SELECT COALESCE(SUM(jumlah_reset),0) as total_reset FROM reset_kuota_izin WHERE id_pegawai = ? AND bulan = ?");
                    $stmtCekReset->bind_param('is', $userId, $bulanCek);
                    $stmtCekReset->execute();
                    $totalResetCek = (int)$stmtCekReset->get_result()->fetch_assoc()['total_reset'];
                    $kuotaEfektifCek = max(0, $totalBulanIni - $totalResetCek);

                    if ($kuotaEfektifCek >= 4) {
                        $msg = 'Pengajuan ditolak otomatis! Anda sudah mencapai batas maksimal 4 kali pengajuan izin di bulan ini.';
                        $msgType = 'danger';
                    } else {
                        $stmt = $db->prepare("INSERT INTO izin (id_pegawai, jenis_izin, tanggal_mulai, tanggal_selesai, keterangan, dokumen) VALUES (?,?,?,?,?,?)");
                        $stmt->bind_param('isssss', $userId, $jenis_izin, $tanggal_mulai, $tanggal_selesai, $keterangan, $dokumen);
                        
                        if ($stmt->execute()) {
                            $msg = 'Pengajuan izin berhasil dikirim! Menunggu persetujuan admin.';
                        } else {
                            $msg = 'Gagal mengajukan izin.';
                            $msgType = 'danger';
                        }
                    }
                }
            }
        }

        if ($action === 'batalkan_izin') {
            $id_izin = (int)$_POST['id_izin'];
            // Only allow canceling pending requests - use prepared statement
            $stmt = $db->prepare("DELETE FROM izin WHERE id_izin = ? AND id_pegawai = ? AND status = 'pending'");
            $stmt->bind_param('ii', $id_izin, $userId);
            if ($stmt->execute()) {
                $msg = 'Pengajuan izin dibatalkan.';
            } else {
                $msg = 'Gagal membatalkan izin.';
                $msgType = 'danger';
            }
        }
    }
}

// Get user's izin history with prepared statement
$stmt = $db->prepare("SELECT * FROM izin WHERE id_pegawai = ? ORDER BY tanggal_pengajuan DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$riwayatIzin = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Hitung jumlah pengajuan izin bulan ini (semua status dihitung)
$bulanIni = date('Y-m');
$stmtKuota = $db->prepare("SELECT COUNT(*) as total FROM izin WHERE id_pegawai = ? AND DATE_FORMAT(tanggal_pengajuan, '%Y-%m') = ?");
$stmtKuota->bind_param('is', $userId, $bulanIni);
$stmtKuota->execute();
$jumlahBulanIni = (int)$stmtKuota->get_result()->fetch_assoc()['total'];

// Hitung reset kuota dari admin
$stmtReset = $db->prepare("SELECT COALESCE(SUM(jumlah_reset),0) as total_reset FROM reset_kuota_izin WHERE id_pegawai = ? AND bulan = ?");
$stmtReset->bind_param('is', $userId, $bulanIni);
$stmtReset->execute();
$totalReset = (int)$stmtReset->get_result()->fetch_assoc()['total_reset'];

$batasIzinPerBulan = 4;
$kuotaEfektif = max(0, $jumlahBulanIni - $totalReset);
$sisaKuota = max(0, $batasIzinPerBulan - $kuotaEfektif);
$kuotaHabis = ($sisaKuota <= 0);

// Jenis izin mapping
$jenisIzinLabels = [
    'cuti_tahunan' => ['label' => 'Cuti Tahunan', 'icon' => 'umbrella-beach', 'color' => '#3b82f6'],
    'sakit' => ['label' => 'Sakit', 'icon' => 'briefcase-medical', 'color' => '#ef4444'],
    'izin_khusus' => ['label' => 'Izin Khusus', 'icon' => 'calendar-day', 'color' => '#8b5cf6'],
    'dinas_luar' => ['label' => 'Dinas Luar', 'icon' => 'plane', 'color' => '#10b981'],
];

// Status mapping
$statusLabels = [
    'pending' => ['label' => 'Menunggu', 'class' => 'badge-average', 'icon' => 'clock'],
    'approved' => ['label' => 'Disetujui', 'class' => 'badge-excellent', 'icon' => 'check-circle'],
    'rejected' => ['label' => 'Ditolak', 'class' => 'badge-poor', 'icon' => 'times-circle'],
];
?>

<style>
.izin-header {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-radius: 16px;
    padding: 28px;
    color: white;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.izin-header-info h2 {
    font-size: 24px;
    font-weight: 800;
    margin-bottom: 6px;
}

.izin-header-info p {
    opacity: 0.9;
    font-size: 14px;
}

.izin-stats {
    display: flex;
    gap: 20px;
}

.izin-stat {
    text-align: center;
    padding: 12px 20px;
    background: rgba(255,255,255,0.15);
    border-radius: 12px;
}

.izin-stat-value {
    font-size: 24px;
    font-weight: 800;
}

.izin-stat-label {
    font-size: 11px;
    opacity: 0.9;
    margin-top: 2px;
}

.izin-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .izin-grid {
        grid-template-columns: 1fr;
    }
}

.izin-form-card {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
    position: sticky;
    top: 80px;
}

.form-card-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-card-header i {
    color: var(--accent);
}

.form-card-header h3 {
    font-size: 15px;
    font-weight: 700;
}

.form-card-body {
    padding: 20px;
}

.jenis-izin-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 16px;
}

.jenis-izin-option {
    position: relative;
}

.jenis-izin-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.jenis-izin-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 16px 12px;
    border: 2px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.jenis-izin-option label:hover {
    border-color: var(--accent);
    background: rgba(99, 102, 241, 0.03);
}

.jenis-izin-option input:checked + label {
    border-color: var(--accent);
    background: rgba(99, 102, 241, 0.08);
}

.jenis-izin-option label i {
    font-size: 24px;
    margin-bottom: 8px;
}

.jenis-izin-option label span {
    font-size: 12px;
    font-weight: 600;
}

.date-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.riwayat-section {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
}

.riwayat-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.riwayat-header h3 {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.riwayat-list {
    max-height: 600px;
    overflow-y: auto;
}

.izin-item {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border-light);
    transition: background 0.2s;
}

.izin-item:last-child {
    border-bottom: none;
}

.izin-item:hover {
    background: var(--bg);
}

.izin-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.izin-type {
    display: flex;
    align-items: center;
    gap: 10px;
}

.izin-type-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: white;
}

.izin-type-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.izin-type-info span {
    font-size: 11px;
    color: var(--muted);
}

.izin-dates {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--text);
    margin-bottom: 8px;
}

.izin-dates i {
    color: var(--muted);
}

.izin-reason {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.5;
    padding: 10px 12px;
    background: var(--bg);
    border-radius: 8px;
}

.izin-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
}

.izin-meta {
    font-size: 11px;
    color: var(--muted);
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
}

.empty-state i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state h4 {
    font-size: 16px;
    color: var(--text);
    margin-bottom: 6px;
}

.empty-state p {
    font-size: 13px;
    color: var(--muted);
}

.duration-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    background: var(--bg);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--muted);
}

.catatan-admin {
    margin-top: 10px;
    padding: 10px 12px;
    background: #fef3c7;
    border-radius: 8px;
    border-left: 3px solid #f59e0b;
}

.catatan-admin p {
    font-size: 12px;
    color: #92400e;
}

.catatan-admin span {
    font-weight: 600;
}
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- Header -->
<?php
$totalIzin = count($riwayatIzin);
$pendingIzin = count(array_filter($riwayatIzin, fn($i) => $i['status'] === 'pending'));
$disetujuiIzin = count(array_filter($riwayatIzin, fn($i) => $i['status'] === 'approved'));
?>
<div class="izin-header">
    <div class="izin-header-info">
        <h2><i class="fas fa-calendar-check"></i> Pengajuan Izin</h2>
        <p>Ajukan cuti atau izin secara online dengan mudah</p>
    </div>
    <div class="izin-stats">
        <div class="izin-stat">
            <div class="izin-stat-value"><?= $totalIzin ?></div>
            <div class="izin-stat-label">Total Pengajuan</div>
        </div>
        <div class="izin-stat">
            <div class="izin-stat-value"><?= $pendingIzin ?></div>
            <div class="izin-stat-label">Menunggu</div>
        </div>
        <div class="izin-stat">
            <div class="izin-stat-value"><?= $disetujuiIzin ?></div>
            <div class="izin-stat-label">Disetujui</div>
        </div>
        <div class="izin-stat" style="<?= $kuotaHabis ? 'background:rgba(239,68,68,0.3)' : '' ?>">
            <div class="izin-stat-value"><?= $sisaKuota ?>/<?= $batasIzinPerBulan ?></div>
            <div class="izin-stat-label">Sisa Kuota Bulan Ini</div>
        </div>
    </div>
</div>

<div class="izin-grid">
    <!-- Form Pengajuan -->
    <div class="izin-form-card">
        <div class="form-card-header">
            <i class="fas fa-plus-circle"></i>
            <h3>Ajukan Izin Baru</h3>
        </div>
        <div class="form-card-body">
            <?php if ($kuotaHabis): ?>
            <div style="text-align:center;padding:40px 20px;">
                <div style="width:70px;height:70px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                    <i class="fas fa-ban" style="font-size:32px;color:#ef4444;"></i>
                </div>
                <h4 style="font-size:16px;font-weight:700;color:#991b1b;margin-bottom:8px;">Kuota Izin Habis</h4>
                <p style="font-size:13px;color:#64748b;line-height:1.6;">Anda sudah mengajukan <strong><?= $jumlahBulanIni ?> kali</strong> izin di bulan <?= strftime('%B %Y', strtotime($bulanIni . '-01')) ?: date('F Y') ?>. Batas maksimal adalah <strong><?= $batasIzinPerBulan ?> kali per bulan</strong>.</p>
                <div style="margin-top:16px;padding:12px;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;">
                    <p style="font-size:12px;color:#991b1b;"><i class="fas fa-info-circle"></i> Kuota akan direset pada awal bulan berikutnya.</p>
                </div>
            </div>
            <?php else: ?>
            <div style="padding:10px 0 14px;margin-bottom:12px;border-bottom:1px solid var(--border-light);display:flex;align-items:center;gap:8px;">
                <i class="fas fa-ticket-alt" style="color:<?= $sisaKuota <= 1 ? '#ef4444' : '#10b981' ?>"></i>
                <span style="font-size:13px;color:var(--text);">Sisa kuota: <strong style="color:<?= $sisaKuota <= 1 ? '#ef4444' : '#10b981' ?>"><?= $sisaKuota ?></strong> dari <?= $batasIzinPerBulan ?> kali bulan ini</span>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="ajukan_izin">
                
                <div class="form-group">
                    <label>Jenis Izin</label>
                    <div class="jenis-izin-grid">
                        <?php foreach ($jenisIzinLabels as $key => $info): ?>
                        <div class="jenis-izin-option">
                            <input type="radio" name="jenis_izin" id="jenis_<?= $key ?>" value="<?= $key ?>" <?= $key === 'cuti_tahunan' ? 'checked' : '' ?>>
                            <label for="jenis_<?= $key ?>">
                                <i class="fas fa-<?= $info['icon'] ?>" style="color:<?= $info['color'] ?>"></i>
                                <span><?= $info['label'] ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Periode Izin</label>
                    <div class="date-grid">
                        <div>
                            <input type="date" name="tanggal_mulai" class="form-control" required min="<?= date('Y-m-d') ?>">
                            <small style="color:var(--muted);font-size:11px">Tanggal Mulai</small>
                        </div>
                        <div>
                            <input type="date" name="tanggal_selesai" class="form-control" required min="<?= date('Y-m-d') ?>">
                            <small style="color:var(--muted);font-size:11px">Tanggal Selesai</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Alasan</label>
                    <textarea name="alasan" class="form-control" rows="3" placeholder="Jelaskan alasan pengajuan izin..." required></textarea>
                </div>

                <div class="form-group">
                    <label>Dokumen Pendukung (opsional)</label>
                    <input type="file" name="dokumen" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                    <small style="color:var(--muted);font-size:11px">Format: JPG, PNG, PDF, DOC (maks 2MB)</small>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <i class="fas fa-paper-plane"></i> Ajukan Izin
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Riwayat Izin -->
    <div class="riwayat-section">
        <div class="riwayat-header">
            <h3><i class="fas fa-history"></i> Riwayat Pengajuan</h3>
            <span style="font-size:13px;color:var(--muted)"><?= $totalIzin ?> pengajuan</span>
        </div>
        <div class="riwayat-list">
            <?php if (empty($riwayatIzin)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>Belum Ada Pengajuan</h4>
                <p>Anda belum pernah mengajukan izin. Mulai ajukan izin pertama Anda!</p>
            </div>
            <?php else: ?>
            <?php foreach ($riwayatIzin as $izin): 
                $jenisInfo = $jenisIzinLabels[$izin['jenis_izin']] ?? ['label' => $izin['jenis_izin'], 'icon' => 'calendar', 'color' => '#64748b'];
                $statusInfo = $statusLabels[$izin['status']] ?? ['label' => $izin['status'], 'class' => '', 'icon' => 'question'];
                $start = new DateTime($izin['tanggal_mulai']);
                $end = new DateTime($izin['tanggal_selesai']);
                $duration = $start->diff($end)->days + 1;
            ?>
            <div class="izin-item">
                <div class="izin-item-header">
                    <div class="izin-type">
                        <div class="izin-type-icon" style="background:<?= $jenisInfo['color'] ?>">
                            <i class="fas fa-<?= $jenisInfo['icon'] ?>"></i>
                        </div>
                        <div class="izin-type-info">
                            <h4><?= $jenisInfo['label'] ?></h4>
                            <span><?= date('d M Y, H:i', strtotime($izin['tanggal_pengajuan'])) ?></span>
                        </div>
                    </div>
                    <span class="badge <?= $statusInfo['class'] ?>">
                        <i class="fas fa-<?= $statusInfo['icon'] ?>"></i>
                        <?= $statusInfo['label'] ?>
                    </span>
                </div>
                
                <div class="izin-dates">
                    <i class="fas fa-calendar"></i>
                    <?= date('d M Y', strtotime($izin['tanggal_mulai'])) ?> — <?= date('d M Y', strtotime($izin['tanggal_selesai'])) ?>
                    <span class="duration-badge">
                        <i class="fas fa-clock"></i> <?= $duration ?> hari
                    </span>
                </div>

                <div class="izin-reason">
                    <?= htmlspecialchars($izin['keterangan'] ?? '') ?>
                </div>

                <?php if ($izin['catatan_admin']): ?>
                <div class="catatan-admin">
                    <p><span>Catatan Admin:</span> <?= htmlspecialchars($izin['catatan_admin']) ?></p>
                </div>
                <?php endif; ?>

                <div class="izin-footer">
                    <div class="izin-meta">
                        <?php if ($izin['dokumen']): ?>
                        <a href="<?= getBaseUrl() ?>uploads/pengaduan/<?= htmlspecialchars($izin['dokumen']) ?>" target="_blank" style="color:var(--accent);text-decoration:none">
                            <i class="fas fa-paperclip"></i> Lihat Dokumen
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if ($izin['status'] === 'pending'): ?>
                    <form method="POST" onsubmit="return confirm('Batalkan pengajuan ini?')" style="margin:0">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="batalkan_izin">
                        <input type="hidden" name="id_izin" value="<?= $izin['id_izin'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger)">
                            <i class="fas fa-times"></i> Batalkan
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
