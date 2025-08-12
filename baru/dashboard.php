<?php
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== 'user') {
    http_response_code(403);
    echo '<div style="color:red;font-weight:bold;text-align:center;margin-top:40px;font-size:1.5em;">Akses ditolak! Anda tidak berhak mengakses halaman ini.</div>';
    exit;
}
require_once "config.php";
$user_id = $_SESSION["user_id"];

// Proses form peminjaman
$peminjaman_success = $peminjaman_error = "";
if (isset($_POST['action']) && $_POST['action'] === 'get_booked_seats') {
    $tanggal = $_POST['tanggal'] ?? '';
    if (!$tanggal) {
        echo json_encode(['booked_seats' => [], 'waiting_seats' => []]);
        exit;
    }
    // Query booked (approved) rooms on the selected date
    $bookedSql = "SELECT DISTINCT ruangan_id FROM peminjaman_ruangan WHERE status IN ('Disetujui') AND tanggal = :tanggal";
    $stmt = $pdo->prepare($bookedSql);
    $stmt->execute([':tanggal' => $tanggal]);
    $bookedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Query waiting approval rooms on the selected date
    $waitingSql = "SELECT DISTINCT ruangan_id FROM peminjaman_ruangan WHERE status = 'Menunggu' AND tanggal = :tanggal";
    $stmt = $pdo->prepare($waitingSql);
    $stmt->execute([':tanggal' => $tanggal]);
    $waitingSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

    header('Content-Type: application/json');
    echo json_encode(['booked_seats' => $bookedSeats, 'waiting_seats' => $waitingSeats]);
    exit;
}

$peminjaman_success = $peminjaman_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajukan_peminjaman"])) {
    // Validasi field yang diperlukan
    if (empty($_POST["ruangan_id"]) || empty($_POST["tanggal"]) || empty($_POST["tanggal_checkout"]) || empty($_POST["waktu_mulai"])) {
        $peminjaman_error = "Data peminjaman tidak lengkap. Silakan isi semua field yang diperlukan.";
    } else {
        $ruangan_ids = $_POST["ruangan_id"];
        $tanggal = $_POST["tanggal"];
        $tanggal_checkout = $_POST["tanggal_checkout"];
        $waktu_mulai = $_POST["waktu_mulai"];
        $keterangan = trim($_POST["keterangan"]);
        $status = "Menunggu";

        // Cek apakah tanggal_checkout >= tanggal
        if ($tanggal_checkout < $tanggal) {
            $peminjaman_error = "Tanggal check-out harus sama atau setelah tanggal check-in.";
        } else {
            $all_available = true;
            foreach ($ruangan_ids as $ruangan_id) {
                // Cek apakah ruangan sedang dipakai pada rentang tanggal yang diminta
                $check_sql = "SELECT COUNT(*) FROM peminjaman_ruangan WHERE ruangan_id = :ruangan_id AND status IN ('Menunggu', 'Disetujui', 'Menunggu Pengembalian') AND NOT (STR_TO_DATE(tanggal, '%Y-%m-%d') > STR_TO_DATE(:tanggal_checkout, '%Y-%m-%d') OR STR_TO_DATE(tanggal, '%Y-%m-%d') < STR_TO_DATE(:tanggal, '%Y-%m-%d'))";
                $check_stmt = $pdo->prepare($check_sql);
                $check_stmt->execute([
                    ':ruangan_id' => $ruangan_id,
                    ':tanggal' => $tanggal,
                    ':tanggal_checkout' => $tanggal_checkout
                ]);
                $count = $check_stmt->fetchColumn();
                unset($check_stmt);
                if ($count > 0) {
                    // Ambil nama ruangan untuk pesan error
                    $roomNameSql = "SELECT nama_ruangan FROM ruangan WHERE ruangan_id = :ruangan_id";
                    $roomNameStmt = $pdo->prepare($roomNameSql);
                    $roomNameStmt->execute([':ruangan_id' => $ruangan_id]);
                    $roomName = $roomNameStmt->fetchColumn() ?: $ruangan_id;
                    $peminjaman_error = "Ruangan $roomName sudah dipakai pada rentang tanggal yang sama.";
                    $all_available = false;
                    break;
                }
            }
            if ($all_available) {
                // Hitung durasi pinjam dalam hari
                $durasi = (strtotime($tanggal_checkout) - strtotime($tanggal)) / 86400;
                if ($durasi <= 0) $durasi = 1; // minimal 1 hari
                $success_count = 0;
                foreach ($ruangan_ids as $ruangan_id) {
                    $sql = "INSERT INTO peminjaman_ruangan (user_id, ruangan_id, tanggal, tanggal_checkout, waktu_mulai, keterangan, status, Durasi_pinjam) VALUES (:user_id, :ruangan_id, :tanggal, :tanggal_checkout, :waktu_mulai, :keterangan, :status, :durasi)";
                    if ($stmt = $pdo->prepare($sql)) {
                        $stmt->bindParam(":user_id", $user_id);
                        $stmt->bindParam(":ruangan_id", $ruangan_id);
                        $stmt->bindParam(":tanggal", $tanggal);
                        $stmt->bindParam(":tanggal_checkout", $tanggal_checkout);
                        $stmt->bindParam(":waktu_mulai", $waktu_mulai);
                        $stmt->bindParam(":keterangan", $keterangan);
                        $stmt->bindParam(":status", $status);
                        $stmt->bindParam(":durasi", $durasi);
                        if ($stmt->execute()) {
                            $success_count++;
                        }
                    }
                }
                if ($success_count === count($ruangan_ids)) {
                    $peminjaman_success = "Pengajuan peminjaman berhasil untuk semua kamar, menunggu approval admin.";
                } else {
                    $peminjaman_error = "Gagal mengajukan peminjaman untuk beberapa kamar.";
                }
            }
        }
    
    }
}

