<?php

declare(strict_types=1);

/**
 * Envío de mails (sin librerías externas).
 *
 * Se elige el modo con MAIL_MODO en el .env:
 *  - log  (por defecto): no manda nada, escribe el mail en logs/mail.log.
 *         Sirve para probar en local sin configurar ninguna casilla.
 *  - smtp: lo manda de verdad por SMTP con SSL (por ejemplo Gmail, puerto 465).
 *         Necesita MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS y MAIL_FROM.
 *         En Gmail, MAIL_PASS es una "contraseña de aplicación", no la de la cuenta.
 */
class Mail
{
    /** Envía un mail de texto plano. Devuelve true si salió bien. */
    public static function enviar(string $para, string $asunto, string $cuerpo): bool
    {
        $modo = $_ENV['MAIL_MODO'] ?? 'log'; 
        if ($modo === 'smtp') {
            return self::enviarPorSmtp($para, $asunto, $cuerpo);
        }
        return self::guardarEnLog($para, $asunto, $cuerpo);
    }

    // Modo log: agrega el mail al final de logs/mail.log
    private static function guardarEnLog(string $para, string $asunto, string $cuerpo): bool
    {
        $carpeta = RAIZ . '/logs';
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        $texto = "==================== " . date('Y-m-d H:i:s') . "\n"
               . "Para: $para\n"
               . "Asunto: $asunto\n\n"
               . "$cuerpo\n\n";
        return file_put_contents("$carpeta/mail.log", $texto, FILE_APPEND) !== false;
    }

    // Modo smtp: hablamos directamente con el servidor de correo siguiendo el protocolo SMTP
    private static function enviarPorSmtp(string $para, string $asunto, string $cuerpo): bool
    {
        $host      = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $puerto    = (int) ($_ENV['MAIL_PORT'] ?? 465);
        $usuario   = $_ENV['MAIL_USER'] ?? '';
        $clave     = $_ENV['MAIL_PASS'] ?? '';
        $remitente = $_ENV['MAIL_FROM'] ?? $usuario;

        // Abrimos la conexión cifrada (SSL) con el servidor; 10 segundos de espera como máximo
        $socket = @stream_socket_client("ssl://$host:$puerto", $codigoError, $mensajeError, 10);
        if ($socket === false) {
            error_log("Mail: no se pudo conectar a $host:$puerto ($mensajeError)");
            return false;
        }

        // Armamos el mail: encabezados + cuerpo. Asunto y cuerpo van en base64 para que
        // las tildes y la ñ lleguen bien.
        $mensaje = "Date: " . date('r') . "\r\n"
                 . "From: Prode <$remitente>\r\n"
                 . "To: <$para>\r\n"
                 . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: base64\r\n"
                 . "\r\n"
                 . chunk_split(base64_encode($cuerpo));

        // Cada paso es [comando que mandamos, código que esperamos que responda el servidor]
        $pasos = [
            [null, 220],                                // saludo del servidor al conectarnos
            ['EHLO localhost', 250],
            ['AUTH LOGIN', 334],
            [base64_encode($usuario), 334],
            [base64_encode($clave), 235],               // 235 = autenticación correcta
            ["MAIL FROM:<$remitente>", 250],
            ["RCPT TO:<$para>", 250],
            ['DATA', 354],
            [$mensaje . "\r\n.", 250],                  // el mail termina con una línea que tiene solo un punto
        ];

        foreach ($pasos as [$comando, $codigoEsperado]) {
            if ($comando !== null) {
                fwrite($socket, $comando . "\r\n");
            }
            $codigo = self::leerRespuesta($socket);
            if ($codigo !== $codigoEsperado) {
                error_log("Mail: el servidor respondió $codigo y se esperaba $codigoEsperado");
                fclose($socket);
                return false;
            }
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        return true;
    }

    // Lee la respuesta del servidor y devuelve su código (los 3 primeros dígitos).
    // Una respuesta puede tener varias líneas ("250-...") y termina en la que tiene un espacio ("250 ...").
    private static function leerRespuesta($socket): int
    {
        while (($linea = fgets($socket, 515)) !== false) {
            if (strlen($linea) < 4 || $linea[3] === ' ') {
                return (int) substr($linea, 0, 3);
            }
        }
        return 0; // el servidor cortó la conexión
    }
}
