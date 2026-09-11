<?php
session_start();
include("conexion.php");

$error = "";
$exito = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $password = $_POST['password'];

    if (empty($nombre) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        $stmt_check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "Este nombre de usuario ya está registrado.";
        } else {
            $stmt_check->close();

            // Inserta usando las columnas exactas: nombre y password (sin encriptar)
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, password) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $nombre, $password);

            if ($stmt_insert->execute()) {
                $exito = "¡Cuenta creada con éxito! Ya puedes iniciar sesión.";
            } else {
                $error = "Error al registrar el usuario. Inténtalo de nuevo.";
            }
            $stmt_insert->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Caserito - Registro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); width: 100%; max-width: 400px; }
        .btn-primary { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border: none; }
        .btn-primary:hover { opacity: 0.9; }
    </style>
</head>
<body>

<div class="card p-4 bg-white">
    <div class="text-center mb-4">
        <h3 class="fw-bold text-dark"><i class="fa-solid fa-store me-2 text-primary"></i>Mi Caserito</h3>
        <p class="text-muted small">Crea una nueva cuenta para empezar</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($exito)): ?>
        <div class="alert alert-success py-2 small" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> <?php echo $exito; ?>
        </div>
    <?php endif; ?>

    <form action="registro.php" method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold">Usuario</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                <input type="text" name="nombre" class="form-control" placeholder="Tu usuario" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label small fw-bold">Contraseña</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="fa-solid fa-lock text-muted"></i></span>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold mb-3">Registrarse</button>
        
        <div class="text-center">
            <a href="login.php" class="text-decoration-none small text-muted"><i class="fa-solid fa-arrow-left me-1"></i> ¿Ya tienes una cuenta? Inicia sesión</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>