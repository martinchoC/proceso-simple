import pathlib
def R(s, a, b, nl):
    a = a.replace("\n", nl); b = b.replace("\n", nl)
    c = s.count(a)
    if c == 0:
        print("  NOT FOUND:", a[:70].replace(nl, " | "))
    return s.replace(a, b)

for mod in ("gestion", "gestion_normal"):
    B = pathlib.Path(r"c:\xampp\htdocs\web_procesosimple\casalucho\modules") / mod
    m = B / "proveedores_ctacte_model.php"
    s = m.read_bytes().decode("utf-8")
    nl = "\r\n" if "\r\n" in s else "\n"
    print(mod)
    s = R(s, "function ctacteDatosProveedor($conexion, $entidad_id)\n", "function ctacteDatosProveedor($conexion, $entidad_id, $empresa_id = 0)\n", nl)
    s = R(s, "            WHERE e.entidad_id = ?\";\n    $fila = ctacteFetchAll($conexion, $sql, 'i', [intval($entidad_id)])[0] ?? null;",
             "            WHERE e.entidad_id = ? AND (? = 0 OR e.empresa_id = ?)\";\n    $empresa_id = intval($empresa_id);\n    $fila = ctacteFetchAll($conexion, $sql, 'iii', [intval($entidad_id), $empresa_id, $empresa_id])[0] ?? null;", nl)
    s = R(s, "function ctacteBuscarProveedores($conexion, $texto, $limite = 30)\n", "function ctacteBuscarProveedores($conexion, $empresa_id, $texto, $limite = 30)\n", nl)
    s = R(s, "            WHERE tabla_estado_registro_id IN (1, 2)\n              AND \" . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'PRO')",
             "            WHERE empresa_id = ?\n              AND tabla_estado_registro_id IN (1, 2)\n              AND \" . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'PRO')", nl)
    s = R(s, "    mysqli_stmt_bind_param($stmt, \"sss\", $like, $like, $like);",
             "    $empresa_id = intval($empresa_id);\n    mysqli_stmt_bind_param($stmt, \"isss\", $empresa_id, $like, $like, $like);", nl)
    m.write_bytes(s.encode("utf-8"))
    a = B / "proveedores_ctacte_ajax.php"
    t = a.read_bytes().decode("utf-8")
    t = R(t, "ctacteBuscarProveedores($conexion, $_REQUEST['q'] ?? '')", "ctacteBuscarProveedores($conexion, $empresa_id, $_REQUEST['q'] ?? '')", nl)
    t = R(t, "ctacteDatosProveedor($conexion, ctacteEntidadParam())", "ctacteDatosProveedor($conexion, ctacteEntidadParam(), $empresa_id)", nl)
    a.write_bytes(t.encode("utf-8"))
