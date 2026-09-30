<?php
// Vacaciones: empleados solicitan días, RRHH aprueba o rechaza
require_once '../includes/auth.php';
require_once '../includes/db.php';

$msg = ''; $tipo = '';
$uid = (int)$_SESSION['usuario_id'];

// RRHH: aprobar o rechazar solicitud
if (esRRHH() && isset($_GET['accion']) && isset($_GET['id'])) {
    $vid    = (int)$_GET['id'];
    $accion = $_GET['accion'];
    if (in_array($accion, ['aprobar','rechazar'])) {
        $estado   = $accion === 'aprobar' ? 'aprobada' : 'rechazada';
        $respuesta = trim($_GET['respuesta'] ?? '');
        $upd = $conn->prepare("UPDATE vacaciones SET estado = ?, respuesta = ? WHERE id = ?");
        $upd->bind_param('ssi', $estado, $respuesta, $vid);
        $upd->execute();
        $msg  = $accion === 'aprobar' ? 'Solicitud aprobada.' : 'Solicitud rechazada.';
        $tipo = $accion === 'aprobar' ? 'ok' : 'aviso';
    }
}

// Empleado: enviar solicitud de vacaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'solicitar') {
    $fecha_ini = $_POST['fecha_ini'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';
    $motivo    = trim($_POST['motivo'] ?? '');

    if (!$fecha_ini || !$fecha_fin) {
        $msg = 'Indica las fechas de inicio y fin.'; $tipo = 'error';
    } else {
        // Calcular días (excluir fines de semana sería ideal, pero aquí contamos días naturales)
        $ini  = new DateTime($fecha_ini);
        $fin  = new DateTime($fecha_fin);
        $dias = (int)$ini->diff($fin)->days + 1;

        if ($dias <= 0) {
            $msg = 'La fecha de fin debe ser posterior a la de inicio.'; $tipo = 'error';
        } elseif ($dias > 15) {
            $msg = 'No puedes solicitar más de 15 días de vacaciones a la vez.'; $tipo = 'error';
        } else {
            $ins = $conn->prepare("INSERT INTO vacaciones (usuario_id, fecha_ini, fecha_fin, dias, motivo) VALUES (?,?,?,?,?)");
            $ins->bind_param('isssi', $uid, $fecha_ini, $fecha_fin, $dias, $motivo);
            $ins->execute();
            $msg  = "Solicitud enviada ($dias días). RRHH la revisará en breve.";
            $tipo = 'ok';
        }
    }
}

// Mis solicitudes (si soy empleado)
$mis_solicitudes = [];
$sq = $conn->prepare("SELECT * FROM vacaciones WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 20");
$sq->bind_param('i', $uid);
$sq->execute();
$mis_solicitudes = $sq->get_result()->fetch_all(MYSQLI_ASSOC);

