<?php
session_start();
include("conexion.php");

$error = "";
$mensaje = "";
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'login'; // Controla qué formulario mostrar

// PROCESAR LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'login') {
    $nombre = trim($_POST['nombre']);
    $password = $_POST['password'];

    if (empty($nombre) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
        $vista = 'login';
    } else {
        $stmt = $conexion->prepare("SELECT id_usuario, nombre, password FROM usuarios WHERE nombre = ?");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($row = $resultado->fetch_assoc()) {
            if ($password === $row['password']) {
                $_SESSION['id_usuario'] = $row['id_usuario'];
                $_SESSION['nombre_usuario'] = $row['nombre'];
                $_SESSION['ultimo_tiempo'] = time();
                
                // REDIRIGE A LA PANTALLA DE BIENVENIDA ESTILO BANCO
                header("Location: bienvenida.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
                $vista = 'login';
            }
        } else {
            $error = "El usuario no existe.";
            $vista = 'login';
        }
        $stmt->close();
    }
}

// PROCESAR REGISTRO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'registro') {
    $nombre = trim($_POST['nombre']);
    $password = $_POST['password'];
    $vista = 'registro'; // Mantiene la vista de registro si hay error

    if (empty($nombre) || empty($password)) {
        $error = "Por favor, completa todos los campos.";
    } else {
        $stmt_check = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE nombre = ?");
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $error = "El nombre de usuario ya está en uso. Elige otro.";
        } else {
            $stmt_insert = $conexion->prepare("INSERT INTO usuarios (nombre, password) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $nombre, $password);
            
            if ($stmt_insert->execute()) {
                $mensaje = "¡Registro exitoso! Ya puedes iniciar sesión.";
                $vista = 'login'; // Al registrarse con éxito, lo mandamos al login automáticamente
            } else {
                $error = "Error al registrar el usuario.";
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Caserito - Acceso al Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
        }
        body { 
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%) !important; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        .card-custom { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15); 
            width: 100%; 
            max-width: 400px; 
            background: #ffffff;
        }
        .btn-primary-custom { 
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); 
            border: none; 
        }
        .btn-primary-custom:hover { 
            opacity: 0.95; 
        }
    </style>
</head>
<body>

<div class="card card-custom p-4">
    
    <!-- MENSAJES DE ALERTA GENERALES -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small rounded-3 border-0 shadow-sm mb-3" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success py-2 small rounded-3 border-0 shadow-sm mb-3" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> <?php echo $mensaje; ?>
        </div>
    <?php endif; ?>

    <!-- VISTA 1: INICIAR SESIÓN (Por defecto) -->
    <div id="seccion-login" style="display: <?php echo ($vista == 'login') ? 'block' : 'none'; ?>;">
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 d-inline-flex mb-3 shadow-sm" style="width: 55px; height: 55px; align-items: center; justify-content: center;">
                <i class="fa-solid fa-store fs-4"></i>
            </div>
            <h3 class="fw-bold text-dark m-0">Mi Caserito</h3>
            <p class="text-muted small mt-1">Ingresa tus datos para acceder al sistema</p>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="accion" value="login">
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">USUARIO</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" name="nombre" class="form-control border-start-0" placeholder="Tu usuario" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">CONTRASEÑA</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom btn-primary w-100 py-2 fw-bold mb-3 shadow-sm">Ingresar</button>

            <div class="text-center pt-3 border-top">
                <span class="text-muted small">¿No tienes una cuenta?</span> 
                <button type="button" class="btn btn-link text-decoration-none small fw-bold p-0 ms-1" onclick="cambiarVista('registro')">Regístrate aquí</button>
            </div>
        </form>
    </div>

    <!-- VISTA 2: REGISTRO (En la misma interfaz) -->
    <div id="seccion-registro" style="display: <?php echo ($vista == 'registro') ? 'block' : 'none'; ?>;">
        <div class="text-center mb-4">
            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-4 d-inline-flex mb-3 shadow-sm" style="width: 55px; height: 55px; align-items: center; justify-content: center;">
                <i class="fa-solid fa-user-plus fs-4"></i>
            </div>
            <h3 class="fw-bold text-dark m-0">Crear Cuenta</h3>
            <p class="text-muted small mt-1">Registra un nuevo usuario para Mi Caserito</p>
        </div>

        <form action="" method="POST">
            <input type="hidden" name="accion" value="registro">
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">NUEVO USUARIO</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" name="nombre" class="form-control border-start-0" placeholder="Crea tu usuario" required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold text-muted">CONTRASEÑA</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary-custom btn-primary w-100 py-2 fw-bold mb-3 shadow-sm">
                <i class="fa-solid fa-user-plus me-1"></i> ¡Registrarme ahora!
            </button>

            <div class="text-center pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-semibold small rounded-3" onclick="cambiarVista('login')">
                    <i class="fa-solid fa-arrow-left me-1"></i> ¿Ya tienes una cuenta? Inicia sesión aquí
                </button>
            </div>
        </form>
    </div>

</div>

<!-- Script simple para alternar la vista en la misma interfaz al instante -->
<script>
function cambiarVista(vista) {
    if (vista === 'registro') {
        document.getElementById('seccion-login').style.display = 'none';
        document.getElementById('seccion-registro').style.display = 'block';
    } else {
        document.getElementById('seccion-registro').style.display = 'none';
        document.getElementById('seccion-login').style.display = 'block';
    }
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>