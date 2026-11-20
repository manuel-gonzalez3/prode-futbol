<?php

declare(strict_types=1);

/**
 * Modelo Usuario.
 *
 * Representa a una persona del sistema (participante o admin) y concentra
 * el registro y el login. La contraseña nunca se guarda en texto plano:
 * se guarda el hash de password_hash() y en el login se compara con password_verify().
 */
class Usuario
{
    // Largo mínimo que pedimos para una contraseña nueva.
    const LARGO_MINIMO_PASSWORD = 8;

    // Hash de una contraseña cualquiera. Se usa cuando el email no existe, para que
    // autenticar() tarde lo mismo y no se pueda adivinar por el tiempo quién está registrado.
    const HASH_DE_RELLENO = '$2y$10$6p/I9R676iRnv2scpNMd0O22OgUS.q7ARoehVMFz3CiGBfOcefSim';

    // Roles del sistema, de menor a mayor poder, con el nombre que se muestra en pantalla.
    // Qué puede hacer cada uno está en app/helpers/permisos.php.
    const ROLES = [
        'participante'    => 'Participante',
        'admin'           => 'Admin',
        'admin_principal' => 'Admin principal',
    ];

    // Columnas de la tabla `usuario`.
    public int $id;
    public string $nombre;
    public string $apellido;
    public string $email;
    public string $password;   // hash, no la contraseña real
    public string $rol;        // 'participante' | 'admin' | 'admin_principal'
    public bool $activo;
    public string $creado_en;

    /** Arma un objeto Usuario desde una fila de la base (factory). */
    private static function desdeFila(array $fila): self
    {
        $usuario = new self();
        $usuario->id            = (int) $fila['id'];
        $usuario->nombre        = $fila['nombre'];
        $usuario->apellido      = $fila['apellido'];
        $usuario->email         = $fila['email'];
        $usuario->password      = $fila['password'];
        $usuario->rol           = $fila['rol'];
        $usuario->activo        = (bool) $fila['activo'];
        $usuario->creado_en     = $fila['creado_en'];
        return $usuario;
    }

    /** Crea un usuario nuevo y devuelve su id. */
    public static function crear(string $nombre, string $apellido, string $email, string $password, string $rol = 'participante'): int
    {
        $sql = 'INSERT INTO usuario (nombre, apellido, email, password, rol) VALUES (?, ?, ?, ?, ?)';
        $cn = Database::conexion();
        $ps = $cn->prepare($sql);
        // Guardamos el hash de la contraseña, no la contraseña escrita
        $ps->execute([$nombre, $apellido, $email, password_hash($password, PASSWORD_DEFAULT), $rol]);
        // lastInsertId() devuelve el id autoincremental que MySQL le asignó a la fila recién creada.
        return (int) $cn->lastInsertId();
    }

    /** Nombre y apellido juntos, para mostrar en pantalla. */
    public function nombreCompleto(): string
    {
        return "$this->nombre $this->apellido";
    }

    /** ¿Es administrador? (admin común o admin principal) */
    public function esAdmin(): bool
    {
        return $this->rol === 'admin' || $this->rol === 'admin_principal';
    }

    /** ¿Es el admin principal? Es el único con poder sobre los otros admins. */
    public function esAdminPrincipal(): bool
    {
        return $this->rol === 'admin_principal';
    }

    /** Nombre del rol para mostrar en pantalla ("Admin principal" en vez de "admin_principal"). */
    public function nombreRol(): string
    {
        return self::ROLES[$this->rol];
    }

    /** Busca un usuario por email. Devuelve el objeto o null si no existe. */
    public static function buscarPorEmail(string $email): ?self
    {
        $sql = 'SELECT * FROM usuario WHERE email = ?';
        $ps = Database::conexion()->prepare($sql);
        // Reemplazamos el "?" por el email (prepared statement: evita SQL injection)
        $ps->execute([$email]);
        $fila = $ps->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }

    /** Busca un usuario por id. Devuelve el objeto o null si no existe. */
    public static function buscarPorId(int $id): ?self
    {
        $sql = 'SELECT * FROM usuario WHERE id = ?';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([$id]);
        $fila = $ps->fetch();
        if (!$fila) {
            return null;
        }
        return self::desdeFila($fila);
    }

    /**
     * Valida las credenciales del login.
     * Devuelve el usuario si el email existe, la cuenta está activa y la
     * contraseña coincide con el hash guardado; si no, devuelve null.
     */
    public static function autenticar(string $email, string $pass): ?self
    {
        $usuario = self::buscarPorEmail($email);

        // Si el email no existe igual verificamos contra un hash de relleno,
        // así la respuesta tarda lo mismo exista o no el usuario.
        $hash = $usuario !== null ? $usuario->password : self::HASH_DE_RELLENO;
        $passCorrecta = password_verify($pass, $hash);

        if ($usuario !== null && $usuario->activo && $passCorrecta) {
            return $usuario;
        }
        return null;
    }

    /** Devuelve todos los usuarios ordenados por apellido y nombre. */
    public static function listar(): array
    {
        $sql = 'SELECT * FROM usuario ORDER BY apellido, nombre';
        $filas = Database::conexion()->query($sql)->fetchAll();
        return array_map([self::class, 'desdeFila'], $filas);
    }

    /**
     * Actualiza los datos de un usuario existente.
     * Si $password viene vacío se deja la contraseña que ya tenía.
     */
    public static function actualizar(int $id, string $nombre, string $apellido, string $email, string $password, string $rol): void
    {
        $sql = 'UPDATE usuario SET nombre = ?, apellido = ?, email = ?, rol = ? WHERE id = ?';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([$nombre, $apellido, $email, $rol, $id]);

        if ($password !== '') {
            self::cambiarPassword($id, $password);
        }
    }

    /** Cambia la contraseña de un usuario (guarda el hash de la nueva). */
    public static function cambiarPassword(int $id, string $password): void
    {
        $sql = 'UPDATE usuario SET password = ? WHERE id = ?';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    /**
     * Habilita o deshabilita un usuario (baja lógica).
     * No se borra la fila porque puede tener inscripciones asociadas.
     */
    public static function cambiarActivo(int $id, bool $activo): void
    {
        $sql = 'UPDATE usuario SET activo = ? WHERE id = ?';
        $ps = Database::conexion()->prepare($sql);
        $ps->execute([(int) $activo, $id]);
    }
}
