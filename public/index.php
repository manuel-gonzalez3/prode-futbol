<?php
/**
 * Front controller: todas las páginas entran por acá.
 * Se usa así: index.php?r=login | logout | recuperar | restablecer | admin | usuarios | permisos | usuario-form | usuario-estado | panel
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';

iniciarSesion(); // arranca la sesión con la cookie configurada de forma segura (ver app/helpers/auth.php)

$ruta   = $_GET['r'] ?? 'login'; // ruta por defecto
$metodo = $_SERVER['REQUEST_METHOD'];

switch ($ruta) {
    case 'login':
        $auth = new AuthController(); // crea el controlador
        if ($metodo === 'POST') {
            $auth->login();          // procesa el formulario
        } else {
            $auth->mostrarLogin();   // muestra el formulario
        }
        break;

    case 'logout':
        (new AuthController())->logout(); // cierra la sesión
        break;

    case 'recuperar':
        $auth = new AuthController();
        if ($metodo === 'POST') {
            $auth->recuperar();          // genera el token y manda el mail
        } else {
            $auth->mostrarRecuperar();   // formulario "olvidé mi contraseña"
        }
        break;

    case 'restablecer':
        $auth = new AuthController();
        if ($metodo === 'POST') {
            $auth->restablecer();          // guarda la contraseña nueva
        } else {
            $auth->mostrarRestablecer();   // formulario de contraseña nueva (llega desde el link del mail)
        }
        break;

    case 'admin':
        requiereAdmin();
        $usuario = usuarioActual();
        require RAIZ . '/app/views/admin/dashboard.php'; // muestra el panel de administración con la raiz que viene de config.php
        break;

    case 'usuarios':
        requiereAdmin();
        (new UsuarioController())->listar(); // tabla de usuarios
        break;

    case 'permisos':
        requiereAdmin();
        (new UsuarioController())->permisos(); // tabla de permisos por rol y por estado
        break;

    case 'usuario-form':
        requiereAdmin();
        $controlador = new UsuarioController();
        if ($metodo === 'POST') {
            $controlador->guardar();     // crea o actualiza
        } else {
            $controlador->formulario();  // alta (sin id) o edición (con id)
        }
        break;

    case 'usuario-estado':
        requiereAdmin();
        if ($metodo === 'POST') {
            (new UsuarioController())->cambiarEstado(); // habilita / deshabilita
        } else {
            redirigir('usuarios');
        }
        break;

    case 'panel':
        requiereLogin();
        $usuario = usuarioActual();
        require RAIZ . '/app/views/usuario/dashboard.php'; // muestra el panel del usuario con la raiz que viene de config.php
        break;

    default:
        http_response_code(404);
        require RAIZ . '/app/views/errores/404.php'; // página completa (con el layout) en vez de un texto suelto
}
