# KinPro — Kinerja Profesional

Sistem Informasi Kinerja Pegawai Berbasis Web

---

## 📋 Deskripsi

KinPro adalah aplikasi web untuk mengelola kinerja pegawai secara digital, mencakup fitur penilaian kinerja, pengaduan, perizinan, rating pegawai, pengelolaan data pegawai, dan profil pengguna. Terdapat 2 role pengguna yaitu **Admin** dan **Pegawai**.

---

## 🛠️ Persyaratan Sistem

- **PHP** >= 7.4
- **MySQL/MariaDB** >= 5.7
- **Web Server** Apache/Nginx (XAMPP, Laragon, dll)
- **Browser** Chrome, Firefox, Edge (versi terbaru)

---

## 🚀 Instalasi

### 1. Clone Project
```bash
git clone https://github.com/MUHULILAMRI/Kinpro.git
```

### 2. Import Database
- Buka phpMyAdmin
- Buat database baru sesuai konfigurasi
- Import file SQL database

### 3. Konfigurasi Database
Edit file `includes/db.php` dan sesuaikan konfigurasi koneksi database Anda.

### 4. Akses Aplikasi
Buka browser dan kunjungi:
```
http://localhost/sikinerja/
```

---

## 📱 Fitur Aplikasi

### Fitur Admin

| Menu | Deskripsi |
|------|-----------|
| **Dashboard** | Statistik keseluruhan: total pegawai, penilaian, rata-rata nilai, grafik distribusi predikat, top performers |
| **Data Pegawai** | CRUD data pegawai dengan tampilan kartu FIFA Ultimate Team |
| **Penilaian Kinerja** | Input dan kelola penilaian 6 aspek: Kedisiplinan, Kinerja, Sikap, Kepemimpinan, Loyalitas, IT |
| **Pengaduan** | Kelola pengaduan dari pegawai (approve/reject) |
| **Rating Pegawai** | Lihat ranking dan beri rating pegawai |
| **Kelola Izin** | Proses pengajuan izin/cuti pegawai |
| **Profil** | Edit profil, ganti password, upload foto |

### Fitur Pegawai

| Menu | Deskripsi |
|------|-----------|
| **Dashboard** | Ringkasan kinerja pribadi |
| **Penilaian Saya** | Lihat riwayat penilaian kinerja (read-only) |
| **Aduan Saya** | Kirim dan lihat riwayat pengaduan |
| **Pengajuan Izin** | Ajukan izin/cuti (kuota 4x/bulan) |
| **Rating Pegawai** | Lihat ranking pegawai |
| **Profil** | Edit profil pribadi |

---

## 📁 Struktur Folder

```
sikinerja/
├── includes/
│   ├── auth.php      # Manajemen autentikasi & session
│   ├── db.php        # Koneksi database
│   ├── functions.php # Fungsi-fungsi helper
│   ├── header.php    # Template header & sidebar
│   └── security.php  # Fungsi keamanan (CSRF, sanitize, dll)
├── pages/
│   ├── aduan.php          # Kelola pengaduan (admin)
│   ├── aduan_saya.php     # Pengaduan pegawai
│   ├── dashboard.php      # Dashboard
│   ├── izin.php           # Pengajuan izin
│   ├── kelola_izin.php    # Kelola izin (admin)
│   ├── pegawai.php        # Data pegawai (admin)
│   ├── penilaian.php      # Penilaian kinerja (admin)
│   ├── penilaian_saya.php # Penilaian sendiri
│   ├── profil.php         # Profil pengguna
│   └── rating.php         # Rating pegawai
├── uploads/               # File upload pengguna
├── index.php              # Entry point
├── login.php              # Halaman login
└── README.md
```

---

## 🔒 Keamanan

- ✅ **CSRF Protection** — Token validasi untuk setiap form
- ✅ **Password Hashing** — bcrypt (PASSWORD_DEFAULT)
- ✅ **Prepared Statements** — Mencegah SQL Injection
- ✅ **Input Sanitization** — Membersihkan input user
- ✅ **Rate Limiting** — Maks 5x percobaan login per 15 menit
- ✅ **Session Management** — Session regeneration & timeout 2 jam
- ✅ **Safe File Upload** — Validasi tipe, ukuran, dan MIME type

---

## 📊 Aspek Penilaian Kinerja

| Aspek | Keterangan |
|-------|------------|
| Kedisiplinan | Kehadiran, ketepatan waktu |
| Kinerja | Hasil kerja, produktivitas |
| Sikap | Perilaku, etika kerja |
| Kepemimpinan | Kemampuan memimpin |
| Loyalitas | Kesetiaan, dedikasi |
| IT | Kemampuan teknologi informasi |

### Predikat Penilaian

| Rata-rata | Predikat |
|-----------|----------|
| ≥ 90 | Sangat Baik |
| ≥ 80 | Baik |
| ≥ 70 | Cukup |
| < 70 | Kurang |

---

## 📝 Jenis Izin

- Cuti Tahunan
- Sakit
- Izin Khusus
- Dinas Luar

---

**© 2024–2026 KinPro — Kinerja Profesional**
