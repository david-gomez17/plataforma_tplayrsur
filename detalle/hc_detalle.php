<?php
ini_set('display_errors', 0);
error_reporting(0);
header("Cache-Control: no-cache, no-store, must-revalidate");
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../login.php");
    exit();
}

include '../conexion.php';

$rol         = $_SESSION['rol'] ?? 'vendedor';
$id_posicion = $_SESSION['id_posicion'] ?? '';
$puestos_comerciales = ['PROMOVENDEDOR PUNTO DE VENTA','VENDEDOR','VENDEDOR NEGOCIOS','VENDEDOR NEGOCIO'];
$puestos_in = "'" . implode("','", $puestos_comerciales) . "'";

// Semana y año más recientes
$semana_actual  = null;
$anio_actual    = null;
$semana_display = '-';
$res_sem = mysqli_query($conexion, "SELECT semana, anio FROM hc ORDER BY anio DESC, semana DESC LIMIT 1");
if ($res_sem && $row_sem = mysqli_fetch_assoc($res_sem)) {
    $semana_actual  = (int)$row_sem['semana'];
    $anio_actual    = (int)$row_sem['anio'];
    $semana_display = $semana_actual + 1;
    if ($semana_display > 52) $semana_display = 1;
}

$roles_labels = [
    'admin'              => 'Administrador',
    'director_regional'  => 'Director Regional',
    'director_distrital' => 'Director Distrital',
    'lider'              => 'Líder',
    'coach'              => 'Coach',
    'vendedor'           => 'Vendedor',
];

// ── CONSTRUIR MATRIZ SEGÚN ROL ───────────────────────────────────────────────

// Obtener directores según rol
function getDirectores($conexion, $rol, $id_posicion, $semana, $anio) {
    if ($rol === 'admin' || $rol === 'director_regional') {
        // Mostrar todos los directores distritales
        $sql = "SELECT DISTINCT id_posicion, nombre_colaborador FROM hc 
                WHERE posicion = 'DIRECTOR DISTRITAL' AND semana = ? AND anio = ? 
                ORDER BY nombre_colaborador";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $semana, $anio);
    } elseif ($rol === 'director_distrital') {
        // Solo el director actual
        $sql = "SELECT DISTINCT id_posicion, nombre_colaborador FROM hc 
                WHERE id_posicion = ? AND semana = ? AND anio = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $id_posicion, $semana, $anio);
    } elseif ($rol === 'lider') {
        // El director al que reporta el lider
        $sql = "SELECT DISTINCT h2.id_posicion, h2.nombre_colaborador FROM hc h1
                INNER JOIN hc h2 ON h1.posicion_lr = h2.id_posicion
                WHERE h1.id_posicion = ? AND h1.semana = ? AND h1.anio = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $id_posicion, $semana, $anio);
    } elseif ($rol === 'coach') {
        // Director del coach (2 niveles arriba)
        $sql = "SELECT DISTINCT h3.id_posicion, h3.nombre_colaborador FROM hc h1
                INNER JOIN hc h2 ON h1.posicion_lr = h2.id_posicion
                INNER JOIN hc h3 ON h2.posicion_lr = h3.id_posicion
                WHERE h1.id_posicion = ? AND h1.semana = ? AND h1.anio = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $id_posicion, $semana, $anio);
    } else {
        return [];
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $dirs = [];
    while ($row = mysqli_fetch_assoc($res)) $dirs[] = $row;
    mysqli_stmt_close($stmt);
    return $dirs;
}

function getLideres($conexion, $dir_id_posicion, $rol, $mi_id_posicion, $semana, $anio) {
    if ($rol === 'coach') {
        // Solo el lider del coach
        $sql = "SELECT DISTINCT h2.id_posicion, h2.nombre_colaborador FROM hc h1
                INNER JOIN hc h2 ON h1.posicion_lr = h2.id_posicion
                WHERE h1.id_posicion = ? AND h1.semana = ? AND h1.anio = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $mi_id_posicion, $semana, $anio);
    } else {
        $sql = "SELECT DISTINCT id_posicion, nombre_colaborador FROM hc 
                WHERE posicion_lr = ? AND posicion LIKE '%LIDER%' AND semana = ? AND anio = ?
                ORDER BY nombre_colaborador";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $dir_id_posicion, $semana, $anio);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lids = [];
    while ($row = mysqli_fetch_assoc($res)) $lids[] = $row;
    mysqli_stmt_close($stmt);
    return $lids;
}

