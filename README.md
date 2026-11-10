# QSL Nexus

MVP en desarrollo: Laravel 13 / PHP 8.3+, PostgreSQL y React 19 / TypeScript / Vite / Tailwind 4.

El recorrido del MVP está implementado: registro y verificación de correo, TOTP, indicativos con revisión administrativa, registro e historial de QSO, matching no ambiguo, confirmación bilateral, tarjetas PNG con QR, verificación pública, notificaciones por cola y dashboard desde la API.

El [checklist](docs/development-plan.md) registra las cinco fases y sus verificaciones. Desarrollo local sin Docker; `compose.yaml` se conserva solo como alternativa.

## Ejecutar localmente

Requisitos: PHP 8.5 (versión verificada con el lock actual) con pdo_pgsql, mbstring, intl, xml, curl y zip; Composer 2; Node 22.12+ y PostgreSQL instalado localmente. Para las tarjetas se requieren ImageMagick 7 (`magick`) con soporte SVG y la fuente DejaVu Sans. Docker no es necesario. No usar Node 22 anterior a 22.12.

### PostgreSQL local

Si ya tienes un servidor PostgreSQL, crea una base `qsl_nexus` y un usuario con permisos sobre ella, y usa sus credenciales en el backend.

Si tienes los binarios PostgreSQL pero todavía no tienes un servidor configurado, puedes crear una instancia de desarrollo sin permisos de administrador. Desde la raíz del proyecto, **solo para una instancia nueva**:

```sh
mkdir -p .local
initdb -D .local/postgres -U qsl_nexus --auth=scram-sha-256 --encoding=UTF8 --locale=C -W
pg_ctl -D .local/postgres -l .local/postgres.log -o "-h 127.0.0.1 -p 5432 -k /tmp" start
createdb -h 127.0.0.1 -p 5432 -U qsl_nexus -W qsl_nexus
```

`initdb` solicita una contraseña nueva; guarda la misma en `DB_PASSWORD`. No repitas la inicialización en cada arranque. Si el puerto 5432 está ocupado, utiliza el servidor existente o elige otro puerto y ajusta `DB_PORT`.

Para siguientes arranques o para detener esta instancia, desde la raíz:

```sh
pg_ctl -D .local/postgres -l .local/postgres.log -o "-h 127.0.0.1 -p 5432 -k /tmp" start
# Al terminar:
pg_ctl -D .local/postgres stop -m fast
```

### Backend y correo

```sh
cd backend
composer install
```

Si no existe `backend/.env`, copiar `.env.example` a `.env`. Si ya existe, conservarlo y ajustar sus variables según el ejemplo. Configurar PostgreSQL (`DB_CONNECTION=pgsql`, host `127.0.0.1`, puerto `5432`, base/usuario `qsl_nexus`, contraseña elegida al crear el usuario), correo de desarrollo (`MAIL_MAILER=log`), `APP_URL=http://localhost:8000`, `FRONTEND_URL=http://localhost:5173`, `QUEUE_CONNECTION=database` y `SESSION_ENCRYPT=true`.

En backend, generar la clave solo para una instalación nueva; cambiarla invalida secretos cifrados existentes:

```sh
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

En otra terminal:

```sh
cd backend
php artisan queue:work --tries=3 --backoff=10
```

En otra terminal:

```sh
cd frontend
npm ci
npm run dev
```

Abrir la SPA en `http://localhost:5173`. Utilizar siempre el mismo host (`localhost`), sin alternarlo con `127.0.0.1`, para conservar las cookies. Vite reenvía `/api` y `/sanctum` al backend, manteniendo mismo origen y CSRF. Con `MAIL_MAILER=log`, los mensajes y sus enlaces aparecen en `backend/storage/logs/laravel.log` cuando el worker procesa la cola. Para verificar correo, abrir el enlace en el mismo navegador donde se inició sesión.

Para probar SMTP de verdad puedes ejecutar Mailpit como binario local y configurar `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1` y `MAIL_PORT=1025`. Mailpit es opcional; también puedes configurar un servidor SMTP propio. Después de cambiar el correo, ejecuta `php artisan config:clear` y reinicia el worker. No se ha instalado Mailpit en este entorno.

Registrar una cuenta, verificar el correo, confirmar la contraseña y escanear el QR con una aplicación TOTP. Guardar los códigos de recuperación. Después se pueden reclamar indicativos, que permanecen pendientes hasta revisión manual. No se deduce el país del prefijo.

Un operador con acceso al servidor puede conceder permisos a una cuenta existente:

```sh
cd backend
php artisan qsl:make-admin admin@example.test
```

El administrador encuentra la pantalla de revisión en el menú de su estación (`/app/admin`). También existe el endpoint documentado en [API](docs/api.md). Un administrador no puede revisar su propio indicativo.

## Datos de demostración

Opcional, solo con `APP_ENV=local` o `testing`:

```sh
cd backend
php artisan db:seed
```

