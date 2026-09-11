<?php
session_start();
date_default_timezone_set('America/Guayaquil');
include("conexion.php");

// Seguridad de sesión y tiempo de inactividad
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

$tiempo_inactividad = 900; 
if (isset($_SESSION['ultimo_tiempo']) && (time() - $_SESSION['ultimo_tiempo'] > $tiempo_inactividad)) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
}
$_SESSION['ultimo_tiempo'] = time();

$id_usuario_actual = $_SESSION['id_usuario'];
$hoy = date('Y-m-d');

// 1. Total de productos y stock total
$stmt_prod = $conexion->prepare("SELECT COUNT(*) as total_prod, SUM(stock) as total_stock FROM productos WHERE id_usuario = ?");
$stmt_prod->bind_param("i", $id_usuario_actual);
$stmt_prod->execute();
$res_prod = $stmt_prod->get_result()->fetch_assoc();
$total_productos = $res_prod['total_prod'] ?? 0;
$total_stock = $res_prod['total_stock'] ?? 0;
$stmt_prod->close();

// 2. Ventas e ingresos del día de hoy
$stmt_ventas = $conexion->prepare("SELECT COUNT(*) as num_ventas, SUM(total) as total_ingresos FROM ventas WHERE id_usuario = ? AND DATE(fecha_venta) = ?");
$stmt_ventas->bind_param("is", $id_usuario_actual, $hoy);
$stmt_ventas->execute();
$res_ventas = $stmt_ventas->get_result()->fetch_assoc();
$ventas_hoy = $res_ventas['num_ventas'] ?? 0;
$ingresos_hoy = $res_ventas['total_ingresos'] ?? 0.00;
$stmt_ventas->close();

// 3. Alerta de Stock Bajo (<= 3)
$stmt_stock = $conexion->prepare("SELECT nombre, stock FROM productos WHERE id_usuario = ? AND stock <= 3 ORDER BY stock ASC");
$stmt_stock->bind_param("i", $id_usuario_actual);
$stmt_stock->execute();
$resultado_stock = $stmt_stock->get_result();

