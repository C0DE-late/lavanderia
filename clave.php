<?php
/** Cambio de clave del propio usuario. */
require __DIR__ . '/app/bootstrap.php';
$u = exigirRol();
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $actual = (string) ($_POST['actual'] ?? '');
    $nueva = (string) ($_POST['nueva'] ?? '');
    $repetir = (string) ($_POST['repetir'] ?? '');

    $st = db()->prepare('SELECT clave_hash FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    if (!password_verify($actual, (string) $st->fetchColumn())) $errores[] = 'La clave actual no es correcta.';
    if (mb_strlen($nueva) < 8) $errores[] = 'La nueva clave debe tener al menos 8 caracteres.';
    if ($nueva !== $repetir) $errores[] = 'Las dos claves nuevas no coinciden.';

    if (!$errores) {
        db()->prepare('UPDATE usuarios SET clave_hash = ? WHERE id = ?')->execute([password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
        aviso('exito', 'Tu clave se cambió correctamente.');
        redirigir('index.php');
    }
}

pagina('Mi clave', function () use ($errores) { ?>
    <h1>Cambiar mi clave</h1>
    <?php if ($errores): ?>
        <div class="aviso aviso-error" role="alert"><?php foreach ($errores as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
    <?php endif; ?>
    <form method="post" class="tarjeta" style="max-width:420px">
        <?= campoCsrf() ?>
        <div class="campo"><label for="actual">Clave actual</label><input type="password" id="actual" name="actual" autocomplete="current-password" required></div>
        <div class="campo"><label for="nueva">Nueva clave (mínimo 8)</label><input type="password" id="nueva" name="nueva" autocomplete="new-password" minlength="8" required></div>
        <div class="campo"><label for="repetir">Repite la nueva clave</label><input type="password" id="repetir" name="repetir" autocomplete="new-password" required></div>
        <button class="boton" type="submit">Cambiar clave</button>
    </form>
<?php });
