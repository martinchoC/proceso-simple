<?php
/** @var array<string,string[]> $flash */
foreach (($flash ?? []) as $tipo => $mensajes) {
    $esError = $tipo === 'error';
    $clase = $esError ? 'aviso-error' : 'aviso-ok';
    $icono = $esError
        ? '<path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/>'
        : '<path d="m5 13 4 4L19 7"/>';

    foreach ($mensajes as $mensaje) {
        echo '<div class="aviso ' . e($clase) . '" role="alert">'
            . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
            . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icono . '</svg>'
            . '<span>' . e($mensaje) . '</span>'
            . '</div>';
    }
}
