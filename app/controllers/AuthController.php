<?php
declare(strict_types=1);

/**
 * Login, logout y recuperación de contraseña.
 * GET  ?r=login               → mostrarLogin()
 * POST ?r=login               → login()
 * GET  ?r=logout              → logout()
 * GET  ?r=recuperar           → mostrarRecuperar()    (formulario "olvidé mi contraseña")
 * POST ?r=recuperar           → recuperar()           (genera el token y manda el mail)
 * GET  ?r=restablecer&token=X → mostrarRestablecer()  (formulario de contraseña nueva)
 * POST ?r=restablecer         → restablecer()         (guarda la contraseña nueva)
 */
class AuthController
{
    //GET: Preparar datos y mostar la vista del formulario de login
    public function mostrarLogin(): void
    {
      $usuario = usuarioActual();
      if ($usuario !== null) {
        $this->irAlPanel($usuario);
      }

      $error = null;
      $email = '';
      // Mensaje que deja otra pantalla antes de redirigir acá (ej: "contraseña actualizada"); se muestra una sola vez
      $mensajeOK = $_SESSION['mensaje_ok'] ?? null;
      unset($_SESSION['mensaje_ok']);
      require RAIZ . '/app/views/auth/login.php'; //require porque es obligatorio que exista el archivo, si no existe tira error a traves de un warning de PHP.
    }

    private function irAlPanel(Usuario $usuario): void
    {
      if ($usuario->esAdmin()) {
        redirigir('admin');
      } else {
        redirigir('panel');
      }
    }

    // Vuelve a mostrar el formulario de login con un mensaje de error
    private function mostrarLoginConError(string $email, string $error): void
    {
      $mensajeOK = null;
      require RAIZ . '/app/views/auth/login.php';
    }

    //POST: Procesar el formulario de login
    public function login(): void
    {
      // Leemos los datos del formulario; al email le sacamos los espacios del inicio y del final
      $email = trim($_POST['email'] ?? '');
      $pass  = $_POST['password'] ?? '';

      // El formulario tiene que traer el token CSRF de nuestra sesión
      if (!validarCsrf()) {
        $this->mostrarLoginConError($email, 'La página expiró, volvé a intentar');
        return;
      }

      // Si ese email acumuló demasiados intentos fallidos, ni siquiera revisamos la contraseña
      $minutosBloqueado = IntentoLogin::minutosBloqueado($email);
      if ($minutosBloqueado > 0) {
        $this->mostrarLoginConError($email, "Demasiados intentos fallidos. Probá de nuevo en $minutosBloqueado minuto(s)");
        return;
      }

      // autenticar() verifica que el email exista, la cuenta esté activa y la contraseña coincida con el hash.
      $usuario = Usuario::autenticar($email, $pass);
      if ($usuario === null) {
        // Registramos el intento fallido aunque el email no exista: así el bloqueo
        // se comporta igual y no revela qué emails están registrados.
        IntentoLogin::registrar($email, $_SERVER['REMOTE_ADDR'] ?? ''); // IP del cliente (si no está disponible, dejamos vacío)
        $this->mostrarLoginConError($email, 'Email o contraseña incorrectos');
        return;
      }

      // Login exitoso: borramos los intentos fallidos de ese email
      IntentoLogin::limpiar($email);

      // Generamos un id de sesión nuevo para que no se pueda reutilizar uno anterior (session fixation)
      session_regenerate_id(true);
      $_SESSION['usuario_id'] = $usuario->id;
      $_SESSION['ultima_actividad'] = time();
      $this->irAlPanel($usuario);
    }

    //GET: Cierra la sesión y redirige al login
    public function logout(): void
    {
      cerrarSesion();
      redirigir('login');
    }

    //GET: Muestra el formulario para pedir el mail de recuperación
    public function mostrarRecuperar(): void
    {
      $error = null;
      $mensajeOK = null;
      $email = '';
      require RAIZ . '/app/views/auth/recuperar.php';
    }

