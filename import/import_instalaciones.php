<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
session_start();
$_SESSION['usuario'] = 'test'; // temporal

require_once $_SERVER['DOCUMENT_ROOT'] . '/plataforma/includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

include $_SERVER['DOCUMENT_ROOT'] . '/plataforma/includes/conexion.php';
$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if ($ext !== 'xlsx') {
        $mensaje = "Solo se permiten archivos .xlsx";
        $tipo_mensaje = "error";
    } else {
        $ruta_temp = '../uploads/' . time() . '_' . $archivo['name'];
        move_uploaded_file($archivo['tmp_name'], $ruta_temp);

        if ($xlsx = SimpleXLSX::parse($ruta_temp)) {
            $filas = $xlsx->rows();
            $total = 0;
            $errores = 0;

            $v = function($val) {
                return isset($val) && $val !== '' ? trim($val) : null;
            };

            $fecha = function($val) {
                if (empty($val)) return null;
                if (is_numeric($val)) {
                    $unix = ($val - 25569) * 86400;
                    return date('Y-m-d', $unix);
                }
                $d = date_create($val);
                return $d ? date_format($d, 'Y-m-d') : null;
            };

            $precio = function($val) {
                if (empty($val)) return null;
                return floatval(str_replace(['$', ',', ' '], '', $val));
            };

            // Columnas del Excel:
            // 0  Fecha
            // 1  Cuenta
            // 2  CuentaFactura: Nombre completo / Razón Social
            // 3  Cotización: Oportunidad: Origen del prospecto
            // 4  Cotización: Oportunidad: Subcanal
            // 5  Experto
            // 6  Coach
            // 7  Lider
            // 8  PLAN
            // 9  Precio Pronto Pago
            // 10 Precio de Lista
            // 11 Cluster
            // 12 Calle
            // 13 Número exterior
            // 14 Número interior
            // 15 Colonia
            // 16 Referencia calle
            // 17 CP
            // 18 Distrito
            // 19 OS
            // 20 Forma de pago
            // 21 (Latitud)
            // 22 (Longitud)
            // 23 Teléfono lugar
            // 24 Teléfono móvil
            // 25 Folio Empleado
            // 26 Folio Coach
            // 27 Folio Lider

            for ($i = 1; $i < count($filas); $i++) {
                $f = $filas[$i];

                if (empty(array_filter($f))) continue;

                $v_fecha            = $fecha($v($f[0]));
                $v_cuenta           = $v($f[1]);
                $v_razon_social     = $v($f[2]);
                $v_origen           = $v($f[3]);
                $v_subcanal         = $v($f[4]);
                $v_experto          = $v($f[5]);
                $v_coach            = $v($f[6]);
                $v_lider            = $v($f[7]);
                $v_plan             = $v($f[8]);
                $v_precio_pronto    = $precio($v($f[9]));
                $v_precio_lista     = $precio($v($f[10]));
                $v_cluster          = $v($f[11]);
                $v_calle            = $v($f[12]);
                $v_num_exterior     = $v($f[13]);
                $v_num_interior     = $v($f[14]);
                $v_colonia          = $v($f[15]);
                $v_referencia       = $v($f[16]);
                $v_cp               = $v($f[17]);
                $v_distrito         = $v($f[18]);
                $v_os               = $v($f[19]);
                $v_forma_pago       = $v($f[20]);
                $v_latitud          = $v($f[21]);
                $v_longitud         = $v($f[22]);
                $v_tel_lugar        = $v($f[23]);
                $v_tel_movil        = $v($f[24]);
                $v_folio_empleado   = $v($f[25]);
                $v_folio_coach      = $v($f[26]);
                $v_folio_lider      = $v($f[27]);
                $v_archivo          = $archivo['name'];

                $stmt = mysqli_prepare($conexion, "INSERT INTO instalaciones (
                    fecha, cuenta, razon_social, origen_prospecto, subcanal,
                    experto, coach, lider, plan, precio_pronto_pago, precio_lista,
                    cluster, calle, numero_exterior, numero_interior, colonia,
                    referencia_calle, cp, distrito, os, forma_pago,
                    latitud, longitud, telefono_lugar, telefono_movil,
                    folio_empleado, folio_coach, folio_lider, archivo_origen
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

                mysqli_stmt_bind_param($stmt, "sssssssssddssssssssssssssssss",
                    $v_fecha, $v_cuenta, $v_razon_social, $v_origen, $v_subcanal,
                    $v_experto, $v_coach, $v_lider, $v_plan,
                    $v_precio_pronto, $v_precio_lista,
                    $v_cluster, $v_calle, $v_num_exterior, $v_num_interior, $v_colonia,
                    $v_referencia, $v_cp, $v_distrito, $v_os, $v_forma_pago,
                    $v_latitud, $v_longitud, $v_tel_lugar, $v_tel_movil,
                    $v_folio_empleado, $v_folio_coach, $v_folio_lider, $v_archivo
                );

                if (mysqli_stmt_execute($stmt)) {
                    $total++;
                } else {
                    $errores++;
                }
                mysqli_stmt_close($stmt);
            }

            $tipo_log = 'instalaciones';
            $log = mysqli_prepare($conexion, "INSERT INTO importaciones_log (tipo, archivo, registros_importados, usuario) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($log, "ssis", $tipo_log, $v_archivo, $total, $_SESSION['usuario']);
            mysqli_stmt_execute($log);
            mysqli_stmt_close($log);

            unlink($ruta_temp);
            $mensaje = "✅ Importación exitosa: $total registros importados. Errores: $errores";
            $tipo_mensaje = "exito";
        } else {
            $mensaje = "Error al leer el archivo Excel: " . SimpleXLSX::parseError();
            $tipo_mensaje = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Importar Instalaciones</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { color: #1a1a2e; margin-bottom: 8px; }
        p.sub { color: #666; font-size: 0.9rem; margin-bottom: 24px; }
        .zona-upload { border: 2px dashed #059669; border-radius: 10px; padding: 40px; text-align: center; color: #059669; cursor: pointer; margin-bottom: 20px; transition: background 0.2s; }
        .zona-upload:hover { background: #ecfdf5; }
        input[type="file"] { display: none; }
        button { width: 100%; padding: 12px; background: #059669; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #047857; }
        .exito { background: #dcfce7; color: #166534; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        .error  { background: #fee2e2; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        .back { display: block; text-align: center; margin-top: 16px; color: #059669; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>📥 Importar Instalaciones</h2>
    <p class="sub">Sube tu archivo Excel (.xlsx) con el reporte de instalaciones</p>

    <?php if ($mensaje): ?>
        <div class="<?= $tipo_mensaje ?>"><?= $mensaje ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <label for="archivo">
            <div class="zona-upload" id="zona">
                📂 Haz clic para seleccionar tu archivo .xlsx
                <br><small id="nombre-archivo"></small>
            </div>
        </label>
        <input type="file" name="archivo" id="archivo" accept=".xlsx" onchange="mostrarNombre(this)">
        <button type="submit">Importar datos</button>
    </form>
    <a href="../dashboard.php" class="back">← Volver al Dashboard</a>
</div>

<script>
function mostrarNombre(input) {
    const nombre = input.files[0]?.name || '';
    document.getElementById('nombre-archivo').textContent = nombre;
    document.getElementById('zona').style.background = '#ecfdf5';
}
</script>
</body>
</html>