function getCoaches($conexion, $lider_id_posicion, $rol, $mi_id_posicion, $semana, $anio) {
    if ($rol === 'coach') {
        // Solo el coach actual
        $sql = "SELECT DISTINCT id_posicion, nombre_colaborador FROM hc 
                WHERE id_posicion = ? AND semana = ? AND anio = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $mi_id_posicion, $semana, $anio);
    } else {
        $sql = "SELECT DISTINCT id_posicion, nombre_colaborador FROM hc 
                WHERE posicion_lr = ? AND posicion LIKE '%COACH%' AND semana = ? AND anio = ?
                ORDER BY nombre_colaborador";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sii", $lider_id_posicion, $semana, $anio);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $coaches = [];
    while ($row = mysqli_fetch_assoc($res)) $coaches[] = $row;
    mysqli_stmt_close($stmt);
    return $coaches;
}

function getHCCoach($conexion, $coach_id_posicion, $semana, $anio, $puestos_in) {
    $activo  = 0;
    $vacante = 0;

    $sql_act = "SELECT COUNT(*) as total FROM hc 
                WHERE posicion_lr = ? AND posicion IN ($puestos_in) 
                AND numero_talento_gs NOT LIKE '%VACANTE%'
                AND semana = ? AND anio = ?";
    $stmt = mysqli_prepare($conexion, $sql_act);
    mysqli_stmt_bind_param($stmt, "sii", $coach_id_posicion, $semana, $anio);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $activo = mysqli_fetch_assoc($res)['total'] ?? 0;
    mysqli_stmt_close($stmt);

    $sql_vac = "SELECT COUNT(*) as total FROM hc 
                WHERE posicion_lr = ? AND posicion IN ($puestos_in) 
                AND numero_talento_gs LIKE '%VACANTE%'
                AND semana = ? AND anio = ?";
    $stmt = mysqli_prepare($conexion, $sql_vac);
    mysqli_stmt_bind_param($stmt, "sii", $coach_id_posicion, $semana, $anio);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $vacante = mysqli_fetch_assoc($res)['total'] ?? 0;
    mysqli_stmt_close($stmt);

    return ['activo' => $activo, 'vacante' => $vacante, 'total' => $activo + $vacante];
}

// Construir estructura de datos
$directores = getDirectores($conexion, $rol, $id_posicion, $semana_actual, $anio_actual);
$matriz = [];

