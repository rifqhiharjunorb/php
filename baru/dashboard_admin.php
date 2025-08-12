<?php
require_once "auth.php";
require_once "pengamanan.php";
hanya_admin();
// Cek apakah user sudah login, jika tidak maka redirect ke halaman login
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Simulasi data untuk dashboard
$total_users = 120;
$active_users = 87;
$total_sales = "Rp 15.750.000";
$pending_orders = 8;

// Contoh data chart (dalam praktiknya data akan diambil dari database)
$revenue_data = json_encode([4500000, 5200000, 4800000, 5600000, 6100000, 5800000]);
$months = json_encode(["Januari", "Februari", "Maret", "April", "Mei", "Juni"]);

require_once "config.php";
// Proses approval
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["approval_id"])) {
    $id = $_POST["approval_id"];
    $aksi = $_POST["aksi"];
    $status = $aksi === "approve" ? "Disetujui" : "Ditolak";
    $sql = "UPDATE peminjaman_ruangan SET status = :status WHERE peminjaman_id = :id";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        unset($stmt);
    }
}
// Ambil data penyewaan yang menunggu approval
$approval = [];
$sql = "SELECT p.peminjaman_id, u.nama_lengkap, r.nama_ruangan, p.tanggal, p.keterangan FROM peminjaman_ruangan p JOIN users u ON p.user_id = u.user_id JOIN ruangan r ON p.ruangan_id = r.ruangan_id WHERE p.status = 'Menunggu' ORDER BY p.tanggal ASC";
foreach($pdo->query($sql) as $row) {
    $approval[] = $row;
}

// --- CRUD KAMAR DAN VILLA ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["tambah_ruangan"])) {
        $nama = trim($_POST["nama_ruangan"]);
        $lokasi = trim($_POST["lokasi"]);
        $kapasitas = intval($_POST["kapasitas"]);
        $harga = intval(str_replace('.', '', $_POST["harga"])); // Remove dots for thousands separator
        if ($nama && $lokasi && $kapasitas > 0 && $harga >= 0 && $harga <= 10000000) {
            $stmt = $pdo->prepare("INSERT INTO ruangan (nama_ruangan, lokasi, kapasitas, harga) VALUES (:nama, :lokasi, :kapasitas, :harga)");
            $stmt->execute([
                ':nama' => $nama,
                ':lokasi' => $lokasi,
                ':kapasitas' => $kapasitas,
                ':harga' => $harga
            ]);
        }
    }
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_ruangan_id"])) {
        $id = $_POST["edit_ruangan_id"];
        $nama = trim($_POST["edit_nama_ruangan"]);
        $lokasi = trim($_POST["edit_lokasi"]);
        $kapasitas = intval($_POST["edit_kapasitas"]);
        $harga = intval(str_replace('.', '', $_POST["edit_harga"])); // Remove dots for thousands separator
        if ($nama && $lokasi && $kapasitas > 0 && $harga >= 0 && $harga <= 10000000) {
            $stmt = $pdo->prepare("UPDATE ruangan SET nama_ruangan=:nama, lokasi=:lokasi, kapasitas=:kapasitas, harga=:harga WHERE ruangan_id=:id");
            $stmt->execute([
                ':nama' => $nama,
                ':lokasi' => $lokasi,
                ':kapasitas' => $kapasitas,
                ':harga' => $harga,
                ':id' => $id
            ]);
        }
    }
// Hapus kamar (hanya jika tidak dipakai di peminjaman_ruangan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["hapus_ruangan_id"])) {
    $id = $_POST["hapus_ruangan_id"];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman_ruangan WHERE ruangan_id = :id");
    $stmt->execute([':id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        $hapus_error = "Kamar tidak bisa dihapus karena masih ada riwayat penyewaan!";
    } else {
        $stmt = $pdo->prepare("DELETE FROM ruangan WHERE ruangan_id=:id");
        $stmt->execute([':id' => $id]);
    }
}
// Ambil data kamar dan villa
$ruangan = [];
foreach($pdo->query("SELECT * FROM ruangan ORDER BY ruangan_id ASC") as $row) {
    $ruangan[] = $row;
}

