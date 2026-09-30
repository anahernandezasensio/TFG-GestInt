<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

// Procesar fichaje (entrada/salida) enviado desde este mismo dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'fichar') {
    $uid = (int)$_SESSION['usuario_id'];
    $sc  = $conn->prepare("SELECT tipo FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
    $sc->bind_param('i', $uid);
    $sc->execute();
    $tipos_hoy = array_column($sc->get_result()->fetch_all(MYSQLI_ASSOC), 'tipo');
    $n = count($tipos_hoy);

    if ($n === 0) {
        // Registrar entrada
        $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'entrada')");
        $ins->bind_param('i', $uid);
        $ins->execute();
    } elseif ($n === 1 && $tipos_hoy[0] === 'entrada') {
        // Registrar salida
        $ins = $conn->prepare("INSERT INTO fichajes (usuario_id, tipo) VALUES (?, 'salida')");
        $ins->bind_param('i', $uid);
        $ins->execute();
    }
    header('Location: dashboard.php'); exit;
}

// Datos para las tarjetas del resumen
$total_empleados = $conn->query("SELECT COUNT(*) FROM empleados WHERE activo=1")->fetch_row()[0] ?? 0;
$total_articulos = $conn->query("SELECT COUNT(*) FROM inventario")->fetch_row()[0] ?? 0;
$stock_bajo      = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad <= stock_minimo")->fetch_row()[0] ?? 0;
$pedidos_activos = $conn->query("SELECT COUNT(*) FROM pedidos WHERE estado != 'recibido'")->fetch_row()[0] ?? 0;
$ultimos_emp     = $conn->query("SELECT nombre,apellidos,departamento,fecha_alta FROM empleados WHERE activo=1 ORDER BY fecha_alta DESC LIMIT 5");
$stock_alerta    = $conn->query("SELECT nombre,cantidad,stock_minimo FROM inventario WHERE cantidad<=stock_minimo ORDER BY cantidad ASC LIMIT 5");

// Estado de fichaje del usuario actual hoy
$uid = (int)$_SESSION['usuario_id'];
$sf  = $conn->prepare("SELECT tipo, fecha FROM fichajes WHERE usuario_id = ? AND DATE(fecha) = CURDATE() ORDER BY fecha ASC");
$sf->bind_param('i', $uid);
$sf->execute();
$fichajes_hoy = $sf->get_result()->fetch_all(MYSQLI_ASSOC);
$n_fich       = count($fichajes_hoy);
$completado   = $n_fich >= 2;
$puede_entrada = $n_fich === 0;
$puede_salida  = $n_fich === 1 && $fichajes_hoy[0]['tipo'] === 'entrada';

// Saludo personalizado con nombre y fecha en español
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$dias   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$meses  = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$fecha_saludo = $dias[date('w')].' '.date('j').' de '.$meses[(int)date('n')].' de '.date('Y');

