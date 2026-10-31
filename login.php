<?php
require __DIR__ . '/app/bootstrap.php';

if (usuarioActual()) {
    redirigir('index.php');
}

$error = '';
$usuarioEscrito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $usuarioEscrito = leerTexto($_POST['usuario'] ?? '', 40);
    $clave = is_string($_POST['clave'] ?? null) ? $_POST['clave'] : '';

    // Freno sencillo contra intentos repetidos.
    $intentos = $_SESSION['intentos_login'] ?? 0;
    if ($intentos >= 5 && time() - ($_SESSION['ultimo_intento'] ?? 0) < 60) {
        $error = 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.';
    } else {
        $st = db()->prepare('SELECT id, nombre, usuario, clave_hash, rol FROM usuarios WHERE usuario = ? AND activo = 1');
        $st->execute([$usuarioEscrito]);
        $u = $st->fetch();
        if ($u && password_verify($clave, $u['clave_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['intentos_login'], $_SESSION['ultimo_intento']);
            $_SESSION['usuario'] = ['id' => (int) $u['id'], 'nombre' => $u['nombre'], 'rol' => $u['rol']];
            redirigir('index.php');
        }
        usleep(400000);
        $_SESSION['intentos_login'] = $intentos + 1;
        $_SESSION['ultimo_intento'] = time();
        $error = 'Usuario o clave incorrectos.';
    }
}

pagina('Ingresar', function () use ($error, $usuarioEscrito) {
    global $CONFIG; ?>
    <div class="login">
        <img class="logo" src="assets/logo.jpg" alt="<?= e($CONFIG['app']['institucion']) ?>" width="482" height="390">
        <form method="post" class="tarjeta">
            <h1>Ingresar</h1>
            <p class="suave"><?= e($CONFIG['app']['nombre']) ?></p>
            <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <?= campoCsrf() ?>
            <div class="campo">
                <label for="usuario">Usuario</label>
                <input id="usuario" name="usuario" value="<?= e($usuarioEscrito) ?>" autocomplete="username"
                       autocapitalize="none" required autofocus>
            </div>
            <div class="campo">
                <label for="clave">Clave</label>
                <input id="clave" name="clave" type="password" autocomplete="current-password" required>
            </div>
            <button class="boton boton-bloque" type="submit">Entrar</button>
        </form>
    </div>
<?php });
