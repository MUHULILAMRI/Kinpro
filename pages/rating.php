<?php
// Cek dan buat tabel rating_komentar jika belum ada
$db->query("CREATE TABLE IF NOT EXISTS rating_komentar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nip_pegawai VARCHAR(30) NOT NULL,
    nip_penilai VARCHAR(30) DEFAULT NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    komentar TEXT,
    tanggal_dibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_nip_pegawai (nip_pegawai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Daftar pengguna yang diizinkan memberikan rating
$allowedRaters = ['dicky mulyadi', 'dickymulyadi', 'sarnaeni', 'wahyuni', 'widiyanto'];

// Fungsi untuk cek apakah user saat ini bisa memberi rating
function canGiveRating() {
    global $allowedRaters;
    $currentUserName = strtolower(trim($_SESSION['user_name'] ?? ''));
    if (in_array($currentUserName, $allowedRaters, true)) {
        return true;
    }
    // Admin juga bisa memberi rating
    return isAdmin();
}

$canRate = canGiveRating();

$msg = '';
$msgType = 'success';

// HANDLE POST - Tambah rating
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $msg = 'Sesi tidak valid. Silakan refresh halaman.';
        $msgType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'tambah_rating') {
            // Cek apakah user diizinkan memberikan rating
            if (!canGiveRating()) {
                $msg = 'Anda tidak memiliki akses untuk memberikan rating.';
                $msgType = 'danger';
            } else {
                $nip_pegawai = sanitize($_POST['nip_pegawai']);
                $rating = (int)$_POST['rating'];
                $komentar = sanitize($_POST['komentar']);

                if ($rating < 1 || $rating > 5) $rating = 5;

                $stmt = $db->prepare("INSERT INTO rating_komentar (nip_pegawai, rating, komentar) VALUES (?,?,?)");
                $stmt->bind_param('sis', $nip_pegawai, $rating, $komentar);
                if ($stmt->execute()) { 
                    $msg = 'Rating berhasil ditambahkan!'; 
                } else { 
                    $msg = 'Gagal menyimpan rating.'; 
                    $msgType = 'danger'; 
                }
            }
        }

        if ($action === 'hapus_rating') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("DELETE FROM rating_komentar WHERE id = ?");
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $msg = 'Rating berhasil dihapus.';
            } else {
                $msg = 'Gagal menghapus rating.';
                $msgType = 'danger';
            }
        }
    }
}

