<?php
// Variables que puede definir cada vista antes de incluir este header:
//   $titulo        título de la pantalla (va en <title>, seguido de "| Prode")
//   $descripcion   texto que muestran los buscadores debajo del título (si la vista no lo define va uno genérico)
//   $rutaCanonica  ruta pública de la pantalla para los buscadores (solo el login, que es la portada del sitio)
//   $noIndexar     true en las pantallas públicas que no tienen que salir en buscadores (recuperar, restablecer, 404).
//                  Las pantallas internas no lo necesitan: piden login, un buscador nunca llega a verlas.
$usuario = usuarioActual();
$rutaActual = $_GET['r'] ?? 'login';
$descripcion = $descripcion ?? 'Prode de torneos de fútbol: pronósticos por partido, puntajes por fecha y pozo por torneo.';
$rutaCanonica = $rutaCanonica ?? null;
$noIndexar = $noIndexar ?? false;
?>
<!DOCTYPE html>
<html lang="es-AR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | Prode</title>
    <meta name="description" content="<?= htmlspecialchars($descripcion) ?>">
<?php if ($rutaCanonica !== null): ?>
    <!-- SEO: URL única de la pantalla (index.php y index.php?r=login muestran lo mismo) y datos para cuando se comparte el link -->
    <link rel="canonical" href="<?= htmlspecialchars(urlBase() . '/index.php?r=' . $rutaCanonica) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Prode">
    <meta property="og:locale" content="es_AR">
    <meta property="og:title" content="<?= htmlspecialchars($titulo) ?> | Prode">
    <meta property="og:description" content="<?= htmlspecialchars($descripcion) ?>">
    <meta property="og:url" content="<?= htmlspecialchars(urlBase() . '/index.php?r=' . $rutaCanonica) ?>">
<?php endif; ?>
<?php if ($noIndexar): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
    <meta name="theme-color" content="#17613a">
    <link rel="icon" href="img/favicon.svg" type="image/svg+xml">
    <!-- La fuente se pide junto con el CSS para que el texto no cambie de forma al cargar -->
    <link rel="preload" href="fonts/archivo.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Accesibilidad: primer elemento al tabular, saltea la barra y va directo al contenido -->
    <a href="#contenido" class="saltar">Saltar al contenido</a>

    <header class="barra">
        <a href="index.php" class="logo">Prode</a>

        <?php if ($usuario !== null): ?>
            <!-- aria-current marca en qué sección está el usuario (lo leen los lectores de pantalla y lo usa el CSS) -->
            <nav aria-label="Principal">
                <ul>
                    <?php if ($usuario->esAdmin()): ?>
                        <li><a href="index.php?r=admin" <?= $rutaActual === 'admin' ? 'aria-current="page"' : '' ?>>Panel admin</a></li>
                        <li><a href="index.php?r=usuarios" <?= in_array($rutaActual, ['usuarios', 'usuario-form', 'permisos'], true) ? 'aria-current="page"' : '' ?>>Usuarios</a></li>
                    <?php else: ?>
                        <li><a href="index.php?r=panel" <?= $rutaActual === 'panel' ? 'aria-current="page"' : '' ?>>Mis torneos</a></li>
                    <?php endif; ?>
                </ul>
            </nav>

            <p class="cuenta">
                <span class="cuenta-nombre"><?= htmlspecialchars($usuario->nombre) ?></span>
                <span class="rol"><?= $usuario->nombreRol() ?></span>
                <a href="index.php?r=logout">Salir</a>
            </p>
        <?php endif; ?>
    </header>

    <main id="contenido" class="contenido" tabindex="-1">