Crea `demo-a@example.test` y `demo-b@example.test`, contraseña `DemoPassword123!`, indicativos artificiales `TEST0DEMOA/B` y contactos recíprocos claramente ficticios. No verifica titularidades, no confirma contactos ni emite tarjetas. Los usuarios deben verificar correo y configurar su propio TOTP. El seeder puede repetirse sin duplicar esos registros.

Para cargar un escenario completo opcional con QSO confirmados, un contacto pendiente y una tarjeta PNG:

```sh
cd backend
php artisan db:seed --class=DemoScenarioSeeder
```

Crea `scenario-a@example.test`, `scenario-b@example.test` y `scenario-admin@example.test`, con contraseña `DemoPassword123!`. Sus correos y las titularidades ficticias se aprueban exclusivamente como fixtures de desarrollo. Cada usuario debe configurar su propio TOTP desde la interfaz. El administrador ficticio puede revisar solicitudes ajenas. Nunca ejecutar estos seeders en producción.

## Recorrido funcional

1. Ingresar o crear cuenta en `/account`; verificar el correo y activar TOTP.
2. Reclamar un indicativo; otro usuario administrador debe revisar su evidencia y aprobarlo.
3. Abrir la estación (`/app`) y registrar QSO con un indicativo verificado, frecuencia en **kHz como texto decimal** y hora UTC.
4. La otra estación registra el contacto recíproco. Si solo hay un candidato compatible en ambos sentidos, aparece una coincidencia pendiente.
5. Ambos usuarios confirman que el contacto ocurrió. Entonces cualquiera puede emitir su tarjeta QSL.
6. Descargar el PNG y abrir su QR: la página pública consulta el estado actual del servidor. Una disputa posterior revoca la tarjeta.

Los QSO pendientes pueden retirarse desde su detalle. Los confirmados se disputan desde coincidencias para conservar trazabilidad. El perfil permite cambiar el indicativo principal, renovar códigos de recuperación y cambiar contraseña. El administrador puede rechazar una titularidad y liberar su reserva cuando no queden QSO activos; se conserva el historial.

## Verificación

```sh
cd backend
php artisan test --compact
vendor/bin/pint --format agent
cd ../frontend
npm run build
npm run format:check
npm run test:e2e
```

La suite predeterminada usa SQLite en memoria; omite tres pruebas que requieren PostgreSQL/procesos y SMTP local. La suite completa se ejecuta contra **una base exclusiva de pruebas cuyo nombre termina en `_test`**:

```sh
cd backend
QSL_INTEGRATION=1 DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=qsl_nexus_test DB_USERNAME=qsl_nexus DB_PASSWORD=TU_CLAVE php artisan test --compact
```

Estas pruebas recrean el esquema y nunca deben apuntar a una base con datos reales. Incluyen registros, confirmaciones y emisiones concurrentes; dos sesiones que intentan consumir el mismo código de recuperación; y un worker que entrega SMTP real a un receptor local temporal que no reenvía mensajes a Internet. Se necesita Python 3 para ese receptor.

Playwright usa Chrome instalado en `/usr/bin/google-chrome` o el ejecutable indicado por `CHROME_BINARY`. Arranca servidores aislados en puertos 18000 y 15173 y recrea únicamente `.local/qsl-browser-test.sqlite`. Sus imágenes se guardan en `.local/browser-files`; no usa la base configurada en tu `.env`. Verifica el recorrido de dos estaciones, administración, menú móvil y accesibilidad con Axe. Capturas y trazas quedan en `frontend/test-results/`.

Verificación realizada: 56 pruebas de backend aprobadas en PostgreSQL, 3 recorridos de navegador aprobados y build del frontend aprobado. Axe no encontró infracciones en las páginas evaluadas; esto no sustituye una auditoría de accesibilidad exhaustiva.

## Correo y archivos

Las notificaciones de candidato, confirmación, disputa y tarjeta se guardan en `notification_deliveries`. El worker realiza hasta tres intentos, con pausas crecientes, y registra entrega o fallo. La verificación de cuenta y recuperación también se envían por cola. Con `MAIL_MAILER=log` los mensajes quedan en el log; para entrega SMTP configurar las variables `MAIL_*` y reiniciar el worker.

Los fallos de cola se consultan con `php artisan queue:failed`; reintentar un trabajo revisado con `php artisan queue:retry ID`. El worker debe tener un timeout inferior a `retry_after`; el job operativo limita su ejecución a 30 segundos y la configuración de base de datos reserva 90 segundos por defecto.

Los PNG se guardan en Laravel Storage privado y se descargan solo mediante rutas autorizadas. `IMAGEMAGICK_BINARY` permite indicar la ruta del ejecutable si no está en PATH. Configurar `FRONTEND_URL` con la dirección accesible por quienes escanearán el QR **antes de emitir** tarjetas; `localhost` solo sirve en el mismo equipo. La verificación pública muestra datos de radio del contacto y nunca nombres, correos, notas ni rutas internas.

No ejecutar `migrate:fresh` en una base con datos que se quieran conservar. La migración operativa conserva historial de reservas liberadas y no ofrece rollback destructivo; utilizar nuevas migraciones para corregirla. Los datos de la instancia nativa persisten en `.local/postgres`.
