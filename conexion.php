<?php
$host = "localhost";
$user = "u873376862_admin_tplay";
$pass = "Regionsur7";
$db   = "u873376862_sistema_login";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>