// --- APPROVAL PENYEWAAN ---
// Proses approval penyewaan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["approval_peminjaman_id"])) {
    $id = $_POST["approval_peminjaman_id"];
    $aksi = $_POST["aksi_peminjaman"];
    $status = $aksi === "approve" ? "Selesai" : "Ditolak";
    $sql = "UPDATE peminjaman_ruangan SET status = :status WHERE peminjaman_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status'=>$status, ':id'=>$id]);
}
// Ambil data pengembalian yang menunggu approval
$approval_pengembalian = [];
$sql = "SELECT p.peminjaman_id, u.nama_lengkap, r.nama_ruangan, p.tanggal as tanggal_pinjam, p.tanggal_checkout as tanggal_kembali FROM peminjaman_ruangan p JOIN users u ON p.user_id = u.user_id JOIN ruangan r ON p.ruangan_id = r.ruangan_id WHERE p.status = 'Menunggu Pengembalian' ORDER BY p.tanggal ASC";
foreach($pdo->query($sql) as $row) {
    $approval_pengembalian[] = $row;
}

// Proses approval pengembalian
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["approval_pengembalian_id"])) {
    $id = $_POST["approval_pengembalian_id"];
    $aksi = $_POST["aksi_pengembalian"];
    $status = $aksi === "approve" ? "Selesai" : "Ditolak";
    $sql = "UPDATE peminjaman_ruangan SET status = :status WHERE peminjaman_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status'=>$status, ':id'=>$id]);
}

// --- REPORT ---
$report = [];
$sql = "SELECT p.peminjaman_id, u.nama_lengkap, r.nama_ruangan, p.tanggal as tanggal_pinjam, p.tanggal_checkout as tanggal_kembali, p.status 
        FROM peminjaman_ruangan p 
        JOIN users u ON p.user_id = u.user_id 
        JOIN ruangan r ON p.ruangan_id = r.ruangan_id 
        WHERE p.status = 'Selesai' OR p.status = 'Ditolak'";
foreach($pdo->query($sql) as $row) {
    $report[] = $row;
}

// Proses hapus data penyewaan dari report
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["hapus_peminjaman_id"])) {
    $id = $_POST["hapus_peminjaman_id"];
    $stmt = $pdo->prepare("DELETE FROM peminjaman_ruangan WHERE peminjaman_id = :id");
    $stmt->execute([':id' => $id]);
    $hapus_sukses = "Data penyewaan berhasil dihapus dari report!";
}
    
// Statistik dashboard
// Total Penyewaan: semua data dari semua user
$stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman_ruangan");
$total_peminjaman = $stmt->fetchColumn();
// Total Selesai: status = 'Selesai'
$stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman_ruangan WHERE status = 'Selesai'");
$total_selesai = $stmt->fetchColumn();
// Total Ditolak: status = 'Ditolak'
$stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman_ruangan WHERE status = 'Ditolak'");
$total_ditolak = $stmt->fetchColumn();
// Penyewaan Aktif: status = 'Disetujui' dan tanggal >= hari ini
$stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman_ruangan WHERE status = 'Disetujui' AND tanggal >= CURDATE()");
$peminjaman_aktif = $stmt->fetchColumn();
// Menunggu Approval: status = 'Menunggu' atau 'Menunggu Pengembalian'
$stmt = $pdo->query("SELECT COUNT(*) FROM peminjaman_ruangan WHERE status = 'Menunggu' OR status = 'Menunggu Pengembalian'");
$total_menunggu = $stmt->fetchColumn();

// Pengguna Aktif: user unik yang sedang meminjam (status Menunggu, Disetujui, Menunggu Pengembalian)
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM peminjaman_ruangan WHERE status IN ('Menunggu', 'Disetujui', 'Menunggu Pengembalian')");
$total_user_aktif = $stmt->fetchColumn();

