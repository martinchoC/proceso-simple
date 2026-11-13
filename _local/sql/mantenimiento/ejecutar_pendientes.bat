@echo off
REM Ejecuta los scripts SQL pendientes del estudio contable, en orden. Pide la clave de MySQL una vez por script.
REM Uso: doble clic, o desde cmd:  ejecutar_pendientes.bat [usuario] [base]
set USR=%~1
if "%USR%"=="" set USR=root
set DB=%~2
if "%DB%"=="" set DB=gestion_multipyme
set MYSQL=C:\xampp\mysql\bin\mysql.exe
cd /d "%~dp0"
for %%F in (20261105_estudio_minigestion_alicuotas_01.sql 20261105_estudio_minigestion_liquidacion_iva_01.sql 20261105_estudio_clientes_origen_datos_01.sql 20261106_estudio_clientes_nivel_servicio_01.sql 20261106_nivel_gestion_empresa_01.sql 20261106_estudio_inspecciones_01.sql 20261106_estudio_clientes_alta_permiso_01.sql 20261106_estudio_minigestion_liquidar_iva_permiso_01.sql 20261106_estudio_importar_arca_01.sql 20261107_estudio_gestion_clientes_01.sql 20261107_estudio_gestion_propia_01.sql 20261107_estudio_cierres_clientes_01.sql 20261107_estudio_contabilidad_cliente_01.sql 20261107_estudio_importar_en_minigestion_01.sql) do (
  echo === %%F ===
  "%MYSQL%" -u %USR% -p --default-character-set=utf8mb4 %DB% < %%F
  if errorlevel 1 (echo FALLO en %%F - se detiene. & pause & exit /b 1)
)
echo Listo: todos los scripts se ejecutaron.
pause