// Proses pengembalian
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["kembalikan_id"])) {
    $id = $_POST["kembalikan_id"];
    $sql = "UPDATE peminjaman_ruangan SET status = 'Menunggu Pengembalian' WHERE peminjaman_id = :id AND user_id = :user_id";
    if($stmt = $pdo->prepare($sql)){
        $stmt->bindParam(":id", $id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        unset($stmt);
    }
}
// Ambil data ruangan yang tersedia (tidak sedang disewa pada waktu yang sama)
$ruangan = [];
$sql = "SELECT * FROM ruangan r WHERE NOT EXISTS (
  SELECT 1 FROM peminjaman_ruangan p
  WHERE p.ruangan_id = r.ruangan_id
    AND p.status IN ('Menunggu', 'Disetujui', 'Menunggu Pengembalian')
    AND p.tanggal = :tanggal
)";
if(isset($_POST['tanggal']) && isset($_POST['waktu_mulai'])) {
    $tanggal = $_POST['tanggal'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tanggal' => $tanggal
    ]);
    foreach($stmt as $row) {
        $ruangan[] = $row;
    }
} else {
    // Default: tampilkan semua jika belum pilih tanggal/waktu
    foreach($pdo->query("SELECT * FROM ruangan") as $row) {
    $ruangan[] = $row;
    }
}
// Ambil riwayat peminjaman user
$riwayat = [];
$sql = "SELECT p.*, r.nama_ruangan FROM peminjaman_ruangan p JOIN ruangan r ON p.ruangan_id = r.ruangan_id WHERE p.user_id = :user_id ORDER BY p.tanggal DESC, p.waktu_mulai DESC";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($stmt);
}
// Ambil data untuk pengembalian
$pengembalian = [];
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $pengembalian = $stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($stmt);
}
// Ambil data peminjaman user yang statusnya 'Menunggu' atau 'Disetujui'
$sedang_dipinjam_full = [];
$sql = "SELECT p.*, r.nama_ruangan FROM peminjaman_ruangan p JOIN ruangan r ON p.ruangan_id = r.ruangan_id WHERE p.user_id = :user_id AND (p.status = 'Menunggu' OR p.status = 'Disetujui') ORDER BY p.tanggal DESC, p.waktu_mulai DESC";
if($stmt = $pdo->prepare($sql)){
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $sedang_dipinjam_full = $stmt->fetchAll(PDO::FETCH_ASSOC);
    unset($stmt);
}
// Mapping hari dan bulan Indonesia
$hariList = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$bulanList = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
// Cek apakah ada peminjaman aktif yang sudah lewat waktu (status Disetujui, tanggal = hari ini)
$show_kembalikan_popup = false;
$kembalikan_id = null;
$kembalikan_ruangan = '';
$kembalikan_tanggal = '';
$kembalikan_waktu = '';
$now_date = date('Y-m-d');
$now_time = date('H:i');
foreach($sedang_dipinjam_full as $r) {
    if ($r['status'] === 'Disetujui' && $r['tanggal'] === $now_date) {
        $show_kembalikan_popup = true;
        $kembalikan_id = $r['peminjaman_id'];
        $kembalikan_ruangan = $r['nama_ruangan'];
        $kembalikan_tanggal = $r['tanggal'];
        $kembalikan_waktu = $r['waktu_mulai'];
        break;
    }
}
// Kirim data peminjaman aktif ke JS untuk pengecekan real time
$js_peminjaman_aktif = [];
foreach($sedang_dipinjam_full as $r) {
    if ($r['status'] === 'Disetujui') {
        $js_peminjaman_aktif[] = [
            'id' => $r['peminjaman_id'],
            'ruangan' => $r['nama_ruangan'],
            'tanggal' => $r['tanggal'],
            'waktu_mulai' => $r['waktu_mulai']
        ];
    }
}
// Hitung total ditolak untuk user
$stmt = $pdo->prepare("SELECT COUNT(*) FROM peminjaman_ruangan WHERE user_id = :user_id AND status = 'Ditolak'");
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();
$total_ditolak = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .sidebar .space-y-2 > * + * {
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
        /* Checkbox improvements */
        .seat-checkbox {
            width: 18px;
            height: 18px;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@600&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar w-64 bg-white shadow-md min-h-screen">
            <div class="p-4">
                <h1 class="text-2xl font-bold text-sky-600 mb-6">Hallo, <?php echo htmlspecialchars($_SESSION["nama_lengkap"] ?? $_SESSION["username"]); ?> 👋🏻😁</h1>
                <ul class="space-y-2">
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition" onclick="showMenu('dashboard');return false;"><i class="fas fa-tachometer-alt w-5 mr-2 inline-block"></i><span class="sidebar-link-text">Dashboard</span></a></li>
<li><a href="#" class="sidebar-link active block px-4 py-2.5 rounded transition text-sky-600" onclick="showMenu('peminjaman');return false;"><i class="fas fa-door-open w-5 mr-2 inline-block"></i><span class="sidebar-link-text">Peminjaman</span></a></li>
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition" onclick="showMenu('pengembalian');return false;"><i class="fas fa-undo w-5 mr-2 inline-block"></i><span class="sidebar-link-text">Pengembalian</span></a></li>
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition" onclick="showMenu('riwayat');return false;"><i class="fas fa-history w-5 mr-2 inline-block"></i><span class="sidebar-link-text">Riwayat</span></a></li>
                </ul>
                <div style="flex:1"></div>
                <ul class="space-y-2" style="margin-top:32px;">
                    <li><a href="#" onclick="showModalLogout();return false;" class="sidebar-link block px-4 py-2.5 rounded transition"><i class="fas fa-sign-out-alt w-5 mr-2 inline-block"></i><span class="sidebar-link-text">Keluar</span></a></li>
                </ul>
            </div>
        </div>
        <!-- Main content -->
        <div class="main-content">
            <div id="menu-dashboard">
            <div class="flex justify-between items-center mb-2">
                <button class="lg:hidden mr-2 focus:outline-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-2xl text-sky-600"></i>
                </button>
                <span id="clock" class="digital-clock"></span>
            </div>
            <h2 class="text-2xl font-semibold text-sky-700 mb-6">Selamat datang, <?php echo htmlspecialchars($_SESSION["nama_lengkap"] ?? $_SESSION["username"]); ?>!</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-sky-500">
                    <p class="text-sm font-medium text-gray-500">Total Peminjaman</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count($riwayat); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-yellow-500">
                    <p class="text-sm font-medium text-gray-500">Menunggu Approval</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count(array_filter($riwayat, function($r){return $r['status']==='Menunggu';})); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-blue-500">
                    <p class="text-sm font-medium text-gray-500">Sudah Dikembalikan</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo count(array_filter($riwayat, function($r){return $r['status']==='Selesai';})); ?></p>
                </div>
                <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-red-500">
                    <p class="text-sm font-medium text-gray-500">Total Ditolak</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $total_ditolak; ?></p>
                </div>
            </div>
            <?php if(!empty($sedang_dipinjam_full)): ?>
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4 text-sky-700">Peminjaman Aktif/Menunggu</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">No</th>
                                <th class="px-4 py-2 border-b">Ruangan</th>
                                <th class="px-4 py-2 border-b">Tanggal</th>
                                <th class="px-4 py-2 border-b">Waktu</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($sedang_dipinjam_full as $i => $r): ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['nama_ruangan']); ?></td>
                                <td class="px-4 py-2 border-b"><?php
                                    $time = strtotime($r['tanggal']);
                                    $hari = $hariList[date('l', $time)];
                                    $tgl = date('d', $time);
                                    $bulan = $bulanList[(int)date('m', $time)];
                                    $tahun = date('Y', $time);
                                    echo $hari . ', ' . $tgl . ' ' . $bulan . ' ' . $tahun;
                                ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['waktu_mulai']) . ' - ' . (isset($r['waktu_selesai']) ? htmlspecialchars($r['waktu_selesai']) : ''); ?></td>
                                <td class="px-4 py-2 border-b">
                                    <?php
                                    if($r['status']==='Menunggu'):
                                        echo '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Menunggu</span>';
                                    elseif($r['status']==='Disetujui'):
                                        echo '<span class="bg-green-100 text-green-700 px-2 py-1 rounded">Sedang Dipinjam</span>';
                                    endif;
                                    ?>
                                </td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['keterangan']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            </div>
