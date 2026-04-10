<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Require login
requireLogin();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Inisialisasi database
$db = getDB();

// Get current user
$currentUser = getCurrentUser();
$isAdminUser = isAdmin();

$page = $_GET['page'] ?? 'dashboard';

// Define valid pages based on role
$adminPages = ['dashboard', 'pegawai', 'penilaian', 'aduan', 'rating', 'kelola_izin', 'profil', 'izin', 'penilaian_saya', 'aduan_saya'];
$userPages = ['dashboard', 'profil', 'izin', 'penilaian_saya', 'rating', 'aduan_saya'];

// Check page access
if ($isAdminUser) {
    $validPages = $adminPages;
} else {
    $validPages = $userPages;
}

if (!in_array($page, $validPages)) $page = 'dashboard';

$pageTitles = [
    'dashboard' => 'Dashboard',
    'pegawai' => 'Data Pegawai',
    'penilaian' => 'Penilaian Kinerja',
    'aduan' => 'Pengaduan',
    'rating' => 'Rating Pegawai',
    'profil' => 'Profil Saya',
    'izin' => 'Pengajuan Izin',
    'penilaian_saya' => 'Penilaian Saya',
    'kelola_izin' => 'Kelola Izin',
    'aduan_saya' => 'Pengaduan Saya',
];
$pageTitle = $pageTitles[$page] ?? 'Dashboard';
$pageSubtitle = [
    'dashboard' => $isAdminUser ? 'Ringkasan Kinerja' : 'Selamat datang, ' . $_SESSION['user_name'],
    'pegawai' => 'Manajemen Pegawai',
    'penilaian' => 'Manajemen Penilaian',
    'aduan' => 'Manajemen Pengaduan',
    'rating' => 'Ranking & Rating Pegawai',
    'profil' => 'Edit Data Profil Anda',
    'izin' => 'Ajukan Izin atau Cuti',
    'aduan_saya' => 'Sampaikan Keluhan Anda',
    'penilaian_saya' => 'Lihat Penilaian Kinerja Anda',
    'kelola_izin' => 'Kelola Pengajuan Izin Pegawai',
];

include 'includes/header.php';
?>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('show');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.querySelector('.sidebar').classList.remove('show');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
document.querySelectorAll('.sidebar .nav-item').forEach(function(item) {
    item.addEventListener('click', function() { if (window.innerWidth <= 768) closeSidebar(); });
});
</script>

