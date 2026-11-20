<?php
$titulo = 'Recuperar contraseña';
$noIndexar = true; // pantalla pública que no tiene que aparecer en buscadores
?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section class="tarjeta-formulario">
    <h1>Recuperar contraseña</h1>

    <?php if ($mensajeOK !== null): ?>
        <p class="alerta alerta-ok" role="status"><?= htmlspecialchars($mensajeOK) ?></p>
    <?php else: ?>
        <p>Ingresá el email de tu cuenta y te mandamos un link para elegir una contraseña nueva.</p>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <p class="alerta alerta-error" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" action="index.php?r=recuperar" class="campos">
        <input type="hidden" name="token_csrf" value="<?= tokenCsrf() ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($email) ?>"
               autocomplete="username" required autofocus>

        <button type="submit" class="boton">Enviar link</button>
    </form>

    <p class="enlace"><a href="index.php?r=login">Volver al login</a></p>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
