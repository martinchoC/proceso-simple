<?php $ruta = $ruta_assets ?? ''; ?>
      
      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">Multigestión</div>
        <strong>Copyright &copy; 2014-<?php echo date("Y");?> <a href="https://developsam.com" class="text-decoration-none">Developsam</a>.</strong>
        All rights reserved.
      </footer>
    </div>
    <script src="<?php echo $ruta; ?>assets/js/bootstrap.bundle.min.js"></script>
    
    <script src="<?php echo $ruta; ?>assets/js/adminlte.min.js"></script>
    
    <script src="<?php echo $ruta; ?>assets/js/overlayscrollbars.browser.es6.min.js"></script>

    <script>
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const Default = {
        scrollbarTheme: 'os-theme-light',
        scrollbarAutoHide: 'leave',
        scrollbarClickScroll: true,
      };
      document.addEventListener('DOMContentLoaded', function () {
        const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
        if (sidebarWrapper && typeof OverlayScrollbarsGlobal !== 'undefined') {
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
  </body>
</html>