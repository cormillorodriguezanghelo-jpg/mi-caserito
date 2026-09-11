<?php
session_start();

// Seguridad: Si no ha iniciado sesión, lo devolvemos al login
if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['nombre_usuario'])) {
    header("Location: login.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre_usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Caserito - ¡Bienvenido!</title>
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
            color: #ffffff;
            margin: 0;
        }
        .welcome-container {
            width: 100%;
            max-width: 420px;
            text-align: center;
            padding: 20px;
        }
        .avatar-circle {
            width: 90px;
            height: 90px;
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
            font-size: 2.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin: 0 auto 25px auto;
            backdrop-filter: blur(5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .spinner-border {
            width: 2rem;
            height: 2rem;
            color: #ffffff;
        }
    </style>
    <!-- Redirección automática a los 5 segundos al panel principal -->
    <meta http-equiv="refresh" content="5;url=index.php">
</head>
<body>

<div class="welcome-container">
    <!-- Ícono de tiendita / kiosquito -->
    <div class="avatar-circle">
        <i class="fa-solid fa-store"></i>
    </div>
    
    <h6 class="text-white-50 text-uppercase fw-bold mb-2" style="letter-spacing: 1.5px; font-size: 0.85rem;">¡Qué gusto verte de nuevo!</h6>
    <h1 class="fw-bold text-white mb-3" style="font-size: 2.2rem;"><?php echo htmlspecialchars($nombre_usuario); ?></h1>
    
    <p class="text-white-50 small mb-4">Preparando el inventario y tu sistema de <strong>Mi Caserito</strong>...</p>

    <div class="d-flex justify-content-center mb-4">
        <div class="spinner-border" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>