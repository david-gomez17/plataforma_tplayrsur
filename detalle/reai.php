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

$rol              = $_SESSION['rol'] ?? 'vendedor';
$id_posicion      = $_SESSION['id_posicion'] ?? '';
$talento_gs_coach = $_SESSION['numero_talento_gs'] ?? '';
$puestos_comerciales = ['PROMOVENDEDOR PUNTO DE VENTA','VENDEDOR','VENDEDOR NEGOCIOS','VENDEDOR NEGOCIO'];
$puestos_in = "'" . implode("','", $puestos_comerciales) . "'";

// Semana y año más recientes
$semana_actual = null; $anio_actual = null;
$res_sem = mysqli_query($conexion, "SELECT semana, anio FROM hc ORDER BY anio DESC, semana DESC LIMIT 1");
if ($res_sem && $row_sem = mysqli_fetch_assoc($res_sem)) {
    $semana_actual = (int)$row_sem['semana'];
    $anio_actual   = (int)$row_sem['anio'];
}

// Solo coaches pueden capturar
$puede_capturar = ($rol === 'coach' || $rol === 'admin');

// ── GUARDAR REAI ─────────────────────────────────────────────────────────────
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puede_capturar && isset($_POST['action']) && $_POST['action'] === 'guardar') {
    $talento_vendedor  = mysqli_real_escape_string($conexion, $_POST['numero_talento_gs'] ?? '');
    $nombre_vendedor   = mysqli_real_escape_string($conexion, $_POST['nombre_colaborador'] ?? '');
    $asunto            = $_POST['asunto'] ?? '';
    $fecha             = $_POST['fecha'] ?? '';
    $descripcion       = mysqli_real_escape_string($conexion, $_POST['descripcion'] ?? '');
    $evidencia_nombre  = '';

    $asuntos_validos = ['Retroalimentación','ECNUs','Acta Administrativa','Incidencia'];
    if (!in_array($asunto, $asuntos_validos)) {
        $mensaje = 'error:Asunto no válido';
    } else {
        // Subir evidencia
        if (!empty($_FILES['evidencia']['name'])) {
            $ext     = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg','jpeg','png','pdf','doc','docx'];
            if (in_array(strtolower($ext), $allowed)) {
                $upload_dir = '../uploads/reai/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                $nombre_archivo = time() . '_' . preg_replace('/[^a-zA-Z0-9._]/', '_', $_FILES['evidencia']['name']);
                if (move_uploaded_file($_FILES['evidencia']['tmp_name'], $upload_dir . $nombre_archivo)) {
                    $evidencia_nombre = $nombre_archivo;
                }
            } else {
                $mensaje = 'error:Formato de archivo no permitido';
            }
        }

        if (strpos($mensaje, 'error') === false) {
            $asunto_esc = mysqli_real_escape_string($conexion, $asunto);
            $sql = "INSERT INTO reai (numero_talento_gs, nombre_colaborador, asunto, fecha, descripcion, evidencia, capturado_por, talento_gs_coach, id_posicion_coach)
                    VALUES ('$talento_vendedor','$nombre_vendedor','$asunto_esc','$fecha','$descripcion','$evidencia_nombre','$talento_gs_coach','$talento_gs_coach','$id_posicion')";
            if (mysqli_query($conexion, $sql)) {
                $mensaje = 'ok:Registro guardado correctamente';
            } else {
                $mensaje = 'error:Error al guardar: ' . mysqli_error($conexion);
            }
        }
    }
    // Respuesta AJAX
    header('Content-Type: application/json');
    $parts = explode(':', $mensaje, 2);
    echo json_encode(['status' => $parts[0], 'msg' => $parts[1] ?? '']);
    exit();
}

// ── OBTENER HISTORIAL REAI (AJAX) ────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'historial') {
    $talento = mysqli_real_escape_string($conexion, $_GET['talento_gs'] ?? '');
    $res = mysqli_query($conexion, "SELECT * FROM reai WHERE numero_talento_gs = '$talento' ORDER BY fecha DESC, created_at DESC");
    $registros = [];
    while ($row = mysqli_fetch_assoc($res)) $registros[] = $row;
    header('Content-Type: application/json');
    echo json_encode($registros);
    exit();
}

