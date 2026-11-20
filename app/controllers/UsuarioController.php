<?php
declare(strict_types=1);

/**
 * Gestión de usuarios (solo admins).
 * GET  ?r=usuarios               → listar()
 * GET  ?r=permisos               → permisos()     (tabla de permisos por rol y por estado)
 * GET  ?r=usuario-form[&id=N]    → formulario()   (alta o edición)
 * POST ?r=usuario-form[&id=N]    → guardar()
 * POST ?r=usuario-estado&id=N    → cambiarEstado() (habilitar / deshabilitar)
 *
 * Quién puede modificar a quién está definido en app/helpers/permisos.php (puedeGestionar):
 * un admin común gestiona participantes; a otro admin solo lo modifica el admin principal.
 */
class UsuarioController
{
    //GET: Muestra la tabla con todos los usuarios
    public function listar(): void
    {
      $usuarios = Usuario::listar();
      // Mensajes que dejó la acción anterior (guardar, cambiar estado) antes de redirigir acá; se muestran una sola vez
      $mensajeOK = $_SESSION['mensaje_ok'] ?? null;
      $error = $_SESSION['mensaje_error'] ?? null;
      unset($_SESSION['mensaje_ok'], $_SESSION['mensaje_error']);
      require RAIZ . '/app/views/admin/usuarios.php';
    }

    //GET: Muestra la tabla de permisos de cada rol y qué implica cada estado de la cuenta
    public function permisos(): void
    {
      require RAIZ . '/app/views/admin/permisos.php';
    }

    //GET: Muestra el formulario vacío (alta) o con los datos del usuario (edición)
    public function formulario(): void
    {
      $editado = $this->usuarioDeLaUrl(); // null si es un alta
      $this->exigirPermisoSobre($editado);
      $datos = [
        'nombre'   => $editado->nombre ?? '',
        'apellido' => $editado->apellido ?? '',
        'email'    => $editado->email ?? '',
        'password' => '', // la contraseña está hasheada: nunca se muestra, en la edición vacía = no cambiarla
        'rol'      => $editado->rol ?? 'participante',
      ];
      $this->mostrarFormulario($editado, $datos, null);
    }