// Get all pegawai with their ratings
$pegawaiRating = $db->query("
    SELECT p.*, 
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(r.id) as total_review,
           COALESCE(AVG(n.rata_rata), 0) as avg_kinerja
    FROM pegawai p
    LEFT JOIN rating_komentar r ON p.nip = r.nip_pegawai
    LEFT JOIN penilaian n ON p.nip = n.nip
    GROUP BY p.id_pegawai
    ORDER BY avg_rating DESC, avg_kinerja DESC
")->fetch_all(MYSQLI_ASSOC);

// Filter
$filter = sanitize($_GET['filter'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');

$filteredPegawai = $pegawaiRating;

if ($search) {
    $filteredPegawai = array_filter($filteredPegawai, function($p) use ($search) {
        return stripos($p['nama_lengkap'], $search) !== false || 
               stripos($p['nip'], $search) !== false ||
               stripos($p['jabatan'], $search) !== false;
    });
}

if ($filter === 'top') {
    $filteredPegawai = array_filter($filteredPegawai, fn($p) => $p['avg_rating'] >= 4);
} elseif ($filter === 'need_review') {
    $filteredPegawai = array_filter($filteredPegawai, fn($p) => $p['total_review'] == 0);
}

$pegawaiList = $db->query("SELECT id_pegawai, nama_lengkap, nip, jabatan, foto_profil FROM pegawai ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);

// Stats for charts
$totalPegawai = count($pegawaiRating);
$totalRating = $db->query("SELECT COUNT(*) as cnt FROM rating_komentar")->fetch_assoc()['cnt'];
$avgOverall = $db->query("SELECT COALESCE(AVG(rating),0) as avg FROM rating_komentar")->fetch_assoc()['avg'];
$ratedPegawai = count(array_filter($pegawaiRating, fn($p) => $p['total_review'] > 0));

// Rating distribution
$ratingDist = [1=>0, 2=>0, 3=>0, 4=>0, 5=>0];
$distResult = $db->query("SELECT rating, COUNT(*) as cnt FROM rating_komentar GROUP BY rating");
while ($row = $distResult->fetch_assoc()) {
    $ratingDist[(int)$row['rating']] = (int)$row['cnt'];
}

// Top 5 pegawai for chart
$top5 = array_slice(array_filter($pegawaiRating, fn($p) => $p['avg_rating'] > 0), 0, 5);

// Recent reviews
$recentReviews = $db->query("
    SELECT r.*, p.nama_lengkap, p.jabatan, p.foto_profil 
    FROM rating_komentar r 
    JOIN pegawai p ON r.nip_pegawai = p.nip 
    ORDER BY r.tanggal_dibuat DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* Enhanced Rating Styles */
:root {
    --gold: #fbbf24;
    --gold-light: #fef3c7;
}

/* Stats Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

@media (max-width: 1024px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 600px) {
    .stats-grid { grid-template-columns: 1fr; }
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 30px rgba(99, 102, 241, 0.1);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
}

.stat-icon.purple { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; }
.stat-icon.gold { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; }
.stat-icon.green { background: linear-gradient(135deg, #10b981, #34d399); color: white; }
.stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #60a5fa); color: white; }

.stat-info h4 {
    font-size: 26px;
    font-weight: 800;
    color: var(--text);
    margin: 0;
}

.stat-info p {
    font-size: 13px;
    color: var(--muted);
    margin: 2px 0 0;
}

/* Charts Section */
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 24px;
}

@media (max-width: 900px) {
    .charts-grid { grid-template-columns: 1fr; }
}

.chart-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border);
}

.chart-card h3 {
    font-size: 15px;
    font-weight: 700;
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-card h3 i {
    color: var(--accent);
}

/* Rating Grid */
.rating-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.rating-card {
    background: white;
    border-radius: 20px;
    overflow: hidden;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
    position: relative;
}

.rating-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15);
}

.rating-card-header {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    padding: 24px;
    text-align: center;
    position: relative;
}

.rating-card-header::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}

.rating-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,0.3);
    margin: 0 auto 12px;
    overflow: hidden;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 800;
    color: white;
    position: relative;
    z-index: 1;
}

.rating-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.rating-card-header h3 {
    color: white;
    font-size: 15px;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
}

.rating-card-header p {
    color: rgba(255,255,255,0.75);
    font-size: 12px;
    margin: 4px 0 0;
    position: relative;
    z-index: 1;
}

.rating-card-body {
    padding: 20px;
}

.rating-stars {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    margin-bottom: 8px;
}

.rating-stars i {
    font-size: 20px;
    color: #fbbf24;
    text-shadow: 0 2px 4px rgba(251, 191, 36, 0.3);
}

.rating-stars i.empty {
    color: #e2e8f0;
    text-shadow: none;
}

.rating-score {
    text-align: center;
    margin-bottom: 16px;
}

.rating-score .score {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.rating-score .total {
    font-size: 12px;
    color: var(--muted);
}

.rating-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 14px;
}

.rating-stat {
    background: var(--bg);
    padding: 10px;
    border-radius: 10px;
    text-align: center;
}

.rating-stat-value {
    font-size: 16px;
    font-weight: 800;
    color: var(--text);
}

