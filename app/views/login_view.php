<div class="login-box" style="margin: 7% auto; max-width: 360px;">
  <div class="card card-outline card-primary">
    <div class="card-header text-center">
      <a href="#" class="h1"><b>Gestión</b>Multipyme</a>
    </div>
    <div class="card-body">
      <p class="login-box-msg">Inicia sesión para comenzar</p>

      <form method="POST" action="">
        <div class="input-group mb-3">
          <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="password" name="clave" class="form-control" placeholder="Contraseña" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>

        <div class="input-group mb-3">
          <input type="number" name="empresa_id" class="form-control" placeholder="ID de Empresa" required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-building"></span></div>
          </div>
        </div>

        <div class="row">
          <div class="col-12">
            <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
          </div>
        </div>
      </form>

      <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