    //POST: Si el email es de un usuario activo, le manda un link con un token temporal
    public function recuperar(): void
    {
      $email = trim($_POST['email'] ?? '');
      $error = null;
      $mensajeOK = null;

      if (!validarCsrf()) {
        $error = 'La página expiró, volvé a intentar';
        require RAIZ . '/app/views/auth/recuperar.php';
        return;
      }

      $usuario = Usuario::buscarPorEmail($email);
      if ($usuario !== null && $usuario->activo) {
        // Generamos el token y armamos el link que va en el mail
        $token = RecuperacionPassword::crear($usuario->id);
        $link = urlBase() . '/index.php?r=restablecer&token=' . $token;

        $asunto = 'Recuperar contraseña - Prode';
        $cuerpo = "Hola {$usuario->nombre}:\n\n"
                . "Recibimos un pedido para restablecer la contraseña de tu cuenta del Prode.\n"
                . "Para elegir una nueva entrá a este link (vence en " . RecuperacionPassword::MINUTOS_VALIDEZ . " minutos):\n\n"
                . "$link\n\n"
                . "Si no fuiste vos, ignorá este mail: tu contraseña no cambia.\n";

        if (!Mail::enviar($usuario->email, $asunto, $cuerpo)) {
          // Lo dejamos en el log de PHP; al usuario no le avisamos (le revelaría que el email existe)
          error_log('No se pudo enviar el mail de recuperación a ' . $usuario->email);
        }
      }

      // Mostramos siempre el mismo mensaje, exista o no el email, para no revelar quién está registrado
      $mensajeOK = 'Si el email está registrado, te enviamos un link para restablecer la contraseña. Revisá tu casilla.';
      $email = '';
      require RAIZ . '/app/views/auth/recuperar.php';
    }

    //GET: Muestra el formulario de contraseña nueva si el link (token) es válido
    public function mostrarRestablecer(): void
    {
      // El token viaja en la URL: le pedimos al navegador que no la mande a otros sitios
      header('Referrer-Policy: no-referrer');

      $token = $_GET['token'] ?? '';
      $error = null;
      $tokenValido = RecuperacionPassword::buscarUsuario($token) !== null;
      if (!$tokenValido) {
        $error = 'El link no es válido o ya venció. Pedí uno nuevo.';
      }
      require RAIZ . '/app/views/auth/restablecer.php';
    }

    //POST: Valida la contraseña nueva, la guarda e invalida el token
    public function restablecer(): void
    {
      header('Referrer-Policy: no-referrer');

      $token = $_POST['token'] ?? '';
      $pass = $_POST['password'] ?? '';
      $passConfirmacion = $_POST['confirmacion'] ?? '';
      $tokenValido = true;
      $error = null;

      // Volvemos a verificar el token: puede haber vencido mientras el usuario completaba el formulario
      $usuario = RecuperacionPassword::buscarUsuario($token);

      if (!validarCsrf()) {
        $error = 'La página expiró, volvé a intentar';
      } elseif ($usuario === null) {
        $tokenValido = false;
        $error = 'El link no es válido o ya venció. Pedí uno nuevo.';
      } elseif (mb_strlen($pass) < Usuario::LARGO_MINIMO_PASSWORD) {
        $error = 'La contraseña tiene que tener al menos ' . Usuario::LARGO_MINIMO_PASSWORD . ' caracteres';
      } elseif ($pass !== $passConfirmacion) {
        $error = 'Las contraseñas no coinciden';
      }

      if ($error !== null) {
        require RAIZ . '/app/views/auth/restablecer.php';
        return;
      }

      Usuario::cambiarPassword($usuario->id, $pass);
      // El link ya se usó: lo invalidamos (y cualquier otro pendiente de ese usuario)
      RecuperacionPassword::invalidarDelUsuario($usuario->id);
      // Si la cuenta estaba bloqueada por intentos fallidos, la desbloqueamos
      IntentoLogin::limpiar($usuario->email);

      $_SESSION['mensaje_ok'] = 'Tu contraseña se actualizó. Ya podés ingresar.';
      redirigir('login');
    }
}
