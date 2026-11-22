<?php
require_once 'config/db.php';
require_once 'includes/security.php';

// Wajib log masuk untuk akses halaman ini
require_login();

$user_id = $_SESSION['user_id'];
$nama_staf = $_SESSION['nama_penuh'];
$mesej_berjaya = '';
$mesej_ralat = '';

// ============================================================================
// PROSES HANTARAN BORANG (POST REQUESTS)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $mesej_ralat = 'Ralat keselamatan (CSRF Token tidak sah). Sila muat semula halaman.';
    } else {
        $tindakan = $_POST['tindakan'] ?? '';

        try {
            // 1A. TAMBAH PERUNTUKAN / VOT BAHARU
            if ($tindakan === 'tambah_vot') {
                $tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
                $sumber_dana = trim($_POST['sumber_dana'] ?? '');
                $kod_vot = strtoupper(trim($_POST['kod_vot'] ?? ''));
                $nama_butiran = trim($_POST['nama_butiran'] ?? '');
                $waran_diterima = (float)($_POST['waran_diterima'] ?? 0);
                $waran_tarik = (float)($_POST['waran_tarik'] ?? 0);
                $tarikh_waran = !empty($_POST['tarikh_waran']) ? $_POST['tarikh_waran'] : null;

                if (!$tahun || $sumber_dana === '' || $kod_vot === '' || $nama_butiran === '') {
                    throw new Exception('Sila lengkapkan semua maklumat wajib bagi Peruntukan Vot.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO peruntukan_vot (tahun, sumber_dana, kod_vot, nama_butiran, waran_diterima, waran_tarik, tarikh_waran, created_by, updated_by)
                    VALUES (:tahun, :sumber, :kod, :butiran, :terima, :tarik, :twaran, :uid, :uid)
                ");
                $stmt->execute([
                    ':tahun'   => $tahun,
                    ':sumber'  => $sumber_dana,
                    ':kod'     => $kod_vot,
                    ':butiran' => $nama_butiran,
                    ':terima'  => $waran_diterima,
                    ':tarik'   => $waran_tarik,
                    ':twaran'  => $tarikh_waran,
                    ':uid'     => $user_id
                ]);
                $mesej_berjaya = "Peruntukan Vot ({$kod_vot}) berjaya disimpan.";
            }

            // 1B. KEMASKINI PERUNTUKAN / VOT
            elseif ($tindakan === 'kemaskini_vot') {
                $vot_id = filter_input(INPUT_POST, 'vot_id', FILTER_VALIDATE_INT);
                $tahun = filter_input(INPUT_POST, 'tahun', FILTER_VALIDATE_INT);
                $sumber_dana = trim($_POST['sumber_dana'] ?? '');
                $kod_vot = strtoupper(trim($_POST['kod_vot'] ?? ''));
                $nama_butiran = trim($_POST['nama_butiran'] ?? '');
                $waran_diterima = (float)($_POST['waran_diterima'] ?? 0);
                $waran_tarik = (float)($_POST['waran_tarik'] ?? 0);
                $tarikh_waran = !empty($_POST['tarikh_waran']) ? $_POST['tarikh_waran'] : null;

                if (!$vot_id || !$tahun || $sumber_dana === '' || $kod_vot === '' || $nama_butiran === '') {
                    throw new Exception('Maklumat kemaskini Peruntukan Vot tidak lengkap.');
                }

                $stmt = $pdo->prepare("
                    UPDATE peruntukan_vot SET
                        tahun = :tahun,
                        sumber_dana = :sumber,
                        kod_vot = :kod,
                        nama_butiran = :butiran,
                        waran_diterima = :terima,
                        waran_tarik = :tarik,
                        tarikh_waran = :twaran,
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':tahun'   => $tahun,
                    ':sumber'  => $sumber_dana,
                    ':kod'     => $kod_vot,
                    ':butiran' => $nama_butiran,
                    ':terima'  => $waran_diterima,
                    ':tarik'   => $waran_tarik,
                    ':twaran'  => $tarikh_waran,
                    ':uid'     => $user_id,
                    ':id'      => $vot_id
                ]);
                $mesej_berjaya = "Peruntukan Vot ({$kod_vot}) berjaya dikemaskini.";
            }

            // 1C. PADAM PERUNTUKAN / VOT
            elseif ($tindakan === 'padam_vot') {
                $vot_id = filter_input(INPUT_POST, 'vot_id', FILTER_VALIDATE_INT);
                if ($vot_id) {
                    $stmt = $pdo->prepare("DELETE FROM peruntukan_vot WHERE id = :id");
                    $stmt->execute([':id' => $vot_id]);
                    $mesej_berjaya = "Rekod Peruntukan Vot berjaya dipadam.";
                }
            }

            // 2A. TAMBAH PROJEK BAHARU
            elseif ($tindakan === 'tambah_projek') {
                $vot_id = filter_input(INPUT_POST, 'vot_id', FILTER_VALIDATE_INT);
                $jajahan_id = filter_input(INPUT_POST, 'bahagian_jajahan_id', FILTER_VALIDATE_INT);
                $kategori_item = trim($_POST['kategori_item'] ?? 'PROJEK');
                $nama_projek = trim($_POST['nama_projek'] ?? '');
                $kaedah = trim($_POST['kaedah_perolehan'] ?? '');
                $no_kontrak = trim($_POST['no_kontrak'] ?? '');
                $kontraktor = trim($_POST['nama_kontraktor'] ?? '');
                $kos_anggaran = (float)($_POST['kos_anggaran'] ?? 0);
                $agihan_peruntukan = (float)($_POST['agihan_peruntukan'] ?? 0);
                $kos_asal = (float)($_POST['kos_kontrak_asal'] ?? 0);
                $kos_sebenar = (float)($_POST['kos_kontrak_terlaras'] ?? 0);
                $bayaran_terdahulu = (float)($_POST['bayaran_terdahulu'] ?? 0);
                $tarikh_mula = !empty($_POST['tarikh_mula']) ? $_POST['tarikh_mula'] : null;
                $tarikh_siap = !empty($_POST['tarikh_siap_asal']) ? $_POST['tarikh_siap_asal'] : null;
                $tarikh_eot = !empty($_POST['tarikh_eot']) ? $_POST['tarikh_eot'] : null;
                $tarikh_cpc = !empty($_POST['tarikh_cpc']) ? $_POST['tarikh_cpc'] : null;
                $tarikh_dlp = !empty($_POST['tarikh_dlp']) ? $_POST['tarikh_dlp'] : null;
                $fiz_jadual = (float)($_POST['fizikal_jadual_pct'] ?? 0);
                $fiz_sebenar = (float)($_POST['fizikal_sebenar_pct'] ?? 0);
                $kew_jadual = (float)($_POST['kewangan_jadual_pct'] ?? 0);
                $kew_sebenar = (float)($_POST['kewangan_sebenar_pct'] ?? 0);
                $status = trim($_POST['status_projek'] ?? 'BELUM KOMIT');
                $catatan = trim($_POST['catatan'] ?? '');
                if ($kos_sebenar == 0 && $kos_asal > 0) {
                    $kos_sebenar = $kos_asal;
                }

                if (!$vot_id || !$jajahan_id || $nama_projek === '' || $kaedah === '') {
                    throw new Exception('Sila lengkapkan Vot, Jajahan/Bahagian, Nama Projek, dan Kaedah Perolehan.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO projek (
                        vot_id, bahagian_jajahan_id, kategori_item, nama_projek, kaedah_perolehan, no_kontrak, 
                        nama_kontraktor, kos_anggaran, agihan_peruntukan, kos_kontrak_asal, kos_kontrak_terlaras, bayaran_terdahulu,
                        tarikh_mula, tarikh_siap_asal, tarikh_eot, tarikh_cpc, tarikh_dlp,
                        fizikal_jadual_pct, fizikal_sebenar_pct, kewangan_jadual_pct, kewangan_sebenar_pct,
                        status_projek, catatan, created_by, updated_by
                    ) VALUES (
                        :vot, :jajahan, :kategori, :nama, :kaedah, :kontrak,
                        :kontraktor, :anggaran, :agihan, :kasal, :ksebenar, :bterdahulu,
                        :mula, :siap, :eot, :cpc, :dlp,
                        :fjadual, :fsebenar, :kjadual, :ksebenar_pct,
                        :status, :catatan, :uid, :uid
                    )
                ");
                $stmt->execute([
                    ':vot'          => $vot_id,
                    ':jajahan'      => $jajahan_id,
                    ':kategori'     => $kategori_item,
                    ':nama'         => $nama_projek,
                    ':kaedah'       => $kaedah,
                    ':kontrak'      => $no_kontrak,
                    ':kontraktor'   => $kontraktor,
                    ':anggaran'     => $kos_anggaran,
                    ':agihan'       => $agihan_peruntukan,
                    ':kasal'        => $kos_asal,
                    ':ksebenar'     => $kos_sebenar,
                    ':bterdahulu'   => $bayaran_terdahulu,
                    ':mula'         => $tarikh_mula,
                    ':siap'         => $tarikh_siap,
                    ':eot'          => $tarikh_eot,
                    ':cpc'          => $tarikh_cpc,
                    ':dlp'          => $tarikh_dlp,
                    ':fjadual'      => $fiz_jadual,
                    ':fsebenar'     => $fiz_sebenar,
                    ':kjadual'      => $kew_jadual,
                    ':ksebenar_pct' => $kew_sebenar,
                    ':status'       => $status,
                    ':catatan'      => $catatan,
                    ':uid'          => $user_id
                ]);
                $mesej_berjaya = "Projek baharu berjaya didaftarkan.";
            }

            // 2B. KEMASKINI PANTAS STATUS & KEMAJUAN (FIZIKAL & KEWANGAN) DARI JADUAL KANAN
            elseif ($tindakan === 'kemaskini_projek') {
                $projek_id = filter_input(INPUT_POST, 'projek_id', FILTER_VALIDATE_INT);
                $fiz_jadual = (float)($_POST['fizikal_jadual_pct'] ?? 0);
                $fiz_sebenar = (float)($_POST['fizikal_sebenar_pct'] ?? 0);
                $kew_jadual = (float)($_POST['kewangan_jadual_pct'] ?? 0);
                $kew_sebenar = (float)($_POST['kewangan_sebenar_pct'] ?? 0);
                $status = trim($_POST['status_projek'] ?? '');

                if (!$projek_id || $status === '') {
                    throw new Exception('Data kemaskini projek tidak lengkap.');
                }

                $stmt = $pdo->prepare("
                    UPDATE projek SET 
                        fizikal_jadual_pct = :fj, 
                        fizikal_sebenar_pct = :fs, 
                        kewangan_jadual_pct = :kj,
                        kewangan_sebenar_pct = :ks,
                        status_projek = :st, 
                        updated_by = :uid, 
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':fj'  => $fiz_jadual,
                    ':fs'  => $fiz_sebenar,
                    ':kj'  => $kew_jadual,
                    ':ks'  => $kew_sebenar,
                    ':st'  => $status,
                    ':uid' => $user_id,
                    ':id'  => $projek_id
                ]);
                $mesej_berjaya = "Kemajuan fizikal, kewangan & status projek berjaya dikemaskini.";
            }

            // 2C. KEMASKINI PENUH BUTIRAN PROJEK (DARI BORANG KIRI)
            elseif ($tindakan === 'kemaskini_penuh_projek') {
                $projek_id = filter_input(INPUT_POST, 'projek_id', FILTER_VALIDATE_INT);
                $vot_id = filter_input(INPUT_POST, 'vot_id', FILTER_VALIDATE_INT);
                $jajahan_id = filter_input(INPUT_POST, 'bahagian_jajahan_id', FILTER_VALIDATE_INT);
                $kategori_item = trim($_POST['kategori_item'] ?? 'PROJEK');
                $nama_projek = trim($_POST['nama_projek'] ?? '');
                $kaedah = trim($_POST['kaedah_perolehan'] ?? '');
                $no_kontrak = trim($_POST['no_kontrak'] ?? '');
                $kontraktor = trim($_POST['nama_kontraktor'] ?? '');
                $kos_anggaran = (float)($_POST['kos_anggaran'] ?? 0);
                $agihan_peruntukan = (float)($_POST['agihan_peruntukan'] ?? 0);
                $kos_asal = (float)($_POST['kos_kontrak_asal'] ?? 0);
                $kos_sebenar = (float)($_POST['kos_kontrak_terlaras'] ?? 0);
                $bayaran_terdahulu = (float)($_POST['bayaran_terdahulu'] ?? 0);
                $tarikh_mula = !empty($_POST['tarikh_mula']) ? $_POST['tarikh_mula'] : null;
                $tarikh_siap = !empty($_POST['tarikh_siap_asal']) ? $_POST['tarikh_siap_asal'] : null;
                $tarikh_eot = !empty($_POST['tarikh_eot']) ? $_POST['tarikh_eot'] : null;
                $tarikh_cpc = !empty($_POST['tarikh_cpc']) ? $_POST['tarikh_cpc'] : null;
                $tarikh_dlp = !empty($_POST['tarikh_dlp']) ? $_POST['tarikh_dlp'] : null;
                $fiz_jadual = (float)($_POST['fizikal_jadual_pct'] ?? 0);
                $fiz_sebenar = (float)($_POST['fizikal_sebenar_pct'] ?? 0);
                $kew_jadual = (float)($_POST['kewangan_jadual_pct'] ?? 0);
                $kew_sebenar = (float)($_POST['kewangan_sebenar_pct'] ?? 0);
                $status = trim($_POST['status_projek'] ?? 'BELUM KOMIT');
                $catatan = trim($_POST['catatan'] ?? '');
                if (!$projek_id || !$vot_id || !$jajahan_id || $nama_projek === '' || $kaedah === '') {
                    throw new Exception('Sila lengkapkan semua medan wajib untuk mengemaskini projek.');
                }

                $stmt = $pdo->prepare("
                    UPDATE projek SET
                        vot_id = :vot,
                        bahagian_jajahan_id = :jajahan,
                        kategori_item = :kategori,
                        nama_projek = :nama,
                        kaedah_perolehan = :kaedah,
                        no_kontrak = :kontrak,
                        nama_kontraktor = :kontraktor,
                        kos_anggaran = :anggaran,
                        agihan_peruntukan = :agihan,
                        kos_kontrak_asal = :kasal,
                        kos_kontrak_terlaras = :ksebenar,
                        bayaran_terdahulu = :bterdahulu,
                        tarikh_mula = :mula,
                        tarikh_siap_asal = :siap,
                        tarikh_eot = :eot,
                        tarikh_cpc = :cpc,
                        tarikh_dlp = :dlp,
                        fizikal_jadual_pct = :fjadual,
                        fizikal_sebenar_pct = :fsebenar,
                        kewangan_jadual_pct = :kjadual,
                        kewangan_sebenar_pct = :ksebenar_pct,
                        status_projek = :status,
                        catatan = :catatan,
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':vot'          => $vot_id,
                    ':jajahan'      => $jajahan_id,
                    ':kategori'     => $kategori_item,
                    ':nama'         => $nama_projek,
                    ':kaedah'       => $kaedah,
                    ':kontrak'      => $no_kontrak,
                    ':kontraktor'   => $kontraktor,
                    ':anggaran'     => $kos_anggaran,
                    ':agihan'       => $agihan_peruntukan,
                    ':kasal'        => $kos_asal,
                    ':ksebenar'     => $kos_sebenar,
                    ':bterdahulu'   => $bayaran_terdahulu,
                    ':mula'         => $tarikh_mula,
                    ':siap'         => $tarikh_siap,
                    ':eot'          => $tarikh_eot,
                    ':cpc'          => $tarikh_cpc,
                    ':dlp'          => $tarikh_dlp,
                    ':fjadual'      => $fiz_jadual,
                    ':fsebenar'     => $fiz_sebenar,
                    ':kjadual'      => $kew_jadual,
                    ':ksebenar_pct' => $kew_sebenar,
                    ':status'       => $status,
                    ':catatan'      => $catatan,
                    ':uid'          => $user_id,
                    ':id'           => $projek_id
                ]);
                $mesej_berjaya = "Maklumat penuh projek berjaya dikemaskini.";
            }

            // 2D. PADAM PROJEK
            elseif ($tindakan === 'padam_projek') {
                $projek_id = filter_input(INPUT_POST, 'projek_id', FILTER_VALIDATE_INT);
                if ($projek_id) {
                    $stmt = $pdo->prepare("DELETE FROM projek WHERE id = :id");
                    $stmt->execute([':id' => $projek_id]);
                    $mesej_berjaya = "Rekod projek berjaya dipadam.";
                }
            }

            // 3A. TAMBAH TRANSAKSI TANGGUNGAN / BAUCER BAYARAN (1GFMAS)
            elseif ($tindakan === 'tambah_transaksi') {
                $projek_id = filter_input(INPUT_POST, 'projek_id', FILTER_VALIDATE_INT);
                $jenis = trim($_POST['jenis_transaksi'] ?? '');
                $no_tanggungan = trim($_POST['no_dokumen_tanggungan'] ?? '');
                $no_baucer = trim($_POST['no_baucer'] ?? '');
                $no_interim = trim($_POST['no_interim'] ?? '');
                $tarikh = $_POST['tarikh_transaksi'] ?? '';
                $penerima = trim($_POST['nama_penerima'] ?? '');
                $perihal = trim($_POST['perihal'] ?? '');
                $amaun = (float)($_POST['amaun'] ?? 0);
                $sebab_batal = trim($_POST['sebab_batal'] ?? '');

                if (!$projek_id || $jenis === '' || $tarikh === '' || $penerima === '' || $amaun <= 0) {
                    throw new Exception('Sila lengkapkan Projek, Jenis Transaksi, Tarikh, Nama Penerima, dan Amaun (> 0).');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO transaksi_kewangan (
                        projek_id, jenis_transaksi, no_dokumen_tanggungan, no_baucer, no_interim,
                        tarikh_transaksi, nama_penerima, perihal, amaun, status_baucer, sebab_batal, created_by, updated_by
                    ) VALUES (
                        :pid, :jenis, :notang, :nobaucer, :nointerim,
                        :tarikh, :penerima, :perihal, :amaun, 'SAH', :sbatal, :uid, :uid
                    )
                ");
                $stmt->execute([
                    ':pid'       => $projek_id,
                    ':jenis'     => $jenis,
                    ':notang'    => $no_tanggungan,
                    ':nobaucer'  => $no_baucer,
                    ':nointerim' => $no_interim,
                    ':tarikh'    => $tarikh,
                    ':penerima'  => $penerima,
                    ':perihal'   => $perihal,
                    ':amaun'     => $amaun,
                    ':sbatal'    => $sebab_batal,
                    ':uid'       => $user_id
                ]);
                $mesej_berjaya = "Rekod transaksi kewangan (RM " . number_format($amaun, 2) . ") berjaya disimpan.";
            }
            // 3B. KEMASKINI TRANSAKSI KEWANGAN
            elseif ($tindakan === 'kemaskini_transaksi') {
                $trx_id = filter_input(INPUT_POST, 'trx_id', FILTER_VALIDATE_INT);
                $projek_id = filter_input(INPUT_POST, 'projek_id', FILTER_VALIDATE_INT);
                $jenis = trim($_POST['jenis_transaksi'] ?? '');
                $no_tanggungan = trim($_POST['no_dokumen_tanggungan'] ?? '');
                $no_baucer = trim($_POST['no_baucer'] ?? '');
                $no_interim = trim($_POST['no_interim'] ?? '');
                $tarikh = $_POST['tarikh_transaksi'] ?? '';
                $penerima = trim($_POST['nama_penerima'] ?? '');
                $perihal = trim($_POST['perihal'] ?? '');
                $amaun = (float)($_POST['amaun'] ?? 0);
                $sebab_batal = trim($_POST['sebab_batal'] ?? '');

                if (!$trx_id || !$projek_id || $jenis === '' || $tarikh === '' || $penerima === '' || $amaun <= 0) {
                    throw new Exception('Maklumat kemaskini transaksi tidak lengkap.');
                }

                $stmt = $pdo->prepare("
                    UPDATE transaksi_kewangan SET
                        projek_id = :pid,
                        jenis_transaksi = :jenis,
                        no_dokumen_tanggungan = :notang,
                        no_baucer = :nobaucer,
                        no_interim = :nointerim,
                        tarikh_transaksi = :tarikh,
                        nama_penerima = :penerima,
                        perihal = :perihal,
                        amaun = :amaun,
                        sebab_batal = :sbatal,
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':pid'       => $projek_id,
                    ':jenis'     => $jenis,
                    ':notang'    => $no_tanggungan,
                    ':nobaucer'  => $no_baucer,
                    ':nointerim' => $no_interim,
                    ':tarikh'    => $tarikh,
                    ':penerima'  => $penerima,
                    ':perihal'   => $perihal,
                    ':amaun'     => $amaun,
                    ':sbatal'    => $sebab_batal,
                    ':uid'       => $user_id,
                    ':id'        => $trx_id
                ]);
                $mesej_berjaya = "Rekod transaksi kewangan berjaya dikemaskini.";
            }

            // 3C. TUKAR STATUS BAUCER (SAH / BATAL)
            elseif ($tindakan === 'tukar_status_baucer') {
                $trx_id = filter_input(INPUT_POST, 'trx_id', FILTER_VALIDATE_INT);
                $status_baharu = ($_POST['status_baharu'] === 'BATAL') ? 'BATAL' : 'SAH';
                $sebab_batal = trim($_POST['sebab_batal'] ?? '');

                if ($trx_id) {
                    $stmt = $pdo->prepare("
                        UPDATE transaksi_kewangan 
                        SET status_baucer = :st, sebab_batal = :sb, updated_by = :uid, updated_at = NOW() 
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':st'  => $status_baharu,
                        ':sb'  => ($status_baharu === 'BATAL') ? $sebab_batal : null,
                        ':uid' => $user_id,
                        ':id'  => $trx_id
                    ]);
                    $mesej_berjaya = "Status baucer berjaya ditukar kepada {$status_baharu}.";
                }
            }

            // 3D. PADAM TRANSAKSI
            elseif ($tindakan === 'padam_transaksi') {
                $trx_id = filter_input(INPUT_POST, 'trx_id', FILTER_VALIDATE_INT);
                if ($trx_id) {
                    $stmt = $pdo->prepare("DELETE FROM transaksi_kewangan WHERE id = :id");
                    $stmt->execute([':id' => $trx_id]);
                    $mesej_berjaya = "Rekod transaksi kewangan berjaya dipadam.";
                }
            }

        } catch (PDOException $e) {
            error_log('Ralat DB Admin: ' . $e->getMessage());
            if ($e->getCode() == '23505') {
                $mesej_ralat = 'Rekod dengan Kod Vot & Tahun tersebut sudah wujud dalam sistem.';
            } elseif ($e->getCode() == '23503') {
                $mesej_ralat = 'Rekod ini tidak boleh dipadam kerana masih mempunyai sub-rekod transaksi/projek yang berkaitan.';
            } else {
                $mesej_ralat = 'Ralat pangkalan data berlaku. Sila semak input anda.';
            }
        } catch (Exception $e) {
            $mesej_ralat = $e->getMessage();
        }
    }
}

