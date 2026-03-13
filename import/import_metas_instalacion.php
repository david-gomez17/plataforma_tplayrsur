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

require_once '../vendor/autoload.php';
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

            // Detectar encabezados en fila 1
            $headers = array_map('trim', $rows[1]);
            // Mapeo de columnas Excel → BD
            $map = [
                'Cluster'             => 'cluster',
                'Casas Liberadas'     => 'casas_liberadas',
                'Distrito'            => 'distrito',
                'Ciudad'              => 'ciudad',
                'Plaza'               => 'plaza',
                'Canal'               => 'canal',
                'Región'              => 'region',
                'Capa'                => 'capa',
                'Fecha de liberación' => 'fecha_liberacion',
                'Empresa'             => 'empresa',
                'Meta'                => 'meta',
                'Mes'                 => 'mes',
                'Año'                 => 'anio',
            ];

            // Encontrar índice de cada columna
            $col_index = [];
            foreach ($headers as $col_letra => $col_nombre) {
                foreach ($map as $excel_name => $bd_name) {
                    if (trim($col_nombre) === $excel_name) {
                        $col_index[$bd_name] = $col_letra;
                    }
                }
            }

            $meses_map = [
                'ENERO'=>1,'FEBRERO'=>2,'MARZO'=>3,'ABRIL'=>4,'MAYO'=>5,'JUNIO'=>6,
                'JULIO'=>7,'AGOSTO'=>8,'SEPTIEMBRE'=>9,'OCTUBRE'=>10,'NOVIEMBRE'=>11,'DICIEMBRE'=>12
            ];

            if (isset($_POST['confirmar'])) {
                // IMPORTAR
                $insertados = 0;
                $errores    = 0;

                // Limpiar metas del mismo mes/año si ya existen
                $mes_check  = null;
                $anio_check = null;

                foreach ($rows as $i => $row) {
                    if ($i === 1) continue; // saltar encabezados
                    if (empty($row[$col_index['meta'] ?? ''])) continue;

                    $cluster          = trim($row[$col_index['cluster'] ?? ''] ?? '');
                    $casas_liberadas  = (int)($row[$col_index['casas_liberadas'] ?? ''] ?? 0);
                    $distrito         = trim($row[$col_index['distrito'] ?? ''] ?? '');
                    $ciudad           = trim($row[$col_index['ciudad'] ?? ''] ?? '');
                    $plaza            = trim($row[$col_index['plaza'] ?? ''] ?? '');
                    $canal            = trim($row[$col_index['canal'] ?? ''] ?? '');
                    $region           = trim($row[$col_index['region'] ?? ''] ?? '');
                    $capa             = trim($row[$col_index['capa'] ?? ''] ?? '');
                    $fecha_lib        = trim($row[$col_index['fecha_liberacion'] ?? ''] ?? '');
                    $empresa          = trim($row[$col_index['empresa'] ?? ''] ?? '');
                    $meta             = (int)($row[$col_index['meta'] ?? ''] ?? 0);
                    $mes_txt          = strtoupper(trim($row[$col_index['mes'] ?? ''] ?? ''));
                    $anio             = (int)($row[$col_index['anio'] ?? ''] ?? 0);
                    $mes_num          = $meses_map[$mes_txt] ?? 0;

                    if (!$mes_check) { $mes_check = $mes_num; $anio_check = $anio; }

                    $stmt = mysqli_prepare($conexion,
                        "INSERT INTO metas_instalacion (cluster, casas_liberadas, distrito, ciudad, plaza, canal, region, capa, fecha_liberacion, empresa, meta, mes, anio)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($stmt, "sissssssssiii",
                        $cluster, $casas_liberadas, $distrito, $ciudad, $plaza,
                        $canal, $region, $capa, $fecha_lib, $empresa,
                        $meta, $mes_num, $anio);

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
                    if (count($preview) >= 5) { $total_filas++; continue; }

                    $mes_txt = strtoupper(trim($row[$col_index['mes'] ?? ''] ?? ''));
                    $preview[] = [
                        'distrito' => trim($row[$col_index['distrito'] ?? ''] ?? ''),
                        'canal'    => trim($row[$col_index['canal'] ?? ''] ?? ''),
                        'meta'     => (int)($row[$col_index['meta'] ?? ''] ?? 0),
                        'mes'      => ucfirst(strtolower($mes_txt)),
                        'anio'     => (int)($row[$col_index['anio'] ?? ''] ?? 0),
                    ];
                    $total_filas++;
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
        :root { --blue:#2b57a7; --bg:#f4f6fb; --white:#ffffff; --text:#1a2540; --text2:#6b7a99; --border:#e2e8f4; --green:#10b981; --red:#ef4444; --sidebar:200px; }
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
        .btn-cancel  { background:#6b7a99; color:white; }
        .alert { padding:14px 18px; border-radius:10px; font-size:0.88rem; font-weight:600; margin-bottom:20px; }
        .alert-success { background:#d1fae5; color:#065f46; }
        .alert-error   { background:#fee2e2; color:#991b1b; }
        table { width:100%; border-collapse:collapse; font-size:0.82rem; margin-top:16px; }
        th { background:var(--blue); color:white; padding:10px 14px; text-align:left; font-size:0.78rem; text-transform:uppercase; }
        td { padding:10px 14px; border-bottom:1px solid var(--border); }
        .preview-title { font-size:0.9rem; font-weight:700; margin-bottom:4px; }
        .preview-sub { font-size:0.78rem; color:var(--text2); }
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
        <p>Solo administradores · Formato Excel (.xlsx)</p>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_msg === 'success' ? 'success' : 'error' ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <?php if (!empty($preview)): ?>
        <!-- PREVIEW -->
        <div class="card">
            <div class="preview-title">Vista previa — <?= $total_filas ?> filas encontradas</div>
            <div class="preview-sub">Se muestran las primeras 5 filas</div>
            <table>
                <thead>
                    <tr><th>Distrito</th><th>Canal</th><th>Meta</th><th>Mes</th><th>Año</th></tr>
                </thead>
                <tbody>
                <?php foreach ($preview as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['distrito']) ?></td>
                        <td><?= htmlspecialchars($p['canal']) ?></td>
                        <td><?= number_format($p['meta']) ?></td>
                        <td><?= htmlspecialchars($p['mes']) ?></td>
                        <td><?= $p['anio'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:20px;">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="confirmar" value="1">
                    <input type="hidden" name="archivo_path" value="<?= htmlspecialchars($_FILES['archivo']['tmp_name'] ?? '') ?>">
                    <button type="submit" name="confirmar" value="1" class="btn btn-confirm"
                        onclick="this.form.enctype='multipart/form-data'">
                        ✅ Confirmar importación
                    </button>
                    <a href="import_metas_instalacion.php" class="btn btn-cancel">✕ Cancelar</a>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- FORMULARIO SUBIDA -->
        <div class="card">
            <form method="POST" enctype="multipart/form-data" id="formImport">
                <div class="upload-area" onclick="document.getElementById('archivo').click()">
                    <div class="upload-icon">📂</div>
                    <div class="upload-label">Arrastra tu archivo aquí o <span>selecciona un archivo</span></div>
                    <div class="upload-label" style="margin-top:6px;">Excel (.xlsx, .xls)</div>
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