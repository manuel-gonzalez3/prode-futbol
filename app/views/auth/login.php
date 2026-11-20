<?php
$titulo = 'Pronósticos de fútbol: iniciar sesión';
// SEO: el login es la única pantalla pública, así que es la que describimos para los buscadores
$descripcion = 'Prode de torneos de fútbol: ingresá con tu cuenta, pronosticá el marcador de cada partido y sumá puntos fecha a fecha para ganar el pozo.';
$rutaCanonica = 'login';
?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<div class="portada">
    <section class="portada-intro">
        <h1>Prode de torneos de fútbol</h1>
        <p>Pronosticá el marcador de cada partido, sumá puntos fecha a fecha y competí por el pozo del torneo.</p>
    </section>

    <section class="portada-ingreso" aria-labelledby="titulo-ingreso">
        <h2 id="titulo-ingreso">Iniciar sesión</h2>

        <?php if ($mensajeOK !== null): ?>
            <p class="alerta alerta-ok" role="status"><?= htmlspecialchars($mensajeOK) ?></p>
        <?php endif; ?>

        <?php if ($error !== null): ?>
            <p class="alerta alerta-error" role="alert"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="index.php?r=login" class="campos">
            <!-- Token CSRF: prueba que el formulario salió de nuestro sitio -->
            <input type="hidden" name="token_csrf" value="<?= tokenCsrf() ?>">

            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>"
                   autocomplete="username" required autofocus>

            <label for="password">Contraseña</label>
            <div class="campo-password">
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
                <button type="button" class="ver-password" data-campo="password"
                        aria-label="Mostrar contraseña" hidden>Mostrar</button>
            </div>

            <button type="submit" class="boton">Ingresar</button>
        </form>

        <p class="enlace"><a href="index.php?r=recuperar">¿Olvidaste tu contraseña?</a></p>
    </section>

    <!-- Ejemplo del reglamento de puntajes; los puntos salen de las constantes de app/helpers/scoring.php -->
    <div class="portada-puntaje">
        <table class="marcador">
            <caption>Cómo se puntúa si pronosticaste 2 a 1</caption>
            <thead>
                <tr>
                    <th scope="col">El partido termina</th>
                    <th scope="col">Acertaste</th>
                    <th scope="col">Puntos</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>2 a 1</td>
                    <td>El marcador exacto</td>
                    <td class="puntos"><?= PUNTOS_EXACTO ?></td>
                </tr>
                <tr>
                    <td>3 a 2</td>
                    <td>El ganador y la diferencia de gol</td>
                    <td class="puntos"><?= PUNTOS_GANADOR_DIF ?></td>
                </tr>
                <tr>
                    <td>4 a 1</td>
                    <td>Solo el ganador</td>
                    <td class="puntos"><?= PUNTOS_GANADOR ?></td>
                </tr>
                <tr>
                    <td>0 a 2</td>
                    <td>Nada</td>
                    <td class="puntos"><?= PUNTOS_SIN_ACIERTO ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