$paginaActiva = 'inicio';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Inicio</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
  <div class="topbar">
    <h1>Inicio</h1>
    <div class="topbar-right">
      <?php if ($_SESSION['rol'] === 'admin'): ?>
        <a href="../backup.php" class="btn btn-gris btn-sm" title="Descargar backup SQL">💾 Backup BD</a>
      <?php endif; ?>
      <?php if (isset($_GET['ticket_ok'])): ?>
        <span class="badge badge-verde">✓ Ticket enviado</span>
      <?php endif; ?>
      <span class="text-gris"><?=date('d/m/Y — H:i')?></span>
    </div>
  </div>
  <main class="main">

    <!-- Banner de saludo con fichaje integrado -->
    <div class="saludo-banner">
      <div style="flex:1;">
        <p class="saludo-hola">Hola, <strong><?=htmlspecialchars($nombre_usuario)?></strong> 👋</p>
        <p class="saludo-fecha">Hoy es <?=htmlspecialchars($fecha_saludo)?></p>
        <!-- Resumen de fichajes de hoy -->
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach ($fichajes_hoy as $f): ?>
            <span style="background:rgba(255,255,255,.2);padding:3px 10px;border-radius:20px;font-size:12px;">
              <?= ucfirst($f['tipo']) ?> <?= date('H:i', strtotime($f['fecha'])) ?>
            </span>
          <?php endforeach; ?>
          <?php if (empty($fichajes_hoy)): ?>
            <span style="opacity:.7;font-size:12px;">Sin fichajes hoy todavía</span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Botón de fichaje -->
      <div style="margin-left:20px;text-align:center;flex-shrink:0;">
        <?php if ($completado): ?>
          <span style="background:rgba(255,255,255,.2);padding:10px 18px;border-radius:8px;font-size:14px;display:block;">
            ✅ Jornada completa
          </span>
        <?php elseif ($puede_entrada): ?>
          <form method="POST">
            <input type="hidden" name="accion" value="fichar">
            <button type="button" class="btn" style="background:#fff;color:var(--azul);font-weight:700;padding:10px 20px;"
                    onclick="confirmar('¿Confirmas que quieres registrar tu ENTRADA ahora?', () => this.closest('form').submit(), 'verde')">
              ✅ Registrar entrada
            </button>
          </form>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="accion" value="fichar">
            <button type="button" class="btn" style="background:rgba(255,255,255,.15);color:#fff;font-weight:700;padding:10px 20px;border:2px solid rgba(255,255,255,.4);"
                    onclick="confirmar('¿Confirmas que quieres registrar tu SALIDA ahora?', () => this.closest('form').submit(), 'rojo')">
              🚪 Registrar salida
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Tarjetas de resumen (solo para roles con acceso a esa info) -->
    <div class="stats-grid">
      <?php if(esRRHH()): ?>
      <div class="stat-card"><div class="stat-label">Empleados activos</div><div class="stat-valor"><?=$total_empleados?></div></div>
      <?php endif; ?>
      <?php if(esAlmacen()): ?>
      <div class="stat-card verde"><div class="stat-label">Artículos en inventario</div><div class="stat-valor"><?=$total_articulos?></div></div>
      <div class="stat-card <?=$stock_bajo>0?'rojo':'verde'?>"><div class="stat-label">Stock bajo alerta</div><div class="stat-valor"><?=$stock_bajo?></div></div>
      <div class="stat-card <?=$pedidos_activos>0?'naranja':''?>"><div class="stat-label">Pedidos stock en curso</div><div class="stat-valor"><?=$pedidos_activos?></div></div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
      <?php if(esRRHH()): ?>
      <div class="panel">
        <div class="panel-header"><h2>Últimas incorporaciones</h2><a href="empleados.php" class="btn btn-gris btn-sm">Ver todos</a></div>
        <table><thead><tr><th>Empleado</th><th>Departamento</th><th>Alta</th></tr></thead><tbody>
        <?php if($ultimos_emp && $ultimos_emp->num_rows>0): while($e=$ultimos_emp->fetch_assoc()): ?>
        <tr><td><?=htmlspecialchars($e['nombre'].' '.$e['apellidos'])?></td><td><span class="badge badge-azul"><?=htmlspecialchars($e['departamento'])?></span></td><td class="text-gris"><?=date('d/m/Y',strtotime($e['fecha_alta']))?></td></tr>
        <?php endwhile; else: ?><tr><td colspan="3" class="text-gris text-center">Sin datos</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
      <?php if(esAlmacen()): ?>
      <div class="panel">
        <div class="panel-header"><h2>⚠ Stock bajo mínimo</h2><a href="inventario.php" class="btn btn-gris btn-sm">Ver inventario</a></div>
        <table><thead><tr><th>Artículo</th><th>Stock</th><th>Mínimo</th></tr></thead><tbody>
        <?php if($stock_alerta && $stock_alerta->num_rows>0): while($a=$stock_alerta->fetch_assoc()): ?>
        <tr><td><?=htmlspecialchars($a['nombre'])?></td><td><span class="badge badge-rojo"><?=$a['cantidad']?></span></td><td class="text-gris"><?=$a['stock_minimo']?></td></tr>
        <?php endwhile; else: ?><tr><td colspan="3" class="text-gris text-center">Todo en orden ✓</td></tr><?php endif; ?>
        </tbody></table>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div>
</div>

<script src="../js/app.js"></script>
</body></html>
