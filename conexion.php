<?php
$host = "localhost";
$user = "root";
$pass = ""; // En XAMPP viene vacío por defecto
$db   = "sistema_login";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>