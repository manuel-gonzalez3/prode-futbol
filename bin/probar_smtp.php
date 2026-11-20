<?php
/**
 * Prueba la conexión y el login SMTP con los datos del .env, sin mandar ningún mail.
 * Uso (desde la carpeta del proyecto):
 *   D:\xampp\php\php.exe bin\probar_smtp.php
 * Muestra la respuesta completa del servidor en cada paso. La contraseña nunca se imprime.
 */

declare(strict_types=1);

require __DIR__ . '/../config/config.php';

$host    = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
$puerto  = (int) ($_ENV['MAIL_PORT'] ?? 465);
$usuario = $_ENV['MAIL_USER'] ?? '';
$clave   = $_ENV['MAIL_PASS'] ?? '';

echo "Servidor: $host:$puerto\n";
echo "Usuario:  $usuario\n";
echo 'Clave:    ' . strlen($clave) . ' caracteres'
   . (str_contains($clave, ' ') ? ' (TIENE ESPACIOS: sacalos)' : '')
   . (preg_match('/^[a-z]{16}$/', $clave) ? ' (formato de contraseña de aplicación: OK)' : ' (no parece una contraseña de aplicación de 16 letras)')
   . "\n\n";

$socket = @stream_socket_client("ssl://$host:$puerto", $codigoError, $mensajeError, 10);
if ($socket === false) {
    exit("No se pudo conectar: $mensajeError\nRevisá la conexión a internet y que la extensión openssl esté activada en php.ini.\n");
}

// Lee la respuesta completa del servidor (puede tener varias líneas) y la muestra
function respuesta($socket): string
{
    $texto = '';
    while (($linea = fgets($socket, 515)) !== false) {
        $texto .= $linea;
        if (strlen($linea) < 4 || $linea[3] === ' ') {
            break;
        }
    }
    echo '  <- ' . str_replace("\n", "\n     ", rtrim($texto)) . "\n";
    return substr($texto, 0, 3);
}

function enviar($socket, string $comando, string $paraMostrar): void
{
    echo "  -> $paraMostrar\n";
    fwrite($socket, $comando . "\r\n");
}

respuesta($socket);
enviar($socket, 'EHLO localhost', 'EHLO localhost');
respuesta($socket);
enviar($socket, 'AUTH LOGIN', 'AUTH LOGIN');
respuesta($socket);
enviar($socket, base64_encode($usuario), "(usuario $usuario en base64)");
respuesta($socket);
enviar($socket, base64_encode($clave), '(contraseña en base64, oculta)');
$codigo = respuesta($socket);
enviar($socket, 'QUIT', 'QUIT');
fclose($socket);

echo "\n";
if ($codigo === '235') {
    echo "LOGIN CORRECTO: el usuario y la contraseña funcionan. Si el mail igual no llega, revisá spam\n";
    echo "y que el email al que pedís la recuperación sea de un usuario activo en la base.\n";
} elseif ($codigo === '535') {
    echo "LOGIN RECHAZADO (535): Gmail no acepta ese usuario con esa contraseña.\n";
    echo "Generá una contraseña de aplicación nueva estando logueado en la MISMA cuenta que MAIL_USER,\n";
    echo "pegala sin espacios en MAIL_PASS y esperá unos minutos antes de volver a probar.\n";
} elseif ($codigo === '534') {
    echo "Gmail pide una contraseña de aplicación (534): en MAIL_PASS está la contraseña normal de la cuenta.\n";
} else {
    echo "Respuesta inesperada ($codigo). Si es 421, Gmail está frenando por muchos intentos: esperá unos minutos.\n";
}
