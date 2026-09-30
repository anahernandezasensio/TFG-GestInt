<?php
$rol    = $_SESSION['rol']    ?? 'empleado';
$nombre = $_SESSION['nombre'] ?? 'Usuario';
$paginaActiva = $paginaActiva ?? '';

$menu = [
    ['href'=>'../pages/dashboard.php',     'icon'=>'⊞',  'label'=>'Inicio',           'id'=>'inicio',         'ok'=>true],

    // RRHH: empleados, fichajes, justificantes, vacaciones
    ['sep'=>'RRHH',                                                                                             'ok'=>esRRHH()],
    ['href'=>'../pages/empleados.php',     'icon'=>'👤', 'label'=>'Empleados',         'id'=>'empleados',      'ok'=>esRRHH()],
    ['href'=>'../pages/vacaciones.php',    'icon'=>'🌴', 'label'=>'Vacaciones',        'id'=>'vacaciones',     'ok'=>esRRHH()],

    // Almacén: inventario, reponer, pedidos stock, pedidos clientes
    ['sep'=>'Almacén',                                                                                          'ok'=>esAlmacen()],
    ['href'=>'../pages/inventario.php',    'icon'=>'📦', 'label'=>'Inventario',        'id'=>'inventario',     'ok'=>esAlmacen()],
    ['href'=>'../pages/carrito.php',       'icon'=>'🛒', 'label'=>'Reponer stock',     'id'=>'carrito',        'ok'=>esAlmacen()],
    ['href'=>'../pages/pedidos.php',       'icon'=>'🚚', 'label'=>'Pedidos stock',     'id'=>'pedidos',        'ok'=>esAlmacen()],

    // Ventas: solo empleados (y admin/director). Almacén también ve pedidos clientes.
    ['sep'=>'Ventas',                                                                                           'ok'=>esVentas() || esAlmacen()],
    ['href'=>'../pages/clientes.php',      'icon'=>'🏢', 'label'=>'Clientes',          'id'=>'clientes',       'ok'=>esVentas()],
    ['href'=>'../pages/ventas.php',        'icon'=>'💼', 'label'=>'Ventas a clientes', 'id'=>'ventas',         'ok'=>esVentas()],
    ['href'=>'../pages/pedidos_ventas.php','icon'=>'📋', 'label'=>'Pedidos clientes',  'id'=>'pedidos_ventas', 'ok'=>esVentas() || esAlmacen()],

    // Sistema: solo admin/soporte
    ['sep'=>'Sistema',                                                                                          'ok'=>esAdmin()],
    ['href'=>'../pages/usuarios.php',      'icon'=>'🔑', 'label'=>'Usuarios',          'id'=>'usuarios',       'ok'=>esAdmin()],
    ['href'=>'../pages/logs.php',          'icon'=>'📜', 'label'=>'Logs acceso',       'id'=>'logs',           'ok'=>esSoporte()],
    ['href'=>'../pages/tickets.php',       'icon'=>'🎫', 'label'=>'Tickets soporte',   'id'=>'tickets',        'ok'=>esSoporte()],

    ['href'=>'../pages/perfil.php',        'icon'=>'👤', 'label'=>'Mi perfil',         'id'=>'perfil',         'ok'=>true],
];
?>
<button class="btn-menu-movil" onclick="abrirSidebar()" title="Menú" style="position:fixed;top:14px;left:14px;z-index:201;">☰</button>

<aside class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-icon">DM</span>
        <div style="display:flex;flex-direction:column;line-height:1.2;">
            <span class="logo-text" style="font-size:13px;letter-spacing:.5px;">DUNDER MIFFLIN</span>
            <span style="font-size:10px;color:rgba(255,255,255,.55);">Scranton Branch</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menu as $item):
            if (!$item['ok']) continue;
            if (isset($item['sep'])): ?>
                <div class="nav-sep"><?= $item['sep'] ?></div>
            <?php else: ?>
            <a href="<?= $item['href'] ?>" class="nav-link <?= $paginaActiva === $item['id'] ? 'activa' : '' ?>">
                <span class="nav-icon"><?= $item['icon'] ?></span>
                <span class="nav-label"><?= $item['label'] ?></span>
            </a>
            <?php endif;
        endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <!-- Enlace rápido a soporte IT, visible para todos -->
        <a href="javascript:void(0)" onclick="abrirModal('modal-ticket')"
           style="display:flex;align-items:center;gap:8px;padding:8px 14px;color:rgba(255,255,255,.7);font-size:12px;border-top:1px solid rgba(255,255,255,.1);text-decoration:none;"
           title="Contactar con soporte">
            🎫 <span>Soporte IT</span>
        </a>
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($nombre, 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($nombre) ?></span>
                <span class="user-rol"><?= nombreRol($rol) ?></span>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout" title="Cerrar sesión">⏻</a>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="cerrarSidebar()"></div>

<!-- Modal de confirmación genérico -->
<div class="modal-overlay" id="modal-confirm">
    <div class="modal" style="max-width:360px;">
        <div class="modal-body" style="padding:28px 20px 16px;text-align:center;">
            <p id="confirm-msg" style="font-size:15px;font-weight:500;line-height:1.5;"></p>
        </div>
        <div class="modal-footer" style="justify-content:center;gap:12px;">
            <button class="btn btn-gris" onclick="cerrarModal('modal-confirm')">Cancelar</button>
            <button class="btn btn-rojo" id="confirm-si" onclick="ejecutarConfirm()">Confirmar</button>
        </div>
    </div>
</div>

<!-- Modal rápido para enviar ticket de soporte desde cualquier página -->
<div class="modal-overlay" id="modal-ticket">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3>🎫 Abrir ticket de soporte</h3>
            <button class="modal-cerrar" onclick="cerrarModal('modal-ticket')">✕</button>
        </div>
        <form method="POST" action="../pages/ticket_rapido.php">
            <div class="modal-body">
                <div class="form-grupo">
                    <label>Asunto *</label>
                    <input type="text" name="t_asunto" required placeholder="Describe brevemente el problema">
                </div>
                <div class="form-grupo">
                    <label>Descripción *</label>
                    <textarea name="t_desc" rows="4" required placeholder="Explica el problema con detalle..."
                        style="width:100%;padding:8px 12px;border:1px solid var(--gris-borde);border-radius:6px;font-size:14px;resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gris" onclick="cerrarModal('modal-ticket')">Cancelar</button>
                <button type="submit" class="btn btn-primario">Enviar ticket</button>
            </div>
        </form>
    </div>
</div>
