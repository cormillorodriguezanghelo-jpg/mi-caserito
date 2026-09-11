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
$mensaje = "";
$error = "";

// PROCESAR NUEVA VENTA
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'registrar_venta') {
    $id_producto = $_POST['id_producto'];
    $cantidad_vendida = intval($_POST['cantidad']);

    if (empty($id_producto) || $cantidad_vendida <= 0) {
        $error = "Por favor, selecciona un producto válido y una cantidad mayor a 0.";
    } else {
        $stmt_check = $conexion->prepare("SELECT nombre, precio_venta, stock FROM productos WHERE id_producto = ? AND id_usuario = ?");
        $stmt_check->bind_param("ii", $id_producto, $id_usuario_actual);
        $stmt_check->execute();
        $res_prod = $stmt_check->get_result();

        if ($res_prod->num_rows > 0) {
            $prod = $res_prod->fetch_assoc();
            $stock_actual = $prod['stock'];
            $precio_unitario = $prod['precio_venta'];
            $nombre_producto = $prod['nombre'];

            if ($cantidad_vendida > $stock_actual) {
                $error = "Stock insuficiente. Solo hay $stock_actual unidades disponibles de '$nombre_producto'.";
            } else {
                $total_venta = $precio_unitario * $cantidad_vendida;
                $nuevo_stock = $stock_actual - $cantidad_vendida;

                $conexion->begin_transaction();

                try {
                    $stmt_venta = $conexion->prepare("INSERT INTO ventas (id_usuario, id_producto, cantidad, precio_unitario, total, fecha_venta) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt_venta->bind_param("iiidd", $id_usuario_actual, $id_producto, $cantidad_vendida, $precio_unitario, $total_venta);
                    $stmt_venta->execute();
                    $stmt_venta->close();

                    $stmt_stock = $conexion->prepare("UPDATE productos SET stock = ? WHERE id_producto = ? AND id_usuario = ?");
                    $stmt_stock->bind_param("iii", $nuevo_stock, $id_producto, $id_usuario_actual);
                    $stmt_stock->execute();
                    $stmt_stock->close();

                    $conexion->commit();
                    $mensaje = "¡Venta registrada con éxito! Total cobrado: $" . number_format($total_venta, 2);

                } catch (Exception $e) {
                    $conexion->rollback();
                    $error = "Error al procesar la venta. Inténtalo de nuevo.";
                }
            }
        } else {
            $error = "El producto seleccionado no existe.";
        }
        $stmt_check->close();
    }
}

// PROCESAR ANULACIÓN DE VENTA
if (isset($_GET['anular'])) {
    $id_venta = intval($_GET['anular']);

    $stmt_v_info = $conexion->prepare("SELECT id_producto, cantidad FROM ventas WHERE id_venta = ? AND id_usuario = ?");
    $stmt_v_info->bind_param("ii", $id_venta, $id_usuario_actual);
    $stmt_v_info->execute();
    $res_v_info = $stmt_v_info->get_result();

    if ($res_v_info->num_rows > 0) {
        $datos_venta = $res_v_info->fetch_assoc();
        $id_prod_afectado = $datos_venta['id_producto'];
        $cantidad_a_devolver = $datos_venta['cantidad'];
        $stmt_v_info->close();

        $conexion->begin_transaction();

        try {
            $stmt_dev_stock = $conexion->prepare("UPDATE productos SET stock = stock + ? WHERE id_producto = ? AND id_usuario = ?");
            $stmt_dev_stock->bind_param("iii", $cantidad_a_devolver, $id_prod_afectado, $id_usuario_actual);
            $stmt_dev_stock->execute();
            $stmt_dev_stock->close();

            $stmt_del_venta = $conexion->prepare("DELETE FROM ventas WHERE id_venta = ? AND id_usuario = ?");
            $stmt_del_venta->bind_param("ii", $id_venta, $id_usuario_actual);
            $stmt_del_venta->execute();
            $stmt_del_venta->close();

            $conexion->commit();
            $mensaje = "Venta anulada correctamente. El stock ha sido devuelto al inventario.";

        } catch (Exception $e) {
            $conexion->rollback();
            $error = "No se pudo anular la venta. Inténtalo de nuevo.";
        }
    } else {
        $error = "La venta que intentas anular no existe o no tienes permisos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Caserito - Caja y Ventas</title>
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
        .nav-link-menu {
            transition: all 0.2s ease-in-out;
            border-radius: 6px;
            padding: 0.5rem 0.75rem !important;
            color: #fff !important;
            text-decoration: none;
        }
        .nav-link-menu:hover {
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
        .table-custom th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 16px;
            position: sticky;
            top: 0;
            z-index: 10;
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
        .tabla-scroll-contenedor {
            max-height: 400px;
            overflow-y: auto;
        }
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); 
            border: none; 
        }
        .btn-primary:hover { 
            opacity: 0.9; 
        }
        #lista-sugerencias {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 220px;
            overflow-y: auto;
            background: white;
            border: 1px solid #cbd5e1;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            display: none;
        }
        .sugerencia-item {
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.9rem;
        }
        .sugerencia-item:hover {
            background-color: #f8fafc;
            color: var(--primary-blue);
        }
        /* Estilos personalizados para las pestañas de secciones */
        .seccion-panel {
            display: none;
        }
        .seccion-panel.activo {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
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
                <a class="nav-link-menu px-3" href="index.php"><i class="fa-solid fa-house me-1 text-info"></i> Dashboard</a>
                <a class="nav-link-menu px-3" href="productos.php"><i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Inventario</a>
                <a class="nav-link-menu fw-semibold px-3 bg-white bg-opacity-10 rounded-2" href="ventas.php"><i class="fa-solid fa-cash-register me-1 text-info"></i> Caja / Ventas</a>
                <div class="d-flex align-items-center ms-lg-3 ps-lg-3 border-start border-light border-opacity-25 py-1">
                    <span class="text-light me-3"><i class="fa-solid fa-user-circle me-1"></i> Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
                    <a class="nav-link-menu fw-bold px-2" href="logout.php" style="color: #ffc107 !important;"><i class="fa-solid fa-right-from-bracket me-1"></i> Salir</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container py-4">
    <!-- ENCABEZADO DE SECCIÓN -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-dark m-0">Caja y Registro de Ventas</h3>
            <p class="text-muted small m-0 mt-1">Control diario de cobros y resumen semanal por fecha.</p>
        </div>
        <div>
            <div class="badge bg-white text-dark shadow-sm p-2 px-3 rounded-pill border d-flex align-items-center">
                <i class="fa-regular fa-calendar-days text-primary me-2"></i><?php echo date('d / m / Y'); ?>
            </div>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i> <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: FORMULARIO DE COBRO RÁPIDO -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Nueva Venta</h5>
                        <small class="text-muted">Cobro y salida de inventario</small>
                    </div>
                </div>

                <form action="ventas.php" method="POST" autocomplete="off">
                    <input type="hidden" name="accion" value="registrar_venta">
                    <input type="hidden" name="id_producto" id="id_producto" required>
                    
                    <div class="mb-3 position-relative">
                        <label class="form-label small fw-bold">Buscar Producto</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="buscador_producto" class="form-control" placeholder="Escribe el nombre del producto..." oninput="filtrarProductos()" required>
                        </div>
                        <div id="lista-sugerencias"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cantidad a Vender</label>
                        <input type="number" name="cantidad" id="cantidad" class="form-control" value="1" min="1" required oninput="calcularTotal()">
                    </div>

                    <div class="mb-3 p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted small">Precio Unitario:</span>
                            <span id="lbl_precio" class="fw-bold text-dark">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted small">Total a Cobrar:</span>
                            <span id="lbl_total" class="fw-bold text-success fs-5">$0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        <i class="fa-solid fa-cash-register me-1"></i> Cobrar y Registrar
                    </button>
                </form>
            </div>
        </div>

        <!-- COLUMNA DERECHA: PESTAÑAS MANUALES (HOY VS HISTORIAL SEMANAL) -->
        <div class="col-lg-8">
            <div class="card card-custom p-4 h-100 d-flex flex-column">
                
                <!-- BOTONES DE NAVEGACIÓN -->
                <div class="d-flex gap-2 mb-3">
                    <button type="button" id="btn-tab-hoy" class="btn btn-primary btn-sm px-3 py-2 fw-bold" onclick="cambiarSeccion('hoy')">
                        <i class="fa-solid fa-sun me-1"></i> Ventas de Hoy
                    </button>
                    <button type="button" id="btn-tab-semana" class="btn btn-outline-secondary btn-sm px-3 py-2 fw-bold" onclick="cambiarSeccion('semana')">
                        <i class="fa-solid fa-chart-line me-1"></i> Resumen Últimos 7 Días
                    </button>
                </div>

                <!-- CONTENIDO 1: VENTAS DE HOY -->
                <div id="seccion-hoy" class="seccion-panel activo">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <small class="text-muted">Transacciones realizadas en la jornada actual</small>
                        <div style="width: 220px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-filter text-muted"></i></span>
                                <input type="text" id="buscador_tabla_ventas" class="form-control" placeholder="Filtrar hoy..." onkeyup="filtrarTablaVentas()">
                            </div>
                        </div>
                    </div>

                    <div class="tabla-scroll-contenedor rounded-3 border flex-grow-1">
                        <table class="table table-custom mb-0" id="tabla_ventas">
                            <thead>
                                <tr>
                                    <th>HORA</th>
                                    <th>PRODUCTO</th>
                                    <th>CANT.</th>
                                    <th>TOTAL</th>
                                    <th class="text-end">ACCIÓN</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_hist = $conexion->prepare("
                                    SELECT v.id_venta, v.fecha_venta, p.nombre, v.cantidad, v.total 
                                    FROM ventas v 
                                    JOIN productos p ON v.id_producto = p.id_producto 
                                    WHERE v.id_usuario = ? AND DATE(v.fecha_venta) = CURDATE()
                                    ORDER BY v.fecha_venta DESC
                                ");
                                $stmt_hist->bind_param("i", $id_usuario_actual);
                                $stmt_hist->execute();
                                $res_hist = $stmt_hist->get_result();

                                if ($res_hist && $res_hist->num_rows > 0) {
                                    while ($row = $res_hist->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td class='text-muted small'>".date("H:i", strtotime($row['fecha_venta']))."</td>";
                                        echo "<td class='fw-semibold text-dark'>".htmlspecialchars($row['nombre'])."</td>";
                                        echo "<td><span class='badge bg-secondary bg-opacity-10 text-secondary px-2 py-1'>".$row['cantidad']." un.</span></td>";
                                        echo "<td class='fw-bold text-success'>$".number_format($row['total'], 2)."</td>";
                                        echo "<td class='text-end'>
                                                <button type='button' class='btn btn-outline-danger btn-sm px-2 py-1' onclick='abrirModalAnular(".$row['id_venta'].", \"".htmlspecialchars($row['nombre'], ENT_QUOTES)."\", ".$row['cantidad'].")' title='Anular Venta'><i class='fa-solid fa-ban me-1'></i> Anular</button>
                                             </td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='5' class='text-center text-muted py-5'><i class='fa-solid fa-receipt fa-2x mb-2 text-secondary opacity-50'></i><br>No hay ventas registradas el día de hoy.</td></tr>";
                                }
                                $stmt_hist->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- CONTENIDO 2: RESUMEN DE LOS ÚLTIMOS 7 DÍAS -->
                <div id="seccion-semana" class="seccion-panel">
                    <div class="mb-3">
                        <small class="text-muted">Desglose de ingresos totales agrupados por cada día de la última semana.</small>
                    </div>

                    <div class="tabla-scroll-contenedor rounded-3 border flex-grow-1">
                        <table class="table table-custom mb-0">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>TRANSACCIONES</th>
                                    <th class="text-end">TOTAL DEL DÍA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_sem = $conexion->prepare("
                                    SELECT DATE(v.fecha_venta) as fecha, COUNT(v.id_venta) as total_transacciones, SUM(v.cantidad) as total_unidades, SUM(v.total) as ingresos_dia 
                                    FROM ventas v 
                                    WHERE v.id_usuario = ? AND v.fecha_venta >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                    GROUP BY DATE(v.fecha_venta)
                                    ORDER BY fecha DESC
                                ");
                                $stmt_sem->bind_param("i", $id_usuario_actual);
                                $stmt_sem->execute();
                                $res_sem = $stmt_sem->get_result();

                                if ($res_sem && $res_sem->num_rows > 0) {
                                    while ($sem = $res_sem->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td class='fw-semibold text-dark'><i class='fa-regular fa-calendar me-1 text-primary'></i> ".date("d/m/Y", strtotime($sem['fecha']))."</td>";
                                        echo "<td><span class='badge bg-info bg-opacity-10 text-info px-2 py-1'>".$sem['total_transacciones']." ventas (".$sem['total_unidades']." un.)</span></td>";
                                        echo "<td class='fw-bold text-success text-end fs-6'>$".number_format($sem['ingresos_dia'], 2)."</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='3' class='text-center text-muted py-5'><i class='fa-solid fa-chart-pie fa-2x mb-2 text-secondary opacity-50'></i><br>Aún no hay registros en los últimos 7 días.</td></tr>";
                                }
                                $stmt_sem->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- MODAL DE CONFIRMACIÓN DE ANULACIÓN -->
<div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white px-4 py-3">
                <h5 class="modal-title fw-bold fs-6"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirmar Anulación de Venta</h5>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4 px-4">
                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle d-inline-flex mb-3" style="width: 60px; height: 60px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-triangle-exclamation fs-3"></i>
                </div>
                <p class="text-dark fw-semibold mb-1">¿Estás seguro de que deseas anular la venta de <span id="txt_producto" class="text-primary"></span>?</p>
                <p class="text-muted small m-0">Se devolverán <span id="txt_cantidad" class="fw-bold"></span> unidades de vuelta al stock del inventario.</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 py-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btn_confirmar_anulacion" class="btn btn-danger btn-sm px-4 fw-bold rounded-pill">Sí, Anular Venta</a>
            </div>
        </div>
    </div>
</div>

<!-- SCRIPTS DE FUNCIONALIDAD E INTERACTIVIDAD -->
<script>
// Función para cambiar de sección de manera directa y segura por JS
function cambiarSeccion(seccion) {
    let btnHoy = document.getElementById('btn-tab-hoy');
    let btnSemana = document.getElementById('btn-tab-semana');
    let panelHoy = document.getElementById('seccion-hoy');
    let panelSemana = document.getElementById('seccion-semana');

    if (seccion === 'hoy') {
        btnHoy.className = 'btn btn-primary btn-sm px-3 py-2 fw-bold';
        btnSemana.className = 'btn btn-outline-secondary btn-sm px-3 py-2 fw-bold';
        panelHoy.classList.add('activo');
        panelSemana.classList.remove('activo');
    } else {
        btnSemana.className = 'btn btn-primary btn-sm px-3 py-2 fw-bold';
        btnHoy.className = 'btn btn-outline-secondary btn-sm px-3 py-2 fw-bold';
        panelSemana.classList.add('activo');
        panelHoy.classList.remove('activo');
    }
}

const productosBD = [
    <?php
    $stmt_sel = $conexion->prepare("SELECT id_producto, nombre, precio_venta, stock FROM productos WHERE id_usuario = ? AND stock > 0 ORDER BY nombre ASC");
    $stmt_sel->bind_param("i", $id_usuario_actual);
    $stmt_sel->execute();
    $res_sel = $stmt_sel->get_result();

    $productos_array = [];
    while($p = $res_sel->fetch_assoc()) {
        $productos_array[] = "{id: ".$p['id_producto'].", nombre: ".json_encode($p['nombre']).", precio: ".$p['precio_venta'].", stock: ".$p['stock']."}";
    }
    $stmt_sel->close();
    echo implode(",", $productos_array);
    ?>
];

let productoSeleccionado = { precio: 0, stock: 1 };

function filtrarProductos() {
    let input = document.getElementById('buscador_producto').value.toLowerCase().trim();
    let listaDiv = document.getElementById('lista-sugerencias');
    listaDiv.innerHTML = '';

    if (input.length === 0) {
        listaDiv.style.display = 'none';
        return;
    }

    let resultados = productosBD.filter(p => p.nombre.toLowerCase().includes(input));

    if (resultados.length > 0) {
        listaDiv.style.display = 'block';
        resultados.forEach(prod => {
            let item = document.createElement('div');
            item.className = 'sugerencia-item';
            item.innerHTML = `<strong>${prod.nombre}</strong> <span class="text-muted float-end">Stock: ${prod.stock} | $${prod.precio.toFixed(2)}</span>`;
            item.onclick = function() {
                seleccionarProducto(prod.id, prod.nombre, prod.precio, prod.stock);
            };
            listaDiv.appendChild(item);
        });
    } else {
        listaDiv.style.display = 'block';
        listaDiv.innerHTML = '<div class="sugerencia-item text-muted text-center">No se encontraron productos</div>';
    }
}

function seleccionarProducto(id, nombre, precio, stock) {
    document.getElementById('buscador_producto').value = nombre;
    document.getElementById('id_producto').value = id;
    document.getElementById('lista-sugerencias').style.display = 'none';

    productoSeleccionado = { precio: precio, stock: stock };
    document.getElementById('lbl_precio').innerText = '$' + precio.toFixed(2);

    let inputCantidad = document.getElementById('cantidad');
    inputCantidad.max = stock;
    if (parseInt(inputCantidad.value) > stock) {
        inputCantidad.value = stock;
    }

    calcularTotal();
}

function calcularTotal() {
    let cantidad = parseInt(document.getElementById('cantidad').value) || 0;
    let total = productoSeleccionado.precio * cantidad;
    document.getElementById('lbl_total').innerText = '$' + total.toFixed(2);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#buscador_producto') && !e.target.closest('#lista-sugerencias')) {
        document.getElementById('lista-sugerencias').style.display = 'none';
    }
});

function filtrarTablaVentas() {
    let filtro = document.getElementById('buscador_tabla_ventas').value.toLowerCase();
    let filas = document.querySelectorAll('#tabla_ventas tbody tr');

    filas.forEach(fila => {
        if (fila.cells.length <= 1) return;
        let textoFila = fila.innerText.toLowerCase();
        if (textoFila.includes(filtro)) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}

function abrirModalAnular(idVenta, nombreProducto, cantidad) {
    document.getElementById('txt_producto').innerText = '"' + nombreProducto + '"';
    document.getElementById('txt_cantidad').innerText = cantidad;
    document.getElementById('btn_confirmar_anulacion').href = 'ventas.php?anular=' + idVenta;
    
    let myModal = new bootstrap.Modal(document.getElementById('modalAnular'));
    myModal.show();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>