/**
 * Cliente activo del estudio, compartido entre páginas (estudio_cliente_activo.php).
 *   EstudioCliente.inicial(): cliente_id de la URL o, si no viene, el último elegido (0 = ninguno).
 *   EstudioCliente.guardar(id): lo deja como activo para las demás páginas.
 */
window.EstudioCliente = (function () {
    let actual = parseInt(window.ESTUDIO_CLIENTE_ACTIVO || 0, 10) || 0;
    function guardar(id) {
        id = parseInt(id || 0, 10) || 0;
        if (!id || id === actual) { return; } // elegir "ninguno" no borra el activo
        actual = id;
        $.post('estudio_cliente_activo_ajax.php', { accion: 'guardar', cliente_id: id }); // preferencia: sin aviso si falla
    }
    return {
        // El que viene en la URL (link desde la ficha del cliente) queda también como activo
        inicial: function () {
            const url = parseInt(new URLSearchParams(location.search).get('cliente_id') || '0', 10);
            if (url > 0) { guardar(url); return url; }
            return actual;
        },
        guardar: guardar
    };
})();
