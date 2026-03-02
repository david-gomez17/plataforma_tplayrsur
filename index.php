<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
include 'includes/conexion.php';

// Query matriz: instalaciones por distrito y mes
$query = "
    SELECT 
        distrito,
        YEAR(fecha) as anio,
        MONTH(fecha) as mes,
        COUNT(cuenta) as total
    FROM instalaciones
    WHERE fecha IS NOT NULL AND distrito IS NOT NULL
    GROUP BY YEAR(fecha), MONTH(fecha), distrito
    ORDER BY anio, mes, distrito
";
$result = mysqli_query($conexion, $query);

$meses_nombres = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

$data = [];
$distritos = [];
$periodos = [];

while ($row = mysqli_fetch_assoc($result)) {
    $distrito = $row['distrito'];
    $periodo  = $row['anio'] . '-' . str_pad($row['mes'], 2, '0', STR_PAD_LEFT);
    $label    = $meses_nombres[$row['mes']] . ' ' . $row['anio'];

    if (!in_array($distrito, $distritos)) $distritos[] = $distrito;
    if (!isset($periodos[$periodo])) $periodos[$periodo] = $label;

    $data[$distrito][$periodo] = $row['total'];
}

ksort($periodos);
sort($distritos);

// Totales por periodo
$totales_periodo = [];
foreach ($periodos as $p => $label) {
    $totales_periodo[$p] = 0;
    foreach ($distritos as $d) {
        $totales_periodo[$p] += $data[$d][$p] ?? 0;
    }
}

// Totales por distrito
$totales_distrito = [];
foreach ($distritos as $d) {
    $totales_distrito[$d] = array_sum($data[$d] ?? []);
}

$gran_total = array_sum($totales_periodo);

