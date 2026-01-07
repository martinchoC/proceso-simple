</div>
</div>
</main>
<footer class="app-footer">
  <div class="float-end d-none d-sm-inline">Anything you want</div>
  <strong>
    Copyright &copy; 2014-<?php echo date("Y"); ?>&nbsp;
    <a href="https://developsam.com" class="text-decoration-none">Developsam</a>.
  </strong>
  All rights reserved.
</footer>
</div>
<script src="<?= asset_local('js/overlayscrollbars.browser.es6.min.js') ?>"></script>
<script src="<?= asset_local('js/popper.min.js') ?>"></script>
<script src="<?= asset_local('js/bootstrap.min.js') ?>"></script>
<script src="<?= asset('js/adminlte.js') ?>"></script>
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
<script src="<?= asset_local('js/Sortable.min.js') ?>"></script>
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