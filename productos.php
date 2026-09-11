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

// PROCESAR NUEVO PRODUCTO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio_venta = trim($_POST['precio_venta'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $fecha_caducidad = trim($_POST['fecha_caducidad'] ?? '');

    // Validación estricta: Ningún campo debe estar vacío, incluyendo la fecha de caducidad
    if ($nombre === '' || $precio_venta === '' || $stock === '' || $fecha_caducidad === '') {
        $error = "Error: Todos los campos son obligatorios (Nombre, Precio, Stock y Fecha de Caducidad). No se permite dejar ningún campo vacío.";
    } elseif (!is_numeric($precio_venta) || $precio_venta < 0) {
        $error = "Error: El precio de venta ingresado no es válido.";
    } elseif (!is_numeric($stock) || $stock < 0) {
        $error = "Error: El stock ingresado no es válido.";
    } else {
        $stmt_insert = $conexion->prepare("INSERT INTO productos (id_usuario, nombre, precio_venta, stock, fecha_caducidad) VALUES (?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("isdds", $id_usuario_actual, $nombre, $precio_venta, $stock, $fecha_caducidad);
        
        if ($stmt_insert->execute()) {
            $mensaje = "¡Producto registrado correctamente!";
        } else {
            $error = "Error al registrar el producto en la base de datos.";
        }
        $stmt_insert->close();
    }
}

