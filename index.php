<?php
$sid = $_GET['sid'] ?? null;

if (!$sid) {
    header("Location: login.php");
    exit;
}

// Acá iría validación real de sesión
// Por ahora, mostramos selector de módulo como prueba

echo "<h1>Seleccioná un módulo</h1>";
echo "<ul>
    <li><a href='modules/ventas/index.php?sid=$sid'>Ventas</a></li>
    <li><a href='modules/stock/index.php?sid=$sid'>Stock</a></li>
    <li><a href='modules/stock/admin_funciones_perfil.php?sid=$sid'>Perfiles Compras</a></li>
    <li><a href='modules/compras/index.php?sid=$sid'>Compras</a></li>
    
</ul>";