// 4. Alerta de Caducidad (Próximos a vencer o vencidos en los próximos 7 días)
$fecha_limite = date('Y-m-d', strtotime('+7 days'));
$stmt_caducidad = $conexion->prepare("SELECT nombre, fecha_caducidad FROM productos WHERE id_usuario = ? AND fecha_caducidad IS NOT NULL AND fecha_caducidad <= ? ORDER BY fecha_caducidad ASC");
$stmt_caducidad->bind_param("is", $id_usuario_actual, $fecha_limite);
$stmt_caducidad->execute();
$resultado_caducidad = $stmt_caducidad->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Caserito - Panel Principal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
            --bg-color: #f4f6f9;
        }
        body { 
            background-color: var(--bg-color); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            color: #334155;
        }
        .navbar-custom { 
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%) !important; 
            box-shadow: 0 4px 15px rgba(30, 60, 114, 0.15);
        }
        .nav-link {
            transition: all 0.2s ease-in-out;
            border-radius: 6px;
            padding: 0.5rem 0.75rem !important;
        }
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card-custom { 
            border: none; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04); 
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
            overflow: hidden;
        }
        .card-custom:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items-center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.25rem;
        }
        .action-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s ease;
            text-decoration: none;
            display: block;
            background: #ffffff;
        }
        .action-card:hover {
            border-color: var(--secondary-blue);
            box-shadow: 0 4px 12px rgba(42, 82, 152, 0.08);
            transform: translateY(-2px);
        }
        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
        }
        .table-custom td {
            padding: 14px 16px;
            vertical-align: middle;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>
<body>

<!-- NAVBAR ESTILIZADA -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom py-3">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
            <div class="bg-white text-primary p-2 rounded-3 me-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="fa-solid fa-store"></i>
            </div>
            Mi Caserito
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <div class="navbar-nav ms-auto align-items-center gap-1">
                <a class="nav-link text-light active fw-semibold px-3" href="index.php"><i class="fa-solid fa-house me-1 text-info"></i> Dashboard</a>
                <a class="nav-link text-light px-3" href="productos.php"><i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Inventario</a>
                <a class="nav-link text-light px-3" href="ventas.php"><i class="fa-solid fa-cash-register me-1 text-info"></i> Caja / Ventas</a>
                <div class="d-flex align-items-center ms-lg-3 ps-lg-3 border-start border-light border-opacity-25 py-1">
                    <span class="text-light me-3"><i class="fa-solid fa-user-circle me-1"></i> Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
                    <a class="nav-link text-warning fw-bold px-2" href="logout.php"><i class="fa-solid fa-right-from-bracket me-1"></i> Salir</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- ENCABEZADO DE SECCIÓN -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark m-0">Resumen General</h3>
            <p class="text-muted small m-0 mt-1">Monitorea el inventario y las ventas de tu local en tiempo real.</p>
        </div>
        <div class="badge bg-white text-dark shadow-sm p-2 px-3 rounded-pill border">
            <i class="fa-regular fa-calendar-days text-primary me-2"></i><?php echo date('d / m / Y'); ?>
        </div>
    </div>
    
    <!-- TARJETAS DE ESTADÍSTICAS MEJORADAS -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card card-custom p-4 border-start border-primary border-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold tracking-wider">TOTAL PRODUCTOS</span>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-1"><?php echo $total_productos; ?></h2>
                <span class="text-muted small d-flex align-items-center mt-2">
                    <i class="fa-solid fa-layer-group text-primary me-1"></i> Stock acumulado: <strong class="text-dark ms-1"><?php echo $total_stock ?? 0; ?> un.</strong>
                </span>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card card-custom p-4 border-start border-success border-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold tracking-wider">VENTAS DE HOY</span>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-cash-register"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-dark mb-1"><?php echo $ventas_hoy; ?></h2>
                <span class="text-success small fw-semibold d-flex align-items-center mt-2">
                    <i class="fa-solid fa-circle-check me-1"></i> Transacciones completadas
                </span>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-custom p-4 border-start border-warning border-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span class="text-muted small fw-bold tracking-wider">INGRESOS DE HOY</span>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                </div>
                <h2 class="fw-bold text-success mb-1">$<?php echo number_format($ingresos_hoy, 2); ?></h2>
                <span class="text-muted small d-flex align-items-center mt-2">
                    <i class="fa-solid fa-wallet text-warning me-1"></i> Caja actual del día
                </span>
            </div>
        </div>
    </div>

    <!-- DOS SECCIONES DE ALERTAS: STOCK BAJO Y CADUCIDAD -->
    <div class="row g-4 mb-4">
        <!-- Alerta Stock Bajo -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3 me-3">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Alertas de Stock Bajo</h5>
                        <small class="text-muted">Productos con 3 unidades o menos</small>
                    </div>
                </div>
                
                <?php if ($resultado_stock && $resultado_stock->num_rows > 0): ?>
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>PRODUCTO</th>
                                    <th class="text-end">STOCK</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item = $resultado_stock->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($item['nombre']); ?></td>
                                        <td class="text-end"><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 fw-bold"><?php echo $item['stock']; ?> un.</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small bg-light rounded-3 border border-dashed">
                        <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                        <span>¡Excelente! No hay productos con stock crítico.</span>
                    </div>
                <?php endif; ?>
                <?php $stmt_stock->close(); ?>
            </div>
        </div>

        <!-- Alerta Caducidad -->
        <div class="col-md-6">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="bg-danger bg-opacity-10 text-danger p-2 rounded-3 me-3">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Alertas de Caducidad</h5>
                        <small class="text-muted">Próximos a vencer en 7 días</small>
                    </div>
                </div>

                <?php if ($resultado_caducidad && $resultado_caducidad->num_rows > 0): ?>
                    <div class="table-responsive rounded-3 border">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>PRODUCTO</th>
                                    <th class="text-end">VENCIMIENTO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($item_c = $resultado_caducidad->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($item_c['nombre']); ?></td>
                                        <td class="text-end"><span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2 fw-bold"><?php echo $item_c['fecha_caducidad']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted small bg-light rounded-3 border border-dashed">
                        <i class="fa-solid fa-circle-check text-success fs-4 mb-2 d-block"></i>
                        <span>No hay productos próximos a caducar.</span>
                    </div>
                <?php endif; ?>
                <?php $stmt_caducidad->close(); ?>
            </div>
        </div>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <div class="row">
        <div class="col-12">
            <div class="card card-custom p-4">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Acciones Rápidas</h5>
                        <small class="text-muted">Accesos directos operativos</small>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="ventas.php" class="action-card p-3 d-flex align-items-center justify-content-between text-decoration-none">
                            <div class="d-flex align-items-center">
                                <div class="bg-primary text-white p-3 rounded-3 me-3 shadow-sm">
                                    <i class="fa-solid fa-cash-register fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Ir a Caja / Venta</h6>
                                    <span class="text-muted small">Registrar nuevas transacciones</span>
                                </div>
                            </div>
                            <div class="text-primary pe-2">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                    
                    <div class="col-md-6">
                        <a href="productos.php" class="action-card p-3 d-flex align-items-center justify-content-between text-decoration-none">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary text-white p-3 rounded-3 me-3 shadow-sm" style="background: linear-gradient(135deg, #475569 0%, #334155 100%) !important;">
                                    <i class="fa-solid fa-boxes-stacked fa-lg"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">Administrar Inventario</h6>
                                    <span class="text-muted small">Gestionar productos y stock</span>
                                </div>
                            </div>
                            <div class="text-secondary pe-2">
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>