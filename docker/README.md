Docker es opcional. El flujo principal usa PostgreSQL nativo, correo de desarrollo en logs y procesos locales siguiendo el README raíz.

Si se prefiere usar contenedores, `../compose.yaml` conserva PostgreSQL y Mailpit. Sus puertos no deben coincidir con servicios locales ya iniciados. Configurar `MAIL_MAILER=smtp` para utilizar Mailpit. No se incluyen imágenes de despliegue de producción.
