<?php
/**
 * @var string $titulo
 * @var \App\Services\SessionGuard $auth
 * @var \App\Support\Csrf $csrf
 * @var int|null $items_carrito
 * @var string|null $seccion
 * @var array<string,mixed>|null $filtros
 */
$items_carrito = $items_carrito ?? null;
$seccion = $seccion ?? '';
$filtros = $filtros ?? [];
$terminos = $filtros['terminos'] ?? [];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
  <meta name="theme-color" content="#2a5bd7">
  <title><?= e($titulo ?? 'CASALUCHO Autoherrajes') ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>

<header class="header">
  <div class="contenedor header-top">
    <a class="marca" href="<?= e(url('/catalogo')) ?>">
      <img src="<?= e(asset('img/logo.png')) ?>" alt="CASALUCHO Autoherrajes LOGO" class="marca-logo">
      <span>CASALUCHO Autoherrajes</span>
    </a>

    <nav class="header-menu" aria-label="Navegación principal">
      <ul class="header-menu-lista">
        <li>
          <a href="<?= e(url('/catalogo')) ?>" class="header-nav-link <?= $seccion === 'catalogo' ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
              <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            <span>Catálogo</span>
          </a>
        </li>
        <li>
          <a href="<?= e(url('/carrito')) ?>" class="header-nav-link <?= $seccion === 'carrito' ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13 5.4 5M7 13l-2 5h13"/>
              <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
            </svg>
            <span>Mi carrito</span>
            <?php if ($items_carrito !== null && $items_carrito > 0): ?>
              <span class="badge-carrito" data-carrito-contador><?= (int) $items_carrito ?></span>
            <?php endif; ?>
          </a>
        </li>
        <li>
          <a href="<?= e(url('/pedidos')) ?>" class="header-nav-link <?= $seccion === 'pedidos' ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 3h6l1 4H8l1-4Z"/><path d="M4 7h16l-1.2 13H5.2L4 7Z"/>
            </svg>
            <span>Mis pedidos</span>
          </a>
        </li>
      </ul>
    </nav>

    <div class="header-acciones">
      <span class="header-btn header-cuenta">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>
        </svg>
        <span>
          <?= e($auth->nombre()) ?>
          <small><?= e($auth->entidadNombre()) ?></small>
        </span>
      </span>

      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= $csrf->field() ?>
        <button type="submit" class="header-btn" title="Cerrar sesión">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 17l5-5-5-5"/><path d="M20 12H9"/><path d="M12 19H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6"/>
          </svg>
          <span class="solo-lectores">Salir</span>
        </button>
      </form>
    </div>
  </div>
</header>

<main class="main">
  <div class="contenedor">
