<?php
/**
 * Inicio: elige el módulo. Quien tiene un solo módulo entra directo a él.
 * Cada tarjeta muestra un resumen rápido del día.
 */
require __DIR__ . '/app/bootstrap.php';
$u = exigirRol();

if (count($u['modulos']) === 1) {
    redirigir($u['modulos'][0] . '/index.php');
}

$hoy = date('Y-m-d');
$cifras = [];
if (in_array('ropa', $u['modulos'], true)) {
    $st = db()->prepare(
        "SELECT SUM(CASE WHEN tipo = 'sucia' THEN peso_kg ELSE 0 END) AS sucia,
                SUM(CASE WHEN tipo = 'limpia' THEN peso_kg ELSE 0 END) AS limpia,
                COUNT(*) AS registros
         FROM registros WHERE fecha = ?"
    );
    $st->execute([$hoy]);
    $r = $st->fetch();
    $cifras['ropa'] = ['Kg sucia' => kg($r['sucia']), 'Kg limpia' => kg($r['limpia']), 'Registros' => (int) $r['registros']];
}
if (in_array('dietas', $u['modulos'], true)) {
    $st = db()->prepare(
        'SELECT SUM(d.pedidas) AS pedidas, SUM(d.entregadas) AS entregadas, COUNT(DISTINCT e.id) AS entregas
         FROM entregas_dieta e JOIN entregas_dieta_detalle d ON d.entrega_id = e.id WHERE e.fecha = ?'
    );
    $st->execute([$hoy]);
    $r = $st->fetch();
    $cifras['dietas'] = ['Pedidas' => (int) $r['pedidas'], 'Entregadas' => (int) $r['entregadas'], 'Entregas' => (int) $r['entregas']];
}

pagina('Inicio', function () use ($u, $cifras) { ?>
    <?php encabezado('Hola, ' . explode(' ', $u['nombre'])[0], '¿En qué módulo vas a trabajar?'); ?>
    <?php if (!$u['modulos']): ?>
        <div class="aviso aviso-info">Tu usuario todavía no tiene módulos asignados. Pídele al administrador que te dé acceso.</div>
    <?php return; endif; ?>
    <div class="modulos">
        <?php foreach ($u['modulos'] as $m): $mod = MODULOS[$m]; ?>
            <a class="modulo" href="<?= e(url("$m/index.php")) ?>">
                <div class="modulo-cabeza">
                    <img src="<?= e(url($mod['icono'])) ?>" alt="" width="52" height="52">
                    <div><strong><?= e($mod['nombre']) ?></strong><span class="suave"><?= e($mod['descripcion']) ?></span></div>
                </div>
                <span class="pendientes">Hoy</span>
                <div class="modulo-cifras">
                    <?php foreach ($cifras[$m] as $etiqueta => $valor): ?>
                        <div><span><?= e($etiqueta) ?></span><b><?= e((string) $valor) ?></b></div>
                    <?php endforeach; ?>
                </div>
                <span class="boton">Entrar</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php });
