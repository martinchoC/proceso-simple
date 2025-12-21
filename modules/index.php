<?php 
require_once '../config/db.php';?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Módulos</title>
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php include('../includes/header.php'); ?>
    <?php include('../includes/sidebar.php'); ?>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1>Gestión de Módulos</h1>
                    </div>
                </div>
            </div>
        </section>

        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Listado de Módulos</h3>
                                <button class="btn btn-success float-right" id="btnNuevo" data-toggle="modal" data-target="#modalForm">
                                    <i class="fas fa-plus"></i> Nuevo Módulo
                                </button>
                            </div>
                            <div class="card-body">
                                <table id="tablaModulos" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Código</th>
                                            <th>Nombre</th>
                                            <th>Descripción</th>
                                            <th>Icono</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Datos cargados via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<!-- Modal para formulario -->
<div class="modal fade" id="modalForm">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitulo">Nuevo Módulo</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- El formulario se carga aquí via AJAX -->
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 4 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Inicializar DataTable
    var tabla = $('#tablaModulos').DataTable({
        "ajax": {
            "url": "procesar.php?accion=listar",
            "type": "POST",
            "dataSrc": ""
        },
        "columns": [
            {"data": "modulo_id"},
            {"data": "codigo"},
            {"data": "nombre"},
            {"data": "descripcion"},
            {"data": "icono", "render": function(data) {
                return data ? '<i class="' + data + '"></i> ' + data : '';
            }},
            {"data": "registro_estado_id", "render": function(data) {
                return data == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>';
            }},
            {"data": null, "render": function(data) {
                return `
                    <button class="btn btn-primary btn-sm btnEditar" data-id="${data.modulo_id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm btnEliminar" data-id="${data.modulo_id}">
                        <i class="fas fa-trash"></i>
                    </button>
                `;
            }}
        ],
        "responsive": true,
        "autoWidth": false
    });

    // Abrir modal para nuevo módulo
    $('#btnNuevo').click(function() {
        $.ajax({
            url: 'modal_form.php',
            type: 'GET',
            data: {accion: 'nuevo'},
            success: function(response) {
                $('#modalForm .modal-body').html(response);
                $('#modalTitulo').text('Nuevo Módulo');
            }
        });
    });

    // Editar módulo
    $(document).on('click', '.btnEditar', function() {
        var id = $(this).data('id');
        $.ajax({
            url: 'modal_form.php',
            type: 'GET',
            data: {accion: 'editar', id: id},
            success: function(response) {
                $('#modalForm .modal-body').html(response);
                $('#modalTitulo').text('Editar Módulo');
                $('#modalForm').modal('show');
            }
        });
    });

    // Guardar módulo (nuevo o editar)
    $(document).on('submit', '#formModulo', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        
        $.ajax({
            url: 'procesar.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                var res = JSON.parse(response);
                if (res.estado) {
                    Swal.fire('Éxito', res.mensaje, 'success');
                    $('#modalForm').modal('hide');
                    tabla.ajax.reload();
                } else {
                    Swal.fire('Error', res.mensaje, 'error');
                }
            }
        });
    });

    // Eliminar módulo
    $(document).on('click', '.btnEliminar', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: '¿Estás seguro?',
            text: "¡No podrás revertir esto!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminarlo!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'procesar.php',
                    type: 'POST',
                    data: {
                        accion: 'eliminar',
                        id: id
                    },
                    success: function(response) {
                        var res = JSON.parse(response);
                        if (res.estado) {
                            Swal.fire('Eliminado!', res.mensaje, 'success');
                            tabla.ajax.reload();
                        } else {
                            Swal.fire('Error', res.mensaje, 'error');
                        }
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>