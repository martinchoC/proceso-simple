</div>
</div>
</main>
<footer class="app-footer">
  <div class="float-end d-none d-sm-inline"></div>
  <strong>
    Copyright &copy; 2014-<?php echo date("Y"); ?>&nbsp;
    <a href="https://developsam.com" class="text-decoration-none">Developsam</a>.
  </strong>
</footer>
</div>
<script src="<?= asset_local('js/overlayscrollbars-2.11.0/overlayscrollbars.browser.es6.min.js') ?>"></script>
<script src="<?= asset_local('js/popperjs-core-2.11.8/popper.min.js') ?>"></script>
<script src="<?= asset_local('js/bootstrap-5.3.7/bootstrap.min.js') ?>"></script>
<script src="<?= asset('js/adminlte.js') ?>"></script>
<script src="<?= asset_local('js/ui-simple.js') ?>?v=<?= @filemtime(__DIR__ . '/../../assets/js/ui-simple.js') ?>"></script>
<script>
  const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
  const Default = {
    scrollbarTheme: 'os-theme-light',
    scrollbarAutoHide: 'leave',
    scrollbarClickScroll: true,
  };
  document.addEventListener('DOMContentLoaded', function () {
    const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
    if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
      OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
        scrollbars: {
          theme: Default.scrollbarTheme,
          autoHide: Default.scrollbarAutoHide,
          clickScroll: Default.scrollbarClickScroll,
        },
      });
    }
  });
</script>
<script>
  // core/ajax_guard.php responde 401 cuando la sesión venció: llevar al login en vez de que
  // cada pantalla muestre un error genérico. (Los fetch() de cada página lo manejan aparte.)
  if (window.jQuery) {
    let redirigiendoLogin = false;
    jQuery(document).ajaxError(function (event, xhr) {
      if (xhr.status === 401 && !redirigiendoLogin) {
        redirigiendoLogin = true;
        alert('La sesión expiró. Vuelva a ingresar al sistema.');
        window.location.href = '<?= url('login.php') ?>';
      }
    });
  }
</script>
<script src="<?= asset_local('js/sortablejs-1.15.0/Sortable.min.js') ?>"></script>
<script>
  const connectedSortable = document.querySelector('.connectedSortable');
  if (connectedSortable) {
    new Sortable(connectedSortable, {
      group: 'shared',
      handle: '.card-header',
    });

    const cardHeaders = connectedSortable.querySelectorAll('.card-header');
    cardHeaders.forEach((cardHeader) => {
      cardHeader.style.cursor = 'move';
    });
  }
</script>
</body>

</html>