foreach ($directores as $dir) {
    $lideres = getLideres($conexion, $dir['id_posicion'], $rol, $id_posicion, $semana_actual, $anio_actual);
    $dir_activo = 0; $dir_vacante = 0;
    $lids_data = [];

    foreach ($lideres as $lid) {
        $coaches = getCoaches($conexion, $lid['id_posicion'], $rol, $id_posicion, $semana_actual, $anio_actual);
        $lid_activo = 0; $lid_vacante = 0;
        $coaches_data = [];

        foreach ($coaches as $coach) {
            $hc = getHCCoach($conexion, $coach['id_posicion'], $semana_actual, $anio_actual, $puestos_in);
            $lid_activo  += $hc['activo'];
            $lid_vacante += $hc['vacante'];
            $coaches_data[] = ['nombre' => $coach['nombre_colaborador'], 'hc' => $hc];
        }

        $dir_activo  += $lid_activo;
        $dir_vacante += $lid_vacante;
        $lids_data[] = [
            'nombre'  => $lid['nombre_colaborador'],
            'activo'  => $lid_activo,
            'vacante' => $lid_vacante,
            'total'   => $lid_activo + $lid_vacante,
            'coaches' => $coaches_data
        ];
    }

    $matriz[] = [
        'nombre'  => $dir['nombre_colaborador'],
        'activo'  => $dir_activo,
        'vacante' => $dir_vacante,
        'total'   => $dir_activo + $dir_vacante,
        'lideres' => $lids_data
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Headcount — TOTALXPEDIENT</title>
    <style>
        :root {
            --blue:   #2b57a7;
            --bg:     #f4f6fb;
            --white:  #ffffff;
            --text:   #1a2540;
            --text2:  #6b7a99;
            --border: #e2e8f4;
            --green:  #10b981;
            --red:    #ef4444;
            --sidebar:200px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        .sidebar { width: var(--sidebar); background: var(--blue); min-height: 100vh; position: fixed; top:0; left:0; display: flex; flex-direction: column; align-items: center; padding: 28px 0; z-index: 100; }
        .sidebar-logo { color: white; font-size: 2rem; margin-bottom: 6px; }
        .sidebar-brand { color: rgba(255,255,255,0.9); font-size: 0.72rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 32px; text-align: center; padding: 0 12px; }
        .nav-item { width: 100%; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 14px 0; color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.78rem; font-weight: 600; transition: all 0.2s; }
        .nav-item:hover, .nav-item.active { color: white; background: rgba(255,255,255,0.12); }
        .nav-icon { font-size: 1.3rem; }
        .sidebar-bottom { margin-top: auto; width: 100%; padding: 0 12px; }
        .logout-btn { display: block; text-align: center; padding: 10px; border-radius: 8px; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.78rem; font-weight: 600; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white; }

        .main { margin-left: var(--sidebar); flex: 1; padding: 32px; }
        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 1.5rem; font-weight: 700; }
        .page-header p { font-size: 0.82rem; color: var(--text2); margin-top: 2px; }

        /* TABLA */
        .table-card { background: var(--white); border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        thead th { background: var(--blue); color: white; padding: 12px 16px; text-align: left; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; }
        thead th.num { text-align: center; }

        tbody tr { border-bottom: 1px solid var(--border); transition: background 0.1s; }
        tbody tr:hover { background: #f8faff; }
        tbody tr:last-child { border-bottom: none; }

        td { padding: 10px 16px; vertical-align: middle; }
        td.num { text-align: center; font-weight: 700; }

        /* NIVELES */
        .row-director td { background: #eef2fb; font-weight: 700; font-size: 0.85rem; }
        .row-lider td { background: #f4f6fb; font-weight: 600; }
        .row-lider td:first-child { padding-left: 28px; }
        .row-coach td { background: #ffffff; }
        .row-coach td:first-child { padding-left: 44px; }
        .row-total td { background: #e8f0fe; font-weight: 700; font-size: 0.82rem; color: var(--blue); }

        /* BADGES */
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; min-width: 36px; text-align: center; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red   { background: #fee2e2; color: #991b1b; }
        .badge-gray  { background: #e2e8f4; color: #1a2540; }
        .badge-zero  { background: #f4f6fb; color: #9ca3af; }

        .toggle-btn { cursor: pointer; user-select: none; margin-right: 6px; color: var(--blue); font-size: 0.9rem; }
        .semana-badge { display: inline-block; background: #e8f0fe; color: var(--blue); border-radius: 8px; padding: 4px 12px; font-size: 0.78rem; font-weight: 700; margin-left: 12px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">📊</div>
    <div class="sidebar-brand">TOTALXPEDIENT</div>
    <a href="../index.php" class="nav-item">
        <span class="nav-icon">⊞</span> Dashboard
    </a>
    <a href="../import/import_instalaciones.php" class="nav-item">
        <span class="nav-icon">🔧</span> Instalaciones
    </a>
    <a href="../import/import_ventas.php" class="nav-item">
        <span class="nav-icon">📈</span> Ventas
    </a>
    <a href="hc_detalle.php" class="nav-item active">
        <span class="nav-icon">👥</span> Headcount
    </a>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="logout-btn">⎋ Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h2>Headcount Comercial <span class="semana-badge">Semana <?= $semana_display ?> · <?= $anio_actual ?></span></h2>
        <p><?= date('d \d\e F Y') ?> · <?= htmlspecialchars($roles_labels[$rol] ?? $rol) ?></p>
    </div>

    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Director</th>
                    <th>Líder</th>
                    <th>Coach</th>
                    <th class="num">Activo</th>
                    <th class="num">Vacante</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matriz as $di => $dir): ?>
                <!-- DIRECTOR -->
                <tr class="row-director" onclick="toggleDir(<?= $di ?>)" style="cursor:pointer;">
                    <td colspan="3">
                        <span class="toggle-btn" id="icon-dir-<?= $di ?>">▼</span>
                        <?= htmlspecialchars($dir['nombre']) ?>
                    </td>
                    <td class="num"><span class="badge badge-green"><?= $dir['activo'] ?></span></td>
                    <td class="num"><span class="badge <?= $dir['vacante'] > 0 ? 'badge-red' : 'badge-zero' ?>"><?= $dir['vacante'] ?></span></td>
                    <td class="num"><span class="badge badge-gray"><?= $dir['total'] ?></span></td>
                </tr>

                <?php foreach ($dir['lideres'] as $li => $lid): ?>
                <!-- LIDER -->
                <tr class="row-lider dir-<?= $di ?>" onclick="toggleLid(<?= $di ?>,<?= $li ?>)" style="cursor:pointer;">
                    <td></td>
                    <td colspan="2">
                        <span class="toggle-btn" id="icon-lid-<?= $di ?>-<?= $li ?>">▼</span>
                        <?= htmlspecialchars($lid['nombre']) ?>
                    </td>
                    <td class="num"><span class="badge badge-green"><?= $lid['activo'] ?></span></td>
                    <td class="num"><span class="badge <?= $lid['vacante'] > 0 ? 'badge-red' : 'badge-zero' ?>"><?= $lid['vacante'] ?></span></td>
                    <td class="num"><span class="badge badge-gray"><?= $lid['total'] ?></span></td>
                </tr>

                <?php foreach ($lid['coaches'] as $ci => $coach): ?>
                <!-- COACH -->
                <tr class="row-coach dir-<?= $di ?> lid-<?= $di ?>-<?= $li ?>">
                    <td></td>
                    <td></td>
                    <td><?= htmlspecialchars($coach['nombre']) ?></td>
                    <td class="num"><span class="badge badge-green"><?= $coach['hc']['activo'] ?></span></td>
                    <td class="num"><span class="badge <?= $coach['hc']['vacante'] > 0 ? 'badge-red' : 'badge-zero' ?>"><?= $coach['hc']['vacante'] ?></span></td>
                    <td class="num"><span class="badge badge-gray"><?= $coach['hc']['total'] ?></span></td>
                </tr>
                <?php endforeach; ?>

                <!-- TOTAL LIDER -->
                <tr class="row-total dir-<?= $di ?> lid-<?= $di ?>-<?= $li ?>">
                    <td></td>
                    <td colspan="2" style="padding-left:44px;">Total <?= htmlspecialchars($lid['nombre']) ?></td>
                    <td class="num"><?= $lid['activo'] ?></td>
                    <td class="num"><?= $lid['vacante'] ?></td>
                    <td class="num"><?= $lid['total'] ?></td>
                </tr>

                <?php endforeach; ?>

                <!-- TOTAL DIRECTOR -->
                <tr class="row-total dir-<?= $di ?>">
                    <td colspan="3" style="padding-left:28px;">Total <?= htmlspecialchars($dir['nombre']) ?></td>
                    <td class="num"><?= $dir['activo'] ?></td>
                    <td class="num"><?= $dir['vacante'] ?></td>
                    <td class="num"><?= $dir['total'] ?></td>
                </tr>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
function toggleDir(di) {
    const rows = document.querySelectorAll('.dir-' + di);
    const icon = document.getElementById('icon-dir-' + di);
    const hidden = rows[0]?.style.display === 'none';
    rows.forEach(r => r.style.display = hidden ? '' : 'none');
    icon.textContent = hidden ? '▼' : '▶';
}

function toggleLid(di, li) {
    const rows = document.querySelectorAll('.lid-' + di + '-' + li);
    const icon = document.getElementById('icon-lid-' + di + '-' + li);
    const hidden = rows[0]?.style.display === 'none';
    rows.forEach(r => r.style.display = hidden ? '' : 'none');
    icon.textContent = hidden ? '▼' : '▶';
}
</script>
</body>
</html>