<?php
/**
 * Helpers de autenticación: sesión, control de acceso y token CSRF.
 * public/index.php llama a iniciarSesion() al principio de cada pedido.
 */

declare(strict_types=1);

// Tiempo máximo sin actividad antes de cerrar la sesión (en segundos).
const MAX_INACTIVIDAD = 30 * 60;

/** Configura la cookie de sesión de forma segura y arranca la sesión. */
function iniciarSesion(): void
{
    session_set_cookie_params([
        'httponly' => true,   // la cookie no se puede leer desde JavaScript (protege si hay un XSS)
        'samesite' => 'Lax',  // la cookie no viaja en formularios enviados desde otros sitios
    ]);
    // Rechazamos ids de sesión que no haya generado el servidor (evita session fixation)
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/** Redirige a otra ruta del front controller y corta la ejecución. */
function redirigir(string $ruta): never
{
    header('Location: index.php?r=' . $ruta); //header hace un redirect, Location es la ruta a la que se redirige.
    exit;
}

/**
 * Devuelve el usuario logueado o null.
 * Si la cuenta fue deshabilitada o borrada, o pasó demasiado tiempo sin
 * actividad, cierra la sesión.
 */
function usuarioActual(): ?Usuario
{
    static $usuario = null;   // cache por request: una sola consulta

    if ($usuario !== null) { //si ya lo buscamos antes, devolvemos el mismo objeto
        return $usuario;
    }
    if (!isset($_SESSION['usuario_id'])) { // no hay sesión iniciada
        return null;
    }

    // Si pasó más de MAX_INACTIVIDAD desde el último pedido, la sesión expira, la actividad se actualiza cuando el usuario hace un pedido, tipo GET o POST.
    if (time() - ($_SESSION['ultima_actividad'] ?? 0) > MAX_INACTIVIDAD) {
        unset($_SESSION['usuario_id'], $_SESSION['ultima_actividad']);
        return null;
    }

    // Busca el usuario en la base de datos para ver si sigue activo.
    $encontrado = Usuario::buscarPorId((int) $_SESSION['usuario_id']);
    // Si no existe o está deshabilitado, cierra la sesión y devuelve null.
    if ($encontrado === null || !$encontrado->activo) {
        unset($_SESSION['usuario_id'], $_SESSION['ultima_actividad']); // cierra la sesión
        return null;
    }

    // Actualizamos la hora del último pedido para contar la inactividad desde acá
    $_SESSION['ultima_actividad'] = time();
    $usuario = $encontrado;
    return $usuario;
}

/** Cierra la sesión por completo: borra los datos, la sesión del servidor y la cookie. */
function cerrarSesion(): void
{
    $_SESSION = [];
    $parametros = session_get_cookie_params();
    //time -3600 hace que la cookie expire en el pasado, así el navegador la borra
    setcookie(session_name(), '', time() - 3600, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']); 
    session_destroy();
}

/** Corta el acceso si no hay sesión iniciada. */
function requiereLogin(): void
{
    if (usuarioActual() === null) {
        redirigir('login'); // no hay sesión iniciada, redirige al login
    }
}

/** Corta el acceso si el usuario no es admin. */
function requiereAdmin(): void
{
    requiereLogin(); // primero verifica que haya sesión iniciada
    if (!usuarioActual()->esAdmin()) {
        redirigir('panel');
    }
}

/**
 * Devuelve el token CSRF de la sesión (lo crea la primera vez).
 * Va en un campo oculto de cada formulario: un sitio externo no lo conoce,
 * así que no puede enviar formularios haciéndose pasar por el usuario.
 * El token sirve para validar que el formulario recibido viene de nuestra sesión y no de un sitio externo (CSRF).
 */
function tokenCsrf(): string
{
    // Si no hay token en la sesión, lo generamos con random_bytes() y lo guardamos en la sesión.
    //random_bytes viene de PHP y genera bytes aleatorios seguros para criptografía, bin2hex convierte esos bytes a hexadecimal para que sea legible.
    if (!isset($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

/** ¿El formulario recibido trae el mismo token CSRF que tiene la sesión? */
function validarCsrf(): bool
{
    $tokenRecibido = $_POST['token_csrf'] ?? '';
    // hash_equals compara en tiempo constante (no da pistas por lo que tarda)
    return isset($_SESSION['token_csrf']) && hash_equals($_SESSION['token_csrf'], $tokenRecibido); //devuelve true si el token recibido coincide con el de la sesión, false si no coincide o no hay token en la sesión.
}

/**
 * URL base del sitio (sin barra final), para armar links absolutos como el del mail.
 * Conviene definir APP_URL en el .env: si se arma con el Host que manda el navegador,
 * un atacante podría cambiarlo y hacer que el link del mail apunte a su sitio.
 */
function urlBase(): string
{
    if (!empty($_ENV['APP_URL'])) {
        return rtrim($_ENV['APP_URL'], '/');
    }
    // Sin APP_URL la armamos con los datos del pedido (sirve solo para desarrollo local)
    $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $protocolo . '://' . $_SERVER['HTTP_HOST'] . $carpeta;
}
