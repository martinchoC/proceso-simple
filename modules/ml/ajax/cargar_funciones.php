<?php
require_once '../../../config/db.php';

$pagina_id = intval($_POST['pagina_id']);
$perfil_id = intval($_POST['perfil_id']);

$sql = "SELECT pf.funcion_id, pf.nombre,
        (SELECT 1 FROM conf__perfiles_funciones WHERE perfil_id = $perfil_id AND funcion_id = pf.funcion_id) AS asignado
        FROM conf__paginas_funciones pf
        WHERE pf.pagina_id = $pagina_id";

$res = mysqli_query($conn, $sql);

echo '<form id="form_funciones">';
while ($row = mysqli_fetch_assoc($res)) {
    $checked = $row['asignado'] ? 'checked' : '';
    echo "<label><input type='checkbox' name='funciones[]' value='{$row['funcion_id']}' $checked /> {$row['nombre']}</label><br>";
}
echo "<input type='hidden' name='pagina_id' value='$pagina_id'>";
echo "<input type='hidden' name='perfil_id' value='$perfil_id'>";
echo '<br><button type="submit">Guardar</button>';
echo '</form>';
?>

<script>
$('#form_funciones').submit(function(e) {
    e.preventDefault();
    $.post('ajax/guardar_funciones_perfil.php', $(this).serialize(), function(res) {
        alert(res);
    });
});
</script>
