<?php
// $db sudah tersedia dari index.php
$msg = '';
$msgType = 'success';

// HANDLE POST - disesuaikan dengan struktur tabel pegawai kemenpu2
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'tambah' || $action === 'edit') {
            $nip = sanitize($_POST['nip']);
            $nama_lengkap = sanitize($_POST['nama_lengkap']);
            $jabatan = sanitize($_POST['jabatan']);
            $username = sanitize($_POST['username']);
            $password = $_POST['password'] ?? '';

            if ($action === 'tambah') {
                $status_pegawai = sanitize($_POST['status_pegawai'] ?? '');
                $hashed_password = password_hash($password ?: 'password123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO pegawai (nip, nama_lengkap, jabatan, status_pegawai, username, password) VALUES (?,?,?,?,?,?)");
                $stmt->bind_param('ssssss', $nip, $nama_lengkap, $jabatan, $status_pegawai, $username, $hashed_password);
                if ($stmt->execute()) { $msg = 'Pegawai berhasil ditambahkan!'; }
                else { $msg = 'Gagal: NIP atau Username sudah terdaftar.'; $msgType = 'danger'; }
            } else {
                $id = (int)$_POST['id_pegawai'];
                $status_pegawai = sanitize($_POST['status_pegawai'] ?? '');
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE pegawai SET nip=?, nama_lengkap=?, jabatan=?, status_pegawai=?, username=?, password=? WHERE id_pegawai=?");
                    $stmt->bind_param('ssssssi', $nip, $nama_lengkap, $jabatan, $status_pegawai, $username, $hashed_password, $id);
                } else {
                    $stmt = $db->prepare("UPDATE pegawai SET nip=?, nama_lengkap=?, jabatan=?, status_pegawai=?, username=? WHERE id_pegawai=?");
                    $stmt->bind_param('sssssi', $nip, $nama_lengkap, $jabatan, $status_pegawai, $username, $id);
                }
                if ($stmt->execute()) { $msg = 'Data pegawai berhasil diperbarui!'; }
                else { $msg = 'Gagal memperbarui data.'; $msgType = 'danger'; }
            }
        }

        if ($action === 'hapus') {
            $id = (int)$_POST['id_pegawai'];
            $stmt = $db->prepare("DELETE FROM pegawai WHERE id_pegawai = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Pegawai berhasil dihapus.';
            } else {
                $msg = 'Gagal menghapus pegawai.';
                $msgType = 'danger';
            }
        }
    }
}

// Use prepared statements for search queries
$search = sanitize($_GET['search'] ?? '');
$filter_jabatan = sanitize($_GET['jabatan'] ?? '');
$filter_status = sanitize($_GET['status_pegawai'] ?? '');

// Build query with prepared statement
$params = [];
$types = '';
$where = "WHERE 1=1";

if ($search) {
    $searchParam = '%' . $search . '%';
    $where .= " AND (nama_lengkap LIKE ? OR nip LIKE ? OR jabatan LIKE ?)";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}
if ($filter_jabatan) {
    $where .= " AND jabatan = ?";
    $params[] = $filter_jabatan;
    $types .= 's';
}
if ($filter_status) {
    $where .= " AND status_pegawai = ?";
    $params[] = $filter_status;
    $types .= 's';
}

$stmt = $db->prepare("SELECT * FROM pegawai $where ORDER BY nama_lengkap");
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$pegawai = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$jabatanList = $db->query("SELECT DISTINCT jabatan FROM pegawai WHERE jabatan IS NOT NULL AND jabatan != '' ORDER BY jabatan")->fetch_all(MYSQLI_ASSOC);