    //POST: Valida y guarda el usuario (crea o actualiza)
    public function guardar(): void
    {
      $editado = $this->usuarioDeLaUrl();
      $this->exigirPermisoSobre($editado);

      $actual = usuarioActual();
      // Nadie se cambia su propio rol: si el admin se edita a sí mismo, se ignora el rol del formulario
      $esPropio = $editado?->id === $actual->id;
      $datos = [
        'nombre'   => trim($_POST['nombre'] ?? ''),
        'apellido' => trim($_POST['apellido'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'rol'      => $esPropio ? $actual->rol : ($_POST['rol'] ?? 'participante'),
      ];

      // El formulario tiene que traer el token CSRF de nuestra sesión
      if (!validarCsrf()) {
        $this->mostrarFormulario($editado, $datos, 'La página expiró, volvé a intentar');
        return;
      }
      //Validamos que los datos sean correctos y que no haya conflictos (email duplicado, rol que no puede asignar, etc)
      $error = $this->validar($datos, $editado);
      if ($error !== null) {
        $this->mostrarFormulario($editado, $datos, $error); // vuelve a mostrar el formulario con el error
        return;
      }

      if ($editado === null) {
        Usuario::crear($datos['nombre'], $datos['apellido'], $datos['email'], $datos['password'], $datos['rol']);
        $_SESSION['mensaje_ok'] = "Se creó el usuario {$datos['nombre']} {$datos['apellido']}.";
      } else {
        Usuario::actualizar($editado->id, $datos['nombre'], $datos['apellido'], $datos['email'], $datos['password'], $datos['rol']);
        $_SESSION['mensaje_ok'] = "Se guardaron los cambios de {$datos['nombre']} {$datos['apellido']}.";
      }
      redirigir('usuarios');
    }

    //POST: Habilita o deshabilita un usuario
    public function cambiarEstado(): void
    {
      if (!validarCsrf()) {
        $this->rechazar('La página expiró, volvé a intentar');
      }
      $editado = $this->usuarioDeLaUrl();
      if ($editado === null) {
        redirigir('usuarios');
      }
      // Nadie puede deshabilitarse a sí mismo (se quedaría afuera).
      if ($editado->id === usuarioActual()->id) {
        $this->rechazar('No podés deshabilitar tu propia cuenta');
      }
      $this->exigirPermisoSobre($editado);

      Usuario::cambiarActivo($editado->id, !$editado->activo);
      $accion = $editado->activo ? 'deshabilitó' : 'habilitó';
      $_SESSION['mensaje_ok'] = "Se $accion a {$editado->nombreCompleto()}.";
      redirigir('usuarios');
    }

    /** Busca el usuario del parámetro ?id= de la URL, o null si no viene (alta). */
    private function usuarioDeLaUrl(): ?Usuario
    {
      if (!isset($_GET['id'])) { // no viene el parámetro, es un alta, $editado será null
        return null;
      }
      $encontrado = Usuario::buscarPorId((int) $_GET['id']);
      // Si viene un id que no existe no lo tratamos como un alta: avisamos y volvemos a la lista
      if ($encontrado === null) {
        $this->rechazar('El usuario no existe');
      }
      return $encontrado;
    }

    /** Corta la acción si el admin logueado no puede modificar a ese usuario (ver puedeGestionar). */
    private function exigirPermisoSobre(?Usuario $editado): void
    {
      if ($editado !== null && !puedeGestionar(usuarioActual(), $editado)) {
        $this->rechazar('No tenés permiso para modificar a ese usuario: a un admin solo lo modifica el admin principal');
      }
    }

    /** Vuelve a la lista de usuarios mostrando un mensaje de error. */
    private function rechazar(string $mensaje): never
    {
      $_SESSION['mensaje_error'] = $mensaje;
      redirigir('usuarios');
    }

    /** Prepara lo que necesita la vista del formulario y la muestra. */
    private function mostrarFormulario(?Usuario $editado, array $datos, ?string $error): void
    {
      $actual = usuarioActual();
      $esPropio = $editado?->id === $actual->id;
      // Roles que el admin logueado puede elegir (un admin común solo 'participante')
      $rolesAsignables = rolesAsignables($actual);
      require RAIZ . '/app/views/admin/usuario_form.php';
    }

    /** Devuelve el mensaje de error o null si los datos son válidos cuando se intenta guardar. */
    private function validar(array $datos, ?Usuario $editado): ?string
    {
      $actual = usuarioActual();

      if ($datos['nombre'] === '' || $datos['apellido'] === '' || $datos['email'] === '') {
        return 'Nombre, apellido y email son obligatorios';
      }
      // La contraseña es obligatoria solo en el alta; en la edición, si viene vacía se deja la que tenía.
      if ($editado === null && $datos['password'] === '') {
        return 'La contraseña es obligatoria';
      }
      if ($datos['password'] !== '' && mb_strlen($datos['password']) < Usuario::LARGO_MINIMO_PASSWORD) {
        return 'La contraseña tiene que tener al menos ' . Usuario::LARGO_MINIMO_PASSWORD . ' caracteres';
      }
      if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        return 'El email no es válido';
      }
      // El email es único: no puede estar usado por otro usuario.
      $existente = Usuario::buscarPorEmail($datos['email']);
      if ($existente !== null && $existente->id !== $editado?->id) {
        return 'Ya existe un usuario con ese email';
      }
      // El rol tiene que ser uno de los que el admin logueado puede asignar
      // (si se edita a sí mismo el rol no cambia, ver guardar()).
      if ($editado?->id !== $actual->id && !in_array($datos['rol'], rolesAsignables($actual), true)) {
        return 'No tenés permiso para asignar ese rol: solo el admin principal puede dar el rol admin';
      }
      return null;
    }
}
