<?php
require __DIR__ . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $_SESSION = [];
    session_destroy();
}
redirigir('login.php');
