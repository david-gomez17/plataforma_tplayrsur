<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['usuario'];
    $email = $_POST['email'];
    // Encriptamos la clave (NUNCA guardes texto plano)
    $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $sql = "INSERT INTO usuarios (username, password, email) VALUES ('$user', '$pass', '$email')";

    if (mysqli_query($conexion, $sql)) {
        echo "<p style='color:green;'>¡Registro exitoso! <a href='login.php'>Ir al Login</a></p>";
    } else {
        echo "Error: " . mysqli_error($conexion);
    }
}
?>

<form method="POST">
    <h2>Crear Cuenta Local</h2>
    <input type="text" name="usuario" placeholder="Nombre de usuario" required><br><br>
    <input type="email" name="email" placeholder="Correo electrónico" required><br><br>
    <input type="password" name="password" placeholder="Contraseña" required><br><br>
    <button type="submit">Registrarme</button>
</form>