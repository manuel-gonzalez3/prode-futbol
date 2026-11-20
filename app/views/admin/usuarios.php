<?php $titulo = 'Gestión de usuarios'; ?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section>
    <div class="encabezado">
        <h1 id="titulo-usuarios">Gestión de usuarios</h1>
        <div class="encabezado-acciones">
            <a href="index.php?r=permisos" class="boton boton-secundario">Ver roles y permisos</a>
            <a href="index.php?r=usuario-form" class="boton">Nuevo usuario</a>
        </div>
    </div>

    <?php if ($mensajeOK !== null): ?>
        <p class="alerta alerta-ok" role="status"><?= htmlspecialchars($mensajeOK) ?></p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="alerta alerta-error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Deja a la vista la regla de admin sobre admin según quién está mirando la lista -->
    <?php if ($usuario->esAdminPrincipal()): ?>
        <p class="ayuda">Sos el admin principal: podés modificar a participantes y a los otros admins.</p>
    <?php else: ?>
        <p class="ayuda">Podés modificar a los participantes y tus propios datos. A los otros admins solo los modifica el admin principal.</p>
    <?php endif; ?>

    <!-- En celulares cada fila se muestra apilada como una ficha (ver .tabla-apilada en el CSS);
         data-etiqueta es el nombre de la columna que se muestra al lado de cada dato -->
    <div class="tabla-scroll">
        <table class="tabla tabla-apilada" aria-labelledby="titulo-usuarios">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Email</th>
                    <th scope="col">Rol</th>
                    <th scope="col">Estado</th>
                    <th scope="col">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <th scope="row">
                            <?= htmlspecialchars($u->nombreCompleto()) ?>
                            <?php if ($u->id === $usuario->id): ?>
                                <span class="vos">(vos)</span>
                            <?php endif; ?>
                        </th>
                        <td data-etiqueta="Email"><?= htmlspecialchars($u->email) ?></td>
                        <td data-etiqueta="Rol"><span class="rol rol-<?= $u->rol ?>"><?= $u->nombreRol() ?></span></td>
                        <td data-etiqueta="Estado">
                            <span class="estado <?= $u->activo ? 'activo' : 'inactivo' ?>">
                                <?= $u->activo ? 'Activo' : 'Deshabilitado' ?>
                            </span>
                        </td>
                        <td data-etiqueta="Acciones">
                            <?php if (puedeGestionar($usuario, $u)): ?>
                                <div class="acciones">
                                    <!-- aria-label: un lector de pantalla lee "Editar a Ana García" en vez de varios "Editar" iguales -->
                                    <a href="index.php?r=usuario-form&amp;id=<?= $u->id ?>"
                                       aria-label="Editar a <?= htmlspecialchars($u->nombreCompleto()) ?>">Editar</a>

                                    <?php if ($u->id !== $usuario->id): ?>
                                        <!-- POST + token CSRF para que no se pueda cambiar el estado entrando a un link -->
                                        <form method="POST" action="index.php?r=usuario-estado&amp;id=<?= $u->id ?>">
                                            <input type="hidden" name="token_csrf" value="<?= tokenCsrf() ?>">
                                            <button type="submit" class="link <?= $u->activo ? 'peligro' : '' ?>"
                                                    aria-label="<?= $u->activo ? 'Deshabilitar' : 'Habilitar' ?> a <?= htmlspecialchars($u->nombreCompleto()) ?>">
                                                <?= $u->activo ? 'Deshabilitar' : 'Habilitar' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="solo-lectura">Solo lectura</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