// Data diagram batang (bar chart) 4 bulan terakhir
$bulanIndo = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$labels = [];
$peminjaman_per_bulan = [];
$selesai_per_bulan = [];
$tahun_sekarang = date('Y');
for($i=3;$i>=0;$i--) {
    $bulan = date('n', strtotime("-$i month", strtotime($tahun_sekarang . '-01-01')));
    $tahun = date('Y', strtotime("-$i month", strtotime($tahun_sekarang . '-01-01')));
    $labels[] = $bulanIndo[$bulan] . ' ' . $tahun;
    // Penyewaan: semua data (tanpa filter status)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman_ruangan WHERE MONTH(tanggal)=:bulan AND YEAR(tanggal)=:tahun");
    $stmt->execute([':bulan'=>$bulan, ':tahun'=>$tahun]);
    $peminjaman_per_bulan[] = (int)$stmt->fetchColumn();
    // Pengembalian: status Selesai
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman_ruangan WHERE MONTH(tanggal)=:bulan AND YEAR(tanggal)=:tahun AND status='Selesai'");
    $stmt->execute([':bulan'=>$bulan, ':tahun'=>$tahun]);
    $selesai_per_bulan[] = (int)$stmt->fetchColumn();
}

$nama = $_SESSION['nama_lengkap'] ?? 'Admin';
$inisial = strtoupper(implode('', array_map(function($v){return $v[0];}, explode(' ', $nama))));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome untuk icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Tambahkan CDN jsPDF & autoTable di <head> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>
    <style>
        /* Sidebar dan layout */
        .sidebar {
            min-height: 100vh;
            border-right: 1.5px solid #e5e7eb;
            background: #fff;
            box-shadow: 0 2px 12px rgba(2,132,200,0.04);
        }
        .sidebar-link {
            font-weight: 600;
            border-left: 4px solid transparent;
            border-radius: 8px;
            margin-bottom: 2px;
            padding-left: 18px !important;
            padding-right: 8px !important;
            letter-spacing: 0.5px;
        }
        .sidebar-link.active, .sidebar-link:hover {
            color: #0284c7 !important;
            background: #e0f2fe !important;
            border-left: 4px solid #0284c7 !important;
        }
        .sidebar-link i {
            color: #0284c7 !important;
            min-width: 22px;
            text-align: center;
        }
        .sidebar-link.active i, .sidebar-link:hover i {
            color: #0284c7 !important;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sidebar-link {
            transition: color 0.2s, background 0.2s, border 0.2s;
        }
        .sidebar .space-y-1 > * + * {
            margin-top: 6px;
        }
        /* Main content */
        .main-content {
            padding: 32px 24px 24px 24px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main-content h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #0284c7;
            margin-bottom: 24px;
            letter-spacing: 0.5px;
        }
        .stats-card, .main-content .bg-white {
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(2,132,200,0.07);
            margin-bottom: 18px;
        }
        .main-content table {
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .main-content th, .main-content td {
            padding: 12px 16px;
            text-align: left;
        }
        .main-content th {
            background: #f1f5f9;
            color: #0284c7;
            font-weight: 700;
        }
        .main-content td {
            background: #fff;
            color: #334155;
        }
        /* Jam digital glassmorphism */
        .digital-clock {
            font-family: 'Orbitron', 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: bold;
            color: #0284c7;
            background: rgba(255,255,255,0.35);
            border: 1.5px solid #bae6fd;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(2,132,200,0.10);
            padding: 8px 32px;
            letter-spacing: 2px;
            backdrop-filter: blur(8px);
            transition: background 0.3s, color 0.3s;
            margin: 0 0 0 0;
            display: inline-block;
            min-width: 120px;
            text-align: center;
            animation: clock-glow 1.5s infinite alternate;
        }
        @keyframes clock-glow {
            0% { box-shadow: 0 4px 24px rgba(2,132,200,0.10); }
            100% { box-shadow: 0 4px 32px rgba(2,132,200,0.18); }
        }
        /* Responsive */
        @media (max-width: 900px) {
            .main-content { padding: 16px 4px; }
        }
        @media (max-width: 700px) {
            .main-content { padding: 8px 2px; }
            .main-content h2 { font-size: 1.2rem; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Navbar -->
    <nav class="bg-white shadow-sm">
      <div class="max-w-screen-lg mx-auto px-2 sm:px-4 lg:px-8">
        <div class="flex items-center justify-between py-2">
          <!-- Kiri: Hallo Admin -->
          <div class="flex-1 flex items-center justify-start">
            <h1 class="text-xl font-bold text-sky-600 flex items-center">
              Hallo Admin <span class="ml-2">😎🤙</span>
            </h1>
          </div>
          <!-- Tengah: Jam -->
          <div class="flex-1 flex justify-center">
            <span id="clock" class="digital-clock"></span>
          </div>
          <!-- Kanan: Greeting, Avatar, Keluar -->
          <div class="flex items-center space-x-2">
            <span class="bg-sky-600 text-white font-bold rounded-full w-9 h-9 flex items-center justify-center">
              <?php echo $inisial; ?>
            </span>
            <!-- Hapus link Keluar di sini -->
          </div>
        </div>
      </div>
    </nav>
    <!-- Overlay untuk sidebar mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 sidebar-overlay" style="display:none;" onclick="toggleSidebar()"></div>
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-white shadow-md sidebar flex flex-col">
            <div class="p-2 flex-1">
                <ul class="space-y-1 text-base">
                    <li>
                        <a href="#" class="flex items-center px-6 py-2 rounded-lg sidebar-link block" onclick="showMenu('dashboard');return false;">
                            <i class="fas fa-tachometer-alt w-5 mr-3"></i>Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-6 py-2 rounded-lg sidebar-link block" onclick="showMenu('ruangan');return false;">
                            <i class="fas fa-door-open w-5 mr-3"></i>CRUD Kamar dan Villa
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-6 py-2 rounded-lg sidebar-link block" onclick="showMenu('approval_peminjaman');return false;">
                            <i class="fas fa-check-circle w-5 mr-3"></i>Approval Penyewaan
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-6 py-2 rounded-lg sidebar-link block" onclick="showMenu('approval_pengembalian');return false;">
                            <i class="fas fa-undo w-5 mr-3"></i>Approval Pengembalian
                        </a>
                    </li>
                    <li>
                        <a href="#" class="flex items-center px-6 py-2 rounded-lg sidebar-link block" onclick="showMenu('report');return false;">
                            <i class="fas fa-file-alt w-5 mr-3"></i>Report
                        </a>
                    </li>
                </ul>
                <div style="flex:1"></div>
                <ul class="space-y-1 text-base" style="margin-top:32px;">
                    <li>
                        <a href="#" onclick="showModalLogout();return false;" class="flex items-center px-6 py-2 rounded-lg sidebar-link block">
                            <i class="fas fa-sign-out-alt w-5 mr-3"></i>Keluar
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Main content -->
        <div class="flex-1 p-2 sm:p-4 md:p-8">
            <div id="menu-dashboard">
            <h2 class="font-semibold text-gray-800 mb-6 text-lg md:text-xl lg:text-2xl">Dashboard Overview</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card border-l-4 border-sky-500">
                    <div class="flex items-center justify-between">
                        <div>
                                <p class="text-sm md:text-base font-medium text-gray-500">Total Penyewaan</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $total_peminjaman; ?></p>
                            </div>
                            <div class="p-3 rounded-full bg-sky-100 text-sky-500">
                                <i class="fas fa-door-open text-xl"></i>
                        </div>
                        </div>
                    </div>
                <div class="bg-white rounded-lg shadow-sm p-6 stats-card border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                                <p class="text-sm md:text-base font-medium text-gray-500">Total Selesai</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $total_selesai; ?></p>
                        </div>
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                <i class="fas fa-undo text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card border-l-4 border-teal-500">
                    <div class="flex items-center justify-between">
                        <div>
                                <p class="text-sm md:text-base font-medium text-gray-500">Pengguna Aktif</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $total_user_aktif; ?></p>
                        </div>
                        <div class="p-3 rounded-full bg-teal-100 text-teal-500">
                                <i class="fas fa-users text-xl"></i>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 stats-card border-l-4 border-red-500">
                    <div class="flex items-center justify-between">
                        <div>
                                <p class="text-sm md:text-base font-medium text-gray-500">Total Ditolak</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $total_ditolak; ?></p>
                        </div>
                        <div class="p-3 rounded-full bg-red-100 text-red-500">
                                <i class="fas fa-times-circle text-xl"></i>
                        </div>
                        </div>
                    </div>
                <div class="bg-white rounded-lg shadow-sm p-6 stats-card border-l-4 border-yellow-500">
                    <div class="flex items-center justify-between">
                        <div>
                                <p class="text-sm md:text-base font-medium text-gray-500">Menunggu Approval</p>
                                <p class="text-xl md:text-2xl font-bold text-gray-800"><?php echo $total_menunggu; ?></p>
                        </div>
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                <i class="fas fa-hourglass-half text-xl"></i>
                            </div>
                        </div>
                    </div>
                    </div>
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6" style="max-width:800px;margin:auto;">
                    <h3 class="text-lg font-semibold mb-4">Rekap Total Penyewaan & Pengembalian</h3>
                    <canvas id="rekapChart" height="100"></canvas>
                </div>
            </div>
            <div id="menu-ruangan" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">CRUD Kamar hotel</h2>
                <div class="mb-4 flex justify-between items-center">
                    <form method="post" class="flex space-x-2">
                        <input type="text" name="nama_ruangan" placeholder="Nama Kamar" class="border rounded px-2 py-1" required>
                <input type="text" name="lokasi" placeholder="Lokasi" class="border rounded px-2 py-1" required>
                <input type="number" name="kapasitas" placeholder="Kapasitas" class="border rounded px-2 py-1" required min="1">
                <input type="number" name="harga" placeholder="Harga (Rp)" class="border rounded px-2 py-1" required min="0">
                <button type="submit" name="tambah_ruangan" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded font-semibold shadow-lg">Tambah</button>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded shadow">
                <thead>
                    <tr>
                        <th class="px-4 py-2 border-b">No</th>
                        <th class="px-4 py-2 border-b">Nama Kamar/Villa</th>
                        <th class="px-4 py-2 border-b">Lokasi</th>
                        <th class="px-4 py-2 border-b">Kapasitas</th>
                        <th class="px-4 py-2 border-b">Harga (Rp)</th>
                        <th class="px-4 py-2 border-b">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ruangan as $i => $r): ?>
                    <tr>
                        <td class="px-4 py-2 border-b"><?php echo $i+1; ?></td>
                        <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['nama_ruangan']); ?></td>
                        <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['lokasi']); ?></td>
                        <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['kapasitas']); ?></td>
                        <td class="px-4 py-2 border-b"><?php echo number_format($r['harga'] ?? 0, 0, ',', '.'); ?></td>
                        <td class="px-4 py-2 border-b">
                            <button type="button" class="text-blue-500 hover:underline mr-2" onclick="showModalEdit('<?php echo $r['ruangan_id']; ?>','<?php echo htmlspecialchars($r['nama_ruangan'],ENT_QUOTES); ?>','<?php echo htmlspecialchars($r['lokasi'],ENT_QUOTES); ?>','<?php echo $r['kapasitas']; ?>','<?php echo $r['harga'] ?? 0; ?>')">Edit</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="hapus_ruangan_id" value="<?php echo $r['ruangan_id']; ?>">
                                <button type="button" class="text-red-500 hover:underline" onclick="showModalHapusRuangan('<?php echo $r['ruangan_id']; ?>')">Hapus</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
            </div>
            <div id="menu-approval_peminjaman" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Approval Penyewaan</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">No</th>
                                <th class="px-4 py-2 border-b">Nama Peminjam</th>
                                <th class="px-4 py-2 border-b">Ruangan</th>
                                <th class="px-4 py-2 border-b">Tanggal</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b">Keterangan</th>
                                <th class="px-4 py-2 border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($approval as $i => $a): ?>
                            <tr>
                                <td class="px-4 py-2 border-b align-top"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2 border-b align-top"><?php echo htmlspecialchars($a['nama_lengkap']); ?></td>
                                <td class="px-4 py-2 border-b align-top"><?php echo htmlspecialchars($a['nama_ruangan']); ?></td>
                                <td class="px-4 py-2 border-b align-top"><?php echo htmlspecialchars($a['tanggal']); ?></td>
                                <td class="px-4 py-2 border-b align-top"><span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Menunggu</span></td>
                                <td class="px-4 py-2 border-b align-top text-center">
                                    <button type="button" onclick="toggleKeterangan('keterangan-<?php echo $a['peminjaman_id']; ?>', this)" class="focus:outline-none">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div id="keterangan-<?php echo $a['peminjaman_id']; ?>" class="mt-2 text-sm text-gray-800 font-normal" style="display:none;max-width:300px;word-break:break-word;">
                                        <div class="font-semibold mb-1">Keterangan:</div>
                                        <div class="bg-yellow-50 border border-yellow-300 rounded px-3 py-2">
                                            <?php echo nl2br(htmlspecialchars($a['keterangan'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 border-b align-top">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="approval_id" value="<?php echo $a['peminjaman_id']; ?>">
<button name="aksi" value="approve" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded mr-2" onclick="return confirm('Setujui peminjaman ini?')">Approve</button>
                                        <button name="aksi" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded" onclick="return confirm('Tolak peminjaman ini?')">Tolak</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($approval)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-gray-400">Tidak ada permohonan peminjaman menunggu approval.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="menu-approval_pengembalian" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Approval Pengembalian</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">No</th>
                                <th class="px-4 py-2">Nama Peminjam</th>
                                <th class="px-4 py-2">Ruangan</th>
                                <th class="px-4 py-2">Tanggal Kembali</th>
                                <th class="px-4 py-2">Jam Pengembalian</th>
                                <th class="px-4 py-2">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($approval_pengembalian as $i => $a): ?>
                            <tr>
                                <td class="px-4 py-2"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($a['nama_lengkap']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($a['nama_ruangan']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($a['tanggal_pinjam']); ?></td>
                                <td class="px-4 py-2"><?php echo htmlspecialchars($a['tanggal_kembali']); ?></td>
                            <td class="px-4 py-2">
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="approval_pengembalian_id" value="<?php echo $a['peminjaman_id']; ?>">
                                    <button name="aksi_pengembalian" value="approve" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded mr-2" onclick="return confirm('Setujui pengembalian ini?')">Setuju</button>
                                    <button name="aksi_pengembalian" value="reject" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded" onclick="return confirm('Tolak pengembalian ini?')">Tolak</button>
                                </form>
                            </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($approval_pengembalian)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-gray-400">Tidak ada pengembalian menunggu approval.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="menu-report" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Report Peminjaman & Pengembalian</h2>
                <button onclick="exportReportPDF()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded font-semibold mb-4">Export PDF</button>
                <div class="overflow-x-auto">
                    <table id="report-table" class="min-w-full bg-white rounded shadow text-xs sm:text-sm md:text-base">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">No</th>
                                <th class="px-4 py-2 border-b">Nama Peminjam</th>
                                <th class="px-4 py-2 border-b">Ruangan</th>
                                <th class="px-4 py-2 border-b">Tanggal Pinjam</th>
                                <th class="px-4 py-2 border-b">Tanggal Kembali</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($report as $i => $r): ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['nama_lengkap']); ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['nama_ruangan']); ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['tanggal_pinjam']); ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['tanggal_kembali']); ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['status']); ?></td>
                                <td class="px-4 py-2 border-b">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="hapus_peminjaman_id" value="<?php echo $r['peminjaman_id']; ?>">
                                        <button type="submit" class="text-red-500 hover:underline" onclick="return confirm('Hapus data ini dari report?')">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($report)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-gray-400">Tidak ada data report.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Edit Ruangan -->
    <div id="modalEditRuangan" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50" style="display:none;">
      <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md relative">
        <button onclick="closeModalEdit()" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        <h3 class="text-lg font-semibold mb-4">Edit Ruangan</h3>
        <form id="formEditRuangan" method="post">
          <input type="hidden" name="edit_ruangan_id" id="edit_ruangan_id">
          <div class="mb-3">
            <label class="block mb-1">Nama Ruangan</label>
            <input type="text" name="edit_nama_ruangan" id="edit_nama_ruangan" class="border rounded px-3 py-2 w-full" required>
          </div>
          <div class="mb-3">
            <label class="block mb-1">Lokasi</label>
            <input type="text" name="edit_lokasi" id="edit_lokasi" class="border rounded px-3 py-2 w-full" required>
          </div>
          <div class="mb-3">
            <label class="block mb-1">Kapasitas</label>
            <input type="number" name="edit_kapasitas" id="edit_kapasitas" class="border rounded px-3 py-2 w-full" min="1" required>
          </div>
          <div class="mb-3">
            <label class="block mb-1">Harga (Rp)</label>
            <input type="number" name="edit_harga" id="edit_harga" class="border rounded px-3 py-2 w-full" min="0" required>
          </div>
          <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 rounded font-semibold w-full">Simpan Perubahan</button>
        </form>
      </div>
    </div>
    <!-- Modal Konfirmasi Logout -->
    <div id="modalLogout" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50" style="display:none;">
      <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-sm text-center relative">
        <h3 class="text-lg font-semibold mb-4">Konfirmasi Logout</h3>
        <p class="mb-6">Apakah Anda yakin ingin keluar?</p>
        <div class="flex justify-center gap-4">
          <button onclick="confirmLogout()" class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded font-semibold">Keluar</button>
          <button onclick="closeModalLogout()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-5 py-2 rounded font-semibold">Batal</button>
        </div>
        <button onclick="closeModalLogout()" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        </div>
    </div>
    <!-- Modal Hapus Ruangan -->
    <div id="modalHapusRuangan" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50" style="display:none;">
      <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-sm text-center relative">
        <h3 class="text-lg font-semibold mb-4">Konfirmasi Hapus</h3>
        <p class="mb-6">Apakah Anda yakin ingin menghapus kamar ini?</p>
        <form id="formHapusRuangan" method="post">
          <input type="hidden" name="hapus_ruangan_id" id="hapus_ruangan_id_modal">
          <div class="flex justify-center gap-4">
            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded font-semibold">Ya, Hapus</button>
            <button type="button" onclick="closeModalHapusRuangan()" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-5 py-2 rounded font-semibold">Batal</button>
          </div>
        </form>
        <button onclick="closeModalHapusRuangan()" class="absolute top-2 right-2 text-gray-400 hover:text-red-500 text-2xl">&times;</button>
        </div>
    </div>
    <script>
    function showMenu(menu) {
        const menus = ['dashboard','ruangan','approval_peminjaman','approval_pengembalian','report'];
        menus.forEach(function(m) {
            document.getElementById('menu-' + m).style.display = (m === menu) ? '' : 'none';
        });
        // Highlight menu aktif
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.classList.remove('active','text-sky-600');
        });
        const idx = menus.indexOf(menu);
        if(idx >= 0) {
            document.querySelectorAll('.sidebar-link')[idx].classList.add('active','text-sky-600');
        }
        // Simpan menu aktif ke localStorage
        localStorage.setItem('menuAktif', menu);
    }