// ============================================================================
// TARIK DATA DROPDOWN & SENARAI TERKINI UNTUK PAPARAN STAF
// ============================================================================
$senarai_jajahan = $pdo->query("SELECT id, kod, nama, kategori FROM lookup_bahagian_jajahan ORDER BY kategori, nama")->fetchAll();
$senarai_vot = $pdo->query("SELECT id, tahun, sumber_dana, kod_vot, nama_butiran, waran_diterima, waran_tarik, tarikh_waran, (waran_diterima - waran_tarik) AS bersih FROM peruntukan_vot ORDER BY tahun DESC, kod_vot ASC")->fetchAll();
// Tarik senarai projek beserta jumlah bayaran semasa (SAH) dan jumlah tanggungan (SAH)
$senarai_projek = $pdo->query("
    SELECT 
        p.*, 
        v.kod_vot, 
        v.tahun, 
        j.nama AS nama_jajahan,
        COALESCE((
            SELECT SUM(t.amaun) 
            FROM transaksi_kewangan t 
            WHERE t.projek_id = p.id 
              AND t.jenis_transaksi != 'TANGGUNGAN' 
              AND t.status_baucer = 'SAH'
        ), 0) AS jumlah_bayaran_semasa,
        COALESCE((
            SELECT SUM(t.amaun) 
            FROM transaksi_kewangan t 
            WHERE t.projek_id = p.id 
              AND t.jenis_transaksi = 'TANGGUNGAN' 
              AND t.status_baucer = 'SAH'
        ), 0) AS jumlah_tanggungan_semasa
    FROM projek p
    JOIN peruntukan_vot v ON p.vot_id = v.id
    JOIN lookup_bahagian_jajahan j ON p.bahagian_jajahan_id = j.id
    ORDER BY p.id DESC LIMIT 100
")->fetchAll();

$senarai_transaksi = $pdo->query("
    SELECT t.*, p.nama_projek, v.kod_vot 
    FROM transaksi_kewangan t
    JOIN projek p ON t.projek_id = p.id
    JOIN peruntukan_vot v ON p.vot_id = v.id
    ORDER BY t.id DESC LIMIT 100
")->fetchAll();

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Key-In Staf - JPS Negeri Kelantan</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark navbar-jps shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="admin.php">
            JPS KELANTAN <span class="fw-normal fs-6">| Panel Kemasukan Data Staf</span>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white small d-none d-md-inline">Staf: <strong><?= e($nama_staf) ?></strong></span>
            <a href="index.php" class="btn btn-outline-light btn-sm">Lihat Graf Bos</a>
            <a href="logout.php" class="btn btn-danger btn-sm">Log Keluar</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">

    <?php if ($mesej_berjaya): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= e($mesej_berjaya) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mesej_ralat): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= e($mesej_ralat) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4 bg-white px-3 pt-2 rounded shadow-sm" id="tabStaf" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-baucer-btn" data-bs-toggle="tab" data-bs-target="#tab-baucer" type="button">
                1. Rekod Baucer & Tanggungan (1GFMAS)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-projek-btn" data-bs-toggle="tab" data-bs-target="#tab-projek" type="button">
                2. Daftar & Kemaskini Projek
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-vot-btn" data-bs-toggle="tab" data-bs-target="#tab-vot" type="button">
                3. Peruntukan & Waran Vot
            </button>
        </li>
    </ul>

    <div class="tab-content" id="tabStafContent">

        <!-- ================================================================ -->
        <!-- TAB 1: BAUCHER & TANGGUNGAN                                      -->
        <!-- ================================================================ -->
        <div class="tab-pane fade show active" id="tab-baucer" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-jps" id="kadBorangTrx">
                        <div class="card-header fw-bold d-flex justify-content-between align-items-center" id="tajukBorangTrx">
                            <span>Tambah Transaksi Kewangan</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="admin.php" id="formTrx">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="tindakan" id="trx_tindakan" value="tambah_transaksi">
                                <input type="hidden" name="trx_id" id="trx_id_edit" value="">

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Pilih Projek / Item *</label>
                                    <select name="projek_id" id="trx_projek_id" class="form-select form-select-sm" required>
                                        <option value="">-- Sila Pilih Projek --</option>
                                        <?php foreach ($senarai_projek as $p): ?>
                                            <option value="<?= e($p['id']) ?>">
                                                [<?= e($p['kod_vot']) ?> - <?= e($p['nama_jajahan']) ?> | <?= e($p['kategori_item'] ?? 'PROJEK') ?>] <?= e(mb_strimwidth($p['nama_projek'], 0, 55, '...')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Jenis Transaksi *</label>
                                        <select name="jenis_transaksi" id="trx_jenis" class="form-select form-select-sm" required>
                                            <option value="BAYARAN_INTERIM">Bayaran Interim / Kerja</option>
                                            <option value="TANGGUNGAN">Tanggungan (Inden/PO/SST)</option>
                                            <option value="PAMPASAN_TANAH">Pampasan Tanah (LA)</option>
                                            <option value="PENGURUSAN_TNT">Pengurusan / TNT / Utiliti</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Tarikh Transaksi *</label>
                                        <input type="date" name="tarikh_transaksi" id="trx_tarikh" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">No. PO / Tanggungan</label>
                                        <input type="text" name="no_dokumen_tanggungan" id="trx_notang" class="form-control form-control-sm" placeholder="53000145">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">No. Baucer</label>
                                        <input type="text" name="no_baucer" id="trx_nobaucer" class="form-control form-control-sm" placeholder="12000132">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">No. Interim</label>
                                        <input type="text" name="no_interim" id="trx_nointerim" class="form-control form-control-sm" placeholder="Interim 1">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Nama Pembekal / Penerima *</label>
                                    <input type="text" name="nama_penerima" id="trx_penerima" class="form-control form-control-sm" required placeholder="Kontraktor / Pungutan MARA / Pemilik Tanah">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Amaun (RM) *</label>
                                    <input type="number" step="0.01" min="0.01" name="amaun" id="trx_amaun" class="form-control form-control-sm" required placeholder="0.00">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Perihal / No. Lot / Rujukan</label>
                                    <textarea name="perihal" id="trx_perihal" rows="2" class="form-control form-control-sm" placeholder="Cth: Interim No. 4 atau Lot 783"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Catatan Kuiri / Sebab Batal (Jika Ada)</label>
                                    <input type="text" name="sebab_batal" id="trx_sebab_batal" class="form-control form-control-sm" placeholder="Cth: Salah Koding / Gantian EFT 12001661">
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" id="btnSubmitTrx" class="btn btn-primary btn-sm w-100 fw-semibold">Simpan Transaksi</button>
                                    <button type="button" id="btnBatalTrx" class="btn btn-secondary btn-sm d-none" onclick="batalEditTrx()">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-jps">
                        <div class="card-header fw-bold">100 Rekod Baucer & Tanggungan Terkini</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-jps mb-0 align-middle small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tarikh</th>
                                            <th>Baucer / Interim</th>
                                            <th>Penerima & Projek</th>
                                            <th>Jenis</th>
                                            <th class="text-end">Amaun (RM)</th>
                                            <th class="text-center">Status & Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($senarai_transaksi as $t): ?>
                                        <tr class="<?= ($t['status_baucer'] === 'BATAL') ? 'table-danger' : '' ?>">
                                            <td><?= e($t['tarikh_transaksi']) ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= e($t['no_baucer'] ?: '-') ?></div>
                                                <?php if (!empty($t['no_interim'])): ?>
                                                    <span class="badge bg-secondary-subtle text-dark border"><?= e($t['no_interim']) ?></span>
                                                <?php endif; ?>
                                                <small class="text-muted d-block"><?= e($t['no_dokumen_tanggungan']) ?></small>
                                            </td>
                                            <td>
                                                <div class="fw-semibold <?= ($t['status_baucer'] === 'BATAL') ? 'text-decoration-line-through' : '' ?>"><?= e($t['nama_penerima']) ?></div>
                                                <small class="text-muted d-block">[<?= e($t['kod_vot']) ?>] <?= e(mb_strimwidth($t['nama_projek'], 0, 45, '...')) ?></small>
                                                <?php if (!empty($t['sebab_batal'])): ?>
                                                    <small class="text-danger fw-semibold d-block">Nota: <?= e($t['sebab_batal']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-info text-dark"><?= e($t['jenis_transaksi']) ?></span></td>
                                            <td class="text-end fw-bold"><?= number_format($t['amaun'], 2) ?></td>
                                            <td class="text-center" style="min-width: 155px;">
                                                <div class="d-flex justify-content-center gap-1 flex-wrap">
                                                    <button type="button"
                                                            class="btn btn-outline-warning text-dark btn-sm py-0 px-2"
                                                            data-trx='<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>'
                                                            onclick="muatEditTrx(this)">
                                                        Edit
                                                    </button>

                                                    <form method="POST" action="admin.php" class="d-inline" onsubmit="return sahkanStatusBaucer(this, '<?= e($t['status_baucer']) ?>')">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="tindakan" value="tukar_status_baucer">
                                                        <input type="hidden" name="trx_id" value="<?= e($t['id']) ?>">
                                                        <input type="hidden" name="sebab_batal" value="">
                                                        <?php if ($t['status_baucer'] === 'SAH'): ?>
                                                            <input type="hidden" name="status_baharu" value="BATAL">
                                                            <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-2">Sah</button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="status_baharu" value="SAH">
                                                            <button type="submit" class="btn btn-secondary btn-sm py-0 px-2">Batal</button>
                                                        <?php endif; ?>
                                                    </form>

                                                    <form method="POST" action="admin.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="tindakan" value="padam_transaksi">
                                                        <input type="hidden" name="trx_id" value="<?= e($t['id']) ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Padam rekod transaksi ini secara kekal?')">Padam</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ================================================================ -->
        <!-- TAB 2: PROJEK & ITEM                                             -->
        <!-- ================================================================ -->
        <div class="tab-pane fade" id="tab-projek" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-jps" id="kadBorangProjek">
                        <div class="card-header fw-bold d-flex justify-content-between align-items-center" id="tajukBorangProjek">
                            <span>Daftar Projek / Item Baharu</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="admin.php" id="formProjek">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="tindakan" id="projek_tindakan" value="tambah_projek">
                                <input type="hidden" name="projek_id" id="projek_id_edit" value="">

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Kod Vot Peruntukan *</label>
                                    <select name="vot_id" id="input_vot_id" class="form-select form-select-sm" required>
                                        <option value="">-- Pilih Kod Vot --</option>
                                        <?php foreach ($senarai_vot as $v): ?>
                                            <option value="<?= e($v['id']) ?>">
                                                [<?= e($v['tahun']) ?>] <?= e($v['kod_vot']) ?> - <?= e(mb_strimwidth($v['nama_butiran'], 0, 45, '...')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-7">
                                        <label class="form-label small fw-semibold">Jajahan / Bahagian *</label>
                                        <select name="bahagian_jajahan_id" id="input_jajahan_id" class="form-select form-select-sm" required>
                                            <option value="">-- Pilih Pelaksana --</option>
                                            <?php foreach ($senarai_jajahan as $j): ?>
                                                <option value="<?= e($j['id']) ?>">[<?= e($j['kategori']) ?>] <?= e($j['nama']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold">Kategori Item *</label>
                                        <select name="kategori_item" id="input_kategori_item" class="form-select form-select-sm" required>
                                            <option value="PROJEK">PROJEK</option>
                                            <option value="PENGURUSAN">PENGURUSAN</option>
                                            <option value="UTILITI">UTILITI</option>
                                            <option value="TANAH">TANAH (LA)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Tajuk / Nama Projek *</label>
                                    <textarea name="nama_projek" id="input_nama_projek" rows="2" class="form-control form-control-sm" required></textarea>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Kaedah Perolehan *</label>
                                        <select name="kaedah_perolehan" id="input_kaedah" class="form-select form-select-sm" required>
                                            <option value="LANTIKAN TERUS">Lantikan Terus (Inden)</option>
                                            <option value="SEBUTHARGA">Sebutharga (SH)</option>
                                            <option value="TENDER">Tender</option>
                                            <option value="KERJA UNDI">Kerja Undi</option>
                                            <option value="E-PEROLEHAN">ePerolehan (EP)</option>
                                            <option value="PENGURUSAN">Pengurusan / TNT</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Status Projek *</label>
                                        <select name="status_projek" id="input_status" class="form-select form-select-sm" required>
                                            <option value="BELUM KOMIT">Belum Komit</option>
                                            <option value="IKLAN / PENILAIAN">Iklan / Penilaian</option>
                                            <option value="DALAM PEMBINAAN">Dalam Pembinaan</option>
                                            <option value="SIAP (CPC)">Siap (CPC)</option>
                                            <option value="PROSES BAYARAN">Proses Bayaran</option>
                                            <option value="TELAH BAYAR">Telah Bayar</option>
                                            <option value="TANGGUH / BATAL">Tangguh / Batal</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Nama Kontraktor / Pembekal</label>
                                    <input type="text" name="nama_kontraktor" id="input_kontraktor" class="form-control form-control-sm">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">No. Kontrak / Inden / Sebutharga</label>
                                    <input type="text" name="no_kontrak" id="input_no_kontrak" class="form-control form-control-sm" placeholder="Cth: KPKM/BPSP/01/2025 (SH)">
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Agihan Peruntukan (RM)</label>
                                        <input type="number" step="0.01" min="0" name="agihan_peruntukan" id="input_agihan_peruntukan" class="form-control form-control-sm" value="0.00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Kos Anggaran (RM)</label>
                                        <input type="number" step="0.01" min="0" name="kos_anggaran" id="input_kos_anggaran" class="form-control form-control-sm" value="0.00">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Harga Kontrak Asal (RM)</label>
                                        <input type="number" step="0.01" min="0" name="kos_kontrak_asal" id="input_kos_asal" class="form-control form-control-sm" value="0.00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Kontrak Terlaras (RM)</label>
                                        <input type="number" step="0.01" min="0" name="kos_kontrak_terlaras" id="input_kos_sebenar" class="form-control form-control-sm" value="0.00">
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Bayaran Terdahulu / Tahun Lepas (RM)</label>
                                    <input type="number" step="0.01" min="0" name="bayaran_terdahulu" id="input_bayaran_terdahulu" class="form-control form-control-sm" value="0.00" placeholder="Jumlah bayaran tahun-tahun sebelum ini">
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Tarikh Mula</label>
                                        <input type="date" name="tarikh_mula" id="input_tarikh_mula" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Tarikh Siap Asal</label>
                                        <input type="date" name="tarikh_siap_asal" id="input_tarikh_siap" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Tarikh EOT</label>
                                        <input type="date" name="tarikh_eot" id="input_tarikh_eot" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Tarikh CPC</label>
                                        <input type="date" name="tarikh_cpc" id="input_tarikh_cpc" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small fw-semibold">Tamat DLP</label>
                                        <input type="date" name="tarikh_dlp" id="input_tarikh_dlp" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Fizikal Jadual (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="fizikal_jadual_pct" id="input_fjadual" class="form-control form-control-sm" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Fizikal Sebenar (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="fizikal_sebenar_pct" id="input_fsebenar" class="form-control form-control-sm" value="0">
                                    </div>
                                </div>

                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Kewangan Jadual (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="kewangan_jadual_pct" id="input_kjadual" class="form-control form-control-sm" value="0">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Kewangan Sebenar (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="kewangan_sebenar_pct" id="input_ksebenar" class="form-control form-control-sm" value="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Catatan / Pelarasan APK / PHK</label>
                                    <textarea name="catatan" id="input_catatan" rows="2" class="form-control form-control-sm" placeholder="Catatan tambahan, pelarasan APK/PHK, atau isu tapak..."></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" id="btnSubmitProjek" class="btn btn-primary btn-sm w-100 fw-semibold">Daftar Projek</button>
                                    <button type="button" id="btnBatalEdit" class="btn btn-secondary btn-sm d-none" onclick="batalEditProjek()">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-jps">
                        <div class="card-header fw-bold">Senarai Projek & Kiraan Automatik Baki Kos Projek</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-jps mb-0 align-middle small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Vot / Pelaksana</th>
                                            <th>Butiran Projek & Kontraktor</th>
                                            <th class="text-end">Kewangan Kontrak (RM)</th>
                                            <th>Kemaskini Kemajuan & Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($senarai_projek as $p): 
                                            $kos_terlaras = (float)$p['kos_kontrak_terlaras'];
                                            $bayar_lepas = (float)($p['bayaran_terdahulu'] ?? 0);
                                            $bayar_semasa = (float)$p['jumlah_bayaran_semasa'];
                                            $jumlah_bayar_keseluruhan = $bayar_lepas + $bayar_semasa;
                                            $baki_kos_projek = $kos_terlaras - $jumlah_bayar_keseluruhan;
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-primary"><?= e($p['kod_vot']) ?></span>
                                                <span class="badge bg-dark"><?= e($p['kategori_item'] ?? 'PROJEK') ?></span>
                                                <div class="fw-semibold mt-1"><?= e($p['nama_jajahan']) ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= e($p['nama_projek']) ?></div>
                                                <small class="text-muted d-block"><?= e($p['nama_kontraktor'] ?: 'Belum Lantik') ?> | <?= e($p['no_kontrak']) ?></small>
                                                <?php if (!empty($p['tarikh_siap_asal']) || !empty($p['tarikh_eot']) || !empty($p['tarikh_cpc'])): ?>
                                                    <small class="text-secondary d-block">
                                                        Siap: <?= e($p['tarikh_siap_asal'] ?: '-') ?>
                                                        <?= !empty($p['tarikh_eot']) ? ' | EOT: ' . e($p['tarikh_eot']) : '' ?>
                                                        <?= !empty($p['tarikh_cpc']) ? ' | CPC: ' . e($p['tarikh_cpc']) : '' ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if (!empty($p['catatan'])): ?>
                                                    <small class="text-info-emphasis d-block fst-italic mb-1"><?= e(mb_strimwidth($p['catatan'], 0, 75, '...')) ?></small>
                                                <?php endif; ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-warning text-dark btn-sm py-0 px-2 fw-semibold" 
                                                            style="font-size: 0.75rem;"
                                                            data-projek='<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>'
                                                            onclick="muatDataEdit(this)">
                                                        Edit Butiran
                                                    </button>
                                                    <form method="POST" action="admin.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="tindakan" value="padam_projek">
                                                        <input type="hidden" name="projek_id" value="<?= e($p['id']) ?>">
                                                        <button type="submit" 
                                                                class="btn btn-outline-danger btn-sm py-0 px-2" 
                                                                style="font-size: 0.75rem;"
                                                                onclick="return confirm('Padam projek ini? Pastikan tiada baucer aktif di bawah projek ini.')">
                                                            Padam
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                            <td class="text-end" style="min-width: 175px;">
                                                <div class="small text-muted">Terlaras: <strong class="text-dark"><?= number_format($kos_terlaras, 2) ?></strong></div>
                                                <div class="small text-muted">Bayar Lepas: <?= number_format($bayar_lepas, 2) ?></div>
                                                <div class="small text-success">Bayar Semasa: <?= number_format($bayar_semasa, 2) ?></div>
                                                <div class="border-top pt-1 mt-1 fw-bold <?= ($baki_kos_projek < 0) ? 'text-danger' : 'text-primary' ?>">
                                                    Baki Kos: <?= number_format($baki_kos_projek, 2) ?>
                                                </div>
                                            </td>
                                            <td style="min-width: 255px;">
                                                <form method="POST" action="admin.php" class="d-flex flex-column gap-1">
                                                    <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                    <input type="hidden" name="tindakan" value="kemaskini_projek">
                                                    <input type="hidden" name="projek_id" value="<?= e($p['id']) ?>">
                                                    
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" title="Fizikal Jadual %">FJ%</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="fizikal_jadual_pct" class="form-control" value="<?= e($p['fizikal_jadual_pct']) ?>">
                                                        <span class="input-group-text" title="Fizikal Sebenar %">FS%</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="fizikal_sebenar_pct" class="form-control" value="<?= e($p['fizikal_sebenar_pct']) ?>">
                                                    </div>

                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" title="Kewangan Jadual %">KJ%</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="kewangan_jadual_pct" class="form-control" value="<?= e($p['kewangan_jadual_pct'] ?? 0) ?>">
                                                        <span class="input-group-text" title="Kewangan Sebenar %">KS%</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="kewangan_sebenar_pct" class="form-control" value="<?= e($p['kewangan_sebenar_pct'] ?? 0) ?>">
                                                    </div>

                                                    <div class="d-flex gap-1">
                                                        <select name="status_projek" class="form-select form-select-sm">
                                                            <?php 
                                                            $senarai_st = ['BELUM KOMIT', 'IKLAN / PENILAIAN', 'DALAM PEMBINAAN', 'SIAP (CPC)', 'PROSES BAYARAN', 'TELAH BAYAR', 'TANGGUH / BATAL'];
                                                            foreach ($senarai_st as $st): 
                                                            ?>
                                                                <option value="<?= $st ?>" <?= ($p['status_projek'] === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-success btn-sm">Simpan</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- TAB 3: PERUNTUKAN & WARAN VOT                                    -->
        <!-- ================================================================ -->
        <div class="tab-pane fade" id="tab-vot" role="tabpanel">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-jps" id="kadBorangVot">
                        <div class="card-header fw-bold d-flex justify-content-between align-items-center" id="tajukBorangVot">
                            <span>Daftar Waran / Peruntukan Vot Baharu</span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="admin.php" id="formVot">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                <input type="hidden" name="tindakan" id="vot_tindakan" value="tambah_vot">
                                <input type="hidden" name="vot_id" id="vot_id_edit" value="">

                                <div class="row g-2 mb-3">
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold">Tahun *</label>
                                        <input type="number" name="tahun" id="vot_tahun" class="form-control form-control-sm" value="<?= date('Y') ?>" required>
                                    </div>
                                    <div class="col-7">
                                        <label class="form-label small fw-semibold">Sumber Dana *</label>
                                        <select name="sumber_dana" id="vot_sumber" class="form-select form-select-sm" required>
                                            <option value="NEGERI (P17)">NEGERI (P17)</option>
                                            <option value="PETRA (P33)">PERSEKUTUAN - PETRA (P33)</option>
                                            <option value="KPKM (P21)">PERSEKUTUAN - KPKM (P21)</option>
                                            <option value="KPK (P20)">PERSEKUTUAN - KPK (P20)</option>
                                            <option value="KKDW (P22)">PERSEKUTUAN - KKDW (P22)</option>
                                            <option value="AP182">PERSEKUTUAN - AP182</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-7">
                                        <label class="form-label small fw-semibold">Kod Vot / Butiran *</label>
                                        <input type="text" name="kod_vot" id="vot_kod" class="form-control form-control-sm" required placeholder="Cth: P21 10013 103 2502">
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold">Tarikh Waran</label>
                                        <input type="date" name="tarikh_waran" id="vot_tarikh_waran" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Nama Maksud Butiran / Program *</label>
                                    <textarea name="nama_butiran" id="vot_butiran" rows="2" class="form-control form-control-sm" required placeholder="Cth: Naik Taraf Pintu-Pintu Air Kecil"></textarea>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Waran Diterima (RM) *</label>
                                        <input type="number" step="0.01" min="0" name="waran_diterima" id="vot_terima" class="form-control form-control-sm" required value="0.00">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Waran Tarik (RM)</label>
                                        <input type="number" step="0.01" min="0" name="waran_tarik" id="vot_tarik" class="form-control form-control-sm" value="0.00">
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" id="btnSubmitVot" class="btn btn-primary btn-sm w-100 fw-semibold">Simpan Peruntukan Vot</button>
                                    <button type="button" id="btnBatalVot" class="btn btn-secondary btn-sm d-none" onclick="batalEditVot()">Batal</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-jps">
                        <div class="card-header fw-bold">Senarai Peruntukan & Vot Berdaftar</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-jps mb-0 align-middle small">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tahun / Tarikh</th>
                                            <th>Sumber Dana</th>
                                            <th>Kod Vot</th>
                                            <th>Nama Butiran</th>
                                            <th class="text-end">Peruntukan Bersih (RM)</th>
                                            <th class="text-center">Tindakan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($senarai_vot as $v): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= e($v['tahun']) ?></div>
                                                <?php if (!empty($v['tarikh_waran'])): ?>
                                                    <small class="text-muted"><?= e($v['tarikh_waran']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= e($v['sumber_dana']) ?></span></td>
                                            <td class="fw-bold"><?= e($v['kod_vot']) ?></td>
                                            <td><?= e($v['nama_butiran']) ?></td>
                                            <td class="text-end fw-bold text-primary">
                                                <?= number_format($v['bersih'], 2) ?>
                                                <?php if ((float)$v['waran_tarik'] > 0): ?>
                                                    <small class="text-danger d-block fw-normal">(-<?= number_format($v['waran_tarik'], 2) ?> tarik)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" style="min-width: 115px;">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <button type="button"
                                                            class="btn btn-outline-warning text-dark btn-sm py-0 px-2"
                                                            data-vot='<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>'
                                                            onclick="muatEditVot(this)">
                                                        Edit
                                                    </button>
                                                    <form method="POST" action="admin.php" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                                                        <input type="hidden" name="tindakan" value="padam_vot">
                                                        <input type="hidden" name="vot_id" value="<?= e($v['id']) ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="return confirm('Padam rekod peruntukan Vot ini?')">Padam</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Kekalkan tab aktif selepas halaman dimuat semula
document.addEventListener('DOMContentLoaded', function () {
    const activeTabId = localStorage.getItem('jps_admin_active_tab');
    if (activeTabId) {
        const tabBtn = document.getElementById(activeTabId);
        if (tabBtn) {
            new bootstrap.Tab(tabBtn).show();
        }
    }
    document.querySelectorAll('#tabStaf button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', e => {
            localStorage.setItem('jps_admin_active_tab', e.target.id);
        });
    });
});
// ============================================================================
// FUNGSI EDIT TAB 1: TRANSAKSI KEWANGAN (BAUCER & TANGGUNGAN)
// ============================================================================
function muatEditTrx(btn) {
    const t = JSON.parse(btn.getAttribute('data-trx'));
    document.getElementById('trx_tindakan').value = 'kemaskini_transaksi';
    document.getElementById('trx_id_edit').value = t.id;

    document.getElementById('trx_projek_id').value = t.projek_id;
    document.getElementById('trx_jenis').value = t.jenis_transaksi;
    document.getElementById('trx_tarikh').value = t.tarikh_transaksi;
    document.getElementById('trx_notang').value = t.no_dokumen_tanggungan || '';
    document.getElementById('trx_nobaucer').value = t.no_baucer || '';
    document.getElementById('trx_nointerim').value = t.no_interim || '';
    document.getElementById('trx_penerima').value = t.nama_penerima || '';
    document.getElementById('trx_amaun').value = t.amaun;
    document.getElementById('trx_perihal').value = t.perihal || '';
    document.getElementById('trx_sebab_batal').value = t.sebab_batal || '';

    const tajuk = document.getElementById('tajukBorangTrx');
    tajuk.innerHTML = '<span class="text-warning-emphasis">Kemaskini Transaksi #' + t.id + '</span>';
    tajuk.classList.add('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitTrx');
    btnSubmit.textContent = 'Simpan Perubahan';
    btnSubmit.classList.replace('btn-primary', 'btn-warning');
    btnSubmit.classList.add('text-dark');

    document.getElementById('btnBatalTrx').classList.remove('d-none');
    document.getElementById('kadBorangTrx').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function batalEditTrx() {
    document.getElementById('formTrx').reset();
    document.getElementById('trx_tindakan').value = 'tambah_transaksi';
    document.getElementById('trx_id_edit').value = '';

    const tajuk = document.getElementById('tajukBorangTrx');
    tajuk.innerHTML = '<span>Tambah Transaksi Kewangan</span>';
    tajuk.classList.remove('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitTrx');
    btnSubmit.textContent = 'Simpan Transaksi';
    btnSubmit.classList.replace('btn-warning', 'btn-primary');
    btnSubmit.classList.remove('text-dark');

    document.getElementById('btnBatalTrx').classList.add('d-none');
}

function sahkanStatusBaucer(form, statusSemasa) {
    if (statusSemasa === 'SAH') {
        const sebab = prompt('Sila nyatakan sebab pembatalan baucer ini (cth: Salah Koding / EFT Batal):');
        if (sebab === null) return false;
        form.querySelector('input[name="sebab_batal"]').value = sebab;
    }
    return true;
}

// ============================================================================
// FUNGSI EDIT TAB 2: PROJEK
// ============================================================================
function muatDataEdit(btn) {
    const p = JSON.parse(btn.getAttribute('data-projek'));

    document.getElementById('projek_tindakan').value = 'kemaskini_penuh_projek';
    document.getElementById('projek_id_edit').value = p.id;

    document.getElementById('input_vot_id').value = p.vot_id;
    document.getElementById('input_jajahan_id').value = p.bahagian_jajahan_id;
    document.getElementById('input_kategori_item').value = p.kategori_item || 'PROJEK';
    document.getElementById('input_nama_projek').value = p.nama_projek;
    document.getElementById('input_kaedah').value = p.kaedah_perolehan;
    document.getElementById('input_status').value = p.status_projek;
    document.getElementById('input_kontraktor').value = p.nama_kontraktor || '';
    document.getElementById('input_no_kontrak').value = p.no_kontrak || '';
    document.getElementById('input_agihan_peruntukan').value = p.agihan_peruntukan || '0.00';
    document.getElementById('input_kos_anggaran').value = p.kos_anggaran || '0.00';
    document.getElementById('input_kos_asal').value = p.kos_kontrak_asal || '0.00';
    document.getElementById('input_kos_sebenar').value = p.kos_kontrak_terlaras || '0.00';
    document.getElementById('input_bayaran_terdahulu').value = p.bayaran_terdahulu || '0.00';
    document.getElementById('input_tarikh_mula').value = p.tarikh_mula || '';
    document.getElementById('input_tarikh_siap').value = p.tarikh_siap_asal || '';
    document.getElementById('input_tarikh_eot').value = p.tarikh_eot || '';
    document.getElementById('input_tarikh_cpc').value = p.tarikh_cpc || '';
    document.getElementById('input_tarikh_dlp').value = p.tarikh_dlp || '';
    document.getElementById('input_fjadual').value = p.fizikal_jadual_pct || 0;
    document.getElementById('input_fsebenar').value = p.fizikal_sebenar_pct || 0;
    document.getElementById('input_kjadual').value = p.kewangan_jadual_pct || 0;
    document.getElementById('input_ksebenar').value = p.kewangan_sebenar_pct || 0;
    document.getElementById('input_catatan').value = p.catatan || '';

    const tajuk = document.getElementById('tajukBorangProjek');
    tajuk.innerHTML = '<span class="text-warning-emphasis">Kemaskini Butiran Projek #' + p.id + '</span>';
    tajuk.classList.add('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitProjek');
    btnSubmit.textContent = 'Simpan Perubahan';
    btnSubmit.classList.replace('btn-primary', 'btn-warning');
    btnSubmit.classList.add('text-dark');

    document.getElementById('btnBatalEdit').classList.remove('d-none');
    document.getElementById('kadBorangProjek').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function batalEditProjek() {
    document.getElementById('formProjek').reset();
    document.getElementById('projek_tindakan').value = 'tambah_projek';
    document.getElementById('projek_id_edit').value = '';

    const tajuk = document.getElementById('tajukBorangProjek');
    tajuk.innerHTML = '<span>Daftar Projek / Item Baharu</span>';
    tajuk.classList.remove('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitProjek');
    btnSubmit.textContent = 'Daftar Projek';
    btnSubmit.classList.replace('btn-warning', 'btn-primary');
    btnSubmit.classList.remove('text-dark');

    document.getElementById('btnBatalEdit').classList.add('d-none');
}

// ============================================================================
// FUNGSI EDIT TAB 3: PERUNTUKAN & WARAN VOT
// ============================================================================
function muatEditVot(btn) {
    const v = JSON.parse(btn.getAttribute('data-vot'));
    document.getElementById('vot_tindakan').value = 'kemaskini_vot';
    document.getElementById('vot_id_edit').value = v.id;

    document.getElementById('vot_tahun').value = v.tahun;
    document.getElementById('vot_sumber').value = v.sumber_dana;
    document.getElementById('vot_kod').value = v.kod_vot;
    document.getElementById('vot_tarikh_waran').value = v.tarikh_waran || '';
    document.getElementById('vot_butiran').value = v.nama_butiran;
    document.getElementById('vot_terima').value = v.waran_diterima;
    document.getElementById('vot_tarik').value = v.waran_tarik;

    const tajuk = document.getElementById('tajukBorangVot');
    tajuk.innerHTML = '<span class="text-warning-emphasis">Kemaskini Peruntukan Vot #' + v.id + '</span>';
    tajuk.classList.add('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitVot');
    btnSubmit.textContent = 'Simpan Perubahan';
    btnSubmit.classList.replace('btn-primary', 'btn-warning');
    btnSubmit.classList.add('text-dark');

    document.getElementById('btnBatalVot').classList.remove('d-none');
    document.getElementById('kadBorangVot').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function batalEditVot() {
    document.getElementById('formVot').reset();
    document.getElementById('vot_tindakan').value = 'tambah_vot';
    document.getElementById('vot_id_edit').value = '';

    const tajuk = document.getElementById('tajukBorangVot');
    tajuk.innerHTML = '<span>Daftar Waran / Peruntukan Vot Baharu</span>';
    tajuk.classList.remove('bg-warning-subtle');

    const btnSubmit = document.getElementById('btnSubmitVot');
    btnSubmit.textContent = 'Simpan Peruntukan Vot';
    btnSubmit.classList.replace('btn-warning', 'btn-primary');
    btnSubmit.classList.remove('text-dark');

    document.getElementById('btnBatalVot').classList.add('d-none');
}
</script>
</body>
</html>