<div class="main">
    <div class="topbar">
        <div style="display:flex;align-items:center;gap:12px">
            <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
            <div class="topbar-left">
                <h1><?= $pageTitle ?></h1>
                <p><?= $pageSubtitle[$page] ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <?php if ($isAdminUser): 
                // Get pending pengaduan
                $stmtPA = $db->prepare("SELECT p.*, pg.nama_lengkap FROM pengaduan p JOIN pegawai pg ON p.nip = pg.nip WHERE p.status=? ORDER BY p.tanggal_pengaduan DESC LIMIT 5");
                $pendingStatus = 'pending';
                $stmtPA->bind_param('s', $pendingStatus);
                $stmtPA->execute();
                $pendingAduan = $stmtPA->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                
                $stmtCA = $db->prepare("SELECT COUNT(*) as c FROM pengaduan WHERE status=?");
                $stmtCA->bind_param('s', $pendingStatus);
                $stmtCA->execute();
                $totalAduan = $stmtCA->get_result()->fetch_assoc()['c'] ?? 0;
                
                // Get pending izin
                $stmtPI = $db->prepare("SELECT i.*, pg.nama_lengkap FROM izin i JOIN pegawai pg ON i.id_pegawai = pg.id_pegawai WHERE i.status=? ORDER BY i.tanggal_pengajuan DESC LIMIT 5");
                $stmtPI->bind_param('s', $pendingStatus);
                $stmtPI->execute();
                $pendingIzin = $stmtPI->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
                
                $stmtCI = $db->prepare("SELECT COUNT(*) as c FROM izin WHERE status=?");
                $stmtCI->bind_param('s', $pendingStatus);
                $stmtCI->execute();
                $totalIzin = $stmtCI->get_result()->fetch_assoc()['c'] ?? 0;
            ?>
            
            <!-- Notifikasi Pengaduan -->
            <div class="notif-dropdown">
                <button class="topbar-btn" onclick="toggleNotif('aduanNotif')" title="Pengaduan Pending">
                    <i class="fas fa-bell"></i>
                    <?php if ($totalAduan > 0): ?><span class="notif-dot"></span><?php endif; ?>
                </button>
                <div id="aduanNotif" class="notif-popup">
                    <div class="notif-header">
                        <h4><i class="fas fa-bell"></i> Pengaduan</h4>
                        <span class="notif-count"><?= $totalAduan ?> pending</span>
                    </div>
                    <div class="notif-body">
                        <?php if (empty($pendingAduan)): ?>
                        <div class="notif-empty">
                            <i class="fas fa-check-circle"></i>
                            <p>Tidak ada pengaduan pending</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pendingAduan as $aduan): ?>
                        <a href="?page=aduan&status=pending" class="notif-item">
                            <div class="notif-icon" style="background:#fee2e2;color:#dc2626">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="notif-content">
                                <h5><?= htmlspecialchars($aduan['nama_lengkap']) ?></h5>
                                <p><?= htmlspecialchars(substr($aduan['jenis_laporan'] ?? $aduan['keterangan'] ?? '', 0, 50)) ?></p>
                                <span class="notif-time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('d M Y', strtotime($aduan['tanggal_pengaduan'])) ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($totalAduan > 0): ?>
                    <div class="notif-footer">
                        <a href="?page=aduan&status=pending">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notifikasi Izin -->
            <div class="notif-dropdown">
                <button class="topbar-btn" onclick="toggleNotif('izinNotif')" title="Izin Pending">
                    <i class="fas fa-calendar-check"></i>
                    <?php if ($totalIzin > 0): ?><span class="notif-dot"></span><?php endif; ?>
                </button>
                <div id="izinNotif" class="notif-popup">
                    <div class="notif-header">
                        <h4><i class="fas fa-calendar-check"></i> Pengajuan Izin</h4>
                        <span class="notif-count"><?= $totalIzin ?> pending</span>
                    </div>
                    <div class="notif-body">
                        <?php if (empty($pendingIzin)): ?>
                        <div class="notif-empty">
                            <i class="fas fa-check-circle"></i>
                            <p>Tidak ada izin pending</p>
                        </div>
                        <?php else: ?>
                        <?php 
                        $jenisLabels = [
                            'cuti_tahunan' => 'Cuti Tahunan',
                            'sakit' => 'Sakit',
                            'izin_khusus' => 'Izin Khusus',
                            'dinas_luar' => 'Dinas Luar',
                        ];
                        foreach ($pendingIzin as $izin): 
                        ?>
                        <a href="?page=kelola_izin" class="notif-item">
                            <div class="notif-icon" style="background:#fef3c7;color:#d97706">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="notif-content">
                                <h5><?= htmlspecialchars($izin['nama_lengkap']) ?></h5>
                                <p><?= $jenisLabels[$izin['jenis_izin']] ?? $izin['jenis_izin'] ?></p>
                                <span class="notif-time">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d M', strtotime($izin['tanggal_mulai'])) ?> - <?= date('d M', strtotime($izin['tanggal_selesai'])) ?>
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($totalIzin > 0): ?>
                    <div class="notif-footer">
                        <a href="?page=kelola_izin">Lihat Semua <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php else: ?>
            <a href="?page=izin" class="topbar-btn" title="Pengajuan Izin">
                <i class="fas fa-calendar-plus"></i>
            </a>
            <?php endif; ?>
            <a href="?page=dashboard" class="topbar-btn" title="Dashboard">
                <i class="fas fa-house"></i>
            </a>
            <div style="width:1px;height:24px;background:var(--border)"></div>
            <div class="user-dropdown" style="position:relative">
                <button onclick="toggleUserMenu()" style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--bg);border-radius:10px;border:1px solid var(--border);cursor:pointer">
                    <div style="width:30px;height:30px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white"><?= getUserInitials() ?></div>
                    <div style="text-align:left">
                        <span style="font-size:13px;font-weight:600;color:var(--text);display:block"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        <span style="font-size:10px;color:var(--muted)"><?= $isAdminUser ? 'Administrator' : 'Pegawai' ?></span>
                    </div>
                    <i class="fas fa-chevron-down" style="font-size:10px;color:var(--muted)"></i>
                </button>
                <div id="userMenu" class="user-menu" style="display:none;position:absolute;right:0;top:100%;margin-top:8px;background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.12);border:1px solid var(--border);min-width:200px;z-index:100">
                    <div style="padding:16px;border-bottom:1px solid var(--border)">
                        <div style="font-size:13px;font-weight:600;color:var(--text)"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                        <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($_SESSION['user_nip'] ?? '') ?></div>
                    </div>
                    <div style="padding:8px">
                        <a href="?page=profil" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;transition:background 0.2s">
                            <i class="fas fa-user" style="width:16px;color:var(--muted)"></i> Profil Saya
                        </a>
                        <?php if (!$isAdminUser): ?>
                        <a href="?page=izin" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--text);font-size:13px;transition:background 0.2s">
                            <i class="fas fa-calendar-check" style="width:16px;color:var(--muted)"></i> Pengajuan Izin
                        </a>
                        <?php endif; ?>
                        <div style="height:1px;background:var(--border);margin:8px 0"></div>
                        <a href="?logout=1" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;text-decoration:none;color:var(--danger);font-size:13px;transition:background 0.2s">
                            <i class="fas fa-sign-out-alt" style="width:16px"></i> Keluar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <?php include "pages/{$page}.php"; ?>
    </div>

    <div style="padding:20px 28px;border-top:1px solid var(--border);text-align:center;font-size:12px;color:var(--muted)">
        KinPro — Sistem Kinerja Pegawai Profesional &copy; <?= date('Y') ?> | donefast
    </div>
