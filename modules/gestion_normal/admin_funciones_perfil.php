<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once '../../config/db.php';
?>

<!DOCTYPE html>
<html>
<head>
  <title>Funciones por Perfil</title>
  <script src="../../templates/adminlte4/assets/plugins/jquery/jquery.min.js"></script>
</head>
<body>
  <h2>Asignar Funciones a Perfiles</h2>

  <label>Perfil:</label>
  <select id="perfil_id">
    <option value="">-- Seleccionar --</option>
    <?php while ($p = mysqli_fetch_assoc($perfiles)): ?>
      <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
    <?php endwhile; ?>
  </select>
  

<div id="mensaje_funcion_creada"></div>

  <label>Página:</label>
  <select id="pagina_id">
    <option value="">-- Seleccionar --</option>
    <?php while ($pg = mysqli_fetch_assoc($paginas)): ?>
      <option value="<?= $pg['pagina_id'] ?>"><?= $pg['modulo'] ?> - <?= $pg['nombre'] ?></option>
    <?php endwhile; ?>
  </select>

  <div id="funciones"></div>

<script>
$('#pagina_id, #perfil_id').change(function() {
    const perfil_id = $('#perfil_id').val();
    const pagina_id = $('#pagina_id').val();

    if (perfil_id && pagina_id) {
        $('#funciones').html('Cargando...');
        $.post('ajax/cargar_funciones.php', { perfil_id, pagina_id }, function(res) {
            $('#funciones').html(res);
        });
    } else {
        $('#funciones').html('');
    }
});
// Actualizar campo oculto cuando se seleccione una página
$('#pagina_id').change(function() {
    $('#pagina_id_oculto').val($(this).val());
});

// Envío de nueva función
$('#form_nueva_funcion').submit(function(e) {
    e.preventDefault();
    $.post('ajax/crear_funcion.php', $(this).serialize(), function(res) {
        $('#mensaje_funcion_creada').html(res);
        $('#form_nueva_funcion')[0].reset();

        // Refrescar lista de funciones asignables
        $('#pagina_id').trigger('change');
    });
});
</script>
</body>
</html>