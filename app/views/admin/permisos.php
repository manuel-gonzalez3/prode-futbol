<?php $titulo = 'Roles y permisos'; ?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section>
    <div class="encabezado">
        <h1>Roles y permisos</h1>
        <div class="encabezado-acciones">
            <a href="index.php?r=usuarios" class="boton boton-secundario">Volver a usuarios</a>
        </div>
    </div>

    <p>Lo que puede hacer una cuenta depende de su <strong>rol</strong> y de su <strong>estado</strong>:
       los permisos del rol solo valen mientras la cuenta está activa.</p>

    <h2 id="titulo-roles">Permisos por rol</h2>
    <!-- La tabla se arma recorriendo PERMISOS (app/helpers/permisos.php), lo mismo que consultan los controladores -->
    <div class="tabla-scroll" role="region" aria-labelledby="titulo-roles" tabindex="0">
        <table class="tabla tabla-permisos">
            <thead>
                <tr>
                    <th scope="col">Permiso</th>
                    <?php foreach (Usuario::ROLES as $nombreRol): ?>
                        <th scope="col"><?= $nombreRol ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (PERMISOS as $permiso): ?>
                    <tr>
                        <th scope="row"><?= $permiso['descripcion'] ?></th>
                        <?php foreach (Usuario::ROLES as $rol => $nombreRol): ?>
                            <?php if (in_array($rol, $permiso['roles'], true)): ?>
                                <td class="si">Sí</td>
                            <?php else: ?>
                                <td class="no">No</td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 id="titulo-admins">Poder de un admin sobre otro usuario</h2>
    <p>Modificar es editar sus datos, cambiarle la contraseña o el rol, y habilitarlo o deshabilitarlo.</p>
    <!-- Refleja la función puedeGestionar() de app/helpers/permisos.php -->
    <div class="tabla-scroll" role="region" aria-labelledby="titulo-admins" tabindex="0">
        <table class="tabla tabla-permisos">
            <thead>
                <tr>
                    <th scope="col">Quién modifica</th>
                    <th scope="col">A un participante</th>
                    <th scope="col">A otro admin</th>
                    <th scope="col">Al admin principal</th>
                    <th scope="col">A sí mismo</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Admin</th>
                    <td class="si">Sí</td>
                    <td class="no">No, solo lo ve</td>
                    <td class="no">No, solo lo ve</td>
                    <td class="si">Sus datos y contraseña</td>
                </tr>
                <tr>
                    <th scope="row">Admin principal</th>
                    <td class="si">Sí</td>
                    <td class="si">Sí</td>
                    <td>No aplica, es él mismo</td>
                    <td class="si">Sus datos y contraseña</td>
                </tr>
            </tbody>
        </table>
    </div>
    <ul class="reglas">
        <li>Un admin no tiene poder sobre otro admin: en la lista de usuarios lo ve como «Solo lectura».</li>
        <li>Solo el admin principal crea admins, les cambia los datos, la contraseña o el rol, y los habilita o deshabilita.</li>
        <li>Al admin principal no lo modifica nadie más. Ese rol se define en la base de datos: desde la aplicación no se puede dar ni quitar.</li>
        <li>Nadie puede deshabilitarse ni cambiarse el rol a sí mismo, así el sistema nunca se queda sin un admin principal activo.</li>
    </ul>

    <h2 id="titulo-estados">Qué implica cada estado</h2>
    <div class="tabla-scroll" role="region" aria-labelledby="titulo-estados" tabindex="0">
        <table class="tabla tabla-permisos">
            <thead>
                <tr>
                    <th scope="col">La cuenta puede</th>
                    <th scope="col"><span class="estado activo">Activo</span></th>
                    <th scope="col"><span class="estado inactivo">Deshabilitado</span></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">Iniciar sesión</th>
                    <td class="si">Sí</td>
                    <td class="no">No</td>
                </tr>
                <tr>
                    <th scope="row">Seguir usando una sesión que ya tenía abierta</th>
                    <td class="si">Sí</td>
                    <td class="no">No, se cierra en su próxima acción</td>
                </tr>
                <tr>
                    <th scope="row">Recibir el mail para recuperar la contraseña</th>
                    <td class="si">Sí</td>
                    <td class="no">No</td>
                </tr>
                <tr>
                    <th scope="row">Usar los permisos de su rol</th>
                    <td class="si">Sí</td>
                    <td class="no">No</td>
                </tr>
                <tr>
                    <th scope="row">Conservar sus datos e inscripciones</th>
                    <td class="si">Sí</td>
                    <td class="si">Sí, y se puede volver a habilitar</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
