<?php
require_once 'header.php';
require_once 'sidebar.php';
?>

<div class="content-wrapper">
  <section class="content pt-3 px-3">
    <?= $contenido ?? '' ?>
  </section>
</div>

<?php require_once 'footer.php'; ?>
