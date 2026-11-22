<?php
require_once 'config/db.php';
require_once 'includes/security.php';

// 1. Dapatkan senarai tahun yang ada dalam pangkalan data (lalai: tahun semasa)
$stmtTahun = $pdo->query("SELECT DISTINCT tahun FROM peruntukan_vot ORDER BY tahun DESC");
$senarai_tahun = $stmtTahun->fetchAll(PDO::FETCH_COLUMN);
$tahun_semasa = !empty($senarai_tahun) ? $senarai_tahun[0] : (int)date('Y');

// Sahkan input tahun dari URL ($_GET['tahun']) supaya hanya nombor bulat diterima
$tahun_dipilih = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT) ?: $tahun_semasa;

// 2. Tarik Ringkasan KPI Keseluruhan bagi tahun dipilih
$stmtKPI = $pdo->prepare("
    SELECT 
        COALESCE(SUM(peruntukan_bersih), 0) AS total_peruntukan,
        COALESCE(SUM(jumlah_tanggungan), 0) AS total_tanggungan,
        COALESCE(SUM(jumlah_belanja), 0) AS total_belanja,
        COALESCE(SUM(baki_peruntukan), 0) AS total_baki
    FROM view_prestasi_kewangan_vot
    WHERE tahun = :tahun
");
$stmtKPI->execute([':tahun' => $tahun_dipilih]);
$kpi = $stmtKPI->fetch();

$total_komited = $kpi['total_tanggungan'] + $kpi['total_belanja'];
$peratus_belanja = ($kpi['total_peruntukan'] > 0) ? ($kpi['total_belanja'] / $kpi['total_peruntukan']) * 100 : 0;
$peratus_komited = ($kpi['total_peruntukan'] > 0) ? ($total_komited / $kpi['total_peruntukan']) * 100 : 0;

// 3. Data Graf 1: Prestasi Mengikut Sumber Dana (Negeri P17, PETRA, KPKM, dll.)
$stmtDana = $pdo->prepare("
    SELECT 
        sumber_dana,
        COALESCE(SUM(peruntukan_bersih), 0) AS peruntukan,
        COALESCE(SUM(jumlah_tanggungan), 0) AS tanggungan,
        COALESCE(SUM(jumlah_belanja), 0) AS belanja
    FROM view_prestasi_kewangan_vot
    WHERE tahun = :tahun
    GROUP BY sumber_dana
    ORDER BY peruntukan DESC
");
$stmtDana->execute([':tahun' => $tahun_dipilih]);
$data_dana = $stmtDana->fetchAll();

// 4. Data Graf 2: Perbelanjaan & Komited Mengikut Jajahan / Bahagian
$stmtJajahan = $pdo->prepare("
    SELECT 
        j.nama AS nama_jajahan,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'TANGGUNGAN' AND t.status_baucer = 'SAH' THEN t.amaun ELSE 0 END), 0) AS tanggungan,
        COALESCE(SUM(CASE WHEN t.jenis_transaksi != 'TANGGUNGAN' AND t.status_baucer = 'SAH' THEN t.amaun ELSE 0 END), 0) AS belanja
    FROM lookup_bahagian_jajahan j
    LEFT JOIN projek p ON p.bahagian_jajahan_id = j.id
    LEFT JOIN peruntukan_vot v ON p.vot_id = v.id AND v.tahun = :tahun
    LEFT JOIN transaksi_kewangan t ON t.projek_id = p.id
    GROUP BY j.id, j.nama
    HAVING COALESCE(SUM(CASE WHEN t.status_baucer = 'SAH' THEN t.amaun ELSE 0 END), 0) > 0
    ORDER BY (
        COALESCE(SUM(CASE WHEN t.jenis_transaksi = 'TANGGUNGAN' AND t.status_baucer = 'SAH' THEN t.amaun ELSE 0 END), 0) +
        COALESCE(SUM(CASE WHEN t.jenis_transaksi != 'TANGGUNGAN' AND t.status_baucer = 'SAH' THEN t.amaun ELSE 0 END), 0)
    ) DESC
");
$stmtJajahan->execute([':tahun' => $tahun_dipilih]);
$data_jajahan = $stmtJajahan->fetchAll();

// 5. Data Graf 3: Pecahan Bilangan Projek Mengikut Status
$stmtStatus = $pdo->prepare("
    SELECT p.status_projek, COUNT(p.id) AS bilangan
    FROM projek p
    JOIN peruntukan_vot v ON p.vot_id = v.id
    WHERE v.tahun = :tahun
    GROUP BY p.status_projek
    ORDER BY bilangan DESC
");
$stmtStatus->execute([':tahun' => $tahun_dipilih]);
$data_status = $stmtStatus->fetchAll();

// 6. Data Jadual Ringkasan Vot untuk semakan terperinci bos
$stmtVotList = $pdo->prepare("
    SELECT * FROM view_prestasi_kewangan_vot 
    WHERE tahun = :tahun 
    ORDER BY sumber_dana ASC, kod_vot ASC
");
$stmtVotList->execute([':tahun' => $tahun_dipilih]);
$senarai_vot = $stmtVotList->fetchAll();

// Fungsi selamat untuk tukar PHP array ke JSON dalam tag <script>
function safe_json($data) {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Papan Pemuka Prestasi Kewangan - JPS Negeri Kelantan</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/chart.umd.min.js"></script>
</head>
<body>

<!-- Bar Navigasi Atas -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-jps shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="index.php">
            JPS NEGERI KELANTAN <span class="fw-normal fs-6 d-none d-md-inline">| Laporan Kemajuan Kewangan & Projek</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="admin.php" class="btn btn-warning btn-sm fw-semibold">Panel Key-In Staf</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Log Keluar</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-light btn-sm fw-semibold text-primary">Log Masuk Staf</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <!-- Penapis Tahun -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0 text-dark">Ringkasan Eksekutif Tahun <?= e($tahun_dipilih) ?></h4>
            <small class="text-muted">Paparan masa nyata daripada pangkalan data dalaman JPS Kelantan</small>
        </div>
        <form method="GET" action="index.php" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <label for="tahun" class="fw-semibold small text-secondary">Pilih Tahun:</label>
            <select name="tahun" id="tahun" class="form-select form-select-sm" style="width: 120px;" onchange="this.form.submit()">
                <?php if (empty($senarai_tahun)): ?>
                    <option value="<?= e($tahun_semasa) ?>"><?= e($tahun_semasa) ?></option>
                <?php else: ?>
                    <?php foreach ($senarai_tahun as $thn): ?>
                        <option value="<?= e($thn) ?>" <?= ($thn == $tahun_dipilih) ? 'selected' : '' ?>>
                            <?= e($thn) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </form>
    </div>

    <!-- Kad KPI Utama -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card kpi-blue">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Peruntukan Bersih</div>
                    <div class="fs-4 fw-bold text-dark mt-1">RM <?= number_format($kpi['total_peruntukan'], 2) ?></div>
                    <div class="small text-secondary mt-1">Waran Diterima - Waran Tarik</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card kpi-orange">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Jumlah Komited (<?= number_format($peratus_komited, 1) ?>%)</div>
                    <div class="fs-4 fw-bold text-dark mt-1">RM <?= number_format($total_komited, 2) ?></div>
                    <div class="small text-secondary mt-1">Tanggungan: RM <?= number_format($kpi['total_tanggungan'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card kpi-green">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Perbelanjaan Sebenar (<?= number_format($peratus_belanja, 1) ?>%)</div>
                    <div class="fs-4 fw-bold text-success mt-1">RM <?= number_format($kpi['total_belanja'], 2) ?></div>
                    <div class="small text-secondary mt-1">Baucer Sah Dibayar (1GFMAS)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm kpi-card kpi-grey">
                <div class="card-body">
                    <div class="text-muted small fw-semibold text-uppercase">Baki Peruntukan</div>
                    <div class="fs-4 fw-bold text-primary mt-1">RM <?= number_format($kpi['total_baki'], 2) ?></div>
                    <div class="small text-secondary mt-1">Belum Komit / Baki Semasa</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris Graf Pertama: Sumber Dana & Status Projek -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card card-jps h-100">
                <div class="card-header fw-bold py-3">
                    Prestasi Kewangan Mengikut Sumber Dana (RM)
                </div>
                <div class="card-body">
                    <canvas id="grafSumberDana" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-jps h-100">
                <div class="card-header fw-bold py-3">
                    Status Pelaksanaan Projek (Bilangan)
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="grafStatusProjek" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Baris Graf Kedua: Prestasi Jajahan & Bahagian -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card card-jps">
                <div class="card-header fw-bold py-3">
                    Prestasi Perbelanjaan & Tanggungan Mengikut Jajahan / Bahagian (RM)
                </div>
                <div class="card-body">
                    <canvas id="grafJajahan" height="95"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Jadual Perincian Vot -->
    <div class="card card-jps">
        <div class="card-header fw-bold py-3">
            Perincian Prestasi Mengikut Kod Vot & Butiran (Tahun <?= e($tahun_dipilih) ?>)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped table-jps mb-0 align-middle small">
                    <thead class="table-light">
                        <tr>
                            <th>Sumber Dana</th>
                            <th>Kod Vot</th>
                            <th>Nama Butiran</th>
                            <th class="text-end">Peruntukan (RM)</th>
                            <th class="text-end">Tanggungan (RM)</th>
                            <th class="text-end">Belanja (RM)</th>
                            <th class="text-end">Baki (RM)</th>
                            <th class="text-center">% Belanja</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($senarai_vot)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Tiada rekod peruntukan bagi tahun <?= e($tahun_dipilih) ?>.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($senarai_vot as $v): 
                                $pct = ($v['peruntukan_bersih'] > 0) ? ($v['jumlah_belanja'] / $v['peruntukan_bersih']) * 100 : 0;
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($v['sumber_dana']) ?></span></td>
                                <td class="fw-semibold"><?= e($v['kod_vot']) ?></td>
                                <td><?= e($v['nama_butiran']) ?></td>
                                <td class="text-end"><?= number_format($v['peruntukan_bersih'], 2) ?></td>
                                <td class="text-end text-warning-emphasis"><?= number_format($v['jumlah_tanggungan'], 2) ?></td>
                                <td class="text-end text-success fw-semibold"><?= number_format($v['jumlah_belanja'], 2) ?></td>
                                <td class="text-end text-primary fw-semibold"><?= number_format($v['baki_peruntukan'], 2) ?></td>
                                <td class="text-center">
                                    <div class="progress progress-jps">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, round($pct)) ?>%;">
                                            <?= number_format($pct, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Data selamat dari PHP
const dataDana = <?= safe_json($data_dana) ?>;
const dataJajahan = <?= safe_json($data_jajahan) ?>;
const dataStatus = <?= safe_json($data_status) ?>;

// Format mata wang RM untuk tooltip graf
const formatRM = (val) => 'RM ' + Number(val).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// 1. GRAF SUMBER DANA (Bar Chart)
new Chart(document.getElementById('grafSumberDana'), {
    type: 'bar',
    data: {
        labels: dataDana.map(d => d.sumber_dana),
        datasets: [
            {
                label: 'Peruntukan Bersih',
                data: dataDana.map(d => d.peruntukan),
                backgroundColor: '#0d6efd'
            },
            {
                label: 'Tanggungan',
                data: dataDana.map(d => d.tanggungan),
                backgroundColor: '#fd7e14'
            },
            {
                label: 'Perbelanjaan Sebenar',
                data: dataDana.map(d => d.belanja),
                backgroundColor: '#198754'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + formatRM(ctx.raw) } }
        },
        scales: { y: { beginAtZero: true } }
    }
});

// 2. GRAF STATUS PROJEK (Doughnut Chart)
new Chart(document.getElementById('grafStatusProjek'), {
    type: 'doughnut',
    data: {
        labels: dataStatus.map(s => s.status_projek),
        datasets: [{
            data: dataStatus.map(s => s.bilangan),
            backgroundColor: ['#6c757d', '#0dcaf0', '#ffc107', '#0d6efd', '#fd7e14', '#198754', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// 3. GRAF JAJAHAN / BAHAGIAN (Stacked Bar Chart)
new Chart(document.getElementById('grafJajahan'), {
    type: 'bar',
    data: {
        labels: dataJajahan.map(j => j.nama_jajahan),
        datasets: [
            {
                label: 'Perbelanjaan Sebenar (RM)',
                data: dataJajahan.map(j => j.belanja),
                backgroundColor: '#198754'
            },
            {
                label: 'Tanggungan / Dalam Proses (RM)',
                data: dataJajahan.map(j => j.tanggungan),
                backgroundColor: '#fd7e14'
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + formatRM(ctx.raw) } }
        },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
        }
    }
});
</script>

</body>
</html>
