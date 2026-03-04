<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
include 'conexion.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user  = $_POST['usuario'];
    $pass  = $_POST['password'];

    // Usamos prepared statement para evitar SQL Injection
    $stmt = mysqli_prepare($conexion, "SELECT password FROM usuarios WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $user);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $hash);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if ($hash && password_verify($pass, $hash)) {
        session_start();
        $_SESSION['usuario'] = $user;
        header("Location: index.php");
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .card {
            background: white;
            padding: 40px 36px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 380px;
        }

        h2 {
            margin-bottom: 24px;
            color: #1a1a2e;
            font-size: 1.6rem;
            text-align: center;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.85rem;
            color: #555;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 10px 14px;
            margin-bottom: 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border 0.2s;
            outline: none;
        }

        input:focus {
            border-color: #4f46e5;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4f46e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #4338ca;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            text-align: center;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.88rem;
            color: #555;
        }

        .register-link a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Iniciar Sesión</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="usuario">Numero de empleado</label>
        <input type="text" id="usuario" name="usuario" placeholder="Tu numero de empleado" required>

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>

        <button type="submit">Entrar</button>
    </form>

</div>

</body>
</html>