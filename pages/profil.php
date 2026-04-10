<?php
// Halaman Profil Pegawai
$msg = '';
$msgType = 'success';

// Get current user data with prepared statement
$userId = (int)$_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $nama_lengkap = sanitize($_POST['nama_lengkap']);
            $email = sanitize($_POST['email']);
            $no_hp = sanitize($_POST['no_hp']);
            $alamat = sanitize($_POST['alamat']);

            // Validate email format if provided
            if (!empty($email) && !validateEmail($email)) {
                $msg = 'Format email tidak valid!';
                $msgType = 'danger';
            }

            // Handle foto upload securely
            $foto_profil = $user['foto_profil'];
            if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = safeUploadFile($_FILES['foto_profil'], 'uploads/', 'profil');
                if (!$uploadResult['success']) {
                    $msg = $uploadResult['error'];
                    $msgType = 'danger';
                } else if ($uploadResult['filename']) {
                    // Delete old photo if exists
                    if ($foto_profil && file_exists('uploads/' . $foto_profil)) {
                        @unlink('uploads/' . $foto_profil);
                    }
                    $foto_profil = $uploadResult['filename'];
                }
            }

            if ($msgType !== 'danger') {
                $stmt = $db->prepare("UPDATE pegawai SET nama_lengkap=?, email=?, no_hp=?, alamat=?, foto_profil=? WHERE id_pegawai=?");
                $stmt->bind_param('sssssi', $nama_lengkap, $email, $no_hp, $alamat, $foto_profil, $userId);
                
                if ($stmt->execute()) {
                    $msg = 'Profil berhasil diperbarui!';
                    $_SESSION['user_name'] = $nama_lengkap;
                    $_SESSION['user_foto'] = $foto_profil;
                    // Refresh user data
                    $stmt2 = $db->prepare("SELECT * FROM pegawai WHERE id_pegawai = ?");
                    $stmt2->bind_param('i', $userId);
                    $stmt2->execute();
                    $user = $stmt2->get_result()->fetch_assoc();
                } else {
                    $msg = 'Gagal memperbarui profil.';
                    $msgType = 'danger';
                }
            }
        }

        if ($action === 'change_password') {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (!password_verify($current_password, $user['password'])) {
                $msg = 'Password saat ini salah!';
                $msgType = 'danger';
            } elseif (strlen($new_password) < 6) {
                $msg = 'Password baru minimal 6 karakter!';
                $msgType = 'danger';
            } elseif ($new_password !== $confirm_password) {
                $msg = 'Konfirmasi password tidak cocok!';
                $msgType = 'danger';
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE pegawai SET password=? WHERE id_pegawai=?");
                $stmt->bind_param('si', $hashed, $userId);
                
                if ($stmt->execute()) {
                    $msg = 'Password berhasil diubah!';
                } else {
                    $msg = 'Gagal mengubah password.';
                    $msgType = 'danger';
                }
            }
        }
    }
}
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 32px;
    color: white;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 300px;
    height: 100%;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.profile-content {
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    z-index: 1;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 20px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    font-weight: 800;
    border: 4px solid rgba(255,255,255,0.3);
    overflow: hidden;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-info h2 {
    font-size: 26px;
    font-weight: 800;
    margin-bottom: 4px;
}

.profile-info p {
    opacity: 0.9;
    font-size: 14px;
}

.profile-badges {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.profile-badge {
    padding: 6px 12px;
    background: rgba(255,255,255,0.2);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

@media (max-width: 900px) {
    .profile-grid {
        grid-template-columns: 1fr;
    }
}

.profile-section {
    background: var(--card);
    border-radius: 14px;
    border: 1px solid var(--border);
}

.section-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header i {
    color: var(--accent);
}

.section-header h3 {
    font-size: 15px;
    font-weight: 700;
}

.section-body {
    padding: 20px;
}

.info-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px dashed var(--border);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    font-size: 13px;
    color: var(--muted);
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
}

.upload-area {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 16px;
}

.upload-area:hover {
    border-color: var(--accent);
    background: rgba(99, 102, 241, 0.03);
}

.upload-area i {
    font-size: 32px;
    color: var(--muted);
    margin-bottom: 8px;
}

.upload-area p {
    font-size: 13px;
    color: var(--muted);
}

.upload-area input {
    display: none;
}

.current-photo {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: var(--bg);
    border-radius: 10px;
    margin-bottom: 16px;
}

.current-photo img {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
}

.current-photo-info {
    flex: 1;
}

.current-photo-info p {
    font-size: 13px;
    font-weight: 600;
}

.current-photo-info span {
    font-size: 11px;
    color: var(--muted);
}
</style>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-content">
        <div class="profile-avatar">
            <?php if ($user['foto_profil'] && file_exists("uploads/{$user['foto_profil']}")): ?>
            <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($user['foto_profil']) ?>" alt="Profile">
            <?php else: ?>
            <?= getInitials($user['nama_lengkap']) ?>
            <?php endif; ?>
        </div>
        <div class="profile-info">
            <h2><?= htmlspecialchars($user['nama_lengkap']) ?></h2>
            <p><?= htmlspecialchars($user['jabatan'] ?? 'Pegawai') ?></p>
            <div class="profile-badges">
                <span class="profile-badge"><i class="fas fa-id-card"></i> <?= htmlspecialchars($user['nip']) ?></span>
                <span class="profile-badge"><i class="fas fa-user"></i> <?= $isAdminUser ? 'Administrator' : 'Pegawai' ?></span>
            </div>
        </div>
    </div>
</div>

<div class="profile-grid">
    <!-- Informasi Akun -->
    <div class="profile-section">
        <div class="section-header">
            <i class="fas fa-user-circle"></i>
            <h3>Informasi Akun</h3>
        </div>
        <div class="section-body">
            <div class="info-list">
                <div class="info-item">
                    <span class="info-label">NIP</span>
                    <span class="info-value"><?= htmlspecialchars($user['nip'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value"><?= htmlspecialchars($user['username'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Jabatan</span>
                    <span class="info-value"><?= htmlspecialchars($user['jabatan'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($user['email'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">No. HP</span>
                    <span class="info-value"><?= htmlspecialchars($user['no_hp'] ?? '-') ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Bergabung</span>
                    <span class="info-value"><?= date('d M Y', strtotime($user['tanggal_dibuat'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profil -->
    <div class="profile-section">
        <div class="section-header">
            <i class="fas fa-edit"></i>
            <h3>Edit Profil</h3>
        </div>
        <div class="section-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder="email@example.com">
                    </div>
                    <div class="form-group">
                        <label>No. HP</label>
                        <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap..."><?= htmlspecialchars($user['alamat'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Foto Profil</label>
                    <?php if ($user['foto_profil'] && file_exists("uploads/{$user['foto_profil']}")): ?>
                    <div class="current-photo">
                        <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($user['foto_profil']) ?>" alt="Current">
                        <div class="current-photo-info">
                            <p>Foto saat ini</p>
                            <span>Upload baru untuk mengganti</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <label class="upload-area">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Klik untuk upload foto baru</p>
                        <input type="file" name="foto_profil" accept="image/*" onchange="this.parentElement.querySelector('p').textContent = this.files[0]?.name || 'Klik untuk upload foto baru'">
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- Ubah Password -->
    <div class="profile-section">
        <div class="section-header">
            <i class="fas fa-lock"></i>
            <h3>Ubah Password</h3>
        </div>
        <div class="section-body">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="change_password">
                
                <div class="form-group">
                    <label>Password Saat Ini</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Password Baru</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>

                <div class="form-group">
                    <label>Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%">
                    <i class="fas fa-key"></i> Ubah Password
                </button>
            </form>
        </div>
    </div>

    <!-- Ringkasan Aktivitas -->
    <div class="profile-section">
        <div class="section-header">
            <i class="fas fa-chart-pie"></i>
            <h3>Ringkasan Aktivitas</h3>
        </div>
        <div class="section-body">
            <?php
            // Get user stats
            $userNip = $_SESSION['user_nip'] ?? '';
            $stmtIzin = $db->prepare("SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as disetujui,
                SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as ditolak
                FROM izin WHERE id_pegawai = ?");
            $stmtIzin->bind_param('i', $userId);
            $stmtIzin->execute();
            $izinStats = $stmtIzin->get_result()->fetch_assoc();
            
            $stmtPen = $db->prepare("SELECT AVG(rata_rata) as avg_nilai FROM penilaian WHERE nip=?");
            $stmtPen->bind_param('s', $userNip);
            $stmtPen->execute();
            $penilaianResult = $stmtPen->get_result();
            $avgNilai = $penilaianResult ? ($penilaianResult->fetch_assoc()['avg_nilai'] ?? 0) : 0;
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div style="padding:16px;background:linear-gradient(135deg,#dbeafe,#eff6ff);border-radius:12px;text-align:center">
                    <div style="font-size:28px;font-weight:800;color:#2563eb"><?= $izinStats['total'] ?? 0 ?></div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px">Total Izin</div>
                </div>
                <div style="padding:16px;background:linear-gradient(135deg,#fef3c7,#fffbeb);border-radius:12px;text-align:center">
                    <div style="font-size:28px;font-weight:800;color:#d97706"><?= $izinStats['pending'] ?? 0 ?></div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px">Menunggu</div>
                </div>
                <div style="padding:16px;background:linear-gradient(135deg,#d1fae5,#ecfdf5);border-radius:12px;text-align:center">
                    <div style="font-size:28px;font-weight:800;color:#059669"><?= $izinStats['disetujui'] ?? 0 ?></div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px">Disetujui</div>
                </div>
                <div style="padding:16px;background:linear-gradient(135deg,#fce7f3,#fdf2f8);border-radius:12px;text-align:center">
                    <div style="font-size:28px;font-weight:800;color:#db2777"><?= number_format($avgNilai, 1) ?></div>
                    <div style="font-size:12px;color:#64748b;margin-top:4px">Rata-rata Nilai</div>
                </div>
            </div>
        </div>
    </div>
</div>
