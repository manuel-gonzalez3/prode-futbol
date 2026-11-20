# Prode de Torneos de Fútbol

Trabajo Final de **Entornos Web** (UCEL 2026).

Plataforma web de predicciones deportivas: los participantes se inscriben a un
torneo, cargan sus pronósticos y, al cerrar cada fecha, el sistema calcula los
puntajes y reparte el pozo entre los ganadores.

**Puntaje:** 5 puntos por marcador exacto, 3 por acertar ganador y diferencia de
goles, 2 por acertar solo el ganador y 0 si no se acierta.

**Stack:** PHP (MVC liviano, sin framework) · MySQL (PDO) · HTML5 · CSS3 · JavaScript.

## Estructura

```
public/     única carpeta expuesta al navegador (front controller, css, js, fonts, img)
app/        controllers / models / views / helpers / services
config/     config.php (bootstrap: .env + autoload) y database.php (PDO)
sql/        schema.sql — crea la base completa + datos de prueba
```

## Puesta en marcha

1. **Requisitos:** [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8.2).
2. **Base de datos:** con MySQL corriendo (panel de XAMPP):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < sql/schema.sql
   ```
   o importar `sql/schema.sql` desde phpMyAdmin. El script es re-ejecutable:
   reconstruye la base desde cero con datos de prueba.
3. **Configuración:** crear un `.env` en la raíz (los valores por defecto ya
   sirven para XAMPP):
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=prode
   DB_USER=root
   DB_PASS=

   # URL pública de la carpeta public/ (se usa para armar el link del mail de recuperación)
   APP_URL=http://localhost/prode/public

   # Mails: "log" los escribe en logs/mail.log (para probar); "smtp" los manda de verdad
   MAIL_MODO=log
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USER=tu.cuenta@gmail.com
   MAIL_PASS=contraseñadeaplicacion
   MAIL_FROM=tu.cuenta@gmail.com
   ```
   Para mandar mails reales con Gmail: activar la verificación en 2 pasos y crear
   una *contraseña de aplicación* (Cuenta de Google → Seguridad); va en `MAIL_PASS`
   sin espacios. Requiere la extensión `openssl` de PHP (viene activada en XAMPP).

## Usuarios de prueba (seed)

| Email | Contraseña | Rol |
|---|---|---|
| admin@demo.com | admin123 | admin principal |
| carla@demo.com | admin123 | admin |
| ana@demo.com / bruno@demo.com | prode123 | participante |

Por ahora el seed carga solo los usuarios; torneos y fechas se crean desde el panel admin.

## Roles y permisos

Hay tres roles: `participante` < `admin` < `admin_principal`. Qué puede hacer cada uno está en un solo
lugar (`app/helpers/permisos.php`): lo consultan los controladores y con eso mismo se arma la pantalla
**Roles y permisos** (`?r=permisos`).

- **Participante:** entra a su panel y ve los torneos.
- **Admin:** administra torneos y participantes (alta, edición, contraseña, habilitar / deshabilitar).
  **No tiene poder sobre otro admin:** en la lista lo ve como «Solo lectura».
- **Admin principal:** además es el único que crea admins, los edita, les cambia la contraseña o el rol
  y los habilita o deshabilita. A él no lo modifica nadie más, y el rol no se puede dar ni quitar desde
  la aplicación (se define en la base).
- Nadie puede deshabilitarse ni cambiarse el rol a sí mismo: el sistema nunca queda sin un admin principal activo.
- **Estado:** una cuenta deshabilitada no puede iniciar sesión, pierde la sesión abierta en su próxima
  acción y no recibe el mail de recuperación; conserva sus datos y se puede volver a habilitar.

Si la base ya estaba creada con el esquema anterior, alcanza con:

```sql
ALTER TABLE usuario MODIFY rol ENUM('participante','admin','admin_principal') NOT NULL DEFAULT 'participante';
UPDATE usuario SET rol = 'admin_principal' WHERE id = 1;
```

## Front: UX, accesibilidad y SEO

- **Botón Mostrar / Ocultar contraseña** (`public/js/password.js`) en login, restablecer y alta/edición de usuarios.
  Muestra lo que se está escribiendo; la contraseña guardada no se puede mostrar porque en la base solo está su hash.
- **Accesibilidad (WCAG 2.1 AA):** link «Saltar al contenido», foco de teclado visible, contraste de 4.5:1 o más,
  tablas con `scope`, avisos con `role="alert"` / `role="status"`, `aria-current` en la navegación y
  botones de al menos 44 px de alto.
- **Responsive:** en celulares la tabla de usuarios se apila en fichas y la portada pasa a una columna.
- **SEO del login** (única pantalla pública): `<title>` y `meta description` propios, `link rel="canonical"`,
  Open Graph, `lang="es-AR"`, favicon y `robots.txt`. Recuperar, restablecer y el 404 llevan `noindex`.
- **Validación:** HTML y CSS sin errores en el validador de W3C (Nu Html Checker) y Lighthouse.
- Tipografía [Archivo](https://github.com/Omnibus-Type/Archivo) (Omnibus-Type, licencia OFL), servida desde `public/fonts`.

## Seguridad del login

- **Contraseñas hasheadas** con `password_hash()` / `password_verify()` (bcrypt); nunca se guardan en texto plano.
- **Bloqueo por intentos fallidos:** 5 intentos fallidos con el mismo email en 15 minutos bloquean ese email
  (tabla `intento_login`). Cuenta igual aunque el email no exista, para no revelar quién está registrado.
- **Mensajes genéricos:** el login responde "Email o contraseña incorrectos" sin decir cuál de los dos falló.
- **Recuperar contraseña por mail** (`?r=recuperar`): siempre muestra el mismo mensaje, exista o no el email.
  Si existe, manda un link con un token aleatorio (`random_bytes`) que vence en 30 minutos y sirve una sola vez;
  en la base (`recuperacion_password`) se guarda solo su hash SHA-256.
- **Token CSRF** en los formularios de login, recuperar y restablecer, y en el alta, edición y cambio de estado de usuarios.
- **Sesión:** cookie `HttpOnly` + `SameSite=Lax`, `use_strict_mode`, `session_regenerate_id()` al loguearse,
  cierre automático tras 30 minutos sin actividad y logout que destruye la sesión.
