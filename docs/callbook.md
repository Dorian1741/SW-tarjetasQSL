# Directorio internacional y perfiles sociales

## Alcance operativo

Implementados: adaptadores QRZ XML y HamQTH **v2**, búsqueda privada autenticada, registro atómico con indicativo obligatorio, solicitudes privadas de titularidad y revisión administrativa, perfiles públicos optativos, biografía, foto propia, país, enlaces HTTPS, historial QSL optativo, seguimiento y publicaciones nativas de texto.

La verificación de correo y TOTP reutilizan Sanctum/Fortify. No se duplicó la autenticación ni se creó un segundo sistema de reservas de indicativos. CallsignService mantiene normalización y reserva única; CallsignLookupService consulta adaptadores registrados mediante la interfaz CallbookProvider. El contrato anterior CallsignVerificationProvider conserva su responsabilidad distinta: futuras fuentes de evidencia de titularidad, no búsqueda de directorio.

## Proveedores y credenciales

Especificaciones oficiales consultadas:

- [QRZ XML](https://www.qrz.com/docs/xml/current_spec.html): autenticación y consulta XML mediante POST HTTPS; se reutiliza una sesión temporal. El acceso completo depende de los permisos/suscripción de la cuenta QRZ.
- [HamQTH v2](https://www.hamqth.com/developers): login POST y consulta con token Bearer. No se utiliza el antiguo endpoint XML v1. Se atribuye la fuente en la interfaz.

Configurar exclusivamente en `backend/.env`:

```dotenv
QRZ_ENABLED=false
QRZ_USERNAME=
QRZ_PASSWORD=
HAMQTH_ENABLED=false
HAMQTH_USERNAME=
HAMQTH_PASSWORD=
```

Activar el proveedor elegido después de configurar una cuenta autorizada. No usar prefijos `VITE_`, no colocar credenciales en React ni incluirlas en URLs públicas. Ejecutar `php artisan config:clear` tras cambiar la configuración. No se modificó el `.env` existente ni se añadieron credenciales reales.

Los adaptadores están implementados y verificados con respuestas controladas. **La conectividad autenticada real con QRZ y HamQTH no se ha probado**, porque no se proporcionaron credenciales autorizadas. Sin proveedores habilitados, la API devuelve 503 con estado `unavailable`; no inventa resultados. Un fallo externo no impide registrarse con un indicativo de formato válido.

## Estados separados

- `format_valid`: únicamente sintaxis validada por CallsignService; un formato inválido devuelve 422.
- `directory_status`: `found`, `not_found` o `unavailable`. Cada proveedor detalla además `unconfigured` o `incomplete` cuando corresponde. No encontrado solo significa que los proveedores configurados consultados lo indicaron; no demuestra inexistencia mundial.
- `ownership_verified`: revisión administrativa de una reserva local activa. Encontrar un registro externo jamás cambia este estado ni aprueba una licencia.

Las formas portátiles se conservan en el indicativo solicitado. Si una fuente devuelve su registro base, `record.callsign` lo identifica sin sustituir la reserva del usuario. No se infiere país del prefijo. Un registro devuelto que no corresponde al indicativo o a sus segmentos portátiles se trata como incompleto.

## Privacidad y resiliencia

Solo se devuelve indicativo y país de la respuesta externa. Se excluyen nombres, direcciones, correo, coordenadas, foto, biografía y demás datos personales del proveedor. No se importan a perfiles, no se guardan resultados ni se implementan barridos o sincronización masiva. El operador debe usar cada cuenta según sus permisos y condiciones de acceso.

Los únicos datos externos cacheados son tokens/sesiones cifrados y estados de cooldown/cuotas. Timeouts: conexión de 2 segundos y respuesta de 5 segundos por solicitud; renovación de sesión como máximo una vez. El modo automático prueba QRZ y después HamQTH hasta encontrar un resultado. No se siguen redirecciones con credenciales. XML rechaza DTD/entidades y usa LIBXML_NONET; se limita el tamaño de respuesta aceptado. Las excepciones de red y errores de autenticación se convierten en estados seguros sin propagar cuerpo ni secretos del proveedor.

El endpoint exige sesión y correo verificado, con 10 consultas/minuto por usuario. El transporte aplica 30 solicitudes/minuto por proveedor/cuenta, incluidos logins. HTTP 429 activa cooldown respetando Retry-After acotado; las caídas generan pausa corta. Una cuenta sin configurar se distingue de un indicativo no encontrado.

## Registro, titularidad y publicación

`POST /api/v1/auth/register` exige `callsign`, `email`, `password` y `password_confirmation`; `name` es opcional en backend. Crea usuario, reserva pendiente y perfil **privado** en una sola transacción. Una reclamación duplicada revierte la creación del usuario; no quedan cuentas huérfanas. Usuarios anteriores conservan sus cuentas y pueden crear su perfil desde el editor.

Después de verificar correo y configurar TOTP, el usuario solicita revisión para un indicativo propio enviando una referencia privada de evidencia. Una solicitud pendiente por indicativo; un administrador ajeno consulta esa evidencia y utiliza la revisión existente. La decisión actualiza la solicitud, guarda responsable/fecha y conserva callsign_reviews y security_events. La respuesta pública no contiene esa evidencia.

El perfil solo es público si su propietario lo publica expresamente, tiene correo verificado, cuenta activa e indicativo reservado con titularidad verificada. Rechazo/disputa, liberación de la reserva, suspensión o retiro del consentimiento lo ocultan inmediatamente en la API. La configuración 2FA protege edición, publicación, seguimiento y solicitudes; no hace falta iniciar sesión para leer un perfil ya público.

Los enlaces son aportados por el usuario, solo HTTPS, hasta cinco, sin credenciales embebidas. Biografía y publicaciones se renderizan como texto, sin HTML. La foto se sube como archivo propio JPEG/PNG/WebP de hasta 2 MB y 4096×4096; ImageMagick la convierte a PNG hasta 1024×1024 y elimina metadatos. No se descargan imágenes desde URLs externas.

El historial público está desactivado inicialmente. Al habilitarlo se muestran únicamente tarjetas emitidas por ese usuario que siguen disponibles y confirmadas, con enlaces a la verificación pública existente. No se publica el historial de QSO ni notas. Las publicaciones provienen exclusivamente de la base de QSL Nexus; no hay importación de contenido de QRZ/HamQTH.

## Rutas

| Método y ruta | Uso |
| --- | --- |
| POST /api/v1/callsigns/lookup | Sesión + correo verificado; `{ "callsign": "HC1VTB", "provider": "auto" }`; provider opcional: auto/qrz/hamqth |
| GET /api/v1/operator-profile | Editor privado propio; null para cuentas anteriores sin perfil |
| PUT /api/v1/operator-profile | callsign_id propio, biography, country_code, links, is_public, show_qsl_history |
| POST /api/v1/operator-profile/photo | multipart photo; solo archivo aportado por el propietario |
| POST /api/v1/callsigns/{id}/ownership-requests | evidence de 20–2000 caracteres, propietario operativo |
| GET /api/v1/callsigns/{id}/ownership-requests | Propietario o administrador; evidencia privada |
| POST /api/v1/admin/callsigns/{id}/review | Reutiliza status/reason existentes; no permite autoaprobación |
| GET /api/v1/public/operators/{callsign} | Perfil público; admite sufijos portátiles codificados |
| GET /api/v1/public/operator-photos/{profile} | PNG, solo si el perfil está actualmente visible |
| GET /api/v1/public/operator-history/{callsign} | Tarjetas emitidas compartidas; 12/página; 404 sin consentimiento |
| GET /api/v1/public/operator-posts/{callsign} | Texto local público; 20/página |
| PUT /api/v1/operators/follow/{callsign} | Sigue un perfil público, idempotente, sin permitir auto-seguimiento |
| DELETE /api/v1/operators/follow/{callsign} | Deja de seguir |
| POST /api/v1/operator-posts | content, hasta 2000 caracteres; requiere perfil propio publicado |
| DELETE /api/v1/operator-posts/{id} | Solo el autor |

Las escrituras sociales exigen cuenta operativa (correo + 2FA), CSRF y sesión. Los recursos ajenos o no públicos responden 404. La búsqueda devuelve 200 para found/not_found, 422 para formato inválido, 503 si no puede determinar el resultado y 429 para cuota local. Todas las consultas privadas responden sin caché compartida.

Frontend: `/directory`, `/operators/HC1VTB` y `/app/operator-profile`. La administración existente añade acceso a las solicitudes de titularidad. HC1VTB se utiliza en pruebas controladas como texto de consulta; no se crean contactos reales ni se atribuye una cuenta real a su titular.

## Verificación y límites

Las pruebas nuevas cubren XML con namespace, renovación, HamQTH v2/Bearer, 404, respuesta incompleta, timeout, 429, fallback, redacción de datos privados, indicativos inválidos, reserva duplicada con rollback, permisos, cuotas, publicación optativa, fotos, enlaces, solicitudes/auditoría, seguimiento idempotente y publicaciones ajenas.

Chrome comprueba además registro con indicativo y publicación de perfil/texto propio, ausencia de correo e historial privado, seguimiento y accesibilidad del perfil. Las integraciones externas se prueban con Http::fake y Http::preventStrayRequests; no se afirman búsquedas reales autenticadas.

Esta entrega inicial no incluye feed agregado de seguidos, comentarios, mensajería, notificaciones de seguimiento, búsqueda masiva, documentos de licencia adjuntos, moderación avanzada ni importación de redes externas. Las publicaciones nativas son de texto. La API y la interfaz paginan el historial compartido.

Resultado de esta sesión: 76 pruebas backend aprobadas en PostgreSQL, 5 recorridos Chrome aprobados, Axe sin infracciones en las páginas evaluadas, y build/formateo correctos. Se aplicó la nueva migración aditiva en la base local existente, conservando cuentas y contactos; no se modificó `.env` ni se realizaron commits.
