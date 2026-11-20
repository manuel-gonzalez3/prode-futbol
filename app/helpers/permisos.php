<?php
/**
 * Permisos por rol.
 *
 * Acá está, en un solo lugar, qué puede hacer cada rol. Lo usan:
 *  - los controladores, para decidir si dejan pasar una acción, y
 *  - la pantalla "Roles y permisos" (?r=permisos), que arma su tabla recorriendo PERMISOS.
 * Así la tabla que ve el admin nunca queda distinta de lo que el sistema realmente controla.
 *
 * Jerarquía: participante < admin < admin_principal.
 * Regla de admin sobre admin: un admin común NO tiene poder sobre otro admin (solo lo ve
 * en la lista). El único que crea, edita, deshabilita o le cambia el rol a un admin es el
 * admin principal. Al admin principal no lo modifica nadie más que él mismo.
 */

declare(strict_types=1);

// Cada permiso dice qué habilita ('descripcion') y qué roles lo tienen ('roles').
const PERMISOS = [
    'panel_usuario' => [
        'descripcion' => 'Entrar al panel de usuario y ver los torneos',
        'roles'       => ['participante', 'admin', 'admin_principal'],
    ],
    'panel_admin' => [
        'descripcion' => 'Entrar al panel de administración',
        'roles'       => ['admin', 'admin_principal'],
    ],
    'gestionar_torneos' => [
        'descripcion' => 'Crear torneos, fechas y partidos',
        'roles'       => ['admin', 'admin_principal'],
    ],
    'ver_usuarios' => [
        'descripcion' => 'Ver la lista de usuarios y la tabla de permisos',
        'roles'       => ['admin', 'admin_principal'],
    ],
    'gestionar_participantes' => [
        'descripcion' => 'Crear, editar, cambiar la contraseña y habilitar o deshabilitar participantes',
        'roles'       => ['admin', 'admin_principal'],
    ],
    'gestionar_admins' => [
        'descripcion' => 'Crear, editar, cambiar la contraseña y habilitar o deshabilitar a otros admins, y dar o quitar el rol admin',
        'roles'       => ['admin_principal'],
    ],
];

/** ¿El rol de ese usuario tiene el permiso? */
function tienePermiso(Usuario $usuario, string $permiso): bool
{
    return in_array($usuario->rol, PERMISOS[$permiso]['roles'], true);
}

/**
 * ¿$actor puede modificar a $otro? (editar sus datos, cambiarle la contraseña, habilitarlo o deshabilitarlo)
 * Es la regla que responde "qué poder tiene un admin sobre otro admin".
 */
function puedeGestionar(Usuario $actor, Usuario $otro): bool
{
    // Sus propios datos los edita cualquier admin (el rol y el estado propios se protegen aparte)
    if ($actor->id === $otro->id) {
        return tienePermiso($actor, 'panel_admin');
    }
    if ($otro->rol === 'participante') {
        return tienePermiso($actor, 'gestionar_participantes');
    }
    if ($otro->rol === 'admin') {
        return tienePermiso($actor, 'gestionar_admins'); // solo el admin principal
    }
    // $otro es admin principal: nadie más que él mismo lo modifica
    return false;
}

/**
 * Roles que $actor puede elegir cuando crea o edita a otro usuario.
 * 'admin_principal' no aparece nunca: ese rol se define en la base, no desde la aplicación.
 */
function rolesAsignables(Usuario $actor): array
{
    if (tienePermiso($actor, 'gestionar_admins')) {
        return ['participante', 'admin'];
    }
    return ['participante'];
}
