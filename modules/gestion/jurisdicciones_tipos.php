<?php
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Jurisdicción";
$currentPage = 'jurisdicciones_tipos';
$modudo_idx = 2;
$pagina_idx = 75; // ajustar

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
<div class="app-content">
<div class="container-fluid">

<div class="card">
<div class="card-header">
    <button class="btn btn-primary" id="btnNuevo">
        <i class="fas fa-plus"></i> Agregar
    </button>
</div>

<div class="card-body">
<table id="tabla" class="table table-bordered">
<thead>
<tr>
    <th>ID</th>
    <th>Tipo</th>
    <th>Código</th>
    <th>Descripción</th>
    <th>Orden</th>
    <th>Estado</th>
    <th>Acciones</th>
</tr>
</thead>
</table>
</div>
</div>

</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modal">
<div class="modal-dialog">
<div class="modal-content">

<div class="modal-header">
<h5 class="modal-title">Jurisdicción Tipo</h5>
</div>

<div class="modal-body">
<form id="form">
<input type="hidden" id="id">

<div class="mb-2">
<label>Tipo</label>
<input type="text" id="jurisdiccion_tipo" class="form-control" required>
</div>

<div class="mb-2">
<label>Código</label>
<input type="text" id="codigo" class="form-control" required>
</div>

<div class="mb-2">
<label>Descripción</label>
<input type="text" id="descripcion" class="form-control">
</div>

<div class="mb-2">
<label>Orden</label>
<input type="number" id="orden" class="form-control" value="1">
</div>

</form>
</div>

<div class="modal-footer">
<button class="btn btn-primary" id="btnGuardar">Guardar</button>
</div>

</div>
</div>
</div>

<script>
let tabla;

$(function(){

tabla = $('#tabla').DataTable({
    ajax:{
        url:'jurisdicciones_tipos_ajax.php',
        data:{accion:'listar'},
        dataSrc:''
    },
    columns:[
        {data:'jurisdiccion_tipo_id'},
        {data:'jurisdiccion_tipo'},
        {data:'codigo'},
        {data:'descripcion'},
        {data:'orden'},
        {data:'estado'},
        {data:'acciones'}
    ]
});

$('#btnNuevo').click(()=>{
    $('#form')[0].reset();
    $('#id').val('');
    new bootstrap.Modal('#modal').show();
});

$('#btnGuardar').click(()=>{
    $.post('jurisdicciones_tipos_ajax.php',{
        accion: $('#id').val() ? 'editar':'agregar',
        id: $('#id').val(),
        jurisdiccion_tipo: $('#jurisdiccion_tipo').val(),
        codigo: $('#codigo').val(),
        descripcion: $('#descripcion').val(),
        orden: $('#orden').val()
    },function(){
        tabla.ajax.reload();
        bootstrap.Modal.getInstance(document.getElementById('modal')).hide();
    },'json');
});

$(document).on('click','.editar',function(){
    let id=$(this).data('id');

    $.get('jurisdicciones_tipos_ajax.php',{
        accion:'obtener',
        id:id
    },function(res){

        $('#id').val(res.jurisdiccion_tipo_id);
        $('#jurisdiccion_tipo').val(res.jurisdiccion_tipo);
        $('#codigo').val(res.codigo);
        $('#descripcion').val(res.descripcion);
        $('#orden').val(res.orden);

        new bootstrap.Modal('#modal').show();
    },'json');
});

});
</script>