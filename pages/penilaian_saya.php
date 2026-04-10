<?php
// Halaman Penilaian Saya - User view their own ratings
$userId = (int)$_SESSION['user_id'];
$userNip = $_SESSION['user_nip'] ?? '';

// Get user's penilaian data - menggunakan NIP karena tabel penilaian relasi dengan NIP
$stmt = $db->prepare("SELECT p.*, pg.nama_lengkap  
          FROM penilaian p 
          LEFT JOIN pegawai pg ON p.nip = pg.nip 
          WHERE p.nip = ? 
          ORDER BY p.tahun DESC, p.bulan DESC");
$stmt->bind_param('s', $userNip);
$stmt->execute();
$penilaianData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate averages
$totalNilai = 0;
$totalCount = count($penilaianData);
foreach ($penilaianData as $p) {
    $totalNilai += $p['rata_rata'] ?? 0;
}
$avgNilai = $totalCount > 0 ? $totalNilai / $totalCount : 0;
$predikat = getPredikat($avgNilai);

// Get latest penilaian details
$latestPenilaian = !empty($penilaianData) ? $penilaianData[0] : null;
?>

<style>
.penilaian-overview {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 24px;
    margin-bottom: 24px;
}

@media (max-width: 900px) {
    .penilaian-overview {
        grid-template-columns: 1fr;
    }
}

.score-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 32px;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.score-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
}

.score-value {
    font-size: 72px;
    font-weight: 800;
    line-height: 1;
    position: relative;
}

.score-label {
    font-size: 14px;
    opacity: 0.9;
    margin-top: 8px;
}

.score-predikat {
    display: inline-block;
    padding: 8px 20px;
    background: rgba(255,255,255,0.2);
    border-radius: 20px;
    font-size: 14px;
    font-weight: 700;
    margin-top: 16px;
}

.detail-cards {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
}

.detail-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.detail-card-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.detail-card-title {
    font-size: 13px;
    color: var(--muted);
}

.detail-card-value {
    font-size: 24px;
    font-weight: 800;
    color: var(--text);
}

.progress-bar-container {
    margin-top: 12px;
}

.progress-bar-label {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 6px;
}

.progress-bar {
    height: 8px;
    background: var(--border);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.5s ease;
}

.history-section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
}

