<?php
/**
 * Bootstrap de la aplicación: variables de entorno + autoload.
 * Todo punto de entrada (public/index.php, bin/*.php) empieza con:
 *   require __DIR__ . '/../config/config.php';
 */

declare(strict_types=1);

define('RAIZ', dirname(__DIR__)); //define la constante RAIZ como el directorio raíz del proyecto

/**
 * Carga las variables de .env en $_ENV.
 * Parser mínimo (KEY=valor, líneas con # se ignoran); cuando se sume Composer
 * puede reemplazarse por vlucas/phpdotenv sin tocar el resto del código.
 */
function cargarEnv(string $ruta): void
{
    if (!is_readable($ruta)) {
        return; // sin .env se usan los valores por defecto de database.php
    }
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $_ENV[trim($clave)] = trim($valor, " \t\"'");
    }
}

cargarEnv(RAIZ . '/.env'); // carga las variables de entorno en $_ENV

/** Autoload simple: ClaseX -> app/models/ClaseX.php | app/services/ClaseX.php */
spl_autoload_register(function (string $clase): void {
    foreach (['models', 'services', 'controllers'] as $capa) {
        $archivo = RAIZ . "/app/$capa/$clase.php";
        if (is_file($archivo)) {
            require $archivo;
            return;
        }
    }
});

require RAIZ . '/config/database.php';
require RAIZ . '/app/helpers/scoring.php';
require RAIZ . '/app/helpers/auth.php';
require RAIZ . '/app/helpers/permisos.php';
