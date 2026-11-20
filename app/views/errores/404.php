<?php
$titulo = 'Página no encontrada';
$noIndexar = true; // pantalla pública que no tiene que aparecer en buscadores
?>
<?php require RAIZ . '/app/views/layouts/header.php'; ?>

<section class="tarjeta-formulario">
    <h1>Página no encontrada</h1>
    <p>La dirección que abriste no existe o cambió de lugar.</p>
    <p class="enlace"><a href="index.php">Volver al inicio</a></p>
</section>

<?php require RAIZ . '/app/views/layouts/footer.php'; ?>
