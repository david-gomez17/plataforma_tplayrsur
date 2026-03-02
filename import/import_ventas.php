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
            // 0  Fecha de cierre
            // 1  Id de Cuenta BRM
            // 2  Canal de Venta
            // 3  Subcanal
            // 4  Jefe de Plaza
            // 5  Jefe de Ventas
            // 6  Distrital
            // 7  Nombre de Empleado
            // 8  Estatus Mesa de Control
            // 9  Nombre del plan
            // 10 Fecha activación
            // 11 Precio Pronto Pago
            // 12 Precio Lista
            // 13 Total (Cargo único)
            // 14 Cluster
            // 15 Distrito
            // 16 Forma de pago
            // 17 Teléfono móvil
            // 18 Plazo
            // 19 Número de oportunidad
            // 20 Folio de empleado
            // 21 Estatus
            // 22 Id OT GIM
            // 23 CP
            // 24 Calle
            // 25 Número exterior
            // 26 Número interior
            // 27 Colonia
            // 28 Delegación / Municipio
            // 29 Ciudad
            // 30 Estado
            // 31 Código postal
            // 32 Region
            // 33 Zona

            for ($i = 1; $i < count($filas); $i++) {
                $f = $filas[$i];

                if (empty(array_filter($f))) continue;

                $v_fecha_cierre     = $fecha($v($f[0]));
                $v_id_cuenta_brm    = $v($f[1]);
                $v_canal_venta      = $v($f[2]);
                $v_subcanal         = $v($f[3]);
                $v_jefe_plaza       = $v($f[4]);
                $v_jefe_ventas      = $v($f[5]);
                $v_distrital        = $v($f[6]);
                $v_nombre_empleado  = $v($f[7]);
                $v_estatus_mesa     = $v($f[8]);
                $v_nombre_plan      = $v($f[9]);
                $v_fecha_activacion = $fecha($v($f[10]));
                $v_precio_pronto    = $precio($v($f[11]));
                $v_precio_lista     = $precio($v($f[12]));
                $v_total_cargo      = $precio($v($f[13]));
                $v_cluster          = $v($f[14]);
                $v_distrito         = $v($f[15]);
                $v_forma_pago       = $v($f[16]);
                $v_telefono         = $v($f[17]);
                $v_plazo            = $v($f[18]);
                $v_num_oportunidad  = $v($f[19]);
                $v_folio_empleado   = $v($f[20]);
                $v_estatus          = $v($f[21]);
                $v_id_ot_gim        = $v($f[22]);
                $v_cp               = $v($f[23]);
                $v_calle            = $v($f[24]);
                $v_num_exterior     = $v($f[25]);
                $v_num_interior     = $v($f[26]);
                $v_colonia          = $v($f[27]);
                $v_municipio        = $v($f[28]);
                $v_ciudad           = $v($f[29]);
                $v_estado           = $v($f[30]);
                $v_codigo_postal    = $v($f[31]);
                $v_region           = $v($f[32]);
                $v_zona             = $v($f[33]);
                $v_archivo          = $archivo['name'];

                $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO ventas (
                    fecha_cierre, id_cuenta_brm, canal_venta, subcanal,
                    jefe_plaza, jefe_ventas, distrital, nombre_empleado,
                    estatus_mesa_control, nombre_plan, fecha_activacion,
                    precio_pronto_pago, precio_lista, total_cargo_unico,
                    cluster, distrito, forma_pago, telefono_movil, plazo,
                    numero_oportunidad, folio_empleado, estatus, id_ot_gim,
                    cp, calle, numero_exterior, numero_interior, colonia,
                    municipio, ciudad, estado, codigo_postal, region, zona,
                    archivo_origen
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

                mysqli_stmt_bind_param($stmt, "sssssssssssdddsssssssssssssssssssss",
                    $v_fecha_cierre, $v_id_cuenta_brm, $v_canal_venta, $v_subcanal,
                    $v_jefe_plaza, $v_jefe_ventas, $v_distrital, $v_nombre_empleado,
                    $v_estatus_mesa, $v_nombre_plan, $v_fecha_activacion,
                    $v_precio_pronto, $v_precio_lista, $v_total_cargo,
                    $v_cluster, $v_distrito, $v_forma_pago, $v_telefono, $v_plazo,
                    $v_num_oportunidad, $v_folio_empleado, $v_estatus, $v_id_ot_gim,
                    $v_cp, $v_calle, $v_num_exterior, $v_num_interior, $v_colonia,
                    $v_municipio, $v_ciudad, $v_estado, $v_codigo_postal, $v_region,
                    $v_zona, $v_archivo
                );

                if (mysqli_stmt_execute($stmt)) {
                    $total++;
                } else {
                    $errores++;
                }
                mysqli_stmt_close($stmt);
            }

            $tipo_log = 'ventas';
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
    <title>Importar Ventas</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .card { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 500px; }
        h2 { color: #1a1a2e; margin-bottom: 8px; }
        p.sub { color: #666; font-size: 0.9rem; margin-bottom: 24px; }
        .zona-upload { border: 2px dashed #4f46e5; border-radius: 10px; padding: 40px; text-align: center; color: #4f46e5; cursor: pointer; margin-bottom: 20px; transition: background 0.2s; }
        .zona-upload:hover { background: #eef2ff; }
        input[type="file"] { display: none; }
        button { width: 100%; padding: 12px; background: #4f46e5; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        button:hover { background: #4338ca; }
        .exito { background: #dcfce7; color: #166534; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        .error  { background: #fee2e2; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px; }
        .back { display: block; text-align: center; margin-top: 16px; color: #4f46e5; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
<div class="card">
    <h2>📥 Importar Ventas</h2>
    <p class="sub">Sube tu archivo Excel (.xlsx) con el reporte de ventas</p>

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
    document.getElementById('zona').style.background = '#eef2ff';
}
</script>
</body>
</html>