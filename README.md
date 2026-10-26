# La Cuponera SV

Aplicación web de cupones y ofertas desarrollada con PHP 8.x, MySQL, PDO, HTML, CSS, JavaScript y Bootstrap.

## Arquitectura

El proyecto utiliza el patrón Modelo-Vista-Controlador (MVC).

- **Modelos:** acceso y operaciones sobre la base de datos mediante PDO.
- **Vistas:** presentación de páginas y formularios.
- **Controladores:** gestión de solicitudes, validaciones y permisos.
- **Helpers:** funciones compartidas.
- **Servicios:** operaciones de negocio específicas.

## Requisitos

- PHP 8.x con PDO y pdo_mysql.
- XAMPP con Apache y MySQL/MariaDB.
- Visual Studio Code.
- Navegador web.

## Ejecución local

1. Colocar el proyecto en `C:\xampp\htdocs\la-cuponera-sv`.
2. Iniciar Apache y MySQL desde XAMPP.
3. Crear y configurar la base de datos.
4. Configurar las variables de entorno locales.
5. Abrir la aplicación desde `http://localhost/la-cuponera-sv/public`.

### Configuración inicial

1. Copiar `.env.example` como `.env`.
2. Configurar la conexión de MySQL/MariaDB.
3. Crear una base de datos llamada `la_cuponera_sv`.
4. Seleccionar esa base e importar `database/schema.sql`.
5. Confirmar que `DB_PORT` coincide con el puerto real de MySQL.
   El archivo de ejemplo utiliza `3307`.
6. Crear el primer administrador desde la raíz del proyecto:

```powershell
& "C:\xampp\php\php.exe" .\scripts\crear-admin.php
```

Las siguientes cuentas de administrador se crean desde el
panel administrativo.

### Abrir la aplicación

- Catálogo:
  `http://localhost/la-cuponera-sv/public/`
- Inicio de sesión:
  `http://localhost/la-cuponera-sv/public/login.php`

### Recuperación de contraseña en desarrollo

Con `APP_ENV=local`, el enlace de recuperación se muestra en pantalla
para facilitar las pruebas.

En un entorno diferente se utiliza `mail()` y debe configurarse
el servicio de correo del servidor.

### Alcance actual

Incluye registro de clientes y empresas, autenticación por roles,
creación de administradores, aprobación de empresas, recuperación
de contraseñas y consulta pública de ofertas.

La compra de cupones y los reportes administrativos se completarán
en la siguiente fase.

## Seguridad

- Contraseñas protegidas con hash.
- Consultas preparadas mediante PDO.
- Autorización según roles.
- Transacciones para operaciones que requieran integridad.
- No almacenar números completos de tarjetas ni códigos CVV.
- No publicar credenciales ni archivos `.env` en el repositorio.

## Estado

Proyecto en fase inicial de configuración.