// Saat halaman load, cek localStorage
const lastMenu = localStorage.getItem('menuAktif');
const hasPendingApproval = <?php echo !empty($approval) ? 'true' : 'false'; ?>;
if (lastMenu && ['dashboard','ruangan','approval_peminjaman','approval_pengembalian','report'].includes(lastMenu)) {
    showMenu(lastMenu);
} else if (hasPendingApproval) {
    showMenu('approval_peminjaman');
} else {
    showMenu('dashboard');
}
    // Chart.js diagram batang untuk dashboard (total, bukan per bulan)
    const labels = ["Total"];
    const dataPeminjaman = [<?php echo $total_peminjaman; ?>];
    const dataSelesai = [<?php echo $total_selesai; ?>];
    const dataDitolak = [<?php echo $total_ditolak; ?>];
    const ctx = document.getElementById('rekapChart').getContext('2d');
    const rekapChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Penyewaan',
                    backgroundColor: '#22c55e',
                    data: dataPeminjaman,
                },
                {
                    label: 'Selesai',
                    backgroundColor: '#3b82f6',
                    data: dataSelesai,
                },
                {
                    label: 'Ditolak',
                    backgroundColor: '#ef4444',
                    data: dataDitolak,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    function showModalEdit(id, nama, lokasi, kapasitas, harga) {
      document.getElementById('modalEditRuangan').style.display = 'flex';
      document.getElementById('edit_ruangan_id').value = id;
      document.getElementById('edit_nama_ruangan').value = nama;
      document.getElementById('edit_lokasi').value = lokasi;
      document.getElementById('edit_kapasitas').value = kapasitas;
      document.getElementById('edit_harga').value = harga;
    }
    function closeModalEdit() {
      document.getElementById('modalEditRuangan').style.display = 'none';
    }
    // Tutup modal jika klik di luar box
    window.onclick = function(event) {
      var modal = document.getElementById('modalEditRuangan');
      if (event.target == modal) {
        closeModalEdit();
      }
    }
    function showModalLogout() {
      document.getElementById('modalLogout').style.display = 'flex';
    }
    function closeModalLogout() {
      document.getElementById('modalLogout').style.display = 'none';
    }
    function confirmLogout() {
      window.location.href = 'logout.php';
    }
    function updateClock() {
      const now = new Date();
      let h = now.getHours().toString().padStart(2,'0');
      let m = now.getMinutes().toString().padStart(2,'0');
      let s = now.getSeconds().toString().padStart(2,'0');
      document.getElementById('clock').textContent = h+":"+m+":"+s;
    }
    setInterval(updateClock, 1000);
    updateClock();
    function toggleKeterangan(id, btn) {
        var el = document.getElementById(id);
        if (el.style.display === 'none' || el.style.display === '') {
            el.style.display = 'block';
            btn.querySelector('i').classList.remove('fa-chevron-down');
            btn.querySelector('i').classList.add('fa-chevron-up');
        } else {
            el.style.display = 'none';
            btn.querySelector('i').classList.remove('fa-chevron-up');
            btn.querySelector('i').classList.add('fa-chevron-down');
        }
    }
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
        } else {
            sidebar.classList.add('open');
            overlay.style.display = 'block';
        }
    }
    function exportReportPDF() {
        const { jsPDF } = window.jspdf;
        var doc = new jsPDF('l', 'pt', 'A4');
        doc.setFontSize(16);
        doc.text("Laporan Peminjaman & Pengembalian", 40, 40);
        var headers = [];
        document.querySelectorAll("#report-table thead tr th").forEach(th => {
            headers.push(th.innerText);
        });
        var data = [];
        document.querySelectorAll("#report-table tbody tr").forEach(tr => {
            var row = [];
            tr.querySelectorAll("td").forEach(td => {
                row.push(td.innerText);
            });
            data.push(row);
        });
        doc.autoTable({
            head: [headers],
            body: data,
            startY: 60,
            styles: { fontSize: 10, cellPadding: 4 },
            headStyles: { fillColor: [34, 197, 94] },
            margin: { left: 40, right: 40 }
        });
        doc.save("report-peminjaman.pdf");
    }
    function exportReportExcel() {
        var table = document.getElementById('report-table');
        var wb = XLSX.utils.table_to_book(table, {sheet:"Report"});
        XLSX.writeFile(wb, "report-peminjaman.xlsx");
    }
    function showModalHapusRuangan(id) {
      document.getElementById('modalHapusRuangan').style.display = 'flex';
      document.getElementById('hapus_ruangan_id_modal').value = id;
    }
    function closeModalHapusRuangan() {
      document.getElementById('modalHapusRuangan').style.display = 'none';
    }
    </script>
    <script>
    // Format input harga dengan titik ribuan
    function formatRupiah(angka, prefix) {
        var number_string = angka.replace(/[^,\d]/g, '').toString(),
            split = number_string.split(','),
            sisa = split[0].length % 3,
            rupiah = split[0].substr(0, sisa),
            ribuan = split[0].substr(sisa).match(/\d{3}/gi);
        if (ribuan) {
            var separator = sisa ? '.' : '';
            rupiah += separator + ribuan.join('.');
        }
        rupiah = split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
        return prefix == undefined ? rupiah : (rupiah ? rupiah : '');
    }

    document.addEventListener('DOMContentLoaded', function() {
        var hargaInput = document.querySelector('input[name="harga"]');
        var editHargaInput = document.querySelector('input[name="edit_harga"]');

        if (hargaInput) {
            hargaInput.addEventListener('input', function(e) {
                this.value = formatRupiah(this.value);
            });
        }
        if (editHargaInput) {
            editHargaInput.addEventListener('input', function(e) {
                this.value = formatRupiah(this.value);
            });
        }
    });
    </script>
    <!-- Tambahkan library SheetJS sebelum </body> jika belum ada -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <?php if (!empty($hapus_sukses)): ?>
        <div class="mb-4 text-green-600 font-semibold"><?php echo $hapus_sukses; ?></div>
    <?php endif; ?>
    <?php if (!empty($hapus_error)): ?>
        <div class="mb-4 text-red-600 font-semibold"><?php echo $hapus_error; ?></div>
    <?php endif; ?>
</body>
</html>
