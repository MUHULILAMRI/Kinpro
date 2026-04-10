<?php
// $db sudah tersedia dari index.php
$msg = '';

// Check if user is admin
$isUserAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$userId = $_SESSION['user_id'] ?? 0;

if ($isUserAdmin) {
    // Admin Dashboard
    $totalPegawai = $db->query("SELECT COUNT(*) as c FROM pegawai")->fetch_assoc()['c'] ?? 0;
    $totalPenilaian = $db->query("SELECT COUNT(*) as c FROM penilaian")->fetch_assoc()['c'] ?? 0;
    $avgNilai = $db->query("SELECT AVG(rata_rata) as a FROM penilaian")->fetch_assoc()['a'] ?? 0;
    
    $stmtAduan = $db->prepare("SELECT COUNT(*) as c FROM pengaduan WHERE status=?");
    $pendingStr = 'pending';
    $stmtAduan->bind_param('s', $pendingStr);
    $stmtAduan->execute();
    $totalAduan = $stmtAduan->get_result()->fetch_assoc()['c'] ?? 0;
    
    $stmtIzin = $db->prepare("SELECT COUNT(*) as c FROM izin WHERE status=?");
    $stmtIzin->bind_param('s', $pendingStr);
    $stmtIzin->execute();
    $totalIzinPending = $stmtIzin->get_result()->fetch_assoc()['c'] ?? 0;

    // Predikat Distribution for Chart
    $predikatData = $db->query("SELECT 
        SUM(CASE WHEN rata_rata >= 86 THEN 1 ELSE 0 END) as istimewa,
        SUM(CASE WHEN rata_rata >= 71 AND rata_rata < 86 THEN 1 ELSE 0 END) as sangat_baik,
        SUM(CASE WHEN rata_rata >= 51 AND rata_rata < 71 THEN 1 ELSE 0 END) as baik,
        SUM(CASE WHEN rata_rata >= 31 AND rata_rata < 51 THEN 1 ELSE 0 END) as cukup,
        SUM(CASE WHEN rata_rata < 31 THEN 1 ELSE 0 END) as kurang
        FROM penilaian")->fetch_assoc();
    
    // Monthly Trend Data (last 6 months)
    $monthlyTrend = $db->query("SELECT 
        bulan, tahun, 
        COUNT(*) as total,
        AVG(rata_rata) as avg_nilai
        FROM penilaian 
        GROUP BY tahun, bulan 
        ORDER BY tahun DESC, FIELD(bulan, 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember') DESC
        LIMIT 6")->fetch_all(MYSQLI_ASSOC);
    $monthlyTrend = array_reverse($monthlyTrend);
    
    // Aspek Average for Radar-like display
    $aspekAvg = $db->query("SELECT 
        AVG(nilai_kedisiplinan) as disiplin,
        AVG(kinerja) as kinerja,
        AVG(sikap) as sikap,
        AVG(kepemimpinan) as kepemimpinan,
        AVG(loyalitas) as loyalitas,
        AVG(it) as it
        FROM penilaian")->fetch_assoc();

    // Top performers
    $topPerformers = $db->query("SELECT p.id_pegawai, p.nama_lengkap, p.jabatan, p.nip, p.foto_profil,
        AVG(n.rata_rata) as avg_nilai
        FROM pegawai p 
        LEFT JOIN penilaian n ON p.nip = n.nip
        GROUP BY p.id_pegawai 
        HAVING avg_nilai IS NOT NULL
        ORDER BY avg_nilai DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // Recent penilaian
    $recentPenilaian = $db->query("SELECT n.*, p.nama_lengkap, p.jabatan, p.id_pegawai
        FROM penilaian n 
        JOIN pegawai p ON n.nip = p.nip
        ORDER BY n.id_penilaian DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

    // Jabatan stats
    $jabatanStats = $db->query("SELECT jabatan, COUNT(*) as total FROM pegawai GROUP BY jabatan")->fetch_all(MYSQLI_ASSOC);

    // Top pegawai paling sering izin (all time)
    $topIzin = $db->query("SELECT p.id_pegawai, p.nama_lengkap, p.jabatan, p.foto_profil,
        COUNT(i.id_izin) as total_izin,
        SUM(CASE WHEN i.status='approved' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN i.status='rejected' THEN 1 ELSE 0 END) as ditolak,
        SUM(CASE WHEN i.status='pending' THEN 1 ELSE 0 END) as pending
        FROM pegawai p
        JOIN izin i ON p.id_pegawai = i.id_pegawai
        GROUP BY p.id_pegawai
        ORDER BY total_izin DESC
        LIMIT 10")->fetch_all(MYSQLI_ASSOC);
} else {
    // User Dashboard
    $userNip = $_SESSION['user_nip'] ?? '';
    
    // Get user's penilaian with prepared statement
    $stmt = $db->prepare("SELECT AVG(rata_rata) as avg_nilai, COUNT(*) as total FROM penilaian WHERE nip=?");
    $stmt->bind_param('s', $userNip);
    $stmt->execute();
    $userPenilaian = $stmt->get_result()->fetch_assoc();
    
    // Get user's izin stats with prepared statement
    $stmt2 = $db->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as disetujui
        FROM izin WHERE id_pegawai = ?");
    $stmt2->bind_param('i', $userId);
    $stmt2->execute();
    $userIzin = $stmt2->get_result()->fetch_assoc();
    
    // Get recent izin with prepared statement
    $stmt3 = $db->prepare("SELECT * FROM izin WHERE id_pegawai = ? ORDER BY tanggal_pengajuan DESC LIMIT 5");
    $stmt3->bind_param('i', $userId);
    $stmt3->execute();
    $recentIzin = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get user's latest penilaian with prepared statement
    $stmt4 = $db->prepare("SELECT * FROM penilaian WHERE nip=? ORDER BY id_penilaian DESC LIMIT 1");
    $stmt4->bind_param('s', $userNip);
    $stmt4->execute();
    $latestPenilaian = $stmt4->get_result()->fetch_assoc();
}
?>

<!-- ====== DASHBOARD ENHANCED STYLES ====== -->
<style>
/* Dashboard Animations */
@keyframes dashFadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes dashCountUp {
    from { opacity: 0; transform: scale(0.5); }
    to { opacity: 1; transform: scale(1); }
}
@keyframes shimmerBg {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes floatSlow {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-8px) rotate(2deg); }
}
@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 4px 15px rgba(99,102,241,0.15); }
    50% { box-shadow: 0 8px 30px rgba(99,102,241,0.25); }
}

/* Dashboard Hero Banner */
.dash-hero {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 30%, #4338ca 60%, #6366f1 100%);
    border-radius: 20px;
    padding: 32px 36px;
    color: white;
    margin-bottom: 28px;
    position: relative;
    overflow: hidden;
    animation: dashFadeUp 0.6s ease;
}
.dash-hero::before {
    content: '';
    position: absolute;
    top: -60%;
    right: -15%;
    width: 400px;
    height: 400px;
    background: radial-gradient(circle, rgba(139,92,246,0.3) 0%, transparent 70%);
    border-radius: 50%;
    animation: floatSlow 6s ease-in-out infinite;
}
.dash-hero::after {
    content: '';
    position: absolute;
    bottom: -40%;
    left: 10%;
    width: 300px;
    height: 300px;
    background: radial-gradient(circle, rgba(99,102,241,0.2) 0%, transparent 70%);
    border-radius: 50%;
    animation: floatSlow 8s ease-in-out infinite reverse;
}
.dash-hero-content { position: relative; z-index: 1; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.dash-hero h2 { font-size: 26px; font-weight: 800; margin-bottom: 6px; letter-spacing: -0.3px; }
.dash-hero p { font-size: 14px; opacity: 0.8; }
.dash-clock { text-align: right; font-family: 'Space Mono', monospace; }
.dash-clock .clock-date { font-size: 12px; opacity: 0.7; }
.dash-clock .clock-time { font-size: 28px; font-weight: 700; letter-spacing: 2px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }

/* Enhanced Stat Cards */
.dash-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
.dash-stat {
    background: white;
    border-radius: 18px;
    padding: 24px;
    display: flex;
    align-items: flex-start;
    gap: 16px;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border);
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    animation: dashFadeUp 0.6s ease backwards;
}
.dash-stat:nth-child(1) { animation-delay: 0.1s; }
.dash-stat:nth-child(2) { animation-delay: 0.2s; }
.dash-stat:nth-child(3) { animation-delay: 0.3s; }
.dash-stat:nth-child(4) { animation-delay: 0.4s; }
.dash-stat:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.08);
    border-color: transparent;
}
.dash-stat::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 0;
    border-radius: 0 4px 4px 0;
    transition: height 0.4s ease;
}
.dash-stat:hover::before { height: 100%; }
.dash-stat.purple::before { background: linear-gradient(180deg, #6366f1, #8b5cf6); }
.dash-stat.green::before { background: linear-gradient(180deg, #10b981, #34d399); }
.dash-stat.amber::before { background: linear-gradient(180deg, #f59e0b, #fbbf24); }
.dash-stat.red::before { background: linear-gradient(180deg, #ef4444, #f87171); }
.dash-stat-icon {
    width: 52px; height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
    transition: transform 0.3s ease;
}
.dash-stat:hover .dash-stat-icon { transform: scale(1.1) rotate(5deg); }
.dash-stat-info h3 {
    font-size: 30px;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
    margin-bottom: 4px;
    letter-spacing: -0.5px;
}
.dash-stat-info p {
    font-size: 13px;
    color: var(--muted);
    font-weight: 500;
}
.dash-stat-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 8px;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
}
.dash-stat-tag.up { background: #ecfdf5; color: #059669; }
.dash-stat-tag.warn { background: #fef3c7; color: #d97706; }
.dash-stat-tag.ok { background: #f0fdf4; color: #16a34a; }

/* Enhanced Cards */
.dash-card {
    background: white;
    border-radius: 18px;
    border: 1px solid var(--border);
    overflow: hidden;
    transition: all 0.3s ease;
    animation: dashFadeUp 0.6s ease backwards;
}
.dash-card:hover {
    box-shadow: 0 8px 30px rgba(0,0,0,0.06);
    border-color: rgba(99,102,241,0.15);
}
.dash-card-head {
    padding: 20px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border-light);
}
.dash-card-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 10px;
}
.dash-card-title i {
    width: 32px; height: 32px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}
.dash-card-body { padding: 24px; }

/* Performer cards */
.performer-row {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    gap: 14px;
    border-bottom: 1px solid #f8fafc;
    transition: all 0.25s ease;
}
.performer-row:hover {
    background: linear-gradient(90deg, #f8fafc, transparent);
    padding-left: 26px;
}
.performer-rank {
    width: 32px; height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
    flex-shrink: 0;
}
.performer-rank.gold { background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; }
.performer-rank.silver { background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #475569; }
.performer-rank.bronze { background: linear-gradient(135deg, #fed7aa, #fdba74); color: #9a3412; }
.performer-rank.normal { background: var(--bg); color: var(--muted); }
.performer-avatar {
    width: 44px; height: 44px;
    border-radius: 12px;
    overflow: hidden;
    flex-shrink: 0;
    border: 2px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.performer-avatar img { width: 100%; height: 100%; object-fit: cover; }
.performer-avatar-fallback {
    width: 100%; height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 14px;
}
.performer-info { flex: 1; min-width: 0; }
.performer-name { font-weight: 700; font-size: 14px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.performer-role { font-size: 12px; color: var(--muted); }
.performer-score {
    text-align: right;
}
.performer-score-val { font-size: 24px; font-weight: 800; color: var(--text); line-height: 1; }

/* Responsive */
@media (max-width: 1100px) {
    .dash-stats { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
    .dash-stats { grid-template-columns: 1fr; }
    .dash-hero { padding: 24px; }
    .dash-hero h2 { font-size: 20px; }
}
</style>

<!-- Dashboard Hero Banner -->
<div class="dash-hero">
    <div class="dash-hero-content">
        <div>
            <h2><?= $isUserAdmin ? '📊 Dashboard Admin' : '👋 Selamat Datang!' ?></h2>
            <p><?= $isUserAdmin ? 'Ringkasan data kinerja pegawai secara realtime' : 'Halo ' . htmlspecialchars($_SESSION['user_name']) . ', berikut ringkasan aktivitas Anda' ?></p>
        </div>
        <div class="dash-clock">
            <div class="clock-date" id="realtime-date"><?= date('d M Y') ?></div>
            <div class="clock-time" id="realtime-clock"></div>
        </div>
    </div>
</div>
<script>
(function(){
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    function update(){
        const now = new Date();
        const d = days[now.getDay()]+', '+now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
        const t = String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0')+':'+String(now.getSeconds()).padStart(2,'0')+' WITA';
        document.getElementById('realtime-date').textContent = d;
        document.getElementById('realtime-clock').textContent = t;
    }
    update();
    setInterval(update, 1000);
})();
</script>

<?php if ($isUserAdmin): ?>
<!-- ADMIN DASHBOARD -->

<style>
/* Chart Styles */
.chart-container {
    position: relative;
    padding: 20px;
}

.donut-chart {
    position: relative;
    width: 200px;
    height: 200px;
    margin: 0 auto;
}

.donut-chart svg {
    transform: rotate(-90deg);
}

.donut-segment {
    fill: none;
    stroke-width: 40;
    stroke-linecap: round;
    animation: donutAnimation 1.5s ease-out forwards;
}

@keyframes donutAnimation {
    from { stroke-dasharray: 0 100; }
}

.donut-center {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.donut-center .value {
    font-size: 36px;
    font-weight: 800;
    color: #1e293b;
}

.donut-center .label {
    font-size: 12px;
    color: #64748b;
}

.chart-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 16px;
    margin-top: 24px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

/* Bar Chart */
.bar-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    height: 220px;
    padding: 40px 10px 20px;
    gap: 16px;
}

.bar-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    height: 100%;
    justify-content: flex-end;
}

.bar {
    width: 100%;
    max-width: 50px;
    background: linear-gradient(180deg, #6366f1, #8b5cf6);
    border-radius: 8px 8px 0 0;
    position: relative;
}

.bar-value {
    font-size: 13px;
    font-weight: 700;
    color: #4f46e5;
    white-space: nowrap;
    margin-bottom: 6px;
    position: relative;
    z-index: 10;
}

.bar-label {
    margin-top: 10px;
    font-size: 11px;
    color: #64748b;
    text-align: center;
}

/* Gauge / Speedometer */
.gauge-container {
    position: relative;
    width: 180px;
    height: 100px;
    margin: 0 auto;
}

.gauge-bg {
    fill: none;
    stroke: #e2e8f0;
    stroke-width: 16;
}

.gauge-fill {
    fill: none;
    stroke-width: 16;
    stroke-linecap: round;
    animation: gaugeAnimation 1.5s ease-out forwards;
}

@keyframes gaugeAnimation {
    from { stroke-dasharray: 0 283; }
}

.gauge-value {
    position: absolute;
    bottom: 10px;
    left: 50%;
    transform: translateX(-50%);
    text-align: center;
}

.gauge-value .number {
    font-size: 28px;
    font-weight: 800;
    color: #1e293b;
}

.gauge-value .label {
    font-size: 11px;
    color: #64748b;
}

/* Aspek Bars */
.aspek-item {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.aspek-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    flex-shrink: 0;
}

.aspek-info {
    flex: 1;
}

.aspek-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.aspek-name {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.aspek-value {
    font-size: 13px;
    font-weight: 700;
}

.aspek-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
}

.aspek-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 1s ease-out;
}

/* Activity Timeline */
.activity-timeline {
    position: relative;
    padding-left: 24px;
}

.activity-timeline::before {
    content: '';
    position: absolute;
    left: 7px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}

.activity-item {
    position: relative;
    padding-bottom: 20px;
}

.activity-item::before {
    content: '';
    position: absolute;
    left: -20px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #6366f1;
    border: 2px solid white;
    box-shadow: 0 0 0 2px #6366f1;
}

.activity-item.success::before { background: #10b981; box-shadow: 0 0 0 2px #10b981; }
.activity-item.warning::before { background: #f59e0b; box-shadow: 0 0 0 2px #f59e0b; }
.activity-item.danger::before { background: #ef4444; box-shadow: 0 0 0 2px #ef4444; }

.activity-content h4 {
    font-size: 14px;
    margin: 0 0 4px 0;
    color: #1e293b;
}

.activity-content p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

.activity-time {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 4px;
}
</style>

<!-- STAT CARDS -->
<div class="dash-stats">
    <div class="dash-stat purple">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6366f1">
            <i class="fas fa-users"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $totalPegawai ?></h3>
            <p>Total Pegawai</p>
            <div class="dash-stat-tag up"><i class="fas fa-check-circle"></i> Terdaftar Aktif</div>
        </div>
    </div>

    <div class="dash-stat green">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#10b981">
            <i class="fas fa-star"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $totalPenilaian ?></h3>
            <p>Total Penilaian</p>
            <div class="dash-stat-tag up"><i class="fas fa-layer-group"></i> Semua Periode</div>
        </div>
    </div>

    <div class="dash-stat amber">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $totalIzinPending ?></h3>
            <p>Izin Pending</p>
            <div class="dash-stat-tag <?= $totalIzinPending > 0 ? 'warn' : 'up' ?>">
                <i class="fas fa-<?= $totalIzinPending > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= $totalIzinPending > 0 ? 'Perlu Diproses' : 'Semua Selesai' ?>
            </div>
        </div>
    </div>

    <div class="dash-stat red">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca);color:#ef4444">
            <i class="fas fa-comment-dots"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $totalAduan ?></h3>
            <p>Pengaduan Pending</p>
            <div class="dash-stat-tag <?= $totalAduan > 0 ? 'warn' : 'up' ?>">
                <i class="fas fa-<?= $totalAduan > 0 ? 'exclamation-triangle' : 'check-circle' ?>"></i>
                <?= $totalAduan > 0 ? 'Perlu Ditangani' : 'Semua Tertangani' ?>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:28px">
    
    <!-- Donut Chart - Predikat Distribution -->
    <div class="dash-card" style="animation-delay:0.15s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-chart-pie" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6366f1"></i> Distribusi Predikat</div>
        </div>
        <div class="chart-container">
            <?php
            $total = ($predikatData['istimewa'] ?? 0) + ($predikatData['sangat_baik'] ?? 0) + 
                     ($predikatData['baik'] ?? 0) + ($predikatData['cukup'] ?? 0) + ($predikatData['kurang'] ?? 0);
            $total = $total ?: 1;
            
            $segments = [
                ['value' => $predikatData['istimewa'] ?? 0, 'color' => '#10b981', 'label' => 'Istimewa'],
                ['value' => $predikatData['sangat_baik'] ?? 0, 'color' => '#3b82f6', 'label' => 'Sangat Baik'],
                ['value' => $predikatData['baik'] ?? 0, 'color' => '#8b5cf6', 'label' => 'Baik'],
                ['value' => $predikatData['cukup'] ?? 0, 'color' => '#f59e0b', 'label' => 'Cukup'],
                ['value' => $predikatData['kurang'] ?? 0, 'color' => '#ef4444', 'label' => 'Kurang'],
            ];
            
            $offset = 0;
            ?>
            <div class="donut-chart">
                <svg width="200" height="200" viewBox="0 0 200 200">
                    <?php foreach ($segments as $seg):
                        $pct = ($seg['value'] / $total) * 100;
                        $dashArray = $pct . " " . (100 - $pct);
                    ?>
                    <circle class="donut-segment" cx="100" cy="100" r="70" 
                        stroke="<?= $seg['color'] ?>" 
                        stroke-dasharray="<?= $dashArray ?>" 
                        stroke-dashoffset="-<?= $offset ?>"
                        pathLength="100"/>
                    <?php $offset += $pct; endforeach; ?>
                </svg>
                <div class="donut-center">
                    <div class="value"><?= $totalPenilaian ?></div>
                    <div class="label">Total</div>
                </div>
            </div>
            <div class="chart-legend">
                <?php foreach ($segments as $seg): if ($seg['value'] > 0): ?>
                <div class="legend-item">
                    <div class="legend-dot" style="background:<?= $seg['color'] ?>"></div>
                    <span><?= $seg['label'] ?> (<?= $seg['value'] ?>)</span>
                </div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Bar Chart - Monthly Trend -->
    <div class="dash-card" style="animation-delay:0.25s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-chart-bar" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#3b82f6"></i> Rata-rata Nilai Bulanan</div>
        </div>
        <div class="chart-container">
            <?php $maxVal = 100; // Maximum nilai adalah 100 ?>
            <div class="bar-chart">
                <?php foreach ($monthlyTrend as $m): 
                    $avgVal = round($m['avg_nilai'] ?? 0, 1);
                    $height = ($avgVal / $maxVal) * 160;
                    $bulanShort = substr($m['bulan'], 0, 3);
                ?>
                <div class="bar-item">
                    <div class="bar-value"><?= $avgVal ?></div>
                    <div class="bar" style="height:<?= max($height, 20) ?>px"></div>
                    <div class="bar-label"><?= $bulanShort ?><br><?= substr($m['tahun'], 2) ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($monthlyTrend)): ?>
                <div style="text-align:center;color:var(--muted);padding:60px 20px;width:100%">
                    <i class="fas fa-chart-bar" style="font-size:40px;opacity:0.3;margin-bottom:10px"></i>
                    <p>Belum ada data</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Gauge - Average Score -->
    <div class="dash-card" style="animation-delay:0.35s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-tachometer-alt" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#10b981"></i> Rata-rata Keseluruhan</div>
        </div>
        <div class="chart-container" style="padding-top:30px">
            <?php
            $avgScore = $avgNilai ?: 0;
            $gaugePercent = $avgScore;
            $gaugeColor = '#10b981';
            if ($avgScore < 51) $gaugeColor = '#f59e0b';
            if ($avgScore < 31) $gaugeColor = '#ef4444';
            $pred = getPredikat($avgScore);
            ?>
            <div class="gauge-container">
                <svg width="180" height="100" viewBox="0 0 180 100">
                    <path class="gauge-bg" d="M 10 90 A 80 80 0 0 1 170 90"/>
                    <path class="gauge-fill" d="M 10 90 A 80 80 0 0 1 170 90" 
                        stroke="<?= $gaugeColor ?>" 
                        stroke-dasharray="<?= ($gaugePercent / 100) * 251 ?> 251"/>
                </svg>
                <div class="gauge-value">
                    <div class="number"><?= number_format($avgScore, 1) ?></div>
                    <div class="label"><?= $pred['label'] ?></div>
                </div>
            </div>
            
            <!-- Mini Stats -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:30px">
                <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-radius:10px">
                    <div style="font-size:20px;font-weight:800;color:#059669"><?= $predikatData['istimewa'] ?? 0 ?></div>
                    <div style="font-size:11px;color:#065f46">Istimewa</div>
                </div>
                <div style="text-align:center;padding:12px;background:linear-gradient(135deg,#dbeafe,#bfdbfe);border-radius:10px">
                    <div style="font-size:20px;font-weight:800;color:#2563eb"><?= $predikatData['sangat_baik'] ?? 0 ?></div>
                    <div style="font-size:11px;color:#1d4ed8">Sangat Baik</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ASPEK ANALYSIS & QUICK STATS -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px">
    
    <!-- Aspek Performance -->
    <div class="dash-card" style="animation-delay:0.2s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-chart-line" style="background:linear-gradient(135deg,#fce7f3,#fbcfe8);color:#ec4899"></i> Performa per Aspek</div>
            <span style="font-size:12px;color:var(--muted);font-weight:500">Rata-rata semua pegawai</span>
        </div>
        <div class="dash-card-body">
            <?php 
            $aspekList = [
                ['name' => 'Kedisiplinan', 'value' => $aspekAvg['disiplin'] ?? 0, 'icon' => 'fa-user-clock', 'color' => '#6366f1'],
                ['name' => 'Kinerja', 'value' => $aspekAvg['kinerja'] ?? 0, 'icon' => 'fa-chart-line', 'color' => '#8b5cf6'],
                ['name' => 'Sikap', 'value' => $aspekAvg['sikap'] ?? 0, 'icon' => 'fa-heart', 'color' => '#ec4899'],
                ['name' => 'Kepemimpinan', 'value' => $aspekAvg['kepemimpinan'] ?? 0, 'icon' => 'fa-crown', 'color' => '#f59e0b'],
                ['name' => 'Loyalitas', 'value' => $aspekAvg['loyalitas'] ?? 0, 'icon' => 'fa-handshake', 'color' => '#10b981'],
                ['name' => 'IT', 'value' => $aspekAvg['it'] ?? 0, 'icon' => 'fa-laptop-code', 'color' => '#3b82f6'],
            ];
            foreach ($aspekList as $asp):
            ?>
            <div class="aspek-item">
                <div class="aspek-icon" style="background:<?= $asp['color'] ?>20;color:<?= $asp['color'] ?>">
                    <i class="fas <?= $asp['icon'] ?>"></i>
                </div>
                <div class="aspek-info">
                    <div class="aspek-header">
                        <span class="aspek-name"><?= $asp['name'] ?></span>
                        <span class="aspek-value" style="color:<?= $asp['color'] ?>"><?= number_format($asp['value'], 1) ?></span>
                    </div>
                    <div class="aspek-bar">
                        <div class="aspek-fill" style="width:<?= $asp['value'] ?>%;background:<?= $asp['color'] ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Quick Stats Cards -->
    <div class="dash-card" style="animation-delay:0.3s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-bolt" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b"></i> Statistik Cepat</div>
        </div>
        <div class="dash-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);padding:20px;border-radius:14px;text-align:center">
                    <div style="width:50px;height:50px;background:#3b82f6;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:white;font-size:20px">
                        <i class="fas fa-users"></i>
                    </div>
                    <div style="font-size:28px;font-weight:800;color:#1e40af"><?= $totalPegawai ?></div>
                    <div style="font-size:12px;color:#3b82f6">Total Pegawai</div>
                </div>
                <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);padding:20px;border-radius:14px;text-align:center">
                    <div style="width:50px;height:50px;background:#10b981;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:white;font-size:20px">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div style="font-size:28px;font-weight:800;color:#065f46"><?= $totalPenilaian ?></div>
                    <div style="font-size:12px;color:#10b981">Total Penilaian</div>
                </div>
                <div style="background:linear-gradient(135deg,#fefce8,#fef9c3);padding:20px;border-radius:14px;text-align:center">
                    <div style="width:50px;height:50px;background:#f59e0b;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:white;font-size:20px">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div style="font-size:28px;font-weight:800;color:#92400e"><?= $totalIzinPending ?></div>
                    <div style="font-size:12px;color:#d97706">Izin Pending</div>
                </div>
                <div style="background:linear-gradient(135deg,#fef2f2,#fecaca);padding:20px;border-radius:14px;text-align:center">
                    <div style="width:50px;height:50px;background:#ef4444;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:white;font-size:20px">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div style="font-size:28px;font-weight:800;color:#991b1b"><?= $totalAduan ?></div>
                    <div style="font-size:12px;color:#dc2626">Aduan Pending</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ROW 2 -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:28px">

    <!-- Top Performers -->
    <div class="dash-card" style="animation-delay:0.2s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-trophy" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b"></i> Top Performer</div>
            <a href="?page=penilaian" class="btn btn-outline btn-sm">Lihat Semua</a>
        </div>
        <div style="padding:0">
            <?php foreach ($topPerformers as $i => $p):
                $pred = getPredikat($p['avg_nilai']);
            ?>
            <div class="performer-row">
                <div class="performer-rank <?= $i === 0 ? 'gold' : ($i === 1 ? 'silver' : ($i === 2 ? 'bronze' : 'normal')) ?>">
                    <?php if ($i < 3): ?><i class="fas fa-crown"></i><?php else: echo $i + 1; endif; ?>
                </div>
                <div class="performer-avatar">
                    <?php if ($p['foto_profil'] && $p['foto_profil'] !== 'default.jpg'): ?>
                    <img src="<?= getBaseUrl() ?>uploads/<?= $p['foto_profil'] ?>" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="performer-avatar-fallback" style="display:none;background:<?= getAvatarColor($p['id_pegawai']) ?>">
                        <?= getInitials($p['nama_lengkap']) ?>
                    </div>
                    <?php else: ?>
                    <div class="performer-avatar-fallback" style="background:<?= getAvatarColor($p['id_pegawai']) ?>">
                        <?= getInitials($p['nama_lengkap']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="performer-info">
                    <div class="performer-name"><?= sanitize($p['nama_lengkap']) ?></div>
                    <div class="performer-role"><?= sanitize($p['jabatan'] ?? 'Pegawai') ?></div>
                </div>
                <div class="performer-score">
                    <div class="performer-score-val"><?= number_format($p['avg_nilai'], 1) ?></div>
                    <span class="badge <?= $pred['class'] ?>" style="font-size:10px"><?= $pred['label'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($topPerformers)): ?>
            <div style="text-align:center;padding:40px;color:var(--muted)">
                <i class="fas fa-trophy" style="font-size:40px;opacity:0.3;margin-bottom:10px"></i>
                <p>Belum ada data penilaian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Jabatan Distribution -->
    <div class="dash-card" style="animation-delay:0.3s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-briefcase" style="background:linear-gradient(135deg,#e0e7ff,#c7d2fe);color:#6366f1"></i> Distribusi Jabatan</div>
            <span style="font-size:12px;color:var(--muted);font-weight:500"><?= $deptTotal = array_sum(array_column($jabatanStats, 'total')) ?> pegawai</span>
        </div>
        <div class="dash-card-body">
            <?php if (empty($jabatanStats)): ?>
            <div style="text-align:center;color:var(--muted);padding:30px">Belum ada data</div>
            <?php else: ?>
            <?php
                $deptColors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#14b8a6','#f43f5e','#a855f7','#0ea5e9'];
                $deptIcons = ['fa-user-tie','fa-user-cog','fa-user-shield','fa-user-check','fa-user-graduate','fa-user-edit','fa-user-nurse','fa-user-astronaut','fa-user-md','fa-user-friends'];
                // Donut chart SVG
                $radius = 60;
                $cx = 80;
                $cy = 80;
                $circumference = 2 * M_PI * $radius;
                $offset = 0;
                $slices = [];
                foreach ($jabatanStats as $i => $d) {
                    $pct = $deptTotal > 0 ? ($d['total'] / $deptTotal) : 0;
                    $dash = $pct * $circumference;
                    $gap = $circumference - $dash;
                    $color = $deptColors[$i % count($deptColors)];
                    $slices[] = ['dash' => $dash, 'gap' => $gap, 'offset' => $offset, 'color' => $color];
                    $offset += $dash;
                }
            ?>
            <!-- Donut Chart -->
            <div style="display:flex;justify-content:center;margin-bottom:20px">
                <div style="position:relative;width:160px;height:160px">
                    <svg width="160" height="160" viewBox="0 0 160 160">
                        <?php foreach ($slices as $s): ?>
                        <circle cx="<?= $cx ?>" cy="<?= $cy ?>" r="<?= $radius ?>" fill="none" stroke="<?= $s['color'] ?>" stroke-width="22"
                            stroke-dasharray="<?= $s['dash'] ?> <?= $s['gap'] ?>"
                            stroke-dashoffset="-<?= $s['offset'] ?>"
                            transform="rotate(-90 <?= $cx ?> <?= $cy ?>)"
                            style="transition:stroke-dasharray 0.6s ease"/>
                        <?php endforeach; ?>
                    </svg>
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center">
                        <div style="font-size:26px;font-weight:800;color:var(--text)"><?= $deptTotal ?></div>
                        <div style="font-size:10px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:0.5px">Pegawai</div>
                    </div>
                </div>
            </div>
            <!-- Legend List -->
            <div style="display:flex;flex-direction:column;gap:10px;max-height:220px;overflow-y:auto;padding-right:4px">
                <?php foreach ($jabatanStats as $i => $d):
                    $pct = $deptTotal > 0 ? round($d['total'] / $deptTotal * 100) : 0;
                    $color = $deptColors[$i % count($deptColors)];
                    $icon = $deptIcons[$i % count($deptIcons)];
                ?>
                <div style="display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:10px;background:var(--bg);transition:background 0.2s">
                    <div style="width:32px;height:32px;border-radius:8px;background:<?= $color ?>15;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas <?= $icon ?>" style="font-size:13px;color:<?= $color ?>"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= sanitize($d['jabatan'] ?: 'Tidak Diset') ?>"><?= sanitize($d['jabatan'] ?: 'Tidak Diset') ?></div>
                        <div style="display:flex;align-items:center;gap:6px;margin-top:3px">
                            <div style="flex:1;height:4px;background:#e2e8f0;border-radius:3px;overflow:hidden">
                                <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:3px"></div>
                            </div>
                            <span style="font-size:10px;color:var(--muted);min-width:28px;text-align:right"><?= $pct ?>%</span>
                        </div>
                    </div>
                    <div style="font-size:15px;font-weight:800;color:var(--text);min-width:24px;text-align:right"><?= $d['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Penilaian -->
<div class="dash-card" style="animation-delay:0.2s">
    <div class="dash-card-head">
        <div class="dash-card-title"><i class="fas fa-clock" style="background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#3b82f6"></i> Penilaian Terbaru</div>
        <a href="?page=penilaian" class="btn btn-outline btn-sm">Lihat Semua</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pegawai</th>
                    <th>Periode</th>
                    <th>Kedisiplinan</th>
                    <th>Kinerja</th>
                    <th>Sikap</th>
                    <th>Kepemimpinan</th>
                    <th>Loyalitas</th>
                    <th>IT</th>
                    <th>Rata-rata</th>
                    <th>Predikat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentPenilaian as $n):
                    $pred = getPredikat($n['rata_rata']);
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="avatar" style="background:<?= getAvatarColor($n['id_pegawai']) ?>;width:32px;height:32px;font-size:11px">
                                <?= getInitials($n['nama_lengkap']) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px"><?= sanitize($n['nama_lengkap']) ?></div>
                                <div style="font-size:11px;color:var(--muted)"><?= sanitize($n['jabatan']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span style="font-family:'Space Mono',monospace;font-size:12px;background:var(--bg);padding:3px 8px;border-radius:5px"><?= $n['bulan'] ?>/<?= $n['tahun'] ?></span></td>
                    <td><?= $n['nilai_kedisiplinan'] ?></td>
                    <td><?= $n['kinerja'] ?></td>
                    <td><?= $n['sikap'] ?></td>
                    <td><?= $n['kepemimpinan'] ?></td>
                    <td><?= $n['loyalitas'] ?></td>
                    <td><?= $n['it'] ?></td>
                    <td>
                        <div style="font-weight:700"><?= number_format($n['rata_rata'], 1) ?></div>
                        <div class="nilai-bar" style="width:60px;margin-top:3px">
                            <div class="nilai-fill" style="width:<?= $n['rata_rata'] ?>%"></div>
                        </div>
                    </td>
                    <td><span class="badge <?= $pred['class'] ?>"><?= $pred['label'] ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recentPenilaian)): ?>
                <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:30px">Belum ada data penilaian</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart: Pegawai Paling Sering Izin -->
<div class="dash-card" style="margin-top:24px;animation-delay:0.25s">
    <div class="dash-card-head">
        <div class="dash-card-title"><i class="fas fa-chart-bar" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b"></i> Pegawai Paling Sering Izin</div>
        <span style="font-size:12px;color:var(--muted);font-weight:500">Semua periode</span>
    </div>
    <div style="padding:24px">
        <?php if (empty($topIzin)): ?>
        <div style="text-align:center;padding:40px;color:var(--muted)">
            <i class="fas fa-calendar-check" style="font-size:40px;opacity:0.3;margin-bottom:12px"></i>
            <p>Belum ada data pengajuan izin</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:14px">
            <?php 
            $maxIzin = $topIzin[0]['total_izin'] ?? 1;
            foreach ($topIzin as $idx => $ti): 
                $pct = round(($ti['total_izin'] / $maxIzin) * 100);
                $barColors = ['#f59e0b','#fb923c','#f97316','#ea580c','#c2410c','#9a3412','#78350f','#a16207','#ca8a04','#eab308'];
                $barColor = $barColors[$idx] ?? '#f59e0b';
            ?>
            <div style="display:flex;align-items:center;gap:14px">
                <div style="width:28px;text-align:center;font-size:13px;font-weight:700;color:<?= $idx < 3 ? '#f59e0b' : 'var(--muted)' ?>">
                    <?php if ($idx === 0): ?><i class="fas fa-trophy"></i><?php else: echo $idx + 1; endif; ?>
                </div>
                <div class="avatar" style="background:<?= getAvatarColor($ti['id_pegawai']) ?>;width:36px;height:36px;min-width:36px;font-size:12px;border-radius:10px">
                    <?= getInitials($ti['nama_lengkap']) ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <div style="min-width:0">
                            <span style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?= sanitize($ti['nama_lengkap']) ?></span>
                            <span style="font-size:10px;color:var(--muted)"><?= sanitize($ti['jabatan'] ?? '-') ?></span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;flex-shrink:0;margin-left:12px">
                            <span style="font-size:11px;color:#059669" title="Disetujui"><i class="fas fa-check-circle"></i> <?= (int)$ti['disetujui'] ?></span>
                            <span style="font-size:11px;color:#d97706" title="Pending"><i class="fas fa-clock"></i> <?= (int)$ti['pending'] ?></span>
                            <span style="font-size:11px;color:#dc2626" title="Ditolak"><i class="fas fa-times-circle"></i> <?= (int)$ti['ditolak'] ?></span>
                            <span style="font-weight:800;font-size:15px;color:var(--text);min-width:28px;text-align:right"><?= $ti['total_izin'] ?></span>
                        </div>
                    </div>
                    <div style="height:8px;background:var(--bg);border-radius:6px;overflow:hidden">
                        <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:6px;transition:width 0.6s ease"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:16px;justify-content:center;margin-top:20px;padding-top:16px;border-top:1px solid var(--border-light)">
            <span style="font-size:11px;color:var(--muted)"><i class="fas fa-check-circle" style="color:#059669"></i> Disetujui</span>
            <span style="font-size:11px;color:var(--muted)"><i class="fas fa-clock" style="color:#d97706"></i> Pending</span>
            <span style="font-size:11px;color:var(--muted)"><i class="fas fa-times-circle" style="color:#dc2626"></i> Ditolak</span>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- USER DASHBOARD -->

<!-- Onboarding Guide Modal -->
<div id="onboardingModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.7);z-index:9999;backdrop-filter:blur(4px)">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:92%;max-width:600px;max-height:92vh;overflow-y:auto;background:white;border-radius:20px;box-shadow:0 25px 80px rgba(0,0,0,0.3)">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 50%,#a855f7 100%);padding:32px 28px 28px;border-radius:20px 20px 0 0;text-align:center;color:white;position:relative;overflow:hidden">
            <div style="position:absolute;top:-20px;right:-20px;width:100px;height:100px;background:rgba(255,255,255,0.1);border-radius:50%"></div>
            <div style="position:absolute;bottom:-30px;left:-20px;width:80px;height:80px;background:rgba(255,255,255,0.08);border-radius:50%"></div>
            <div style="font-size:48px;margin-bottom:12px">📖</div>
            <h2 style="font-size:22px;font-weight:800;margin-bottom:6px">Panduan Lengkap KinPro</h2>
            <p style="font-size:13px;opacity:0.9">Pelajari semua fitur dan cara penggunaannya</p>
        </div>
        <!-- Steps -->
        <div id="onboardSteps" style="padding:24px 28px">

            <!-- STEP 0: Dashboard & Profil -->
            <div class="ob-step" data-step="0">
                <div style="margin-bottom:16px">
                    <span style="display:inline-block;padding:4px 12px;background:#ede9fe;color:#6366f1;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.5px">HALAMAN 1 / 4</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:16px">
                    <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-house" style="color:white;font-size:16px"></i></div>
                            <h4 style="font-size:15px;font-weight:700;color:#1e40af">Dashboard</h4>
                        </div>
                        <p style="font-size:12.5px;color:#1e3a5f;line-height:1.7;margin-bottom:8px">Dashboard adalah halaman utama yang tampil setelah Anda login. Di sini Anda bisa melihat:</p>
                        <ul style="font-size:12px;color:#2563eb;line-height:1.8;padding-left:18px;margin:0">
                            <li><strong>Rata-rata Nilai Kinerja</strong> — skor rata-rata dari seluruh penilaian bulanan Anda</li>
                            <li><strong>Status Izin</strong> — jumlah izin yang diajukan, disetujui, dan masih pending</li>
                            <li><strong>Riwayat Izin Terakhir</strong> — daftar 5 pengajuan izin terbaru lengkap dengan statusnya</li>
                            <li><strong>Detail Penilaian Terakhir</strong> — breakdown nilai per aspek dari bulan terakhir</li>
                        </ul>
                    </div>
                    <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#6366f1;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-user" style="color:white;font-size:16px"></i></div>
                            <h4 style="font-size:15px;font-weight:700;color:#4338ca">Profil Saya</h4>
                        </div>
                        <p style="font-size:12.5px;color:#3b2e6e;line-height:1.7;margin-bottom:8px">Kelola informasi akun dan data diri Anda. Cara menggunakannya:</p>
                        <ul style="font-size:12px;color:#6366f1;line-height:1.8;padding-left:18px;margin:0">
                            <li>Klik menu <strong>"Profil Saya"</strong> di sidebar kiri</li>
                            <li>Anda bisa mengubah <strong>foto profil</strong> dengan klik pada area foto → pilih file gambar → simpan</li>
                            <li>Lihat data lengkap: NIP, nama, jabatan, unit kerja, dan status kepegawaian</li>
                            <li>Pastikan data Anda selalu terbaru agar penilaian tercatat dengan benar</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- STEP 1: Penilaian Kinerja -->
            <div class="ob-step" data-step="1" style="display:none">
                <div style="margin-bottom:16px">
                    <span style="display:inline-block;padding:4px 12px;background:#fef3c7;color:#92400e;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.5px">HALAMAN 2 / 4</span>
                </div>
                <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#fefce8,#fef9c3);border:1px solid #fde047">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                        <div style="width:40px;height:40px;border-radius:10px;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-star" style="color:white;font-size:16px"></i></div>
                        <h4 style="font-size:15px;font-weight:700;color:#92400e">Penilaian Kinerja Saya</h4>
                    </div>
                    <p style="font-size:12.5px;color:#78350f;line-height:1.7;margin-bottom:12px">Setiap bulan, kinerja Anda dinilai oleh atasan berdasarkan <strong>6 aspek</strong> penilaian:</p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px">
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">📋 Kedisiplinan</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Kehadiran, ketepatan waktu, kepatuhan terhadap aturan</div>
                        </div>
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">⚡ Kinerja</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Kualitas & kuantitas hasil kerja, pencapaian target</div>
                        </div>
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">🤝 Sikap</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Etika kerja, sopan santun, kerjasama tim</div>
                        </div>
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">👑 Kepemimpinan</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Inisiatif, kemampuan mengarahkan, pengambilan keputusan</div>
                        </div>
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">❤️ Loyalitas</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Dedikasi, komitmen terhadap organisasi</div>
                        </div>
                        <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                            <div style="font-size:11px;font-weight:700;color:#92400e">💻 IT</div>
                            <div style="font-size:10.5px;color:#a16207;margin-top:3px">Kemampuan teknologi informasi & literasi digital</div>
                        </div>
                    </div>
                    <div style="padding:12px 14px;background:white;border-radius:10px;border:1px solid #fde68a">
                        <p style="font-size:11.5px;color:#78350f;line-height:1.7;margin:0"><strong>Skala Nilai:</strong> Setiap aspek dinilai <strong>0–100</strong>. Rata-rata dari semua aspek menentukan predikat Anda:</p>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                            <span style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:#dcfce7;color:#166534">≥86 Istimewa</span>
                            <span style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:#dbeafe;color:#1e40af">71–85 Sangat Baik</span>
                            <span style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:#fef9c3;color:#854d0e">51–70 Baik</span>
                            <span style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:#fed7aa;color:#9a3412">31–50 Cukup</span>
                            <span style="padding:3px 10px;border-radius:8px;font-size:10px;font-weight:700;background:#fecaca;color:#991b1b">&lt;31 Kurang</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Pengajuan Izin & Pengaduan -->
            <div class="ob-step" data-step="2" style="display:none">
                <div style="margin-bottom:16px">
                    <span style="display:inline-block;padding:4px 12px;background:#d1fae5;color:#065f46;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.5px">HALAMAN 3 / 4</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:16px">
                <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border:1px solid #6ee7b7">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                        <div style="width:40px;height:40px;border-radius:10px;background:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-calendar-plus" style="color:white;font-size:16px"></i></div>
                        <h4 style="font-size:15px;font-weight:700;color:#065f46">Pengajuan Izin / Cuti</h4>
                    </div>
                    <p style="font-size:12.5px;color:#064e3b;line-height:1.7;margin-bottom:12px">Ajukan izin atau cuti secara online tanpa perlu dokumen fisik:</p>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">1</div>
                            <p style="font-size:12px;color:#065f46;line-height:1.6;margin:0">Klik menu <strong>"Pengajuan Izin"</strong> di sidebar, lalu klik tombol <strong>"Ajukan Izin Baru"</strong></p>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">2</div>
                            <p style="font-size:12px;color:#065f46;line-height:1.6;margin:0">Pilih <strong>jenis izin</strong> (Cuti Tahunan, Sakit, Izin Khusus, Dinas Luar), isi tanggal & alasan</p>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#10b981;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">3</div>
                            <p style="font-size:12px;color:#065f46;line-height:1.6;margin:0">Lampirkan dokumen jika ada, lalu klik <strong>"Kirim"</strong>. Kuota: <strong>maks 4x per bulan</strong></p>
                        </div>
                    </div>
                </div>

                <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #fbbf24">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                        <div style="width:40px;height:40px;border-radius:10px;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-comment-dots" style="color:white;font-size:16px"></i></div>
                        <h4 style="font-size:15px;font-weight:700;color:#92400e">Pengaduan</h4>
                    </div>
                    <p style="font-size:12.5px;color:#78350f;line-height:1.7;margin-bottom:12px">Sampaikan keluhan, laporan, atau saran kepada Admin secara rahasia:</p>
                    <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px">
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#f59e0b;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">1</div>
                            <p style="font-size:12px;color:#92400e;line-height:1.6;margin:0">Klik menu <strong>"Pengaduan"</strong>, lalu klik <strong>"Buat Pengaduan"</strong></p>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#f59e0b;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">2</div>
                            <p style="font-size:12px;color:#92400e;line-height:1.6;margin:0">Pilih <strong>jenis laporan</strong> (Pelanggaran Disiplin, Keluhan Fasilitas, Saran, dll)</p>
                        </div>
                        <div style="display:flex;gap:10px;align-items:flex-start">
                            <div style="width:26px;height:26px;border-radius:50%;background:#f59e0b;color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0">3</div>
                            <p style="font-size:12px;color:#92400e;line-height:1.6;margin:0">Isi detail kejadian, lampirkan bukti jika ada, lalu <strong>"Kirim Pengaduan"</strong></p>
                        </div>
                    </div>
                    <div style="padding:10px 12px;background:white;border-radius:10px;border:1px solid #fde68a">
                        <p style="margin:0;font-size:11px;color:#92400e;line-height:1.6"><i class="fas fa-shield-alt"></i> <strong>Privasi:</strong> Laporan Anda bersifat rahasia dan hanya dapat dilihat oleh Admin. Pantau status pengaduan (Menunggu → Ditanggapi / Ditolak) kapan saja.</p>
                    </div>
                </div>
                </div>
            </div>

            <!-- STEP 3: Rating & Tips -->
            <div class="ob-step" data-step="3" style="display:none">
                <div style="margin-bottom:16px">
                    <span style="display:inline-block;padding:4px 12px;background:#fce7f3;color:#9d174d;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:0.5px">HALAMAN 4 / 4</span>
                </div>
                <div style="display:flex;flex-direction:column;gap:16px">
                    <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border:1px solid #f9a8d4">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#ec4899;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-ranking-star" style="color:white;font-size:16px"></i></div>
                            <h4 style="font-size:15px;font-weight:700;color:#9d174d">Rating Pegawai</h4>
                        </div>
                        <p style="font-size:12.5px;color:#831843;line-height:1.7;margin-bottom:8px">Fitur untuk melihat peringkat kinerja seluruh pegawai:</p>
                        <ul style="font-size:12px;color:#be185d;line-height:1.8;padding-left:18px;margin:0">
                            <li>Tampilan <strong>kartu bergaya FIFA</strong> — setiap pegawai ditampilkan dalam kartu interaktif dengan rating</li>
                            <li>Lihat <strong>peringkat terbaik hingga terendah</strong> berdasarkan rata-rata nilai kinerja</li>
                            <li>Klik kartu pegawai untuk melihat <strong>detail breakdown nilai</strong> per aspek penilaian</li>
                            <li>Gunakan fitur <strong>pencarian & filter</strong> untuk menemukan pegawai tertentu</li>
                        </ul>
                    </div>
                    <div style="padding:18px;border-radius:14px;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border:1px solid #cbd5e1">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
                            <div style="width:40px;height:40px;border-radius:10px;background:#6366f1;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-lightbulb" style="color:white;font-size:16px"></i></div>
                            <h4 style="font-size:15px;font-weight:700;color:#334155">Tips Penggunaan</h4>
                        </div>
                        <ul style="font-size:12px;color:#475569;line-height:1.8;padding-left:18px;margin:0">
                            <li><strong>Navigasi:</strong> Gunakan menu sidebar di sebelah kiri untuk berpindah antar halaman</li>
                            <li><strong>Di HP/Tablet:</strong> Tekan tombol <strong>☰</strong> di pojok kiri atas untuk membuka sidebar</li>
                            <li><strong>Perbarui Profil:</strong> Selalu pastikan foto dan data Anda sudah terbaru</li>
                            <li><strong>Cek Rutin:</strong> Pantau nilai kinerja dan status izin secara berkala</li>
                            <li><strong>Logout:</strong> Selalu logout setelah selesai, terutama di perangkat bersama — klik <strong>"Keluar"</strong> di bagian bawah sidebar</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
        <!-- Footer -->
        <div style="padding:0 28px 24px;display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;gap:6px" id="obDots">
                <span style="width:10px;height:10px;border-radius:50%;background:#6366f1" id="obDot0"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#e2e8f0" id="obDot1"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#e2e8f0" id="obDot2"></span>
                <span style="width:10px;height:10px;border-radius:50%;background:#e2e8f0" id="obDot3"></span>
            </div>
            <div style="display:flex;gap:10px">
                <button id="obPrevBtn" onclick="prevOnboardStep()" style="display:none;padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;background:white;color:#64748b;font-size:13px;font-weight:600;cursor:pointer"><i class="fas fa-arrow-left" style="margin-right:4px"></i> Kembali</button>
                <button onclick="closeOnboarding()" style="padding:10px 20px;border:1px solid #e2e8f0;border-radius:10px;background:white;color:#64748b;font-size:13px;font-weight:600;cursor:pointer">Lewati</button>
                <button id="obNextBtn" onclick="nextOnboardStep()" style="padding:10px 24px;border:none;border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 4px 15px rgba(99,102,241,0.4)">Lanjut <i class="fas fa-arrow-right" style="margin-left:4px"></i></button>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var key = 'kinpro_onboard_<?= $userId ?>';
    var totalSteps = 4;
    if (!localStorage.getItem(key)) {
        document.getElementById('onboardingModal').style.display = 'block';
    }
    window.obStep = 0;

    function updateStepUI() {
        for (var i = 0; i < totalSteps; i++) {
            var el = document.querySelector('[data-step="'+i+'"]');
            if (el) el.style.display = (i === window.obStep) ? 'block' : 'none';
            var dot = document.getElementById('obDot'+i);
            if (dot) dot.style.background = (i === window.obStep) ? '#6366f1' : '#e2e8f0';
        }
        document.getElementById('obPrevBtn').style.display = (window.obStep > 0) ? 'inline-flex' : 'none';
        if (window.obStep === totalSteps - 1) {
            document.getElementById('obNextBtn').innerHTML = 'Selesai <i class="fas fa-check" style="margin-left:4px"></i>';
        } else {
            document.getElementById('obNextBtn').innerHTML = 'Lanjut <i class="fas fa-arrow-right" style="margin-left:4px"></i>';
        }
    }

    window.nextOnboardStep = function() {
        if (window.obStep < totalSteps - 1) {
            window.obStep++;
            updateStepUI();
        } else {
            closeOnboarding();
        }
    };
    window.prevOnboardStep = function() {
        if (window.obStep > 0) {
            window.obStep--;
            updateStepUI();
        }
    };
    window.closeOnboarding = function() {
        localStorage.setItem(key, '1');
        document.getElementById('onboardingModal').style.display = 'none';
    };
    window.openOnboarding = function() {
        window.obStep = 0;
        updateStepUI();
        document.getElementById('onboardingModal').style.display = 'block';
    };
})();
</script>

<!-- Welcome Banner -->
<div class="dash-hero" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 40%,#a855f7 70%,#c084fc 100%)">
    <div class="dash-hero-content">
        <div style="position:relative;z-index:1">
            <p style="font-size:14px;opacity:0.85;margin-bottom:4px">Selamat Datang Kembali 👋</p>
            <h2 style="font-size:26px;font-weight:800;margin-bottom:8px"><?= htmlspecialchars($_SESSION['user_name']) ?></h2>
            <p style="font-size:13px;opacity:0.8"><?= htmlspecialchars($_SESSION['user_jabatan'] ?? 'Pegawai') ?> &bull; Bapekom 8 Makassar</p>
        </div>
        <div style="display:flex;gap:12px;position:relative;z-index:1">
            <a href="?page=izin" style="padding:12px 20px;background:rgba(255,255,255,0.2);border-radius:12px;color:white;text-decoration:none;font-size:13px;font-weight:600;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.2);transition:all 0.3s" onmouseover="this.style.background='rgba(255,255,255,0.3)';this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.2)';this.style.transform=''">
                <i class="fas fa-calendar-plus"></i> Ajukan Izin
            </a>
            <button onclick="openOnboarding()" style="padding:12px 20px;background:rgba(255,255,255,0.12);border-radius:12px;color:white;border:1px solid rgba(255,255,255,0.2);font-size:13px;font-weight:600;cursor:pointer;backdrop-filter:blur(8px);transition:all 0.3s" onmouseover="this.style.background='rgba(255,255,255,0.22)';this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.12)';this.style.transform=''">
                <i class="fas fa-book-open"></i> Panduan
            </button>
        </div>
    </div>
</div>

<!-- STAT CARDS -->
<div class="dash-stats" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    <div class="dash-stat purple">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6366f1">
            <i class="fas fa-star"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= number_format($userPenilaian['avg_nilai'] ?? 0, 1) ?></h3>
            <p>Rata-rata Nilai</p>
            <?php $pred = getPredikat($userPenilaian['avg_nilai'] ?? 0); ?>
            <div class="dash-stat-tag up"><span class="badge <?= $pred['class'] ?>" style="font-size:10px"><?= $pred['label'] ?></span></div>
        </div>
    </div>

    <div class="dash-stat green">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#10b981">
            <i class="fas fa-clipboard-check"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $userPenilaian['total'] ?? 0 ?></h3>
            <p>Total Penilaian</p>
            <div class="dash-stat-tag up"><i class="fas fa-layer-group"></i> Periode</div>
        </div>
    </div>

    <div class="dash-stat amber">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $userIzin['total'] ?? 0 ?></h3>
            <p>Total Izin</p>
            <div class="dash-stat-tag up"><i class="fas fa-check-circle"></i> Diajukan</div>
        </div>
    </div>

    <div class="dash-stat red">
        <div class="dash-stat-icon" style="background:linear-gradient(135deg,#fee2e2,#fecaca);color:#ef4444">
            <i class="fas fa-clock"></i>
        </div>
        <div class="dash-stat-info">
            <h3><?= $userIzin['pending'] ?? 0 ?></h3>
            <p>Izin Pending</p>
            <div class="dash-stat-tag <?= ($userIzin['pending'] ?? 0) > 0 ? 'warn' : 'up' ?>">
                <i class="fas fa-<?= ($userIzin['pending'] ?? 0) > 0 ? 'hourglass-half' : 'check-circle' ?>"></i>
                <?= ($userIzin['pending'] ?? 0) > 0 ? 'Menunggu' : 'Semua Selesai' ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Info -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:20px;margin-bottom:28px">
    <!-- Quick Actions -->
    <div class="dash-card" style="animation-delay:0.15s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-bolt" style="background:linear-gradient(135deg,#fef3c7,#fde68a);color:#f59e0b"></i> Akses Cepat</div>
        </div>
        <div class="dash-card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <a href="?page=profil" style="display:flex;align-items:center;gap:14px;padding:18px 16px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:14px;text-decoration:none;transition:all 0.2s;border:1px solid transparent" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#93c5fd'" onmouseout="this.style.transform='';this.style.borderColor='transparent'">
                    <div style="width:44px;height:44px;border-radius:12px;background:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-user" style="color:white;font-size:18px"></i></div>
                    <div><span style="font-size:14px;font-weight:700;color:#1e40af;display:block">Edit Profil</span><span style="font-size:11px;color:#3b82f6">Data pribadi</span></div>
                </a>
                <a href="?page=izin" style="display:flex;align-items:center;gap:14px;padding:18px 16px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-radius:14px;text-decoration:none;transition:all 0.2s;border:1px solid transparent" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#6ee7b7'" onmouseout="this.style.transform='';this.style.borderColor='transparent'">
                    <div style="width:44px;height:44px;border-radius:12px;background:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-calendar-plus" style="color:white;font-size:18px"></i></div>
                    <div><span style="font-size:14px;font-weight:700;color:#065f46;display:block">Ajukan Izin</span><span style="font-size:11px;color:#10b981">Cuti & izin</span></div>
                </a>
                <a href="?page=penilaian_saya" style="display:flex;align-items:center;gap:14px;padding:18px 16px;background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:14px;text-decoration:none;transition:all 0.2s;border:1px solid transparent" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#fbbf24'" onmouseout="this.style.transform='';this.style.borderColor='transparent'">
                    <div style="width:44px;height:44px;border-radius:12px;background:#f59e0b;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-chart-line" style="color:white;font-size:18px"></i></div>
                    <div><span style="font-size:14px;font-weight:700;color:#92400e;display:block">Penilaian Saya</span><span style="font-size:11px;color:#d97706">Nilai kinerja</span></div>
                </a>
                <a href="?page=rating" style="display:flex;align-items:center;gap:14px;padding:18px 16px;background:linear-gradient(135deg,#fce7f3,#fbcfe8);border-radius:14px;text-decoration:none;transition:all 0.2s;border:1px solid transparent" onmouseover="this.style.transform='translateY(-2px)';this.style.borderColor='#f9a8d4'" onmouseout="this.style.transform='';this.style.borderColor='transparent'">
                    <div style="width:44px;height:44px;border-radius:12px;background:#ec4899;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-trophy" style="color:white;font-size:18px"></i></div>
                    <div><span style="font-size:14px;font-weight:700;color:#9d174d;display:block">Ranking</span><span style="font-size:11px;color:#db2777">Peringkat pegawai</span></div>
                </a>
            </div>
        </div>
    </div>

    <!-- Latest Penilaian Summary -->
    <div class="dash-card" style="animation-delay:0.25s">
        <div class="dash-card-head">
            <div class="dash-card-title"><i class="fas fa-star" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6366f1"></i> Penilaian Terakhir</div>
            <a href="?page=penilaian_saya" class="btn btn-outline btn-sm">Lihat Detail</a>
        </div>
        <div class="dash-card-body">
            <?php if ($latestPenilaian): ?>
            <div style="text-align:center;padding:16px">
                <div style="font-size:56px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent"><?= number_format($latestPenilaian['rata_rata'], 1) ?></div>
                <?php $latestPred = getPredikat($latestPenilaian['rata_rata']); ?>
                <span class="badge <?= $latestPred['class'] ?>" style="font-size:12px;margin-top:8px"><?= $latestPred['label'] ?></span>
                <p style="color:var(--muted);font-size:13px;margin-top:12px">
                    Periode: <?= $latestPenilaian['bulan'] ?>/<?= $latestPenilaian['tahun'] ?>
                </p>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:8px;margin-top:16px">
                <div style="text-align:center;padding:10px;background:var(--bg);border-radius:8px">
                    <div style="font-size:18px;font-weight:700"><?= $latestPenilaian['nilai_kedisiplinan'] ?? 0 ?></div>
                    <div style="font-size:10px;color:var(--muted)">Disiplin</div>
                </div>
                <div style="text-align:center;padding:10px;background:var(--bg);border-radius:8px">
                    <div style="font-size:18px;font-weight:700"><?= $latestPenilaian['kinerja'] ?? 0 ?></div>
                    <div style="font-size:10px;color:var(--muted)">Kinerja</div>
                </div>
                <div style="text-align:center;padding:10px;background:var(--bg);border-radius:8px">
                    <div style="font-size:18px;font-weight:700"><?= $latestPenilaian['sikap'] ?? 0 ?></div>
                    <div style="font-size:10px;color:var(--muted)">Sikap</div>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:40px;color:var(--muted)">
                <i class="fas fa-chart-pie" style="font-size:48px;margin-bottom:16px;opacity:0.3"></i>
                <p>Belum ada penilaian</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Izin -->
<div class="dash-card" style="animation-delay:0.2s">
    <div class="dash-card-head">
        <div class="dash-card-title"><i class="fas fa-history" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#10b981"></i> Pengajuan Izin Terbaru</div>
        <a href="?page=izin" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajukan Baru</a>
    </div>
    <?php if (!empty($recentIzin)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Jenis Izin</th>
                    <th>Periode</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th>Tanggal Pengajuan</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $jenisLabels = [
                    'cuti_tahunan' => 'Cuti Tahunan',
                    'sakit' => 'Sakit',
                    'izin_khusus' => 'Izin Khusus',
                    'dinas_luar' => 'Dinas Luar',
                ];
                $statusLabels = [
                    'pending' => ['label' => 'Menunggu', 'class' => 'badge-average'],
                    'approved' => ['label' => 'Disetujui', 'class' => 'badge-excellent'],
                    'rejected' => ['label' => 'Ditolak', 'class' => 'badge-poor'],
                ];
                foreach ($recentIzin as $izin):
                    $statusInfo = $statusLabels[$izin['status']] ?? ['label' => $izin['status'], 'class' => ''];
                ?>
                <tr>
                    <td><span style="font-weight:600"><?= $jenisLabels[$izin['jenis_izin']] ?? $izin['jenis_izin'] ?></span></td>
                    <td>
                        <?= date('d M', strtotime($izin['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($izin['tanggal_selesai'])) ?>
                    </td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($izin['keterangan'] ?? '') ?></td>
                    <td><span class="badge <?= $statusInfo['class'] ?>"><?= $statusInfo['label'] ?></span></td>
                    <td><?= date('d M Y, H:i', strtotime($izin['tanggal_pengajuan'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="padding:40px;text-align:center;color:var(--muted)">
        <i class="fas fa-inbox" style="font-size:40px;margin-bottom:16px;opacity:0.3"></i>
        <p>Belum ada pengajuan izin</p>
        <a href="?page=izin" class="btn btn-primary btn-sm" style="margin-top:16px">
            <i class="fas fa-plus"></i> Ajukan Izin Pertama
        </a>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<!-- Number Count-Up Animation -->
<script>
(function() {
    function animateValue(el, start, end, duration) {
        var isDecimal = String(end).indexOf('.') !== -1;
        var startTime = null;
        function step(timestamp) {
            if (!startTime) startTime = timestamp;
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
            var current = start + (end - start) * eased;
            el.textContent = isDecimal ? current.toFixed(1) : Math.round(current);
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }
    // Animate all stat numbers
    document.querySelectorAll('.dash-stat-info h3').forEach(function(el) {
        var val = parseFloat(el.textContent);
        if (!isNaN(val) && val > 0) {
            el.textContent = '0';
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        animateValue(el, 0, val, 1200);
                        observer.unobserve(el);
                    }
                });
            }, { threshold: 0.3 });
            observer.observe(el);
        }
    });
    // Animate aspek bars width
    document.querySelectorAll('.aspek-fill').forEach(function(bar) {
        var targetWidth = bar.style.width;
        bar.style.width = '0%';
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    setTimeout(function() { bar.style.width = targetWidth; }, 200);
                    observer.unobserve(bar);
                }
            });
        }, { threshold: 0.2 });
        observer.observe(bar);
    });
})();
</script>