</div>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    // Close notification popups when opening user menu
    closeAllNotifPopups();
}

function toggleNotif(id) {
    const popup = document.getElementById(id);
    const isOpen = popup.classList.contains('show');
    
    // Close all other popups first
    closeAllNotifPopups();
    document.getElementById('userMenu').style.display = 'none';
    
    // Toggle the clicked popup
    if (!isOpen) {
        popup.classList.add('show');
    }
}

function closeAllNotifPopups() {
    document.querySelectorAll('.notif-popup').forEach(popup => {
        popup.classList.remove('show');
    });
}

// Close menus when clicking outside
document.addEventListener('click', function(e) {
    // Close user dropdown
    const dropdown = document.querySelector('.user-dropdown');
    const menu = document.getElementById('userMenu');
    if (dropdown && !dropdown.contains(e.target)) {
        menu.style.display = 'none';
    }
    
    // Close notification dropdowns
    const notifDropdowns = document.querySelectorAll('.notif-dropdown');
    let clickedInsideNotif = false;
    notifDropdowns.forEach(nd => {
        if (nd.contains(e.target)) {
            clickedInsideNotif = true;
        }
    });
    if (!clickedInsideNotif) {
        closeAllNotifPopups();
    }
});

// Add hover effect for dropdown items
document.querySelectorAll('#userMenu a').forEach(link => {
    link.addEventListener('mouseenter', function() {
        this.style.background = 'var(--bg)';
    });
    link.addEventListener('mouseleave', function() {
        this.style.background = 'transparent';
    });
});
</script>

</body>
</html>