// ── OBTENER VENDEDORES DEL COACH ─────────────────────────────────────────────
$vendedores = [];
if ($semana_actual && $anio_actual) {
    $sql_vend = "SELECT v.nombre_colaborador, v.numero_talento_gs, v.fecha_alta,
                 TIMESTAMPDIFF(MONTH, v.fecha_alta, CURDATE()) as antiguedad
                 FROM hc v
                 WHERE v.posicion_lr = ? AND v.posicion IN ($puestos_in)
                 AND v.semana = ? AND v.anio = ?
                 AND v.numero_talento_gs NOT LIKE '%VACANTE%'
                 ORDER BY v.nombre_colaborador";
    $stmt = mysqli_prepare($conexion, $sql_vend);
    mysqli_stmt_bind_param($stmt, "sii", $id_posicion, $semana_actual, $anio_actual);
    mysqli_stmt_execute($stmt);
    $res_vend = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res_vend)) $vendedores[] = $row;
    mysqli_stmt_close($stmt);
}

// Contar REAIs por vendedor y asunto
$reai_counts = [];
if (!empty($vendedores)) {
    $talentos = array_column($vendedores, 'numero_talento_gs');
    $ph = implode(',', array_fill(0, count($talentos), '?'));
    $stmt_c = mysqli_prepare($conexion, "SELECT numero_talento_gs, asunto, COUNT(*) as total FROM reai WHERE numero_talento_gs IN ($ph) GROUP BY numero_talento_gs, asunto");
    $tipos = str_repeat('s', count($talentos));
    mysqli_stmt_bind_param($stmt_c, $tipos, ...$talentos);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    while ($row = mysqli_fetch_assoc($res_c)) {
        $reai_counts[$row['numero_talento_gs']][$row['asunto']] = $row['total'];
    }
    mysqli_stmt_close($stmt_c);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>REAI — TOTALXPEDIENT</title>
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
        table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        thead th { background: var(--blue); color: white; padding: 12px 16px; text-align: left; font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.5px; }
        thead th.center { text-align: center; }
        tbody tr { border-bottom: 1px solid var(--border); }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover td { background: #f8faff; }
        td { padding: 11px 16px; vertical-align: middle; }
        td.center { text-align: center; }

        .antiguedad { font-size: 0.75rem; color: var(--text2); }

        /* BADGES REAI */
        .reai-badge { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; transition: all 0.15s; border: none; }
        .reai-badge.has-data { background: #e8f0fe; color: var(--blue); }
        .reai-badge.no-data  { background: #f4f6fb; color: #d1d5db; }
        .reai-badge.can-add  { background: #f0fdf4; color: #059669; }
        .reai-badge:hover    { transform: scale(1.15); }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: white; border-radius: 16px; padding: 28px; width: 100%; max-width: 520px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1rem; font-weight: 700; }
        .modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text2); line-height: 1; }

        /* FORM */
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--text2); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px;
            font-size: 0.9rem; font-family: inherit; outline: none; transition: border 0.2s;
        }
        .form-group select:focus, .form-group input:focus, .form-group textarea:focus { border-color: var(--blue); }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .btn-primary { width: 100%; padding: 12px; background: var(--blue); color: white; border: none; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
        .btn-primary:hover { background: #1d4ed8; }
        .btn-primary:disabled { background: #9ca3af; cursor: not-allowed; }

        /* HISTORIAL */
        .historial-item { border: 1px solid var(--border); border-radius: 10px; padding: 14px; margin-bottom: 10px; }
        .historial-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .historial-asunto { font-size: 0.8rem; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .asunto-r  { background: #dbeafe; color: #1d4ed8; }
        .asunto-e  { background: #fef3c7; color: #92400e; }
        .asunto-a  { background: #fee2e2; color: #991b1b; }
        .asunto-i  { background: #f3e8ff; color: #6b21a8; }
        .historial-fecha { font-size: 0.75rem; color: var(--text2); }
        .historial-desc { font-size: 0.82rem; color: var(--text); margin-top: 6px; }
        .historial-evidencia { margin-top: 8px; }
        .historial-evidencia a { font-size: 0.78rem; color: var(--blue); text-decoration: none; font-weight: 600; }

        .toast { position: fixed; bottom: 24px; right: 24px; background: #1a2540; color: white; padding: 12px 20px; border-radius: 10px; font-size: 0.85rem; font-weight: 600; z-index: 9999; display: none; }
        .toast.show { display: block; }
        .toast.success { background: #065f46; }
        .toast.error   { background: #991b1b; }

        .empty-state { text-align: center; padding: 40px; color: var(--text2); font-size: 0.88rem; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">📊</div>
    <div class="sidebar-brand">TOTALXPEDIENT</div>
    <a href="../index.php" class="nav-item"><span class="nav-icon">⊞</span> Dashboard</a>
    <a href="../import/import_instalaciones.php" class="nav-item"><span class="nav-icon">🔧</span> Instalaciones</a>
    <a href="../import/import_ventas.php" class="nav-item"><span class="nav-icon">📈</span> Ventas</a>
    <a href="hc_detalle.php" class="nav-item"><span class="nav-icon">👥</span> Headcount</a>
    <a href="reai.php" class="nav-item active"><span class="nav-icon">📋</span> REAI</a>
    <div class="sidebar-bottom">
        <a href="../logout.php" class="logout-btn">⎋ Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-header">
        <h2>REAI — Seguimiento de Colaboradores</h2>
        <p><?= date('d \d\e F Y') ?> · 
        <?php if ($puede_capturar): ?>
            <span style="color:#059669;font-weight:700;">✓ Puedes capturar registros</span>
        <?php else: ?>
            <span style="color:var(--text2);">Solo visualización</span>
        <?php endif; ?>
        </p>
    </div>

    <?php if (empty($vendedores)): ?>
        <div class="table-card">
            <div class="empty-state">No se encontraron colaboradores asignados a tu equipo.</div>
        </div>
    <?php else: ?>
    <div class="table-card">
        <table>
            <thead>
                <tr>
                    <th>Colaborador</th>
                    <th class="center">Antigüedad</th>
                    <th class="center">R</th>
                    <th class="center">E</th>
                    <th class="center">A</th>
                    <th class="center">I</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vendedores as $vend):
                $tgs     = $vend['numero_talento_gs'];
                $nombre  = $vend['nombre_colaborador'];
                $antig   = $vend['antiguedad'] ?? 0;
                $counts  = $reai_counts[$tgs] ?? [];
                $cnt_r   = $counts['Retroalimentación'] ?? 0;
                $cnt_e   = $counts['ECNUs'] ?? 0;
                $cnt_a   = $counts['Acta Administrativa'] ?? 0;
                $cnt_i   = $counts['Incidencia'] ?? 0;
            ?>
            <tr>
                <td>
                    <div style="font-weight:600;"><?= htmlspecialchars($nombre) ?></div>
                    <div class="antiguedad"><?= $tgs ?></div>
                </td>
                <td class="center">
                    <span style="font-weight:700;"><?= $antig ?></span>
                    <span class="antiguedad"> m</span>
                </td>
                <!-- R -->
                <td class="center">
                    <?php if ($puede_capturar): ?>
                        <button class="reai-badge <?= $cnt_r > 0 ? 'has-data' : 'can-add' ?>"
                            onclick="abrirModal('<?= htmlspecialchars($tgs) ?>','<?= htmlspecialchars(addslashes($nombre)) ?>','Retroalimentación')"
                            title="<?= $cnt_r > 0 ? "Ver/Agregar Retroalimentación ($cnt_r)" : 'Agregar Retroalimentación' ?>">
                            <?= $cnt_r > 0 ? $cnt_r : '+' ?>
                        </button>
                    <?php else: ?>
                        <button class="reai-badge <?= $cnt_r > 0 ? 'has-data' : 'no-data' ?>"
                            onclick="<?= $cnt_r > 0 ? "verHistorial('$tgs','$nombre','Retroalimentación')" : '' ?>"
                            title="<?= $cnt_r > 0 ? "Ver Retroalimentación ($cnt_r)" : 'Sin registros' ?>">
                            <?= $cnt_r > 0 ? $cnt_r : '—' ?>
                        </button>
                    <?php endif; ?>
                </td>
                <!-- E -->
                <td class="center">
                    <?php if ($puede_capturar): ?>
                        <button class="reai-badge <?= $cnt_e > 0 ? 'has-data' : 'can-add' ?>"
                            onclick="abrirModal('<?= htmlspecialchars($tgs) ?>','<?= htmlspecialchars(addslashes($nombre)) ?>','ECNUs')"
                            title="<?= $cnt_e > 0 ? "Ver/Agregar ECNUs ($cnt_e)" : 'Agregar ECNU' ?>">
                            <?= $cnt_e > 0 ? $cnt_e : '+' ?>
                        </button>
                    <?php else: ?>
                        <button class="reai-badge <?= $cnt_e > 0 ? 'has-data' : 'no-data' ?>"
                            onclick="<?= $cnt_e > 0 ? "verHistorial('$tgs','$nombre','ECNUs')" : '' ?>"
                            title="<?= $cnt_e > 0 ? "Ver ECNUs ($cnt_e)" : 'Sin registros' ?>">
                            <?= $cnt_e > 0 ? $cnt_e : '—' ?>
                        </button>
                    <?php endif; ?>
                </td>
                <!-- A -->
                <td class="center">
                    <?php if ($puede_capturar): ?>
                        <button class="reai-badge <?= $cnt_a > 0 ? 'has-data' : 'can-add' ?>"
                            onclick="abrirModal('<?= htmlspecialchars($tgs) ?>','<?= htmlspecialchars(addslashes($nombre)) ?>','Acta Administrativa')"
                            title="<?= $cnt_a > 0 ? "Ver/Agregar Acta ($cnt_a)" : 'Agregar Acta' ?>">
                            <?= $cnt_a > 0 ? $cnt_a : '+' ?>
                        </button>
                    <?php else: ?>
                        <button class="reai-badge <?= $cnt_a > 0 ? 'has-data' : 'no-data' ?>"
                            onclick="<?= $cnt_a > 0 ? "verHistorial('$tgs','$nombre','Acta Administrativa')" : '' ?>"
                            title="<?= $cnt_a > 0 ? "Ver Actas ($cnt_a)" : 'Sin registros' ?>">
                            <?= $cnt_a > 0 ? $cnt_a : '—' ?>
                        </button>
                    <?php endif; ?>
                </td>
                <!-- I -->
                <td class="center">
                    <?php if ($puede_capturar): ?>
                        <button class="reai-badge <?= $cnt_i > 0 ? 'has-data' : 'can-add' ?>"
                            onclick="abrirModal('<?= htmlspecialchars($tgs) ?>','<?= htmlspecialchars(addslashes($nombre)) ?>','Incidencia')"
                            title="<?= $cnt_i > 0 ? "Ver/Agregar Incidencia ($cnt_i)" : 'Agregar Incidencia' ?>">
                            <?= $cnt_i > 0 ? $cnt_i : '+' ?>
                        </button>
                    <?php else: ?>
                        <button class="reai-badge <?= $cnt_i > 0 ? 'has-data' : 'no-data' ?>"
                            onclick="<?= $cnt_i > 0 ? "verHistorial('$tgs','$nombre','Incidencia')" : '' ?>"
                            title="<?= $cnt_i > 0 ? "Ver Incidencias ($cnt_i)" : 'Sin registros' ?>">
                            <?= $cnt_i > 0 ? $cnt_i : '—' ?>
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<!-- MODAL CAPTURA / HISTORIAL -->
<div class="modal-overlay" id="modalOverlay" onclick="cerrarModal(event)">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">REAI</div>
            <button class="modal-close" onclick="cerrarModalBtn()">×</button>
        </div>
        <div id="modalBody"></div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<script>
let currentTalento = '';
let currentNombre  = '';
let currentAsunto  = '';

const asuntoColors = {
    'Retroalimentación': 'asunto-r',
    'ECNUs':             'asunto-e',
    'Acta Administrativa': 'asunto-a',
    'Incidencia':        'asunto-i',
};

function abrirModal(talento, nombre, asunto) {
    currentTalento = talento;
    currentNombre  = nombre;
    currentAsunto  = asunto;

    document.getElementById('modalTitle').textContent = nombre + ' — ' + asunto;
    document.getElementById('modalOverlay').classList.add('open');

    const puedeCapturar = <?= $puede_capturar ? 'true' : 'false' ?>;

    // Cargar historial + formulario
    fetch('reai.php?action=historial&talento_gs=' + encodeURIComponent(talento))
        .then(r => r.json())
        .then(data => {
            const filtrados = data.filter(r => r.asunto === asunto);
            let html = '';

            if (filtrados.length > 0) {
                html += '<h4 style="font-size:0.8rem;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">Historial (' + filtrados.length + ')</h4>';
                filtrados.forEach(r => {
                    html += `<div class="historial-item">
                        <div class="historial-header">
                            <span class="historial-asunto ${asuntoColors[r.asunto] || ''}">${r.asunto}</span>
                            <span class="historial-fecha">${r.fecha}</span>
                        </div>
                        <div class="historial-desc">${r.descripcion || '—'}</div>
                        ${r.evidencia ? `<div class="historial-evidencia"><a href="../uploads/reai/${r.evidencia}" target="_blank">📎 Ver evidencia</a></div>` : ''}
                    </div>`;
                });
            } else {
                html += '<div style="text-align:center;padding:16px 0;color:var(--text2);font-size:0.85rem;">Sin registros previos</div>';
            }

            if (puedeCapturar) {
                const hoy = new Date().toISOString().split('T')[0];
                html += `
                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
                <h4 style="font-size:0.8rem;color:var(--text2);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px;">Nuevo registro</h4>
                <div class="form-group">
                    <label>Asunto</label>
                    <select id="f_asunto">
                        <option value="Retroalimentación" ${asunto==='Retroalimentación'?'selected':''}>Retroalimentación</option>
                        <option value="ECNUs" ${asunto==='ECNUs'?'selected':''}>ECNUs</option>
                        <option value="Acta Administrativa" ${asunto==='Acta Administrativa'?'selected':''}>Acta Administrativa</option>
                        <option value="Incidencia" ${asunto==='Incidencia'?'selected':''}>Incidencia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" id="f_fecha" value="${hoy}">
                </div>
                <div class="form-group">
                    <label>Descripción</label>
                    <textarea id="f_descripcion" placeholder="Escribe los detalles..."></textarea>
                </div>
                <div class="form-group">
                    <label>Evidencia (jpg, png, pdf, doc)</label>
                    <input type="file" id="f_evidencia" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                </div>
                <button class="btn-primary" id="btnGuardar" onclick="guardarReai()">Guardar registro</button>`;
            }

            document.getElementById('modalBody').innerHTML = html;
        });
}

function verHistorial(talento, nombre, asunto) {
    abrirModal(talento, nombre, asunto);
}

function guardarReai() {
    const btn = document.getElementById('btnGuardar');
    btn.disabled = true;
    btn.textContent = 'Guardando...';

    const formData = new FormData();
    formData.append('action', 'guardar');
    formData.append('numero_talento_gs', currentTalento);
    formData.append('nombre_colaborador', currentNombre);
    formData.append('asunto', document.getElementById('f_asunto').value);
    formData.append('fecha', document.getElementById('f_fecha').value);
    formData.append('descripcion', document.getElementById('f_descripcion').value);
    const ev = document.getElementById('f_evidencia');
    if (ev.files[0]) formData.append('evidencia', ev.files[0]);

    fetch('reai.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                mostrarToast(data.msg, 'success');
                cerrarModalBtn();
                setTimeout(() => location.reload(), 800);
            } else {
                mostrarToast(data.msg, 'error');
                btn.disabled = false;
                btn.textContent = 'Guardar registro';
            }
        })
        .catch(() => {
            mostrarToast('Error de conexión', 'error');
            btn.disabled = false;
            btn.textContent = 'Guardar registro';
        });
}

function cerrarModal(e) {
    if (e.target === document.getElementById('modalOverlay')) cerrarModalBtn();
}
function cerrarModalBtn() {
    document.getElementById('modalOverlay').classList.remove('open');
    document.getElementById('modalBody').innerHTML = '';
}

function mostrarToast(msg, tipo) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast show ' + tipo;
    setTimeout(() => t.className = 'toast', 3000);
}
</script>
</body>
</html>