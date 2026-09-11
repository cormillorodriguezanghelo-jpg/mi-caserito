<?php
$host = "sql307.infinityfree.com";
$usuario = "if0_42879200";
$password = "9SxXYVrA8mz"; 
$base_datos = "if0_42879200_caserito";

$conexion = new mysqli($host, $usuario, $password, $base_datos);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8");
?>
