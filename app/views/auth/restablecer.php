<?php
$titulo = 'Nueva contraseña';
$noIndexar = true; // pantalla pública que no tiene que aparecer en buscadores
?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section class="tarjeta-formulario">
    <h1>Nueva contraseña</h1>

    <?php if ($error !== null): ?>
        <p class="alerta alerta-error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($tokenValido): ?>
        <form method="POST" action="index.php?r=restablecer" class="campos">
            <input type="hidden" name="token_csrf" value="<?= tokenCsrf() ?>">
            <!-- El token del link viaja oculto para que restablecer() sepa de quién es la cuenta -->
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <label for="password">Contraseña nueva</label>
            <div class="campo-password">
                <input type="password" id="password" name="password"
                       minlength="<?= Usuario::LARGO_MINIMO_PASSWORD ?>"
                       autocomplete="new-password" aria-describedby="ayuda-password" required autofocus>
                <button type="button" class="ver-password" data-campo="password"
                        aria-label="Mostrar contraseña" hidden>Mostrar</button>
            </div>
            <p class="ayuda" id="ayuda-password">Mínimo <?= Usuario::LARGO_MINIMO_PASSWORD ?> caracteres.</p>

            <label for="confirmacion">Repetir contraseña</label>
            <div class="campo-password">
                <input type="password" id="confirmacion" name="confirmacion"
                       minlength="<?= Usuario::LARGO_MINIMO_PASSWORD ?>"
                       autocomplete="new-password" required>
                <button type="button" class="ver-password" data-campo="confirmacion"
                        aria-label="Mostrar contraseña" hidden>Mostrar</button>
            </div>

            <button type="submit" class="boton">Guardar contraseña</button>
        </form>
    <?php else: ?>
        <p class="enlace"><a href="index.php?r=recuperar">Pedir un link nuevo</a></p>
    <?php endif; ?>

    <p class="enlace"><a href="index.php?r=login">Volver al login</a></p>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