<div id="menu-peminjaman">
                <h2 class="text-2xl font-semibold text-sky-700 mb-6">Ajukan Penyewaan Kamar dan Villa</h2>
                <?php if($peminjaman_success) echo '<div class="mb-4 text-sky-600">'.$peminjaman_success.'</div>'; ?>
                <?php if($peminjaman_error) echo '<div class="mb-4 text-red-600">'.$peminjaman_error.'</div>'; ?>
                    <form method="post" class="bg-white rounded-lg shadow-sm p-6 max-w-lg" id="peminjamanForm">
                        <input type="hidden" name="ajukan_peminjaman" value="1" />
                    <div class="mb-4 text-center">
                        <label class="block mb-1 font-semibold">Pilih Kamar</label>
                        <div id="seat-container" class="flex flex-wrap gap-4 justify-center mx-auto">
                                <?php
                                $totalSeats = count($ruangan);
                                $cols = 2; // 2 columns as in example image
                                $rows = ceil($totalSeats / $cols);
                                $seatIndex = 0;
                                for ($r = 0; $r < $rows; $r++) {
                                    echo '<div class="flex gap-4 mb-2 justify-center">';
                                    for ($c = 1; $c <= $cols; $c++) {
                                        if ($seatIndex < $totalSeats) {
                                            $room = $ruangan[$seatIndex];
                                echo '<div class="flex items-center bg-blue-200 rounded px-4 py-2 cursor-pointer w-48">';
                                echo '<input type="checkbox" name="ruangan_id[]" value="' . htmlspecialchars($room['ruangan_id']) . '" id="seat-' . $room['ruangan_id'] . '" class="seat-checkbox mr-3 cursor-pointer">';
                                echo '<label for="seat-' . $room['ruangan_id'] . '" class="cursor-pointer select-none">' . htmlspecialchars($room['nama_ruangan']) . '</label>';
                                echo '</div>';
                                            $seatIndex++;
                                        }
                                    }
                                    echo '</div>';
                                }
                                ?>
                        </div>
                        <div id="seat-error" class="text-red-600 mt-2 hidden">Silakan pilih minimal 1 kamar!</div>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Tanggal</label>
                        <input type="date" name="tanggal" id="tanggal" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Waktu check in</label>
                        <input type="time" name="waktu_mulai" id="waktu_mulai" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Tanggal Check-out</label>
                        <input type="date" name="tanggal_checkout" id="tanggal_checkout" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Keterangan</label>
                        <textarea name="keterangan" class="w-full border rounded px-3 py-2"></textarea>
                    </div>
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded font-semibold" id="submitBtn">Ajukan</button>
                </form>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const seatCheckboxes = document.querySelectorAll('input[name="ruangan_id[]"]');
                    const seatError = document.getElementById('seat-error');
                    const waktuMulaiInput = document.getElementById('waktu_mulai');
                    const waktuSelesaiInput = document.getElementById('waktu_selesai');
                    const seatContainer = document.getElementById('seat-container');
                    const tanggalInput = document.getElementById('tanggal');

                    function resetSeats() {
                        seatCheckboxes.forEach(checkbox => {
                            checkbox.disabled = false;
                            checkbox.parentElement.classList.remove('bg-red-500', 'bg-yellow-400', 'bg-blue-600', 'text-white');
                            checkbox.parentElement.classList.add('bg-gray-300');
                            checkbox.checked = false;
                        });
                    }

                    function fetchBookedSeats() {
                        const tanggal = tanggalInput.value;
                        if (!tanggal) {
                            resetSeats();
                            return;
                        }
                        fetch('dashboard.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'get_booked_seats',
                                tanggal: tanggal
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            resetSeats();
                            data.booked_seats.forEach(id => {
                                const checkbox = document.querySelector('input[value="'+id+'"]');
                                if (checkbox) {
                                    checkbox.disabled = true;
                                    checkbox.parentElement.classList.remove('bg-gray-300', 'bg-yellow-400');
                                    checkbox.parentElement.classList.add('bg-red-500', 'text-white');
                                    checkbox.checked = false;
                                }
                            });
                            data.waiting_seats.forEach(id => {
                                const checkbox = document.querySelector('input[value="'+id+'"]');
                                if (checkbox && !checkbox.parentElement.classList.contains('bg-red-500')) {
                                    checkbox.disabled = true;
                                    checkbox.parentElement.classList.remove('bg-gray-300');
                                    checkbox.parentElement.classList.add('bg-yellow-400', 'text-white');
                                    checkbox.checked = false;
                                }
                            });
                        })
                        .catch(err => {
                            console.error('Error fetching booked seats:', err);
                        });
                    }

                    tanggalInput.addEventListener('change', function() {
                        resetSeats();
                        fetchBookedSeats();
                    });

                    seatCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', () => {
                            seatError.classList.add('hidden');
                            if (checkbox.checked) {
                                checkbox.parentElement.classList.remove('bg-gray-300');
                                checkbox.parentElement.classList.add('bg-blue-600', 'text-white');
                            } else {
                                checkbox.parentElement.classList.remove('bg-blue-600', 'text-white');
                                checkbox.parentElement.classList.add('bg-gray-300');
                            }
                            
                            // Batasi maksimal 5 kamar
                            const checkedSeats = document.querySelectorAll('input[name="ruangan_id[]"]:checked');
                            if (checkedSeats.length > 5) {
                                checkbox.checked = false;
                                checkbox.parentElement.classList.remove('bg-blue-600', 'text-white');
                                checkbox.parentElement.classList.add('bg-gray-300');
                                alert('Maksimal hanya bisa memilih 5 kamar!');
                            }
                        });
                    });

                    const peminjamanForm = document.getElementById('peminjamanForm');
                    peminjamanForm.addEventListener('submit', function(event) {
                        const anyChecked = Array.from(seatCheckboxes).some(cb => cb.checked);
                        if (!anyChecked) {
                            event.preventDefault();
                            seatError.classList.remove('hidden');
                        }
                    });

                    // Initial reset
                    resetSeats();
                });
            </script>
            <div id="menu-pengembalian" style="display:none;">
                <h2 class="text-2xl font-semibold text-sky-700 mb-6">Pengembalian Ruangan</h2>
                <?php if(empty($pengembalian)): ?>
                    <p>Tidak ada ruangan yang perlu dikembalikan saat ini.</p>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">No</th>
                                <th class="px-4 py-2 border-b">Ruangan</th>
                                <th class="px-4 py-2 border-b">Tanggal</th>
                                <th class="px-4 py-2 border-b">Waktu</th>
                                <th class="px-4 py-2 border-b">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($pengembalian as $i => $p): ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($p['nama_ruangan']); ?></td>
                                <td class="px-4 py-2 border-b"><?php
                                    $time = strtotime($p['tanggal']);
                                    $hari = $hariList[date('l', $time)];
                                    $tgl = date('d', $time);
                                    $bulan = $bulanList[(int)date('m', $time)];
                                    $tahun = date('Y', $time);
                                    echo $hari . ', ' . $tgl . ' ' . $bulan . ' ' . $tahun;
                                ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($p['waktu_mulai']).' - '.htmlspecialchars($p['tanggal_checkout']); ?></td>
                                <td class="px-4 py-2 border-b">
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="kembalikan_id" value="<?php echo $p['peminjaman_id']; ?>">
                                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded" onclick="return confirm('Kembalikan ruangan ini?')">Kembalikan</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div id="menu-riwayat" style="display:none;">
                <h2 class="text-2xl font-semibold text-sky-700 mb-6">Riwayat Peminjaman</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white rounded shadow">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">No</th>
                                <th class="px-4 py-2 border-b">Ruangan</th>
                                <th class="px-4 py-2 border-b">Tanggal</th>
                                <th class="px-4 py-2 border-b">Waktu</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($riwayat as $i => $r): ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?php echo $i+1; ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['nama_ruangan']); ?></td>
                                <td class="px-4 py-2 border-b"><?php
                                    $time = strtotime($r['tanggal']);
                                    $hari = $hariList[date('l', $time)];
                                    $tgl = date('d', $time);
                                    $bulan = $bulanList[(int)date('m', $time)];
                                    $tahun = date('Y', $time);
                                    echo $hari . ', ' . $tgl . ' ' . $bulan . ' ' . $tahun;
                                ?></td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['waktu_mulai']) . ' - ' . (isset($r['waktu_selesai']) ? htmlspecialchars($r['waktu_selesai']) : ''); ?></td>
                                <td class="px-4 py-2 border-b">
                                    <?php
                                    if($r['status']==='Menunggu'):
                                        echo '<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Menunggu</span>';
                                    elseif($r['status']==='Selesai'):
                                        echo '<span class="bg-blue-100 text-blue-700 px-2 py-1 rounded">Selesai</span>';
                                    elseif($r['status']==='Ditolak'):
                                        echo '<span class="bg-red-100 text-red-700 px-2 py-1 rounded">Ditolak</span>';
                                    elseif($r['status']==='Disetujui'):
                                        $today = date('Y-m-d');
                                        if($r['tanggal'] >= $today) {
                                            echo '<span class="bg-green-100 text-green-700 px-2 py-1 rounded">Masih Dipakai</span>';
                                        } else {
                                            echo '<span class="bg-pink-100 text-pink-700 px-2 py-1 rounded">Belum Dikembalikan</span>';
                                        }
                                    else:
                                        echo '<span class="bg-green-100 text-green-700 px-2 py-1 rounded">'.htmlspecialchars($r['status']).'</span>';
                                    endif;
                                    ?>
                                </td>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['keterangan']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
    <?php if($show_kembalikan_popup): ?>
    <div id="popupKembalikan" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50" style="display:none;">
      <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md text-center relative">
        <h3 class="text-lg font-semibold mb-4">Waktu Peminjaman Habis</h3>
        <p class="mb-4" id="popupKembalikanMsg"></p>
        <form method="post" id="formKembalikan">
          <input type="hidden" name="kembalikan_id" id="kembalikan_id">
          <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded font-semibold">Kembalikan Sekarang</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <!-- Overlay untuk sidebar mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 sidebar-overlay" style="display:none;" onclick="toggleSidebar()"></div>
    <script>
    function showMenu(menu) {
        const menus = ['dashboard','peminjaman','pengembalian','riwayat'];
        menus.forEach(function(m) {
            const el = document.getElementById('menu-' + m);
            if(el) {
                el.style.display = (m === menu) ? '' : 'none';
            }
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
    // Saat halaman load, selalu tampilkan dashboard terlebih dahulu
    showMenu('dashboard');
    function showModalLogout() {
      document.getElementById('modalLogout').style.display = 'flex';
    }
    function closeModalLogout() {
      document.getElementById('modalLogout').style.display = 'none';
    }
    function confirmLogout() {
      window.location.href = 'logout.php';
    }
    // Data peminjaman aktif dari PHP
const peminjamanAktif = <?php echo json_encode($js_peminjaman_aktif); ?>;
function cekPeminjamanHabis() {
  const now = new Date();
  const today = now.toISOString().slice(0,10);
  const jamMenit = now.toTimeString().slice(0,5);
  for (let p of peminjamanAktif) {
    if (p.tanggal === today && p.tanggal_checkout < jamMenit) {
      document.getElementById('popupKembalikanMsg').innerText = 
        `Waktu peminjaman ruangan ${p.ruangan} telah habis. Silakan kembalikan segera!`;
      document.getElementById('kembalikan_id').value = p.id;
      document.getElementById('popupKembalikan').style.display = 'flex';
      return;
    }
  }
}

    // Panggil cekPeminjamanHabis setiap menit
    setInterval(cekPeminjamanHabis, 60000);
    
    function toggleSidebar() {
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebarOverlay');
      sidebar.classList.toggle('hidden');
      overlay.style.display = sidebar.classList.contains('hidden') ? 'none' : 'block';
    }

    // Inisialisasi saat halaman dimuat
    window.onload = function() {
      // Cek peminjaman yang habis
      cekPeminjamanHabis();
      
      // Tampilkan popup kembalikan jika ada
      <?php if($show_kembalikan_popup): ?>
      document.getElementById('popupKembalikanMsg').innerText = 
        `Waktu peminjaman ruangan ${<?php echo json_encode($kembalikan_ruangan); ?>} telah habis. Silakan kembalikan segera!`;
      document.getElementById('kembalikan_id').value = <?php echo json_encode($kembalikan_id); ?>;
      document.getElementById('popupKembalikan').style.display = 'flex';
      <?php endif; ?>
      
      // Update jam digital setiap detik
      function updateClock() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('clock').innerText = `${hours}:${minutes}:${seconds}`;
      }
      updateClock();
      setInterval(updateClock, 1000);
    };
    </script>
</body>
</html>