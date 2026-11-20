// Botón "Mostrar / Ocultar" de los campos de contraseña (login, restablecer y alta/edición de usuarios).
// Cada botón apunta a su campo con data-campo="id-del-input".
document.querySelectorAll('.ver-password').forEach(function (boton) {
    var campo = document.getElementById(boton.dataset.campo);

    // El botón viene oculto en el HTML: si el navegador no ejecuta JavaScript no aparece un botón que no hace nada.
    boton.hidden = false;

    boton.addEventListener('click', function () {
        var mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        // Cambiamos el texto visible y el que leen los lectores de pantalla
        boton.textContent = mostrar ? 'Ocultar' : 'Mostrar';
        boton.setAttribute('aria-label', (mostrar ? 'Ocultar' : 'Mostrar') + ' contraseña');
    });

    // Al enviar el formulario el campo vuelve a ser de tipo password,
    // así el navegador no lo guarda como si fuera un texto común.
    campo.form.addEventListener('submit', function () {
        campo.type = 'password';
    });
});
