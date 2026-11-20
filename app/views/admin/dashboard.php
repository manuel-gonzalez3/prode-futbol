<?php $titulo = 'Panel admin'; ?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section>
    <h1>Hola, <?= htmlspecialchars($usuario->nombre) ?></h1>
    <p>Panel de administración.</p>

    <ul class="opciones">
        <li>
            <a href="index.php?r=usuarios" class="tarjeta">
                <h2>Gestión de usuarios</h2>
                <p>Alta, edición y baja de usuarios.</p>
            </a>
        </li>
        <li>
            <a href="index.php?r=permisos" class="tarjeta">
                <h2>Roles y permisos</h2>
                <p>Qué puede hacer cada rol y qué implica cada estado.</p>
            </a>
        </li>
        <li>
            <!-- Gestión de torneos todavía no tiene ruta: se muestra como tarjeta sin link en vez de un link que no lleva a ningún lado -->
            <div class="tarjeta tarjeta-pendiente">
                <h2>Gestión de torneos</h2>
                <p>Crear torneos, fechas y partidos.</p>
                <p class="pendiente">Próximamente</p>
            </div>
        </li>
    </ul>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
