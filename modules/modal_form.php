<?php 
require_once '../config/db.php';

$accion = $_GET['accion'] ?? 'nuevo';
$modulo = [
    'modulo_id' => '',
    'codigo' => '',
    'nombre' => '',
    'descripcion' => '',
    'icono' => 'fas fa-cube',
    'ruta_inicio' => '',
    'orden' => 0,
    'registro_estado_id' => 1
];

if ($accion == 'editar' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $conexion->prepare("SELECT * FROM gestion__modulos WHERE modulo_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $modulo = $result->fetch_assoc();
    $stmt->close();
}
?>

<form id="formModulo">
    <input type="hidden" name="accion" value="<?= $accion ?>">
    <input type="hidden" name="modulo_id" value="<?= $modulo['modulo_id'] ?>">
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="codigo">Código</label>
                <input type="text" class="form-control" id="codigo" name="codigo" 
                       value="<?= $modulo['codigo'] ?>" required>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="nombre">Nombre</label>
                <input type="text" class="form-control" id="nombre" name="nombre" 
                       value="<?= $modulo['nombre'] ?>" required>
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label for="descripcion">Descripción</label>
        <textarea class="form-control" id="descripcion" name="descripcion" 
                  rows="3"><?= $modulo['descripcion'] ?></textarea>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="icono">Icono (FontAwesome)</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="<?= $modulo['icono'] ?>"></i></span>
                    </div>
                    <input type="text" class="form-control" id="icono" name="icono" 
                           value="<?= $modulo['icono'] ?>" placeholder="fas fa-icono">
                </div>
                <small class="form-text text-muted">
                    Ejemplo: fas fa-user, fas fa-cog, etc. Ver <a href="https://fontawesome.com/icons" target="_blank">FontAwesome</a>
                </small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="ruta_inicio">Ruta de inicio</label>
                <input type="text" class="form-control" id="ruta_inicio" name="ruta_inicio" 
                       value="<?= $modulo['ruta_inicio'] ?>" placeholder="Ej: /modulo/inicio">
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label for="orden">Orden</label>
                <input type="number" class="form-control" id="orden" name="orden" 
                       value="<?= $modulo['orden'] ?>">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label for="registro_estado_id">Estado</label>
                <select class="form-control" id="registro_estado_id" name="registro_estado_id">
                    <option value="1" <?= $modulo['registro_estado_id'] == 1 ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= $modulo['registro_estado_id'] == 0 ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>