// Get latest penilaian for each pegawai
$penilaianData = [];
$penilaianResult = $db->query("SELECT p.nip, p.nilai_kedisiplinan, p.kinerja, p.sikap, p.kepemimpinan, p.loyalitas, p.it, p.rata_rata,
    p.bulan, p.tahun
    FROM penilaian p
    INNER JOIN (
        SELECT nip, MAX(id_penilaian) as max_id
        FROM penilaian
        GROUP BY nip
    ) latest ON p.nip = latest.nip AND p.id_penilaian = latest.max_id");
if ($penilaianResult) {
    while ($row = $penilaianResult->fetch_assoc()) {
        $penilaianData[$row['nip']] = $row;
    }
}
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&display=swap');

.pegawai-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 30px;
    padding: 24px;
}

/* === FIFA CARD FRAME === */
.fut-card {
    position: relative;
    width: 260px;
    height: 400px;
    margin: 0 auto;
    transition: transform 0.3s ease, filter 0.3s ease;
    cursor: default;
}

.fut-card:hover {
    transform: translateY(-10px) scale(1.03);
    filter: brightness(1.05);
}

.fut-card:hover .fut-actions {
    opacity: 1;
}

/* SVG frame background */
.fut-card-frame {
    position: absolute;
    inset: 0;
    z-index: 0;
    width: 100%;
    height: 100%;
}

/* Content overlay */
.fut-card-content {
    position: absolute;
    inset: 0;
    z-index: 1;
    font-family: 'Oswald', sans-serif;
    display: flex;
    flex-direction: column;
}

/* === TOP AREA: left info + right photo === */
.fut-top {
    display: flex;
    align-items: flex-start;
    padding: 20px 14px 0 20px;
    height: 220px;
    position: relative;
}

/* Left column */
.fut-left {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 50px;
    z-index: 2;
    padding-top: 8px;
}

.fut-rating {
    font-size: 42px;
    font-weight: 700;
    line-height: 1;
    color: #3b2a14;
}

.fut-pos {
    font-size: 9px;
    font-weight: 600;
    color: #3b2a14;
    letter-spacing: 0.5px;
    margin-top: 0;
}

