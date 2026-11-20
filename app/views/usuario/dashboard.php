<?php $titulo = 'Panel usuario'; ?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section>
    <h1>Hola, <?= htmlspecialchars($usuario->nombre) ?></h1>
    <p>Panel de usuario.</p>

    <ul class="opciones">
        <li>
            <!-- La opción todavía no tiene ruta: se muestra como tarjeta sin link en vez de un link que no lleva a ningún lado -->
            <div class="tarjeta tarjeta-pendiente">
                <h2>Ver torneos</h2>
                <p>Torneos disponibles y en los que participás.</p>
                <p class="pendiente">Próximamente</p>
            </div>
        </li>
    </ul>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
