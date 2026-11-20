<?php $titulo = $editado === null ? 'Nuevo usuario' : 'Editar usuario'; ?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section class="tarjeta-formulario">
    <h1><?= $titulo ?></h1>

    <?php if ($error !== null): ?>
        <p class="alerta alerta-error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- Si se edita, el id viaja en la URL para que guardar() sepa qué usuario actualizar -->
    <form method="POST" action="index.php?r=usuario-form<?= $editado !== null ? '&amp;id=' . $editado->id : '' ?>" class="campos">
        <!-- Token CSRF: prueba que el formulario salió de nuestro sitio -->
        <input type="hidden" name="token_csrf" value="<?= tokenCsrf() ?>">

        <!-- autocomplete="off": son datos de otra persona, el navegador no tiene que completar los del admin -->
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="60" autocomplete="off"
               value="<?= htmlspecialchars($datos['nombre']) ?>" required>

        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" maxlength="60" autocomplete="off"
               value="<?= htmlspecialchars($datos['apellido']) ?>" required>

        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="120" autocomplete="off"
               value="<?= htmlspecialchars($datos['email']) ?>" required>

        <label for="password">Contraseña</label>
        <div class="campo-password">
            <input type="password" id="password" name="password" maxlength="100"
                   minlength="<?= Usuario::LARGO_MINIMO_PASSWORD ?>" autocomplete="new-password"
                   aria-describedby="ayuda-password" <?= $editado === null ? 'required' : '' ?>>
            <!-- Muestra lo que se está escribiendo. La contraseña guardada no se puede mostrar: en la base solo está su hash -->
            <button type="button" class="ver-password" data-campo="password"
                    aria-label="Mostrar contraseña" hidden>Mostrar</button>
        </div>
        <p class="ayuda" id="ayuda-password">
            Mínimo <?= Usuario::LARGO_MINIMO_PASSWORD ?> caracteres.
            <?= $editado !== null ? 'Dejala vacía para no cambiarla.' : '' ?>
        </p>

        <?php if ($esPropio): ?>
            <!-- Nadie se cambia su propio rol: se muestra pero no se puede editar -->
            <p class="campo-fijo">Rol: <strong><?= $editado->nombreRol() ?></strong></p>
            <p class="ayuda">No podés cambiar tu propio rol.</p>
        <?php elseif (count($rolesAsignables) === 1): ?>
            <!-- Un admin común solo crea y edita participantes -->
            <p class="campo-fijo">Rol: <strong><?= Usuario::ROLES[$rolesAsignables[0]] ?></strong></p>
            <p class="ayuda">Solo el admin principal puede dar el rol admin.</p>
        <?php else: ?>
            <label for="rol">Rol</label>
            <select id="rol" name="rol">
                <?php foreach ($rolesAsignables as $rol): ?>
                    <option value="<?= $rol ?>" <?= $datos['rol'] === $rol ? 'selected' : '' ?>><?= Usuario::ROLES[$rol] ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>

        <div class="botones">
            <button type="submit" class="boton">Guardar</button>
            <a href="index.php?r=usuarios" class="boton boton-secundario">Cancelar</a>
        </div>
    </form>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
