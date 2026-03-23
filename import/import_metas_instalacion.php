<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}
if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden importar metas.");
}

include '../conexion.php';

$mensaje     = '';
$tipo_msg    = '';
$preview     = [];
$total_filas = 0;

require_once __DIR__ . '/../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    $nombre  = $_FILES['archivo']['name'];
    $ext     = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

    if (!in_array($ext, ['xlsx','xls'])) {
        $mensaje  = 'Solo se permiten archivos Excel (.xlsx o .xls)';
        $tipo_msg = 'error';
    } else {
        try {
            $spreadsheet = IOFactory::load($archivo);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            $headers = array_map('trim', $rows[1]);

            $map = [
                'Cluster'            => 'cluster',
                'Casas_Liberadas'    => 'casas_liberadas',
                'Distrito'           => 'distrito',
                'Ciudad'             => 'ciudad',
                'Plaza'              => 'plaza',
                'Canal'              => 'canal',
                'Region'             => 'region',
                'Capa'               => 'capa',
                'Fecha_Liberacion'   => 'fecha_liberacion',
                'Empresa'            => 'empresa',
                'Meta'               => 'meta',
                'Mes'                => 'mes',
                'Mes_num'            => 'mes_num',
                'Anio'               => 'anio',
                'Dia'                => 'dia',
                'Dias_Del_Mes'       => 'dias_del_mes',
                'Meta_Diaria'        => 'meta_diaria',
                'Fecha'              => 'fecha',
            ];

            $col_index = [];
            foreach ($headers as $col_letra => $col_nombre) {
                foreach ($map as $excel_name => $bd_name) {
                    if (trim($col_nombre) === $excel_name) {
                        $col_index[$bd_name] = $col_letra;
                    }
                }
            }

            if (isset($_POST['confirmar'])) {
                $insertados = 0;
                $errores    = 0;

                // Detectar mes y año para limpiar antes de insertar
                $mes_check = null; $anio_check = null;
                foreach ($rows as $i => $row) {
                    if ($i === 1) continue;
                    if (empty($row[$col_index['meta'] ?? ''])) continue;
                    if (!$mes_check) {
                        $mes_check  = $row[$col_index['mes_num'] ?? ''] ?? null;
                        $anio_check = $row[$col_index['anio'] ?? ''] ?? null;
                        break;
                    }
                }
                if ($mes_check && $anio_check) {
                    mysqli_query($conexion, "DELETE FROM metas_instalacion WHERE mes_num=$mes_check AND anio=$anio_check");
                }

                foreach ($rows as $i => $row) {
                    if ($i === 1) continue;
                    if (empty($row[$col_index['meta'] ?? ''])) continue;

                    $cluster      = trim($row[$col_index['cluster'] ?? ''] ?? '');
                    $casas_lib    = (int)($row[$col_index['casas_liberadas'] ?? ''] ?? 0);
                    $distrito     = trim($row[$col_index['distrito'] ?? ''] ?? '');
                    $ciudad       = trim($row[$col_index['ciudad'] ?? ''] ?? '');
                    $plaza        = trim($row[$col_index['plaza'] ?? ''] ?? '');
                    $canal        = trim($row[$col_index['canal'] ?? ''] ?? '');
                    $region       = trim($row[$col_index['region'] ?? ''] ?? '');
                    $capa         = trim($row[$col_index['capa'] ?? ''] ?? '');
                    $fecha_lib    = trim($row[$col_index['fecha_liberacion'] ?? ''] ?? '');
                    $empresa      = trim($row[$col_index['empresa'] ?? ''] ?? '');
                    $meta         = (int)($row[$col_index['meta'] ?? ''] ?? 0);
                    $mes          = trim($row[$col_index['mes'] ?? ''] ?? '');
                    $mes_num      = (int)($row[$col_index['mes_num'] ?? ''] ?? 0);
                    $anio         = (int)($row[$col_index['anio'] ?? ''] ?? 0);
                    $dia          = (int)($row[$col_index['dia'] ?? ''] ?? 0);
                    $dias_mes     = (int)($row[$col_index['dias_del_mes'] ?? ''] ?? 0);
                    $meta_diaria  = (float)($row[$col_index['meta_diaria'] ?? ''] ?? 0);
                    $fecha_raw    = $row[$col_index['fecha'] ?? ''] ?? '';

                    // Convertir fecha
                    if ($fecha_raw instanceof \DateTime) {
                        $fecha = $fecha_raw->format('Y-m-d');
                    } elseif (is_numeric($fecha_raw)) {
                        $fecha = date('Y-m-d', ($fecha_raw - 25569) * 86400);
                    } else {
                        $fecha = date('Y-m-d', strtotime($fecha_raw));
                    }

                    $stmt = mysqli_prepare($conexion,
                        "INSERT INTO metas_instalacion (cluster, casas_liberadas, distrito, ciudad, plaza, canal, region, capa, fecha_liberacion, empresa, meta, mes, mes_num, anio, dia, dias_del_mes, meta_diaria, fecha)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($stmt, "sissssssssisiiiids",
                        $cluster, $casas_lib, $distrito, $ciudad, $plaza,
                        $canal, $region, $capa, $fecha_lib, $empresa,
                        $meta, $mes, $mes_num, $anio, $dia, $dias_mes,
                        $meta_diaria, $fecha);

                    if (mysqli_stmt_execute($stmt)) {
                        $insertados++;
                    } else {
                        $errores++;
                    }
                    mysqli_stmt_close($stmt);
                }

                $mensaje  = "✅ Importación completada: $insertados filas insertadas" . ($errores > 0 ? ", $errores errores." : ".");
                $tipo_msg = 'success';

            } else {
                // PREVIEW
                foreach ($rows as $i => $row) {
                    if ($i === 1) continue;
                    if (empty($row[$col_index['meta'] ?? ''])) continue;
                    $total_filas++;
                    if (count($preview) < 5) {
                        $fecha_raw = $row[$col_index['fecha'] ?? ''] ?? '';
                        if ($fecha_raw instanceof \DateTime) {
                            $fecha = $fecha_raw->format('Y-m-d');
                        } elseif (is_numeric($fecha_raw)) {
                            $fecha = date('Y-m-d', ($fecha_raw - 25569) * 86400);
                        } else {
                            $fecha = date('Y-m-d', strtotime($fecha_raw));
                        }
                        $preview[] = [
                            'distrito'    => trim($row[$col_index['distrito'] ?? ''] ?? ''),
                            'canal'       => trim($row[$col_index['canal'] ?? ''] ?? ''),
                            'meta'        => (int)($row[$col_index['meta'] ?? ''] ?? 0),
                            'meta_diaria' => round((float)($row[$col_index['meta_diaria'] ?? ''] ?? 0), 4),
                            'fecha'       => $fecha,
                        ];
                    }
                }
            }

        } catch (Exception $e) {
            $mensaje  = 'Error al leer el archivo: ' . $e->getMessage();
            $tipo_msg = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Metas — TOTALXPEDIENT</title>
    <style>
        :root { --blue:#2b57a7; --bg:#f4f6fb; --white:#ffffff; --text:#1a2540; --text2:#6b7a99; --border:#e2e8f4; --sidebar:200px; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',sans-serif; background:var(--bg); color:var(--text); display:flex; min-height:100vh; }
        .sidebar { width:var(--sidebar); background:var(--blue); min-height:100vh; position:fixed; top:0; left:0; display:flex; flex-direction:column; align-items:center; padding:28px 0; z-index:100; }
        .sidebar-logo { color:white; font-size:2rem; margin-bottom:6px; }
        .sidebar-brand { color:rgba(255,255,255,0.9); font-size:0.72rem; font-weight:800; letter-spacing:1px; text-transform:uppercase; margin-bottom:32px; text-align:center; padding:0 12px; }
        .nav-item { width:100%; display:flex; flex-direction:column; align-items:center; gap:4px; padding:14px 0; color:rgba(255,255,255,0.65); text-decoration:none; font-size:0.78rem; font-weight:600; transition:all 0.2s; }
        .nav-item:hover, .nav-item.active { color:white; background:rgba(255,255,255,0.12); }
        .nav-icon { font-size:1.3rem; }
        .sidebar-bottom { margin-top:auto; width:100%; padding:0 12px; }
        .logout-btn { display:block; text-align:center; padding:10px; border-radius:8px; color:rgba(255,255,255,0.6); text-decoration:none; font-size:0.78rem; font-weight:600; }
        .logout-btn:hover { background:rgba(255,255,255,0.1); color:white; }
        .main { margin-left:var(--sidebar); flex:1; padding:32px; max-width:800px; }
        .page-header { margin-bottom:28px; }
        .page-header h2 { font-size:1.5rem; font-weight:700; }
        .page-header p { font-size:0.82rem; color:var(--text2); margin-top:2px; }
        .card { background:var(--white); border-radius:16px; padding:28px; border:1px solid var(--border); box-shadow:0 2px 8px rgba(0,0,0,0.04); margin-bottom:20px; }
        .upload-area { border:2px dashed var(--border); border-radius:12px; padding:40px; text-align:center; cursor:pointer; transition:border 0.2s; }
        .upload-area:hover { border-color:var(--blue); }
        .upload-area input { display:none; }
        .upload-icon { font-size:2.5rem; margin-bottom:12px; }
        .upload-label { font-size:0.9rem; color:var(--text2); }
        .upload-label span { color:var(--blue); font-weight:700; cursor:pointer; }
        .file-name { margin-top:10px; font-size:0.85rem; font-weight:600; color:var(--blue); }
        .btn { padding:12px 24px; border:none; border-radius:8px; font-size:0.9rem; font-weight:700; cursor:pointer; transition:all 0.2s; }
        .btn-primary { background:var(--blue); color:white; width:100%; margin-top:16px; padding:14px; font-size:1rem; }
        .btn-primary:hover { background:#1d4ed8; }
        .btn-confirm { background:#059669; color:white; margin-right:10px; }
        .btn-cancel  { background:#6b7a99; color:white; text-decoration:none; padding:12px 24px; border-radius:8px; font-weight:700; font-size:0.9rem; }
        .alert { padding:14px 18px; border-radius:10px; font-size:0.88rem; font-weight:600; margin-bottom:20px; }
        .alert-success { background:#d1fae5; color:#065f46; }
        .alert-error   { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse:collapse; font-size:0.82rem; margin-top:16px; }
        th { background:var(--blue); color:white; padding:10px 14px; text-align:left; font-size:0.78rem; text-transform:uppercase; }
        td { padding:10px 14px; border-bottom:1px solid var(--border); }
        .preview-title { font-size:0.9rem; font-weight:700; margin-bottom:4px; }
        .preview-sub { font-size:0.78rem; color:var(--text2); margin-bottom:16px; }
    </style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-logo">📊</div>
    <div class="sidebar-brand">TOTALXPEDIENT</div>
    <a href="../index.php" class="nav-item"><span class="nav-icon">⊞</span> Dashboard</a>
    <a href="import_instalaciones.php" class="nav-item"><span class="nav-icon">🔧</span> Instalaciones</a>
    <a href="import_ventas.php" class="nav-item"><span class="nav-icon">📈</span> Ventas</a>
    <a href="import_hc.php" class="nav-item"><span class="nav-icon">👥</span> Headcount</a>
    <a href="import_metas_instalacion.php" class="nav-item active"><span class="nav-icon">🎯</span> Metas</a>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="logout-btn">⎋ Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h2>Importar Metas de Instalación</h2>
        <p>Solo administradores · Sube el archivo generado por el script Python</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!empty($preview)): ?>
        <div class="card">
            <div class="preview-title">Vista previa — <?= number_format($total_filas) ?> filas encontradas</div>
            <div class="preview-sub">Se muestran las primeras 5 filas</div>
            <table>
                <thead>
                    <tr><th>Distrito</th><th>Canal</th><th>Meta</th><th>Meta Diaria</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach ($preview as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['distrito']) ?></td>
                        <td><?= htmlspecialchars($p['canal']) ?></td>
                        <td><?= number_format($p['meta']) ?></td>
                        <td><?= $p['meta_diaria'] ?></td>
                        <td><?= $p['fecha'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:20px;display:flex;gap:10px;align-items:center;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="confirmar" value="1">
                    <button type="submit" class="btn btn-confirm">✅ Confirmar importación</button>
                </form>
                <a href="import_metas_instalacion.php" class="btn-cancel">✕ Cancelar</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('archivo').click()">
                    <div class="upload-icon">📂</div>
                    <div class="upload-label">Arrastra tu archivo aquí o <span>selecciona un archivo</span></div>
                    <div class="upload-label" style="margin-top:6px;">Archivo generado por el script Python (Metas_diarias.xlsx)</div>
                    <div class="file-name" id="fileName"></div>
                    <input type="file" id="archivo" name="archivo" accept=".xlsx,.xls" onchange="mostrarNombre(this)">
                </div>
                <button type="submit" class="btn btn-primary">Ver vista previa</button>
            </form>
        </div>
    <?php endif; ?>
</main>

<script>
function mostrarNombre(input) {
    document.getElementById('fileName').textContent = input.files[0]?.name || '';
}
</script>
</body>
</html>