.history-header {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-header h3 {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.history-header h3 i {
    color: var(--accent);
}

.history-list {
    max-height: 500px;
    overflow-y: auto;
}

.history-item {
    padding: 18px 20px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.history-item:last-child {
    border-bottom: none;
}

.history-item:hover {
    background: var(--bg);
}

.history-info h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 4px;
}

.history-info p {
    font-size: 12px;
    color: var(--muted);
}

.history-score {
    text-align: right;
}

.history-score-value {
    font-size: 24px;
    font-weight: 800;
    line-height: 1;
}

.history-score-badge {
    margin-top: 4px;
}

.criteria-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 16px;
}

.criteria-item {
    padding: 12px;
    background: var(--bg);
    border-radius: 10px;
}

.criteria-label {
    font-size: 11px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.criteria-value {
    font-size: 18px;
    font-weight: 700;
}

.empty-state {
    padding: 80px 20px;
    text-align: center;
}

.empty-state i {
    font-size: 64px;
    color: var(--border);
    margin-bottom: 20px;
}

.empty-state h3 {
    font-size: 18px;
    margin-bottom: 8px;
}

.empty-state p {
    font-size: 14px;
    color: var(--muted);
    max-width: 300px;
    margin: 0 auto;
}

.chart-container {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
    margin-top: 24px;
}

.chart-header {
    margin-bottom: 20px;
}

.chart-header h3 {
    font-size: 15px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 8px;
}

.chart-header h3 i {
    color: var(--accent);
}

.simple-chart {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    height: 200px;
    padding: 20px 0;
}

.chart-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.chart-bar-fill {
    width: 100%;
    max-width: 50px;
    border-radius: 8px 8px 0 0;
    background: linear-gradient(180deg, #667eea, #764ba2);
    transition: height 0.5s ease;
}

.chart-bar-label {
    font-size: 11px;
    color: var(--muted);
    text-align: center;
}

.chart-bar-value {
    font-size: 12px;
    font-weight: 700;
}
</style>

<?php if (empty($penilaianData)): ?>
<div class="empty-state">
    <i class="fas fa-chart-pie"></i>
    <h3>Belum Ada Penilaian</h3>
    <p>Anda belum memiliki riwayat penilaian kinerja. Penilaian akan muncul setelah admin melakukan evaluasi.</p>
</div>
<?php else: ?>

<!-- Overview Section -->
<div class="penilaian-overview">
    <!-- Main Score Card -->
    <div class="score-card">
        <div class="score-value"><?= number_format($avgNilai, 1) ?></div>
        <div class="score-label">Rata-rata Nilai Kinerja</div>
        <div class="score-predikat">
            <i class="fas fa-award"></i> <?= $predikat['label'] ?>
        </div>
    </div>
    
    <!-- Detail Cards -->
    <div class="detail-cards">
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon" style="background:#dbeafe;color:#2563eb">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div>
                    <div class="detail-card-title">Total Penilaian</div>
                    <div class="detail-card-value"><?= $totalCount ?></div>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon" style="background:#d1fae5;color:#059669">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <div class="detail-card-title">Penilaian Terakhir</div>
                    <div class="detail-card-value"><?= $latestPenilaian ? date('d M Y', strtotime($latestPenilaian['tanggal_input'])) : '-' ?></div>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon" style="background:#fef3c7;color:#d97706">
                    <i class="fas fa-star"></i>
                </div>
                <div>
                    <div class="detail-card-title">Nilai Tertinggi</div>
                    <div class="detail-card-value"><?= $totalCount > 0 ? number_format(max(array_column($penilaianData, 'rata_rata')), 1) : 0 ?></div>
                </div>
            </div>
        </div>
        
        <div class="detail-card">
            <div class="detail-card-header">
                <div class="detail-card-icon" style="background:#fce7f3;color:#db2777">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div>
                    <div class="detail-card-title">Nilai Terendah</div>
                    <div class="detail-card-value"><?= $totalCount > 0 ? number_format(min(array_column($penilaianData, 'rata_rata')), 1) : 0 ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($latestPenilaian): ?>
<!-- Latest Penilaian Details -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-clipboard-list"></i> Detail Penilaian Terakhir</div>
        <span style="font-size:12px;color:var(--muted)"><?= $latestPenilaian['bulan'] ?> <?= $latestPenilaian['tahun'] ?></span>
    </div>
    <div class="card-body">
        <div class="criteria-grid">
            <?php 
            $criteria = [
                'nilai_kedisiplinan' => ['label' => 'Kedisiplinan', 'icon' => 'clock', 'color' => '#3b82f6'],
                'kinerja' => ['label' => 'Kinerja', 'icon' => 'chart-line', 'color' => '#10b981'],
                'sikap' => ['label' => 'Sikap', 'icon' => 'user-check', 'color' => '#8b5cf6'],
                'kepemimpinan' => ['label' => 'Kepemimpinan', 'icon' => 'crown', 'color' => '#f59e0b'],
                'loyalitas' => ['label' => 'Loyalitas', 'icon' => 'heart', 'color' => '#ec4899'],
                'it' => ['label' => 'IT', 'icon' => 'laptop', 'color' => '#14b8a6'],
            ];
            foreach ($criteria as $key => $info):
                $value = $latestPenilaian[$key] ?? 0;
                $valuePredikat = getPredikat($value);
            ?>
            <div class="criteria-item">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                    <i class="fas fa-<?= $info['icon'] ?>" style="color:<?= $info['color'] ?>"></i>
                    <span class="criteria-label"><?= $info['label'] ?></span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span class="criteria-value"><?= $value ?></span>
                    <span class="badge <?= $valuePredikat['class'] ?>" style="font-size:10px"><?= $valuePredikat['label'] ?></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width:<?= $value ?>%;background:<?= $info['color'] ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($latestPenilaian['masukan_atasan'])): ?>
        <div style="margin-top:20px;padding:16px;background:var(--bg);border-radius:10px;border-left:4px solid var(--accent)">
            <div style="font-size:12px;color:var(--muted);margin-bottom:6px">Masukan Atasan</div>
            <div style="font-size:14px;color:var(--text)"><?= htmlspecialchars($latestPenilaian['masukan_atasan']) ?></div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Simple Chart -->
<?php if (count($penilaianData) > 1): ?>
<div class="chart-container">
    <div class="chart-header">
        <h3><i class="fas fa-chart-bar"></i> Trend Penilaian</h3>
    </div>
    <div class="simple-chart">
        <?php 
        $chartData = array_slice(array_reverse($penilaianData), 0, 6);
        $maxValue = max(array_column($chartData, 'rata_rata'));
        foreach ($chartData as $p):
            $height = ($p['rata_rata'] / 100) * 150;
        ?>
        <div class="chart-bar">
            <div class="chart-bar-value"><?= number_format($p['rata_rata'], 1) ?></div>
            <div class="chart-bar-fill" style="height:<?= $height ?>px"></div>
            <div class="chart-bar-label"><?= $p['bulan'] ?> <?= $p['tahun'] ?></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- History Section -->
<div class="history-section" style="margin-top:24px">
    <div class="history-header">
        <h3><i class="fas fa-history"></i> Riwayat Penilaian</h3>
        <span style="font-size:13px;color:var(--muted)"><?= $totalCount ?> penilaian</span>
    </div>
    <div class="history-list">
        <?php foreach ($penilaianData as $p): 
            $pPredikat = getPredikat($p['rata_rata']);
        ?>
        <div class="history-item">
            <div class="history-info">
                <h4>Penilaian <?= $p['bulan'] ?> <?= $p['tahun'] ?></h4>
                <p>
                    <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($p['tanggal_input'])) ?>
                </p>
            </div>
            <div class="history-score">
                <div class="history-score-value" style="color:var(--accent)"><?= number_format($p['rata_rata'], 1) ?></div>
                <div class="history-score-badge">
                    <span class="badge <?= $pPredikat['class'] ?>"><?= $pPredikat['label'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>