// KPIs
$kpi_instalaciones = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(cuenta) as total FROM instalaciones"));
$kpi_distritos     = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(DISTINCT distrito) as total FROM instalaciones"));
$kpi_mes_actual    = mysqli_fetch_assoc(mysqli_query($conexion, "SELECT COUNT(cuenta) as total FROM instalaciones WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())"));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Plataforma</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:        #0d1117;
            --bg2:       #161b22;
            --bg3:       #21262d;
            --border:    #30363d;
            --text:      #e6edf3;
            --text2:     #8b949e;
            --accent:    #2563eb;
            --accent2:   #3b82f6;
            --accent3:   #60a5fa;
            --success:   #10b981;
            --warning:   #f59e0b;
            --danger:    #ef4444;
            --cyan:      #06b6d4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--bg2);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }

        .sidebar-logo {
            padding: 24px 20px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-logo h1 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.3px;
        }

        .sidebar-logo span {
            font-size: 0.75rem;
            color: var(--text2);
            font-weight: 400;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
        }

        .nav-section {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 12px 8px 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 6px;
            color: var(--text2);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s;
            margin-bottom: 2px;
        }

        .nav-item:hover { background: var(--bg3); color: var(--text); }
        .nav-item.active { background: rgba(37,99,235,0.15); color: var(--accent3); }
        .nav-item .icon { font-size: 1rem; width: 18px; text-align: center; }

        .sidebar-footer {
            padding: 16px 12px;
            border-top: 1px solid var(--border);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
        }

        .avatar {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--cyan));
            display: flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: 700; color: white;
        }

        .user-name { font-size: 0.85rem; font-weight: 600; }
        .user-role { font-size: 0.7rem; color: var(--text2); }

        .logout-btn {
            display: block;
            margin-top: 8px;
            padding: 8px 10px;
            border-radius: 6px;
            color: var(--danger);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: background 0.15s;
        }
        .logout-btn:hover { background: rgba(239,68,68,0.1); }

        /* ── MAIN ── */
        .main {
            margin-left: 240px;
            flex: 1;
            padding: 32px;
            max-width: calc(100vw - 240px);
        }

        /* ── HEADER ── */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .page-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .page-title p {
            font-size: 0.85rem;
            color: var(--text2);
            margin-top: 2px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent2); }
        .btn-ghost { background: var(--bg3); color: var(--text); border: 1px solid var(--border); }
        .btn-ghost:hover { background: var(--border); }

        /* ── KPI CARDS ── */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .kpi-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
            transition: border-color 0.2s;
        }

        .kpi-card:hover { border-color: var(--accent); }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
        }

        .kpi-card:nth-child(1)::before { background: var(--accent2); }
        .kpi-card:nth-child(2)::before { background: var(--success); }
        .kpi-card:nth-child(3)::before { background: var(--warning); }

        .kpi-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .kpi-value {
            font-size: 2.2rem;
            font-weight: 700;
            font-family: 'DM Mono', monospace;
            letter-spacing: -1px;
            line-height: 1;
        }

        .kpi-card:nth-child(1) .kpi-value { color: var(--accent3); }
        .kpi-card:nth-child(2) .kpi-value { color: var(--success); }
        .kpi-card:nth-child(3) .kpi-value { color: var(--warning); }

        .kpi-sub {
            font-size: 0.75rem;
            color: var(--text2);
            margin-top: 6px;
        }

        /* ── MATRIX CARD ── */
        .matrix-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .card-subtitle {
            font-size: 0.75rem;
            color: var(--text2);
            margin-top: 2px;
        }

        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .badge-blue { background: rgba(37,99,235,0.15); color: var(--accent3); }

        /* ── TABLE ── */
        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }

        thead th {
            background: var(--bg3);
            padding: 12px 16px;
            text-align: center;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        thead th:first-child { text-align: left; }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background 0.1s;
        }

        tbody tr:hover { background: rgba(255,255,255,0.02); }
        tbody tr:last-child { border-bottom: none; }

        tbody td {
            padding: 13px 16px;
            text-align: center;
            font-family: 'DM Mono', monospace;
            font-size: 0.85rem;
            color: var(--text2);
        }

        tbody td:first-child {
            text-align: left;
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            color: var(--text);
        }

        .cell-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .cell-high   { background: rgba(37,99,235,0.2);  color: var(--accent3); }
        .cell-mid    { background: rgba(6,182,212,0.15); color: var(--cyan); }
        .cell-low    { background: rgba(139,148,158,0.1); color: var(--text2); }
        .cell-zero   { color: #444; }

        /* TOTALES */
        .row-total td {
            background: var(--bg3);
            font-weight: 700;
            color: var(--text) !important;
            border-top: 2px solid var(--border);
        }

        .col-total {
            background: rgba(37,99,235,0.08) !important;
            font-weight: 700 !important;
            color: var(--accent3) !important;
        }

        /* ── IMPORT SECTION ── */
        .import-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 24px;
        }

        .import-card {
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            transition: all 0.15s;
        }

        .import-card:hover {
            border-color: var(--accent);
            transform: translateY(-1px);
        }

        .import-icon {
            width: 42px; height: 42px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .import-card:nth-child(1) .import-icon { background: rgba(37,99,235,0.15); }
        .import-card:nth-child(2) .import-icon { background: rgba(16,185,129,0.15); }
        .import-card:nth-child(3) .import-icon { background: rgba(245,158,11,0.15); }

        .import-info p { font-size: 0.85rem; font-weight: 600; }
        .import-info span { font-size: 0.75rem; color: var(--text2); }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            margin-top: 32px;
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h1>📊 Plataforma</h1>
        <span>Panel de Control</span>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="index.php" class="nav-item active">
            <span class="icon">⊞</span> Dashboard
        </a>
        <div class="nav-section">Módulos</div>
        <a href="modulos/instalaciones/" class="nav-item">
            <span class="icon">🔧</span> Instalaciones
        </a>
        <a href="modulos/ventas/" class="nav-item">
            <span class="icon">💰</span> Ventas
        </a>
        <a href="modulos/hc/" class="nav-item">
            <span class="icon">👥</span> HC
        </a>
        <div class="nav-section">Importar</div>
        <a href="import/import_instalaciones.php" class="nav-item">
            <span class="icon">📥</span> Instalaciones
        </a>
        <a href="import/import_ventas.php" class="nav-item">
            <span class="icon">📥</span> Ventas
        </a>
        <a href="import/import_hc.php" class="nav-item">
            <span class="icon">📥</span> HC
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= strtoupper(substr($_SESSION['usuario'], 0, 1)) ?></div>
            <div>
                <div class="user-name"><?= htmlspecialchars($_SESSION['usuario']) ?></div>
                <div class="user-role">Administrador</div>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">⎋ Cerrar sesión</a>
    </div>
</aside>

<!-- MAIN -->
<main class="main">

    <!-- HEADER -->
    <div class="page-header">
        <div class="page-title">
            <h2>Dashboard General</h2>
            <p>Resumen de instalaciones por distrito y período</p>
        </div>
        <div class="header-actions">
            <a href="import/import_instalaciones.php" class="btn btn-primary">+ Importar datos</a>
        </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-label">Total Instalaciones</div>
            <div class="kpi-value"><?= number_format($kpi_instalaciones['total']) ?></div>
            <div class="kpi-sub">Acumulado histórico</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Distritos Activos</div>
            <div class="kpi-value"><?= $kpi_distritos['total'] ?></div>
            <div class="kpi-sub">Con registros en sistema</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Mes Actual</div>
            <div class="kpi-value"><?= number_format($kpi_mes_actual['total']) ?></div>
            <div class="kpi-sub"><?= date('F Y') ?></div>
        </div>
    </div>

    <!-- MATRIZ -->
    <div class="matrix-card">
        <div class="card-header">
            <div>
                <div class="card-title">Instalaciones por Distrito y Mes</div>
                <div class="card-subtitle">COUNT de cuentas instaladas · agrupado por período</div>
            </div>
            <span class="badge badge-blue"><?= count($distritos) ?> distritos</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Distrito</th>
                        <?php foreach ($periodos as $p => $label): ?>
                            <th><?= $label ?></th>
                        <?php endforeach; ?>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($distritos as $distrito): ?>
                    <?php
                        $total_row = $totales_distrito[$distrito] ?? 0;
                        $max_val = max(array_map('max', array_map(function($d) use ($data, $periodos) {
                            return array_map(fn($p) => $data[$d][$p] ?? 0, array_keys($periodos));
                        }, $distritos)));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($distrito) ?></td>
                        <?php foreach ($periodos as $p => $label): ?>
                        <?php
                            $val = $data[$distrito][$p] ?? 0;
                            $pct = $max_val > 0 ? $val / $max_val : 0;
                            if ($val == 0) $cls = 'cell-zero';
                            elseif ($pct >= 0.66) $cls = 'cell-high';
                            elseif ($pct >= 0.33) $cls = 'cell-mid';
                            else $cls = 'cell-low';
                        ?>
                        <td>
                            <?php if ($val > 0): ?>
                                <span class="cell-value <?= $cls ?>"><?= number_format($val) ?></span>
                            <?php else: ?>
                                <span class="cell-zero">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="col-total"><?= number_format($total_row) ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <!-- FILA TOTAL -->
                    <tr class="row-total">
                        <td>TOTAL</td>
                        <?php foreach ($periodos as $p => $label): ?>
                            <td class="col-total"><?= number_format($totales_periodo[$p]) ?></td>
                        <?php endforeach; ?>
                        <td class="col-total"><?= number_format($gran_total) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- IMPORTAR -->
    <div class="section-title">Accesos rápidos — Importar datos</div>
    <div class="import-grid">
        <a href="import/import_instalaciones.php" class="import-card">
            <div class="import-icon">🔧</div>
            <div class="import-info">
                <p>Instalaciones</p>
                <span>Subir reporte .xlsx</span>
            </div>
        </a>
        <a href="import/import_ventas.php" class="import-card">
            <div class="import-icon">💰</div>
            <div class="import-info">
                <p>Ventas</p>
                <span>Subir reporte .xlsx</span>
            </div>
        </a>
        <a href="import/import_hc.php" class="import-card">
            <div class="import-icon">👥</div>
            <div class="import-info">
                <p>Headcount</p>
                <span>Subir reporte .xlsx</span>
            </div>
        </a>
    </div>

</main>

</body>
</html>