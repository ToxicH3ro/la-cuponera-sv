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

## Seguridad

- Contraseñas protegidas con hash.
- Consultas preparadas mediante PDO.
- Autorización según roles.
- Transacciones para operaciones que requieran integridad.
- No almacenar números completos de tarjetas ni códigos CVV.
- No publicar credenciales ni archivos `.env` en el repositorio.

## Estado

Proyecto en fase inicial de configuración.
