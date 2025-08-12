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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["ajukan_peminjaman"])) {
    $ruangan_id = $_POST["ruangan_id"];
    $tanggal = $_POST["tanggal"];
    $waktu_mulai = $_POST["waktu_mulai"];
    $waktu_selesai = $_POST["waktu_selesai"];
    $keterangan = trim($_POST["keterangan"]);
    if ($keterangan == "") {
        $peminjaman_error = "Keterangan harus diisi.";
    } else {
        $status = "Menunggu";
        // Hitung durasi pinjam dalam jam
        $durasi = (strtotime($waktu_selesai) - strtotime($waktu_mulai)) / 3600;
        if ($durasi <= 0) $durasi = 1; // minimal 1 jam
        $sql = "INSERT INTO peminjaman_ruangan (user_id, ruangan_id, tanggal, waktu_mulai, waktu_selesai, keterangan, status, Durasi_pinjam) VALUES (:user_id, :ruangan_id, :tanggal, :waktu_mulai, :waktu_selesai, :keterangan, :status, :durasi)";
        if($stmt = $pdo->prepare($sql)){
            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":ruangan_id", $ruangan_id);
            $stmt->bindParam(":tanggal", $tanggal);
            $stmt->bindParam(":waktu_mulai", $waktu_mulai);
            $stmt->bindParam(":waktu_selesai", $waktu_selesai);
            $stmt->bindParam(":keterangan", $keterangan);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":durasi", $durasi);
            if($stmt->execute()){
                $peminjaman_success = "Pengajuan peminjaman berhasil, menunggu approval admin.";
            } else {
                $peminjaman_error = "Gagal mengajukan peminjaman.";
            }
            unset($stmt);
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
// Ambil data ruangan
$ruangan = [];
$sql = "SELECT * FROM ruangan";
foreach($pdo->query($sql) as $row) {
    $ruangan[] = $row;
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
$sql = "SELECT p.peminjaman_id, r.nama_ruangan, p.tanggal, p.waktu_mulai, p.waktu_selesai FROM peminjaman_ruangan p JOIN ruangan r ON p.ruangan_id = r.ruangan_id WHERE p.user_id = :user_id AND p.status = 'Disetujui' ORDER BY p.tanggal ASC";
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
// Cek apakah ada peminjaman aktif yang sudah lewat waktu selesai (status Disetujui, tanggal = hari ini, waktu_selesai < waktu sekarang)
$show_kembalikan_popup = false;
$kembalikan_id = null;
$kembalikan_ruangan = '';
$kembalikan_tanggal = '';
$kembalikan_waktu = '';
$now_date = date('Y-m-d');
$now_time = date('H:i');
foreach($sedang_dipinjam_full as $r) {
    if ($r['status'] === 'Disetujui' && $r['tanggal'] === $now_date && $r['waktu_selesai'] < $now_time) {
        $show_kembalikan_popup = true;
        $kembalikan_id = $r['peminjaman_id'];
        $kembalikan_ruangan = $r['nama_ruangan'];
        $kembalikan_tanggal = $r['tanggal'];
        $kembalikan_waktu = $r['waktu_mulai'] . ' - ' . $r['waktu_selesai'];
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
            'waktu_mulai' => $r['waktu_mulai'],
            'waktu_selesai' => $r['waktu_selesai']
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
        @media (max-width: 1024px) {
            .sidebar { position: fixed; left: -100%; top: 0; z-index: 50; width: 70%; transition: left 0.3s; height: 100vh; }
            .sidebar.open { left: 0; }
            .sidebar-overlay { display: block; }
        }
        @media (min-width: 1024px) {
            .sidebar-overlay { display: none !important; }
            .sidebar { position: static !important; left: 0 !important; width: 16rem; height: auto; display: block !important; }
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar w-64 bg-white shadow-md min-h-screen">
            <div class="p-4">
                <h1 class="text-2xl font-bold text-green-700 mb-6">Hallo, <?php echo htmlspecialchars($_SESSION["nama_lengkap"] ?? $_SESSION["username"]); ?> 👋🏻😁</h1>
                <ul class="space-y-2">
                    <li><a href="#" class="sidebar-link active block px-4 py-2.5 rounded transition text-green-600" onclick="showMenu('dashboard')"><i class="fas fa-tachometer-alt w-5 mr-2 inline-block"></i>Dashboard</a></li>
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition text-gray-600" onclick="showMenu('peminjaman')"><i class="fas fa-door-open w-5 mr-2 inline-block"></i>Peminjaman</a></li>
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition text-gray-600" onclick="showMenu('pengembalian')"><i class="fas fa-undo w-5 mr-2 inline-block"></i>Pengembalian</a></li>
                    <li><a href="#" class="sidebar-link block px-4 py-2.5 rounded transition text-gray-600" onclick="showMenu('riwayat')"><i class="fas fa-history w-5 mr-2 inline-block"></i>Riwayat</a></li>
                    <li><a href="#" onclick="showModalLogout();return false;" class="block px-4 py-2.5 rounded transition text-gray-600 hover:text-green-600"><i class="fas fa-sign-out-alt w-5 mr-2 inline-block"></i>Keluar</a></li>
                </ul>
            </div>
        </div>
        <!-- Main content -->
        <div class="flex-1 p-2 sm:p-4 md:p-8">
            <div class="flex justify-between items-center mb-2">
                <button class="lg:hidden mr-2 focus:outline-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-2xl text-green-600"></i>
                </button>
                <span id="clock" style="font-size:1.1em;font-weight:bold;padding:6px 18px;background:#fff;color:#166534;border-radius:18px;border:1.5px solid #22c55e;box-shadow:0 2px 8px rgba(34,197,94,0.06);letter-spacing:1px;"></span>
            </div>
            <div id="menu-dashboard">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Selamat datang, <?php echo htmlspecialchars($_SESSION["nama_lengkap"] ?? $_SESSION["username"]); ?>!</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 border-l-4 border-green-500">
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
                    <h3 class="text-lg font-semibold mb-4 text-green-700">Peminjaman Aktif/Menunggu</h3>
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
                                    <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['waktu_mulai']).' - '.htmlspecialchars($r['waktu_selesai']); ?></td>
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
            <div id="menu-peminjaman" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Ajukan Peminjaman Ruangan</h2>
                <?php if($peminjaman_success) echo '<div class="mb-4 text-green-600">'.$peminjaman_success.'</div>'; ?>
                <?php if($peminjaman_error) echo '<div class="mb-4 text-red-600">'.$peminjaman_error.'</div>'; ?>
                <form method="post" class="bg-white rounded-lg shadow-sm p-6 max-w-lg">
                    <input type="hidden" name="ajukan_peminjaman" value="1" />
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Ruangan</label>
                        <select name="ruangan_id" class="w-full border rounded px-3 py-2" required>
                            <option value="">Pilih Ruangan</option>
                            <?php foreach($ruangan as $r): ?>
                                <option value="<?php echo $r['ruangan_id']; ?>"><?php echo htmlspecialchars($r['nama_ruangan']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Tanggal</label>
                        <input type="date" name="tanggal" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Waktu Mulai</label>
                        <input type="time" name="waktu_mulai" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Waktu Selesai</label>
                        <input type="time" name="waktu_selesai" class="w-full border rounded px-3 py-2" required />
                    </div>
                    <div class="mb-4">
                        <label class="block mb-1 font-semibold">Keterangan</label>
                        <textarea name="keterangan" class="w-full border rounded px-3 py-2" required></textarea>
                    </div>
                    <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded font-semibold">Ajukan</button>
                </form>
            </div>
            <div id="menu-pengembalian" style="display:none;">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Pengembalian Ruangan</h2>
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
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($p['waktu_mulai']).' - '.htmlspecialchars($p['waktu_selesai']); ?></td>
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
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Riwayat Peminjaman</h2>
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
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($r['waktu_mulai']).' - '.htmlspecialchars($r['waktu_selesai']); ?></td>
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
            document.getElementById('menu-' + m).style.display = (m === menu) ? '' : 'none';
        });
        // Highlight menu aktif
        document.querySelectorAll('.sidebar-link').forEach(function(link) {
            link.classList.remove('active','text-green-600');
        });
        const idx = menus.indexOf(menu);
        if(idx >= 0) {
            document.querySelectorAll('.sidebar-link')[idx].classList.add('active','text-green-600');
        }
    }
    // Default menu
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
        if (p.tanggal === today && p.waktu_selesai < jamMenit) {
          // Tampilkan popup
          document.getElementById('popupKembalikan').style.display = 'flex';
          document.getElementById('kembalikan_id').value = p.id;
          document.getElementById('popupKembalikanMsg').innerHTML = `Waktu peminjaman ruangan <b>${p.ruangan}</b> (${p.tanggal}, ${p.waktu_mulai} - ${p.waktu_selesai}) sudah habis.<br>Silakan lakukan pengembalian sekarang.`;
          return;
        }
      }
      document.getElementById('popupKembalikan').style.display = 'none';
    }
    setInterval(cekPeminjamanHabis, 1000);
    window.onload = cekPeminjamanHabis;
    function updateClock() {
      const now = new Date();
      let h = now.getHours().toString().padStart(2,'0');
      let m = now.getMinutes().toString().padStart(2,'0');
      let s = now.getSeconds().toString().padStart(2,'0');
      document.getElementById('clock').textContent = h+":"+m+":"+s;
    }
    setInterval(updateClock, 1000);
    updateClock();
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
    </script>
</body>
</html> 