// PROCESAR EDITAR PRODUCTO
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id_producto = $_POST['id_producto'] ?? '';
    $nombre = trim($_POST['nombre'] ?? '');
    $precio_venta = trim($_POST['precio_venta'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $fecha_caducidad = trim($_POST['fecha_caducidad'] ?? '');

    if (empty($id_producto) || $nombre === '' || $precio_venta === '' || $stock === '' || $fecha_caducidad === '') {
        $error = "Error: Todos los campos son obligatorios para actualizar el producto. Ninguno puede quedar vacío.";
    } elseif (!is_numeric($precio_venta) || $precio_venta < 0 || !is_numeric($stock) || $stock < 0) {
        $error = "Error: Verifique que los valores numéricos de precio y stock sean correctos.";
    } else {
        $stmt_update = $conexion->prepare("UPDATE productos SET nombre = ?, precio_venta = ?, stock = ?, fecha_caducidad = ? WHERE id_producto = ? AND id_usuario = ?");
        $stmt_update->bind_param("sddsii", $nombre, $precio_venta, $stock, $fecha_caducidad, $id_producto, $id_usuario_actual);
        
        if ($stmt_update->execute()) {
            $mensaje = "¡Producto actualizado con éxito!";
        } else {
            $error = "Error al actualizar el producto.";
        }
        $stmt_update->close();
    }
}

// PROCESAR ELIMINAR PRODUCTO
if (isset($_GET['eliminar'])) {
    $id_producto = intval($_GET['eliminar']);
    $stmt_del = $conexion->prepare("DELETE FROM productos WHERE id_producto = ? AND id_usuario = ?");
    $stmt_del->bind_param("ii", $id_producto, $id_usuario_actual);
    if ($stmt_del->execute()) {
        $mensaje = "Producto eliminado del inventario.";
    }
    $stmt_del->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Caserito - Gestión de Inventario</title>
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
        /* Contenedor con scroll para evitar que la lista crezca infinitamente */
        .table-container-scroll {
            max-height: 420px;
            overflow-y: auto;
        }
        .table-custom th {
            position: sticky;
            top: 0;
            z-index: 10;
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
                <a class="nav-link text-light px-3" href="index.php"><i class="fa-solid fa-house me-1 text-info"></i> Dashboard</a>
                <a class="nav-link text-light active fw-semibold px-3" href="productos.php"><i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Inventario</a>
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
            <h3 class="fw-bold text-dark m-0"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Gestión de Inventario</h3>
            <p class="text-muted small m-0 mt-1">Registra, edita y controla el stock y fechas de caducidad para prevenir pérdidas.</p>
        </div>
        <div class="badge bg-white text-dark shadow-sm p-2 px-3 rounded-pill border">
            <i class="fa-regular fa-calendar-days text-primary me-2"></i><?php echo date('d / m / Y'); ?>
        </div>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 rounded-4 mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 rounded-4 mb-4" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- COLUMNA IZQUIERDA: FORMULARIO DE NUEVO PRODUCTO -->
        <div class="col-lg-4">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                    <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                        <i class="fa-solid fa-square-plus"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold text-dark m-0">Nuevo Producto</h5>
                        <small class="text-muted">Agregar al stock</small>
                    </div>
                </div>

                <form action="productos.php" method="POST" onsubmit="return validarFormularioCrear()">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOMBRE DEL PRODUCTO</label>
                        <input type="text" name="nombre" id="crear_nombre" class="form-control" required placeholder="Ej. Leche Entera 1L">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PRECIO DE VENTA ($)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">$</span>
                            <input type="number" step="0.01" name="precio_venta" id="crear_precio" class="form-control border-start-0" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">STOCK INICIAL</label>
                        <input type="number" name="stock" id="crear_stock" class="form-control" required placeholder="0">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">FECHA DE CADUCIDAD <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_caducidad" id="crear_fecha" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm" style="background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%); border: none;">
                        <i class="fa-solid fa-plus me-1"></i> Guardar Producto
                    </button>
                </form>
            </div>
        </div>

        <!-- COLUMNA DERECHA: TABLA DE PRODUCTOS CON BUSCADOR -->
        <div class="col-lg-8">
            <div class="card card-custom p-4 h-100">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3 pb-2 border-bottom gap-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                            <i class="fa-solid fa-list-check"></i>
                        </div>
                        <div>
                            <h5 class="fw-bold text-dark m-0">Lista de Productos</h5>
                            <small class="text-muted">Inventario registrado</small>
                        </div>
                    </div>
                    <!-- BUSCADOR EN TIEMPO REAL -->
                    <div class="input-group" style="max-width: 280px;">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="buscadorProductos" class="form-control border-start-0 shadow-none ps-0" placeholder="Buscar producto..." onkeyup="filtrarTabla()">
                    </div>
                </div>

                <div class="table-responsive rounded-3 border table-container-scroll">
                    <table class="table table-custom mb-0" id="tablaInventario">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio Venta</th>
                                <th>Stock</th>
                                <th>Caducidad</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt_prod = $conexion->prepare("SELECT id_producto, nombre, precio_venta, stock, fecha_caducidad FROM productos WHERE id_usuario = ? ORDER BY nombre ASC");
                            $stmt_prod->bind_param("i", $id_usuario_actual);
                            $stmt_prod->execute();
                            $resultado = $stmt_prod->get_result();

                            $hoy = new DateTime();

                            if ($resultado && $resultado->num_rows > 0) {
                                while ($row = $resultado->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='fw-bold text-dark'>".htmlspecialchars($row['nombre'])."</td>";
                                    echo "<td class='text-success fw-semibold'>$".number_format($row['precio_venta'], 2)."</td>";
                                    
                                    if ($row['stock'] <= 3) {
                                        echo "<td><span class='badge bg-danger bg-opacity-10 text-danger px-2 py-1 fw-bold'><i class='fa-solid fa-triangle-exclamation me-1'></i> ".$row['stock']." un. (Bajo)</span></td>";
                                    } else {
                                        echo "<td><span class='badge bg-success bg-opacity-10 text-success px-2 py-1 fw-bold'>".$row['stock']." un.</span></td>";
                                    }
                                    
                                    $fecha_cad = new DateTime($row['fecha_caducidad']);
                                    $diferencia = $hoy->diff($fecha_cad);
                                    $dias_restantes = (int)$diferencia->format('%r%a');

                                    if ($dias_restantes < 0) {
                                        echo "<td><span class='badge bg-danger bg-opacity-10 text-danger px-2 py-1'><i class='fa-solid fa-triangle-exclamation me-1'></i> Vencido (".$row['fecha_caducidad'].")</span></td>";
                                    } elseif ($dias_restantes <= 7) {
                                        echo "<td><span class='badge bg-danger bg-opacity-10 text-danger px-2 py-1'><i class='fa-solid fa-clock me-1'></i> ¡Vence pronto! (".$row['fecha_caducidad'].")</span></td>";
                                    } else {
                                        echo "<td><span class='text-muted small'>".$row['fecha_caducidad']."</span></td>";
                                    }
                                    
                                    echo "<td class='text-end'>
                                            <button class='btn btn-outline-primary btn-sm me-1 rounded-2' onclick='abrirModalEditar(".$row['id_producto'].", \"".htmlspecialchars($row['nombre'], ENT_QUOTES)."\", ".$row['precio_venta'].", ".$row['stock'].", \"".$row['fecha_caducidad']."\")' title='Editar'><i class='fa-solid fa-pen'></i></button>
                                            <button class='btn btn-outline-danger btn-sm rounded-2' onclick='abrirModalEliminar(".$row['id_producto'].", \"".htmlspecialchars($row['nombre'], ENT_QUOTES)."\")' title='Eliminar'><i class='fa-solid fa-trash'></i></button>
                                          </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr id='filaSinResultados'><td colspan='5' class='text-center text-muted py-5'><i class='fa-solid fa-box-open fa-2x mb-2 text-secondary'></i><br>No hay productos registrados todavía.<br>Usa el formulario de la izquierda para agregar el primero.</td></tr>";
                            }
                            $stmt_prod->close();
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE EDICIÓN -->
<div class='modal fade' id='modalEditar' tabindex='-1' aria-hidden='true'>
    <div class='modal-dialog modal-dialog-centered'>
        <div class='modal-content text-start border-0 shadow-lg rounded-4'>
            <form action='productos.php' method='POST' onsubmit='return validarFormularioEditar()'>
                <input type='hidden' name='accion' value='editar'>
                <input type='hidden' name='id_producto' id='edit_id_producto'>
                <div class='modal-header border-bottom'>
                    <h5 class='modal-title fw-bold text-dark'><i class='fa-solid fa-pen-to-square me-2 text-primary'></i>Editar Producto</h5>
                    <button type='button' class='btn-close shadow-none' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body p-4'>
                    <div class='mb-3'>
                        <label class='form-label small fw-bold text-muted'>NOMBRE DEL PRODUCTO</label>
                        <input type='text' name='nombre' id='edit_nombre' class='form-control' required>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label small fw-bold text-muted'>PRECIO DE VENTA ($)</label>
                        <div class='input-group'>
                            <span class='input-group-text bg-light'>$</span>
                            <input type='number' step='0.01' name='precio_venta' id='edit_precio_venta' class='form-control' required>
                        </div>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label small fw-bold text-muted'>STOCK / CANTIDAD</label>
                        <input type='number' name='stock' id='edit_stock' class='form-control' required>
                    </div>
                    <div class='mb-3'>
                        <label class='form-label small fw-bold text-muted'>FECHA DE CADUCIDAD <span class="text-danger">*</span></label>
                        <input type='date' name='fecha_caducidad' id='edit_fecha_caducidad' class='form-control' required>
                    </div>
                </div>
                <div class='modal-footer border-top bg-light rounded-bottom-4'>
                    <button type='button' class='btn btn-secondary btn-sm px-3' data-bs-dismiss='modal'>Cancelar</button>
                    <button type='submit' class='btn btn-primary btn-sm px-3 fw-bold'>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DE ELIMINACIÓN -->
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header bg-danger text-white px-4 py-3">
                <h5 class="modal-title fw-bold fs-6"><i class="fa-solid fa-triangle-exclamation me-2"></i>Confirmar Eliminación</h5>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4 px-4">
                <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle d-inline-flex mb-3" style="width: 60px; height: 60px; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-trash fs-3"></i>
                </div>
                <p class="text-dark fw-semibold mb-1">¿Estás seguro de eliminar el producto <span id="txt_producto_eliminar" class="text-primary"></span>?</p>
                <p class="text-muted small m-0">Esta acción borrará el artículo permanentemente del inventario.</p>
            </div>
            <div class="modal-footer justify-content-center bg-light border-0 py-3">
                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <a href="#" id="btn_confirmar_eliminacion" class="btn btn-danger btn-sm px-4 fw-bold rounded-pill">Sí, Eliminar</a>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModalEditar(id, nombre, precio, stock, caducidad) {
    document.getElementById('edit_id_producto').value = id;
    document.getElementById('edit_nombre').value = nombre;
    document.getElementById('edit_precio_venta').value = precio;
    document.getElementById('edit_stock').value = stock;
    document.getElementById('edit_fecha_caducidad').value = caducidad;
    
    let modal = new bootstrap.Modal(document.getElementById('modalEditar'));
    modal.show();
}

function abrirModalEliminar(id, nombre) {
    document.getElementById('txt_producto_eliminar').innerText = '"' + nombre + '"';
    document.getElementById('btn_confirmar_eliminacion').href = 'productos.php?eliminar=' + id;
    
    let modal = new bootstrap.Modal(document.getElementById('modalEliminar'));
    modal.show();
}

// Filtro de búsqueda en tiempo real
function filtrarTabla() {
    let input = document.getElementById('buscadorProductos');
    let filtro = input.value.toLowerCase();
    let tabla = document.getElementById('tablaInventario');
    let filas = tabla.getElementsByTagName('tr');

    for (let i = 1; i < filas.length; i++) {
        let celdaNombre = filas[i].getElementsByTagName('td')[0];
        if (celdaNombre) {
            let textoValor = celdaNombre.textContent || celdaNombre.innerText;
            if (textoValor.toLowerCase().indexOf(filtro) > -1) {
                filas[i].style.display = "";
            } else {
                filas[i].style.display = "none";
            }
        }
    }
}

// Validaciones de JavaScript para el formulario de creación
function validarFormularioCrear() {
    let nombre = document.getElementById('crear_nombre').value.trim();
    let precio = document.getElementById('crear_precio').value.trim();
    let stock = document.getElementById('crear_stock').value.trim();
    let fecha = document.getElementById('crear_fecha').value.trim();

    if (nombre === "" || precio === "" || stock === "" || fecha === "") {
        alert("¡Alerta! Todos los campos, incluyendo la fecha de caducidad, son obligatorios para controlar el stock y evitar pérdidas.");
        return false;
    }
    return true;
}

// Validaciones de JavaScript para el formulario de edición en el modal
function validarFormularioEditar() {
    let nombre = document.getElementById('edit_nombre').value.trim();
    let precio = document.getElementById('edit_precio_venta').value.trim();
    let stock = document.getElementById('edit_stock').value.trim();
    let fecha = document.getElementById('edit_fecha_caducidad').value.trim();

    if (nombre === "" || precio === "" || stock === "" || fecha === "") {
        alert("¡Alerta! No se puede guardar el producto con campos vacíos. La fecha de caducidad es indispensable.");
        return false;
    }
    return true;
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>