.fut-flag {
    margin-top: 6px;
    width: 28px;
    height: 18px;
    background: linear-gradient(180deg, #000 33%, #f7d618 33%, #f7d618 66%, #e31e24 66%);
    border-radius: 2px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.fut-club-logo {
    margin-top: 5px;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.fut-club-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

/* Right photo area */
.fut-photo {
    flex: 1;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    position: relative;
    overflow: hidden;
    height: 100%;
}

.fut-photo img {
    max-height: 175px;
    max-width: 100%;
    object-fit: contain;
    filter: drop-shadow(0 3px 8px rgba(0,0,0,0.25));
}

.fut-photo .fut-avatar {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    font-weight: 800;
    color: white;
    font-family: 'Oswald', sans-serif;
    filter: drop-shadow(0 3px 8px rgba(0,0,0,0.25));
    margin-bottom: 8px;
}

/* === BOTTOM AREA: name + jabatan + stats === */
.fut-bottom {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 24px 0;
}

.fut-line {
    width: 75%;
    height: 2px;
    background: rgba(59, 42, 20, 0.2);
    margin: 2px 0;
    position: relative;
}

.fut-line::after {
    content: '';
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%,-50%);
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: rgba(59, 42, 20, 0.15);
}

.fut-name {
    font-size: 15px;
    font-weight: 700;
    color: #3b2a14;
    text-transform: uppercase;
    letter-spacing: 2px;
    text-align: center;
    padding: 2px 0 0;
    max-width: 100%;
    white-space: nowrap;
    line-height: 1.2;
}

.fut-jabatan {
    font-size: 9px;
    font-weight: 400;
    color: #3b2a14;
    opacity: 0.7;
    text-align: center;
    letter-spacing: 0.5px;
    padding: 0 0 2px;
    max-width: 100%;
    white-space: nowrap;
    font-family: 'Oswald', sans-serif;
    text-transform: uppercase;
}

.fut-card.tier-special .fut-jabatan { color: #8dc6ff; }

/* Stats */
.fut-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0px 14px;
    width: 80%;
    margin-top: 2px;
}

.fut-stat {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.fut-stat-val {
    font-size: 16px;
    font-weight: 700;
    color: #3b2a14;
}

.fut-stat-lbl {
    font-size: 11px;
    font-weight: 500;
    color: #3b2a14;
    opacity: 0.7;
    letter-spacing: 1px;
}

/* === TIER COLORS === */

/* Gold (default) - colors in SVG */
.fut-card .fut-gold-stop1 { stop-color: #b8860b; }
.fut-card .fut-gold-stop2 { stop-color: #f5d060; }
.fut-card .fut-gold-stop3 { stop-color: #e8c84a; }
.fut-card .fut-gold-stop4 { stop-color: #c5983a; }
.fut-card .fut-gold-stop5 { stop-color: #f5d060; }
.fut-card .fut-gold-stop6 { stop-color: #b8860b; }

/* Silver */
.fut-card.tier-silver .fut-gold-stop1 { stop-color: #8a9bae; }
.fut-card.tier-silver .fut-gold-stop2 { stop-color: #c0cfe0; }
.fut-card.tier-silver .fut-gold-stop3 { stop-color: #b0bfcf; }
.fut-card.tier-silver .fut-gold-stop4 { stop-color: #8a9bae; }
.fut-card.tier-silver .fut-gold-stop5 { stop-color: #c0cfe0; }
.fut-card.tier-silver .fut-gold-stop6 { stop-color: #8a9bae; }

/* Bronze */
.fut-card.tier-bronze .fut-gold-stop1 { stop-color: #8b5e3c; }
.fut-card.tier-bronze .fut-gold-stop2 { stop-color: #cd8c52; }
.fut-card.tier-bronze .fut-gold-stop3 { stop-color: #b87a48; }
.fut-card.tier-bronze .fut-gold-stop4 { stop-color: #8b5e3c; }
.fut-card.tier-bronze .fut-gold-stop5 { stop-color: #cd8c52; }
.fut-card.tier-bronze .fut-gold-stop6 { stop-color: #8b5e3c; }

/* Special (TOTY) */
.fut-card.tier-special .fut-gold-stop1 { stop-color: #0d1b3e; }
.fut-card.tier-special .fut-gold-stop2 { stop-color: #1a3a6b; }
.fut-card.tier-special .fut-gold-stop3 { stop-color: #234d8a; }
.fut-card.tier-special .fut-gold-stop4 { stop-color: #1a3a6b; }
.fut-card.tier-special .fut-gold-stop5 { stop-color: #0d1b3e; }
.fut-card.tier-special .fut-gold-stop6 { stop-color: #0a1530; }
.fut-card.tier-special .fut-rating,
.fut-card.tier-special .fut-pos,
.fut-card.tier-special .fut-name,
.fut-card.tier-special .fut-stat-val,
.fut-card.tier-special .fut-stat-lbl { color: #8dc6ff; }
.fut-card.tier-special .fut-line { background: rgba(141,198,255,0.2); }
.fut-card.tier-special .fut-line::after { background: rgba(141,198,255,0.15); }

/* No score muted */
.fut-card.no-score .fut-rating { opacity: 0.45; }

/* Hover actions */
.fut-actions {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 10;
}

.fut-actions button {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}

.fut-actions .btn-edit {
    background: rgba(255,255,255,0.95);
    color: #3b82f6;
}

.fut-actions .btn-delete {
    background: rgba(255,255,255,0.95);
    color: #ef4444;
}

.fut-actions button:hover {
    transform: scale(1.15);
}

/* View toggle */
.view-toggle {
    display: flex;
    gap: 8px;
    margin-left: auto;
}

.view-toggle button {
    padding: 8px 12px;
    border: 1px solid var(--border);
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    color: var(--muted);
    transition: all 0.2s;
}

.view-toggle button.active {
    background: var(--accent);
    color: white;
    border-color: var(--accent);
}

/* Responsive */
@media (max-width: 768px) {
    .pegawai-grid {
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 16px;
        padding: 12px;
    }
    .fut-card { width: 200px; height: 310px; }
    .fut-top { height: 170px; padding: 16px 10px 0 14px; }
    .fut-rating { font-size: 30px; }
    .fut-pos { font-size: 8px; }
    .fut-photo img { max-height: 125px; }
    .fut-photo .fut-avatar { width: 80px; height: 80px; font-size: 28px; }
    .fut-name { font-size: 11px; letter-spacing: 1.5px; }
    .fut-jabatan { font-size: 7px; }
    .fut-stat-val { font-size: 13px; }
    .fut-stat-lbl { font-size: 9px; }
    .fut-left { min-width: 36px; }
    .fut-club-logo { width: 22px; height: 22px; }
    .fut-flag { width: 22px; height: 14px; }
    .fut-bottom { padding: 0 16px 0; }
    .fut-stats { gap: 0 10px; width: 85%; }
}
</style>

<div class="page-header">
    <div>
        <h2>Data Pegawai</h2>
        <p>Kelola seluruh data pegawai Kemen PU</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalTambah').classList.add('show')">
        <i class="fas fa-plus"></i> Tambah Pegawai
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
            <input type="hidden" name="page" value="pegawai">
            <div style="flex:2;min-width:200px" class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIP, jabatan..." value="<?= $search ?>">
            </div>
            <div style="flex:1;min-width:150px">
                <select name="status_pegawai" class="form-control">
                    <option value="">Semua Status</option>
                    <option value="PNS" <?= $filter_status === 'PNS' ? 'selected' : '' ?>>PNS</option>
                    <option value="PPPK" <?= $filter_status === 'PPPK' ? 'selected' : '' ?>>PPPK</option>
                </select>
            </div>
            <div style="flex:1;min-width:150px">
                <select name="jabatan" class="form-control">
                    <option value="">Semua Jabatan</option>
                    <?php foreach ($jabatanList as $j): ?>
                    <option value="<?= $j['jabatan'] ?>" <?= $filter_jabatan === $j['jabatan'] ? 'selected' : '' ?>><?= $j['jabatan'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="?page=pegawai" class="btn btn-outline"><i class="fas fa-rotate"></i> Reset</a>
            <div class="view-toggle">
                <button type="button" class="active" onclick="setView('grid')" id="btnGrid"><i class="fas fa-th-large"></i></button>
                <button type="button" onclick="setView('table')" id="btnTable"><i class="fas fa-list"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- CARD VIEW -->
<div class="card" id="viewGrid">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> Daftar Pegawai</div>
        <span style="font-size:13px;color:var(--muted)"><?= count($pegawai) ?> data ditemukan</span>
    </div>
    <div class="pegawai-grid">
        <?php foreach ($pegawai as $p): 
            $nilai = $penilaianData[$p['nip']] ?? null;
            $rataRata = $nilai ? floatval($nilai['rata_rata']) : 0;
            // Determine card tier based on rating
            $tierClass = '';
            if ($nilai) {
                if ($rataRata >= 90) $tierClass = 'tier-special';
                elseif ($rataRata >= 70) $tierClass = '';
                elseif ($rataRata >= 50) $tierClass = 'tier-silver';
                else $tierClass = 'tier-bronze';
            } else {
                $tierClass = 'tier-silver no-score';
            }
            $jabatanShort = 'BAPEKOM8MKS';

            $namaCard = trim($p['nama_lengkap']);
            $statusPeg = $p['status_pegawai'] ?? '';
        ?>
        <div class="fut-card <?= $tierClass ?>">
            <!-- SVG Card Frame Shape -->
            <svg class="fut-card-frame" viewBox="0 0 260 400" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="cardGrad_<?= $p['id_pegawai'] ?>" x1="0" y1="0" x2="260" y2="400" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" class="fut-gold-stop1"/>
                        <stop offset="20%" class="fut-gold-stop2"/>
                        <stop offset="40%" class="fut-gold-stop3"/>
                        <stop offset="60%" class="fut-gold-stop4"/>
                        <stop offset="80%" class="fut-gold-stop5"/>
                        <stop offset="100%" class="fut-gold-stop6"/>
                    </linearGradient>
                    <filter id="cardShadow_<?= $p['id_pegawai'] ?>" x="-10%" y="-5%" width="120%" height="115%">
                        <feDropShadow dx="0" dy="4" stdDeviation="8" flood-opacity="0.25"/>
                    </filter>
                </defs>
                <path d="M22,4 L218,4 Q236,4 238,20 L238,320 Q238,336 228,346 L140,388 Q132,392 130,392 Q128,392 120,388 L12,346 Q2,336 2,320 L2,20 Q2,4 22,4 Z" 
                      fill="url(#cardGrad_<?= $p['id_pegawai'] ?>)" 
                      filter="url(#cardShadow_<?= $p['id_pegawai'] ?>)"
                      stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
            </svg>

            <div class="fut-card-content">
                <!-- TOP: Left info + Photo -->
                <div class="fut-top">
                    <div class="fut-left">
                        <div class="fut-rating"><?= $nilai ? round($rataRata) : '-' ?></div>
                        <div class="fut-pos"><?= $jabatanShort ?></div>
                        <div class="fut-flag" title="Indonesia" style="background: linear-gradient(180deg, #ff0000 50%, #ffffff 50%); border-radius: 2px;"></div>
                        <div class="fut-club-logo">
                            <img src="<?= getBaseUrl() ?>uploads/pu-logo.png" alt="PU" onerror="this.parentElement.innerHTML='<i class=\'fas fa-building\' style=\'font-size:20px;color:#3b2a14;opacity:0.6\'></i>'">
                        </div>
                    </div>
                    <div class="fut-photo">
                        <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                        <img src="<?= getBaseUrl() ?>uploads/<?= $p['foto_profil'] ?>" alt="<?= sanitize($p['nama_lengkap']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                        <div class="fut-avatar" style="background:<?= getAvatarColor($p['id_pegawai']) ?>;display:none">
                            <?= getInitials($p['nama_lengkap']) ?>
                        </div>
                        <?php else: ?>
                        <div class="fut-avatar" style="background:<?= getAvatarColor($p['id_pegawai']) ?>">
                            <?= getInitials($p['nama_lengkap']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- BOTTOM: Name + Jabatan + Stats -->
                <div class="fut-bottom">
                    <div class="fut-line"></div>
                    <div class="fut-name" title="<?= sanitize($p['nama_lengkap']) ?>"><?= strtoupper(sanitize($namaCard)) ?></div>
                    <div class="fut-jabatan" title="<?= sanitize($p['jabatan'] ?: 'Pegawai') ?>"><?= sanitize($p['jabatan'] ?: 'Pegawai') ?></div>
                    <?php if ($statusPeg): ?>
                    <span style="display:inline-block;font-family:'Oswald',sans-serif;font-size:8px;font-weight:600;letter-spacing:1px;padding:2px 8px;border-radius:4px;margin-top:1px;color:<?= $statusPeg === 'PNS' ? '#065f46' : '#7c2d12' ?>;background:<?= $statusPeg === 'PNS' ? 'rgba(16,185,129,0.18)' : 'rgba(249,115,22,0.18)' ?>"><?= $statusPeg ?></span>
                    <?php endif; ?>
                    <div class="fut-line"></div>
                    <div class="fut-stats">
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['nilai_kedisiplinan']) : '-' ?></span>
                            <span class="fut-stat-lbl">DIS</span>
                        </div>
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['kepemimpinan']) : '-' ?></span>
                            <span class="fut-stat-lbl">KEP</span>
                        </div>
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['kinerja']) : '-' ?></span>
                            <span class="fut-stat-lbl">KIN</span>
                        </div>
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['loyalitas']) : '-' ?></span>
                            <span class="fut-stat-lbl">LOY</span>
                        </div>
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['sikap']) : '-' ?></span>
                            <span class="fut-stat-lbl">SIK</span>
                        </div>
                        <div class="fut-stat">
                            <span class="fut-stat-val"><?= $nilai ? round($nilai['it']) : '-' ?></span>
                            <span class="fut-stat-lbl">IT</span>
                        </div>
                    </div>
                </div>

                <!-- Hover Actions -->
                <div class="fut-actions">
                    <button class="btn-edit" onclick="editPegawai(<?= htmlspecialchars(json_encode($p)) ?>)" title="Edit">
                        <i class="fas fa-pen"></i>
                    </button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Hapus pegawai ini?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="page" value="pegawai">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id_pegawai" value="<?= $p['id_pegawai'] ?>">
                        <button type="submit" class="btn-delete" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($pegawai)): ?>
        <div style="grid-column: 1/-1; text-align:center; color:var(--muted); padding:60px">
            <i class="fas fa-futbol" style="font-size:48px; margin-bottom:16px; opacity:0.3"></i>
            <p>Tidak ada data pegawai</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- TABLE VIEW (Hidden by default) -->
<div class="card" id="viewTable" style="display:none">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-users"></i> Daftar Pegawai</div>
        <span style="font-size:13px;color:var(--muted)"><?= count($pegawai) ?> data ditemukan</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>NIP</th>
                    <th>Status</th>
                    <th>Jabatan</th>
                    <th>Rating</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pegawai as $p): 
                    $nilai = $penilaianData[$p['nip']] ?? null;
                    $rataRata = $nilai ? floatval($nilai['rata_rata']) : 0;
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                            <img src="<?= getBaseUrl() ?>uploads/<?= $p['foto_profil'] ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                            <div class="avatar" style="background:<?= getAvatarColor($p['id_pegawai']) ?>;display:none">
                                <?= getInitials($p['nama_lengkap']) ?>
                            </div>
                            <?php else: ?>
                            <div class="avatar" style="background:<?= getAvatarColor($p['id_pegawai']) ?>">
                                <?= getInitials($p['nama_lengkap']) ?>
                            </div>
                            <?php endif; ?>
                            <div style="font-weight:600"><?= sanitize($p['nama_lengkap']) ?></div>
                        </div>
                    </td>
                    <td><code style="font-size:12px;background:var(--bg);padding:3px 8px;border-radius:5px"><?= sanitize($p['nip']) ?></code></td>
                    <td>
                        <?php $sp = $p['status_pegawai'] ?? ''; if ($sp): ?>
                        <span style="font-size:11px;font-weight:600;padding:3px 10px;border-radius:6px;color:<?= $sp === 'PNS' ? '#065f46' : '#7c2d12' ?>;background:<?= $sp === 'PNS' ? '#d1fae5' : '#ffedd5' ?>"><?= $sp ?></span>
                        <?php else: ?>
                        <span style="color:var(--muted)">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= sanitize($p['jabatan'] ?: '-') ?></td>
                    <td>
                        <?php if ($nilai): ?>
                        <span style="font-weight:700;color:<?= $rataRata >= 70 ? '#10b981' : ($rataRata >= 50 ? '#f59e0b' : '#ef4444') ?>">
                            <i class="fas fa-star"></i> <?= number_format($rataRata, 1) ?>
                        </span>
                        <?php else: ?>
                        <span style="color:var(--muted)">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button class="btn btn-outline btn-sm" onclick="editPegawai(<?= htmlspecialchars(json_encode($p)) ?>)">
                                <i class="fas fa-pen"></i>
                            </button>
                            <form method="POST" onsubmit="return confirm('Hapus pegawai ini?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="page" value="pegawai">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id_pegawai" value="<?= $p['id_pegawai'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($pegawai)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:30px">Tidak ada data pegawai</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function setView(view) {
    const gridView = document.getElementById('viewGrid');
    const tableView = document.getElementById('viewTable');
    const btnGrid = document.getElementById('btnGrid');
    const btnTable = document.getElementById('btnTable');
    
    if (view === 'grid') {
        gridView.style.display = 'block';
        tableView.style.display = 'none';
        btnGrid.classList.add('active');
        btnTable.classList.remove('active');
    } else {
        gridView.style.display = 'none';
        tableView.style.display = 'block';
        btnGrid.classList.remove('active');
        btnTable.classList.add('active');
    }
    localStorage.setItem('pegawaiView', view);
}

// Load saved view preference
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('pegawaiView') || 'grid';
    setView(savedView);

    // Auto-scale long names to fit inside card
    setTimeout(function() {
        document.querySelectorAll('.fut-name').forEach(function(el) {
            var card = el.closest('.fut-card');
            var maxW = (card ? card.offsetWidth : 210) - 50;
            var fontSize = 15;
            var letterSpacing = 2;
            el.style.fontSize = fontSize + 'px';
            el.style.letterSpacing = letterSpacing + 'px';
            while (el.scrollWidth > maxW && fontSize > 7) {
                fontSize -= 0.5;
                if (letterSpacing > 0.5) letterSpacing -= 0.25;
                el.style.fontSize = fontSize + 'px';
                el.style.letterSpacing = Math.max(0.3, letterSpacing) + 'px';
            }
        });

        document.querySelectorAll('.fut-jabatan').forEach(function(el) {
            var card = el.closest('.fut-card');
            var maxW = (card ? card.offsetWidth : 210) - 50;
            var fontSize = 9;
            el.style.fontSize = fontSize + 'px';
            while (el.scrollWidth > maxW && fontSize > 5.5) {
                fontSize -= 0.5;
                el.style.fontSize = fontSize + 'px';
            }
        });
    }, 100);
});
</script>

<!-- MODAL TAMBAH -->
<div class="modal" id="modalTambah">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 16px 16px 0 0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 60px; height: 60px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; border: 3px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 4px;">Tambah Pegawai Baru</h3>
                    <p style="font-size: 13px; opacity: 0.9;">Tambahkan data pegawai ke sistem</p>
                </div>
            </div>
            <button class="close-btn" style="background: rgba(255,255,255,0.2); color: white; border: none;" onclick="document.getElementById('modalTambah').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" style="padding: 28px;">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="pegawai">
            <input type="hidden" name="action" value="tambah">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-id-card" style="color: #10b981; font-size: 12px;"></i>
                        NIP <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" name="nip" class="form-control" required placeholder="Masukkan NIP" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user" style="color: #10b981; font-size: 12px;"></i>
                        Nama Lengkap <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" name="nama_lengkap" class="form-control" required placeholder="Masukkan nama lengkap" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-briefcase" style="color: #10b981; font-size: 12px;"></i>
                        Jabatan
                    </label>
                    <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Analis SDM Ahli Pertama" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-id-badge" style="color: #10b981; font-size: 12px;"></i>
                        Status Pegawai <span style="color:#ef4444">*</span>
                    </label>
                    <select name="status_pegawai" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                        <option value="">-- Pilih Status --</option>
                        <option value="PNS">PNS</option>
                        <option value="PPPK">PPPK</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-at" style="color: #10b981; font-size: 12px;"></i>
                        Username
                    </label>
                    <input type="text" name="username" class="form-control" placeholder="Username untuk login" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-lock" style="color: #10b981; font-size: 12px;"></i>
                        Password
                    </label>
                    <input type="password" name="password" class="form-control" placeholder="Default: password123" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
            </div>
            
            <div style="background: #ecfdf5; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: #10b981;"></i>
                <span style="font-size: 12px; color: #064e3b;">Jika password dikosongkan, password default adalah <strong>password123</strong></span>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" style="padding: 12px 24px;" onclick="document.getElementById('modalTambah').classList.remove('show')">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-success" style="padding: 12px 24px;">
                    <i class="fas fa-plus"></i> Tambah Pegawai
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal" id="modalEdit">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 16px 16px 0 0; padding: 24px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div id="edit_avatar_preview" style="width: 60px; height: 60px; border-radius: 14px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; color: white; border: 3px solid rgba(255,255,255,0.3);"></div>
                <div>
                    <h3 style="font-size: 18px; margin-bottom: 4px;"><i class="fas fa-user-edit"></i> Edit Pegawai</h3>
                    <p id="edit_nama_preview" style="font-size: 13px; opacity: 0.9;"></p>
                </div>
            </div>
            <button class="close-btn" style="background: rgba(255,255,255,0.2); color: white; border: none;" onclick="document.getElementById('modalEdit').classList.remove('show')">&times;</button>
        </div>
        <form method="POST" style="padding: 28px;">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="pegawai">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id_pegawai" id="edit_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-id-card" style="color: #6366f1; font-size: 12px;"></i>
                        NIP <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" name="nip" id="edit_nip" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-user" style="color: #6366f1; font-size: 12px;"></i>
                        Nama Lengkap <span style="color:#ef4444">*</span>
                    </label>
                    <input type="text" name="nama_lengkap" id="edit_nama" class="form-control" required style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" oninput="updateEditPreview()">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-briefcase" style="color: #6366f1; font-size: 12px;"></i>
                        Jabatan
                    </label>
                    <input type="text" name="jabatan" id="edit_jabatan" class="form-control" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" placeholder="Contoh: Analis SDM Ahli Pertama">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-id-badge" style="color: #6366f1; font-size: 12px;"></i>
                        Status Pegawai
                    </label>
                    <select name="status_pegawai" id="edit_status_pegawai" class="form-control" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                        <option value="">-- Pilih Status --</option>
                        <option value="PNS">PNS</option>
                        <option value="PPPK">PPPK</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-at" style="color: #6366f1; font-size: 12px;"></i>
                        Username
                    </label>
                    <input type="text" name="username" id="edit_username" class="form-control" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;" placeholder="email@pu.go.id">
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-lock" style="color: #6366f1; font-size: 12px;"></i>
                        Password Baru
                    </label>
                    <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah" style="padding: 12px 16px; font-size: 14px; border-radius: 10px;">
                </div>
            </div>
            
            <div style="background: #f8fafc; border-radius: 10px; padding: 14px 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: #6366f1;"></i>
                <span style="font-size: 12px; color: #64748b;">Password hanya akan diubah jika diisi. Biarkan kosong untuk mempertahankan password lama.</span>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" style="padding: 12px 24px;" onclick="document.getElementById('modalEdit').classList.remove('show')">
                    <i class="fas fa-times"></i> Batal
                </button>
                <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function getInitialsJS(name) {
    if (!name) return '?';
    const words = name.split(' ');
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

function updateEditPreview() {
    const nama = document.getElementById('edit_nama').value;
    document.getElementById('edit_nama_preview').textContent = nama || 'Nama Pegawai';
    document.getElementById('edit_avatar_preview').textContent = getInitialsJS(nama);
}

function editPegawai(p) {
    document.getElementById('edit_id').value = p.id_pegawai;
    document.getElementById('edit_nip').value = p.nip || '';
    document.getElementById('edit_nama').value = p.nama_lengkap || '';
    document.getElementById('edit_jabatan').value = p.jabatan || '';
    document.getElementById('edit_status_pegawai').value = p.status_pegawai || '';
    document.getElementById('edit_username').value = p.username || '';
    
    // Update preview
    document.getElementById('edit_nama_preview').textContent = p.nama_lengkap || 'Nama Pegawai';
    document.getElementById('edit_avatar_preview').textContent = getInitialsJS(p.nama_lengkap);
    
    document.getElementById('modalEdit').classList.add('show');
}
</script>