// Todas las solicitudes (si soy RRHH/admin)
$todas_solicitudes = [];
if (esRRHH()) {
    $filtro_estado = $_GET['filtro'] ?? 'pendientes';
    $where_f = $filtro_estado === 'todos' ? '' : "WHERE v.estado = 'pendiente'";
    $todas_solicitudes = $conn->query("
        SELECT v.*, u.nombre, u.apellidos
        FROM vacaciones v
        JOIN usuarios u ON u.id = v.usuario_id
        $where_f
        ORDER BY v.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

$paginaActiva = 'vacaciones';
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dunder Mifflin — Vacaciones</title>
<link rel="stylesheet" href="../css/style.css">
</head><body>
<div class="layout">
<?php require_once '../includes/sidebar.php'; ?>
<div class="contenido">
    <div class="topbar"><h1>Vacaciones</h1></div>
    <main class="main">
        <?php if ($msg): ?><div class="alerta alerta-<?= $tipo ?>"><?= $msg ?></div><?php endif; ?>

        <!-- Formulario de solicitud (todos los roles) -->
        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-header"><h2>Solicitar vacaciones</h2></div>
            <div class="panel-body">
                <form method="POST" class="form-grid">
                    <input type="hidden" name="accion" value="solicitar">
                    <div class="form-grupo">
                        <label>Fecha inicio *</label>
                        <input type="date" name="fecha_ini" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-grupo">
                        <label>Fecha fin *</label>
                        <input type="date" name="fecha_fin" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-grupo full">
                        <label>Motivo (opcional)</label>
                        <input type="text" name="motivo" placeholder="Ej: vacaciones verano, asunto personal...">
                    </div>
                    <div class="form-grupo full">
                        <p class="text-gris" style="font-size:13px;margin-bottom:8px;">Máximo 15 días por solicitud. RRHH revisará y aprobará la solicitud.</p>
                        <button type="submit" class="btn btn-primario">Enviar solicitud</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mis solicitudes -->
        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-header"><h2>Mis solicitudes</h2></div>
            <?php if (!empty($mis_solicitudes)): ?>
            <table>
                <thead><tr><th>Período</th><th>Días</th><th>Motivo</th><th>Estado</th><th>Respuesta</th></tr></thead>
                <tbody>
                <?php foreach ($mis_solicitudes as $v): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($v['fecha_ini'])) ?> → <?= date('d/m/Y', strtotime($v['fecha_fin'])) ?></td>
                    <td class="fw-600"><?= $v['dias'] ?></td>
                    <td class="text-gris"><?= htmlspecialchars($v['motivo'] ?: '—') ?></td>
                    <td>
                        <?php if ($v['estado'] === 'aprobada'): ?>
                            <span class="badge badge-verde">Aprobada</span>
                        <?php elseif ($v['estado'] === 'rechazada'): ?>
                            <span class="badge badge-rojo">Rechazada</span>
                        <?php else: ?>
                            <span class="badge badge-naranja">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-gris"><?= htmlspecialchars($v['respuesta'] ?: '—') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="panel-body"><p class="text-gris">No tienes solicitudes todavía.</p></div>
            <?php endif; ?>
        </div>

        <!-- Gestión RRHH: todas las solicitudes -->
        <?php if (esRRHH()): ?>
        <div class="panel">
            <div class="panel-header">
                <h2>Solicitudes de todos los empleados</h2>
                <div class="d-flex gap-8">
                    <a href="?filtro=pendientes" class="btn <?= ($filtro_estado??'pendientes')==='pendientes'?'btn-primario':'btn-gris' ?> btn-sm">Pendientes</a>
                    <a href="?filtro=todos"      class="btn <?= ($filtro_estado??'')==='todos'?'btn-primario':'btn-gris' ?> btn-sm">Todas</a>
                </div>
            </div>
            <?php if (!empty($todas_solicitudes)): ?>
            <table>
                <thead><tr><th>Empleado</th><th>Período</th><th>Días</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($todas_solicitudes as $v): ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($v['nombre'].' '.$v['apellidos']) ?></td>
                    <td><?= date('d/m/Y', strtotime($v['fecha_ini'])) ?> — <?= date('d/m/Y', strtotime($v['fecha_fin'])) ?></td>
                    <td><?= $v['dias'] ?></td>
                    <td class="text-gris"><?= htmlspecialchars($v['motivo'] ?: '—') ?></td>
                    <td>
                        <?php if ($v['estado'] === 'aprobada'): ?>
                            <span class="badge badge-verde">Aprobada</span>
                        <?php elseif ($v['estado'] === 'rechazada'): ?>
                            <span class="badge badge-rojo">Rechazada</span>
                        <?php else: ?>
                            <span class="badge badge-naranja">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($v['estado'] === 'pendiente'): ?>
                        <div class="d-flex gap-8">
                            <a href="?accion=aprobar&id=<?= $v['id'] ?>&filtro=<?= $filtro_estado??'pendientes' ?>"
                               class="btn btn-verde btn-sm"
                               onclick="return confirm('¿Aprobar esta solicitud?')">Aprobar</a>
                            <a href="?accion=rechazar&id=<?= $v['id'] ?>&filtro=<?= $filtro_estado??'pendientes' ?>"
                               class="btn btn-rojo btn-sm"
                               onclick="return confirm('¿Rechazar esta solicitud?')">Rechazar</a>
                        </div>
                        <?php else: ?>
                            <span class="text-gris" style="font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="panel-body"><p class="text-gris">No hay solicitudes pendientes.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
</div>
<script src="../js/app.js"></script>
</body></html>