.rating-stat-label {
    font-size: 10px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rating-card-footer {
    padding: 0 20px 20px;
    display: flex;
    gap: 8px;
}

.rating-card-footer .btn {
    flex: 1;
    justify-content: center;
    font-size: 12px;
    padding: 8px 12px;
}

/* Rank Badge */
.rank-badge {
    position: absolute;
    top: 12px;
    left: 12px;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    z-index: 2;
}

.rank-badge.gold {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: white;
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
}

.rank-badge.silver {
    background: linear-gradient(135deg, #94a3b8, #64748b);
    color: white;
}

.rank-badge.bronze {
    background: linear-gradient(135deg, #f97316, #ea580c);
    color: white;
}

/* Filter Pills */
.filter-pills {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.filter-pill {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid var(--border);
    color: var(--muted);
    background: white;
    transition: all 0.2s;
}

.filter-pill:hover {
    border-color: var(--accent);
    color: var(--accent);
}

.filter-pill.active {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    color: white;
    border-color: transparent;
}

/* Enhanced Modal */
.modal-rating {
    max-width: 520px;
}

.modal-rating .modal-header {
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    padding: 24px;
    border-radius: 16px 16px 0 0;
}

.modal-rating .modal-header h3 {
    color: white;
    margin: 0;
    font-size: 18px;
}

.modal-rating .modal-header p {
    color: rgba(255,255,255,0.8);
    font-size: 13px;
    margin: 4px 0 0;
}

.modal-rating .close-btn {
    color: white;
    opacity: 0.8;
}

.modal-rating .close-btn:hover {
    opacity: 1;
}

/* Pegawai Select Card */
.pegawai-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 10px;
    max-height: 280px;
    overflow-y: auto;
    padding: 4px;
}

.pegawai-select-card {
    background: var(--bg);
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}

.pegawai-select-card:hover {
    border-color: var(--accent);
    background: white;
}

.pegawai-select-card.selected {
    border-color: var(--accent);
    background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(139,92,246,0.1));
}

.pegawai-select-card .avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin: 0 auto 8px;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
    overflow: hidden;
}

.pegawai-select-card .avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pegawai-select-card .name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pegawai-select-card .job {
    font-size: 10px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Star Rating Input Enhanced */
.star-rating-wrapper {
    background: linear-gradient(135deg, var(--gold-light), #fff7ed);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    margin: 16px 0;
}

.star-rating-wrapper label.title {
    font-size: 14px;
    font-weight: 600;
    color: var(--text);
    display: block;
    margin-bottom: 12px;
}

.star-rating-input {
    display: flex;
    gap: 8px;
    flex-direction: row-reverse;
    justify-content: center;
}

.star-rating-input input {
    display: none;
}

.star-rating-input label {
    font-size: 36px;
    color: #e2e8f0;
    cursor: pointer;
    transition: all 0.15s;
}

.star-rating-input label:hover,
.star-rating-input label:hover ~ label,
.star-rating-input input:checked ~ label {
    color: #fbbf24;
    transform: scale(1.15);
    text-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
}

.rating-text {
    margin-top: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--accent);
}

/* Recent Reviews */
.review-item {
    display: flex;
    gap: 12px;
    padding: 14px;
    background: var(--bg);
    border-radius: 12px;
    margin-bottom: 10px;
}

.review-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
    overflow: hidden;
}

.review-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.review-content {
    flex: 1;
    min-width: 0;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 4px;
}

.review-name {
    font-weight: 700;
    font-size: 13px;
    color: var(--text);
}

.review-stars {
    display: flex;
    gap: 2px;
}

.review-stars i {
    font-size: 11px;
    color: #fbbf24;
}

.review-text {
    font-size: 12px;
    color: var(--muted);
    line-height: 1.5;
}

.review-date {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
}

/* Search box improvements */
.search-filter-bar {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: space-between;
    padding: 16px 20px;
}
</style>

<div class="page-header">
    <div>
        <h2><i class="fas fa-star" style="color:#fbbf24"></i> Rating Pegawai</h2>
        <p>Lihat ranking, statistik, dan berikan rating untuk pegawai</p>
    </div>
    <?php if ($canRate): ?>
    <button class="btn btn-primary" onclick="openRatingModal()">
        <i class="fas fa-plus"></i> Beri Rating Baru
    </button>
    <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>">
    <i class="fas fa-<?= $msgType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $msg ?>
</div>
<?php endif; ?>

<!-- STATS CARDS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h4><?= $totalPegawai ?></h4>
            <p>Total Pegawai</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold"><i class="fas fa-star"></i></div>
        <div class="stat-info">
            <h4><?= $totalRating ?></h4>
            <p>Total Rating</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-chart-line"></i></div>
        <div class="stat-info">
            <h4><?= number_format($avgOverall, 1) ?></h4>
            <p>Rata-rata Rating</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
            <h4><?= $ratedPegawai ?></h4>
            <p>Sudah Direview</p>
        </div>
    </div>
</div>

<!-- CHARTS -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Distribusi Rating</h3>
        <canvas id="chartDistribusi" height="200"></canvas>
    </div>
    <div class="chart-card">
        <h3><i class="fas fa-trophy"></i> Top 5 Pegawai</h3>
        <canvas id="chartTop5" height="200"></canvas>
    </div>
</div>

<!-- RECENT REVIEWS + FILTERS -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="fas fa-comments"></i> Review Terbaru</h3>
        <?php if (empty($recentReviews)): ?>
        <div style="text-align:center;padding:30px;color:var(--muted)">
            <i class="fas fa-comment-slash" style="font-size:24px;margin-bottom:8px;display:block"></i>
            Belum ada review
        </div>
        <?php else: ?>
        <?php foreach ($recentReviews as $rev): ?>
        <div class="review-item">
            <div class="review-avatar">
                <?php if ($rev['foto_profil'] && $rev['foto_profil'] !== 'default.jpg'): ?>
                <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($rev['foto_profil']) ?>" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='<?= htmlspecialchars(getInitials($rev['nama_lengkap'])) ?>'">
                <?php else: ?>
                <?= getInitials($rev['nama_lengkap']) ?>
                <?php endif; ?>
            </div>
            <div class="review-content">
                <div class="review-header">
                    <span class="review-name"><?= sanitize($rev['nama_lengkap']) ?></span>
                    <div class="review-stars">
                        <?php for($i=0; $i<$rev['rating']; $i++) echo '<i class="fas fa-star"></i>'; ?>
                    </div>
                </div>
                <p class="review-text"><?= htmlspecialchars($rev['komentar'] ?: 'Tidak ada komentar') ?></p>
                <span class="review-date"><?= date('d M Y, H:i', strtotime($rev['tanggal_dibuat'])) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="chart-card">
        <h3><i class="fas fa-chart-pie"></i> Persentase Rating</h3>
        <canvas id="chartPie" height="200"></canvas>
    </div>
</div>

<!-- FILTERS -->
<div class="card" style="margin-bottom:24px">
    <div class="search-filter-bar">
        <div class="filter-pills">
            <a href="?page=rating&filter=all" class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
                <i class="fas fa-th"></i> Semua (<?= $totalPegawai ?>)
            </a>
            <a href="?page=rating&filter=top" class="filter-pill <?= $filter === 'top' ? 'active' : '' ?>">
                <i class="fas fa-trophy"></i> Top Rated
            </a>
            <a href="?page=rating&filter=need_review" class="filter-pill <?= $filter === 'need_review' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i> Belum Direview
            </a>
        </div>
        <form method="GET" style="display:flex;gap:8px">
            <input type="hidden" name="page" value="rating">
            <input type="hidden" name="filter" value="<?= $filter ?>">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIP, jabatan..." value="<?= $search ?>" style="width:240px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
</div>

<!-- RATING CARDS -->
<?php if (empty($filteredPegawai)): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:60px">
        <i class="fas fa-user-slash" style="font-size:48px;color:var(--border);margin-bottom:16px;display:block"></i>
        <div style="color:var(--muted);font-size:15px">Tidak ada pegawai ditemukan</div>
        <a href="?page=rating" class="btn btn-outline" style="margin-top:16px">Reset Filter</a>
    </div>
</div>
<?php else: ?>
<div class="rating-grid">
    <?php 
    $rank = 0;
    foreach ($filteredPegawai as $p): 
        $rank++;
        $avgRating = round($p['avg_rating'], 1);
        $fullStars = floor($avgRating);
        $hasHalf = ($avgRating - $fullStars) >= 0.5;
        $emptyStars = 5 - $fullStars - ($hasHalf ? 1 : 0);
    ?>
    <div class="rating-card">
        <?php if ($rank <= 3 && $avgRating > 0): ?>
        <div class="rank-badge <?= $rank === 1 ? 'gold' : ($rank === 2 ? 'silver' : 'bronze') ?>">
            <?= $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉') ?>
        </div>
        <?php endif; ?>

        <div class="rating-card-header">
            <div class="rating-avatar">
                <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($p['foto_profil']) ?>" alt="<?= sanitize($p['nama_lengkap']) ?>" onerror="this.style.display='none';this.parentElement.innerHTML='<?= htmlspecialchars(getInitials($p['nama_lengkap'])) ?>'">
                <?php else: ?>
                <?= getInitials($p['nama_lengkap']) ?>
                <?php endif; ?>
            </div>
            <h3><?= sanitize($p['nama_lengkap']) ?></h3>
            <p><?= sanitize($p['jabatan'] ?: 'Pegawai') ?></p>
        </div>

        <div class="rating-card-body">
            <div class="rating-stars">
                <?php 
                for ($i = 0; $i < $fullStars; $i++) echo '<i class="fas fa-star"></i>';
                if ($hasHalf) echo '<i class="fas fa-star-half-alt"></i>';
                for ($i = 0; $i < $emptyStars; $i++) echo '<i class="fas fa-star empty"></i>';
                ?>
            </div>
            
            <div class="rating-score">
                <span class="score"><?= $avgRating ?: '0.0' ?></span>
                <span class="total">/ 5.0 (<?= $p['total_review'] ?> review)</span>
            </div>

            <div class="rating-stats">
                <div class="rating-stat">
                    <div class="rating-stat-value"><?= number_format($p['avg_kinerja'], 1) ?></div>
                    <div class="rating-stat-label">Skor Kinerja</div>
                </div>
                <div class="rating-stat">
                    <div class="rating-stat-value">#<?= $rank ?></div>
                    <div class="rating-stat-label">Ranking</div>
                </div>
            </div>
        </div>

        <div class="rating-card-footer">
            <button class="btn btn-outline btn-sm" onclick="showDetail('<?= $p['nip'] ?>')">
                <i class="fas fa-eye"></i> Detail
            </button>
            <?php if ($canRate): ?>
            <button class="btn btn-primary btn-sm" onclick="rateNow('<?= $p['nip'] ?>')">
                <i class="fas fa-star"></i> Rate
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- MODAL RATING BARU (ENHANCED) -->
<div class="modal" id="modalRating">
    <div class="modal-content modal-rating">
        <div class="modal-header">
            <div>
                <h3><i class="fas fa-star"></i> Beri Rating Pegawai</h3>
                <p>Pilih pegawai dan berikan penilaian Anda</p>
            </div>
            <button class="close-btn" onclick="closeRatingModal()">&times;</button>
        </div>
        <form method="POST" id="formRating">
            <?= csrfField() ?>
            <input type="hidden" name="page" value="rating">
            <input type="hidden" name="action" value="tambah_rating">
            <input type="hidden" name="nip_pegawai" id="input_nip" required>
            
            <div style="padding:20px">
                <!-- Step 1: Pilih Pegawai -->
                <div class="form-group">
                    <label style="font-weight:600;margin-bottom:12px;display:flex;align-items:center;gap:8px">
                        <span style="width:24px;height:24px;background:var(--accent);color:white;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px">1</span>
                        Pilih Pegawai
                    </label>
                    <input type="text" id="searchPegawai" class="form-control" placeholder="Ketik nama untuk mencari..." style="margin-bottom:12px">
                    <div class="pegawai-select-grid" id="pegawaiGrid">
                        <?php foreach ($pegawaiList as $p): ?>
                        <div class="pegawai-select-card" data-nip="<?= $p['nip'] ?>" data-name="<?= strtolower($p['nama_lengkap']) ?>" onclick="selectPegawai(this, '<?= $p['nip'] ?>')">
                            <div class="avatar">
                                <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                                <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($p['foto_profil']) ?>" alt="" onerror="this.style.display='none';this.parentElement.innerHTML='<?= htmlspecialchars(getInitials($p['nama_lengkap'])) ?>'">
                                <?php else: ?>
                                <?= getInitials($p['nama_lengkap']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="name"><?= sanitize($p['nama_lengkap']) ?></div>
                            <div class="job"><?= sanitize($p['jabatan'] ?: 'Pegawai') ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Step 2: Rating -->
                <div class="star-rating-wrapper">
                    <label class="title">
                        <span style="width:24px;height:24px;background:var(--accent);color:white;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:6px">2</span>
                        Berikan Rating
                    </label>
                    <div class="star-rating-input">
                        <input type="radio" name="rating" value="5" id="star5" checked>
                        <label for="star5"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="4" id="star4">
                        <label for="star4"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="3" id="star3">
                        <label for="star3"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="2" id="star2">
                        <label for="star2"><i class="fas fa-star"></i></label>
                        <input type="radio" name="rating" value="1" id="star1">
                        <label for="star1"><i class="fas fa-star"></i></label>
                    </div>
                    <div class="rating-text" id="ratingText">Sangat Baik</div>
                </div>
                
                <!-- Step 3: Komentar -->
                <div class="form-group">
                    <label style="font-weight:600;display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <span style="width:24px;height:24px;background:var(--accent);color:white;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px">3</span>
                        Tulis Komentar (Opsional)
                    </label>
                    <textarea name="komentar" class="form-control" rows="3" placeholder="Bagikan pengalaman kerja sama Anda dengan pegawai ini..." style="resize:none"></textarea>
                </div>
            </div>

            <div class="modal-footer" style="background:var(--bg);border-top:1px solid var(--border)">
                <button type="button" class="btn btn-outline" onclick="closeRatingModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitRating">
                    <i class="fas fa-paper-plane"></i> Kirim Rating
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL DETAIL -->
<div class="modal" id="modalDetail">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-user"></i> Detail Rating</h3>
            <button class="close-btn" onclick="document.getElementById('modalDetail').classList.remove('show')">&times;</button>
        </div>
        <div id="detailContent" style="padding:20px">
            Loading...
        </div>
    </div>
</div>

<script>
// Charts
document.addEventListener('DOMContentLoaded', function() {
    // Distribution Chart
    new Chart(document.getElementById('chartDistribusi'), {
        type: 'bar',
        data: {
            labels: ['⭐ 1', '⭐ 2', '⭐ 3', '⭐ 4', '⭐ 5'],
            datasets: [{
                label: 'Jumlah Rating',
                data: [<?= $ratingDist[1] ?>, <?= $ratingDist[2] ?>, <?= $ratingDist[3] ?>, <?= $ratingDist[4] ?>, <?= $ratingDist[5] ?>],
                backgroundColor: [
                    'rgba(239, 68, 68, 0.8)',
                    'rgba(249, 115, 22, 0.8)',
                    'rgba(234, 179, 8, 0.8)',
                    'rgba(34, 197, 94, 0.8)',
                    'rgba(99, 102, 241, 0.8)'
                ],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } }
            }
        }
    });

    // Top 5 Chart
    new Chart(document.getElementById('chartTop5'), {
        type: 'bar',
        data: {
            labels: [<?php foreach($top5 as $t) echo '"'.addslashes(substr($t['nama_lengkap'],0,15)).'",'; ?>],
            datasets: [{
                label: 'Rating',
                data: [<?php foreach($top5 as $t) echo round($t['avg_rating'],1).','; ?>],
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderRadius: 8
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, max: 5 }
            }
        }
    });

    // Pie Chart
    new Chart(document.getElementById('chartPie'), {
        type: 'doughnut',
        data: {
            labels: ['⭐ 5', '⭐ 4', '⭐ 3', '⭐ 2', '⭐ 1'],
            datasets: [{
                data: [<?= $ratingDist[5] ?>, <?= $ratingDist[4] ?>, <?= $ratingDist[3] ?>, <?= $ratingDist[2] ?>, <?= $ratingDist[1] ?>],
                backgroundColor: [
                    'rgba(99, 102, 241, 0.9)',
                    'rgba(34, 197, 94, 0.9)',
                    'rgba(234, 179, 8, 0.9)',
                    'rgba(249, 115, 22, 0.9)',
                    'rgba(239, 68, 68, 0.9)'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});

// Modal functions
function openRatingModal() {
    document.getElementById('modalRating').classList.add('show');
    document.getElementById('input_nip').value = '';
    document.querySelectorAll('.pegawai-select-card').forEach(c => c.classList.remove('selected'));
}

function closeRatingModal() {
    document.getElementById('modalRating').classList.remove('show');
}

function selectPegawai(el, nip) {
    document.querySelectorAll('.pegawai-select-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('input_nip').value = nip;
}

function rateNow(nip) {
    openRatingModal();
    setTimeout(() => {
        const card = document.querySelector(`.pegawai-select-card[data-nip="${nip}"]`);
        if (card) {
            selectPegawai(card, nip);
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, 100);
}

// Search pegawai in modal
document.getElementById('searchPegawai').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.pegawai-select-card').forEach(card => {
        const name = card.dataset.name;
        card.style.display = name.includes(q) ? 'block' : 'none';
    });
});

// Rating text update
const ratingTexts = {1: 'Sangat Buruk', 2: 'Buruk', 3: 'Cukup', 4: 'Baik', 5: 'Sangat Baik'};
document.querySelectorAll('.star-rating-input input').forEach(input => {
    input.addEventListener('change', function() {
        document.getElementById('ratingText').textContent = ratingTexts[this.value];
    });
});

// Form validation
document.getElementById('formRating').addEventListener('submit', function(e) {
    if (!document.getElementById('input_nip').value) {
        e.preventDefault();
        alert('Silakan pilih pegawai terlebih dahulu!');
    }
});

// Detail modal
function showDetail(nip) {
    document.getElementById('modalDetail').classList.add('show');
    document.getElementById('detailContent').innerHTML = '<div style="text-align:center;padding:40px"><i class="fas fa-spinner fa-spin" style="font-size:24px;color:var(--accent)"></i></div>';
    
    // In real app, use AJAX. Here we use embedded data
    <?php foreach ($pegawaiRating as $p): ?>
    if (nip === '<?= $p['nip'] ?>') {
        let html = `
            <div style="text-align:center;margin-bottom:20px">
                <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);margin:0 auto 12px;display:flex;align-items:center;justify-content:center;color:white;font-size:24px;font-weight:800;overflow:hidden">
                    <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                    <img src="<?= getBaseUrl() ?>uploads/<?= htmlspecialchars($p['foto_profil']) ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';this.parentElement.innerHTML='<?= htmlspecialchars(getInitials($p['nama_lengkap'])) ?>'">
                    <?php else: ?>
                    <?= getInitials($p['nama_lengkap']) ?>
                    <?php endif; ?>
                </div>
                <h4 style="margin:0;font-size:16px"><?= sanitize($p['nama_lengkap']) ?></h4>
                <p style="color:var(--muted);font-size:13px;margin:4px 0"><?= sanitize($p['jabatan'] ?: 'Pegawai') ?></p>
                <div style="font-size:36px;font-weight:800;background:linear-gradient(135deg,#6366f1,#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-top:12px"><?= number_format($p['avg_rating'],1) ?></div>
                <div style="color:var(--muted);font-size:13px"><?= $p['total_review'] ?> ulasan</div>
            </div>
            <h5 style="font-size:14px;margin:16px 0 12px;font-weight:600"><i class="fas fa-comments" style="color:var(--accent)"></i> Komentar Terbaru</h5>
        `;
        <?php 
        $stmtCmts = $db->prepare("SELECT * FROM rating_komentar WHERE nip_pegawai=? ORDER BY tanggal_dibuat DESC LIMIT 5");
        $stmtCmts->bind_param('s', $p['nip']);
        $stmtCmts->execute();
        $cmts = $stmtCmts->get_result()->fetch_all(MYSQLI_ASSOC);
        if (empty($cmts)): ?>
        html += '<div style="text-align:center;padding:20px;color:var(--muted);background:var(--bg);border-radius:10px">Belum ada komentar</div>';
        <?php else: foreach ($cmts as $c): ?>
        html += `<div class="review-item">
            <div class="review-content" style="flex:1">
                <div class="review-header">
                    <div class="review-stars"><?php for($i=0;$i<$c['rating'];$i++) echo '<i class="fas fa-star"></i>'; ?></div>
                    <span class="review-date"><?= date('d M Y', strtotime($c['tanggal_dibuat'])) ?></span>
                </div>
                <p class="review-text"><?= htmlspecialchars($c['komentar'] ?: 'Tidak ada komentar') ?></p>
            </div>
        </div>`;
        <?php endforeach; endif; ?>
        document.getElementById('detailContent').innerHTML = html;
    }
    <?php endforeach; ?>
}
</script>
