<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Dashboard</title></head>
<body>
    <h2>Bienvenido, <?= htmlspecialchars($_SESSION['usuario']) ?>!</h2>
    <a href="logout.php">Cerrar sesión</a>
</body>
</html>