# Checklist del MVP — 5 de 5 fases

Actualizado: 9 de noviembre de 2026 (America/Guayaquil). Ejecución preferida: local, sin Docker.

## Fase 1: entorno y base de datos

- [x] Conservar Laravel existente e inicializar React/TypeScript/Vite/Tailwind independientes.
- [x] PostgreSQL nativo, variables de ejemplo e instrucciones reales de arranque.
- [x] Migraciones, modelos, relaciones, índices y restricciones de integridad.
- [x] Frecuencia decimal exacta y fechas UTC.
- [x] Seeders limitados a desarrollo/pruebas, con indicativos explícitamente artificiales.
- [x] Migraciones y persistencia verificadas en PostgreSQL; fixtures repetibles.

## Fase 2: usuarios y seguridad

- [x] Registro/login/logout por sesiones Sanctum, cookies y CSRF.
- [x] Verificación de correo y recuperación de contraseña mediante cola.
- [x] TOTP confirmado, QR, códigos de recuperación, renovación y desactivación.
- [x] Consumo de recuperación serializado por usuario y probado con procesos concurrentes.
- [x] Invalidez de sesiones después de suspensión o cambio/recuperación de contraseña.
- [x] Perfil y preferencias; reclamación/listado de indicativos y cambio de principal.
- [x] Revisión administrativa en API/UI, sin permitir autoaprobación.
- [x] Responsable, fecha, motivo e historial de revisiones.
- [x] Disputas, rechazo y liberación de reservas con conservación del historial.
- [x] Contrato para futuras fuentes autorizadas de verificación, sin scraping.
- [x] Policies, límites de solicitudes, aislamiento y protección de secretos.

## Fase 3: contactos y matching

- [x] QsoService, Form Request, Resource y Policy.
- [x] API/UI de registro y detalle, retirada de pendientes e historial.
- [x] Búsqueda, filtros y paginación.
- [x] Reciprocidad, propietarios distintos, misma banda/modo y límite inclusivo de 30 minutos.
- [x] Unicidad de candidato en ambos sentidos; no confirma ambigüedades.
- [x] Invalidación de candidatos anteriores si llega un tercer QSO ambiguo.
- [x] Confirmación de ambas estaciones; rechazo/disputa y revocación coherente.
- [x] Transacciones, bloqueo compartido de dominio y reserva única por QSO confirmado.
- [x] Pruebas de límites, incompatibilidad, duplicados, ambigüedad y concurrencia PostgreSQL.

## Fase 4: tarjetas QSL

- [x] QslService y PNG real mediante ImageMagick y QR mediante BaconQrCode.
- [x] UUID público, indicativos, datos del contacto, emisión e identificador.
- [x] Verificación pública que consulta el estado actual, sin información privada.
- [x] Almacenamiento privado y descarga autorizada para participantes.
- [x] Idempotencia de emisión, incluida emisión simultánea.
- [x] Notificaciones de candidato, confirmación, disputa y tarjeta por jobs.
- [x] Estado de entrega, reintentos y fallos registrados.
- [x] Entrega SMTP real mediante el worker comprobada con receptor local temporal.

## Fase 5: integración y pruebas

- [x] Dashboard y actividad reciente desde datos reales de la API.
- [x] Pantallas de cuenta, seguridad, perfil, indicativos, QSO, coincidencias y tarjetas.
- [x] Detalle de tarjeta, verificación pública, administración y notificaciones.
- [x] AppLayout, Sidebar, Header, StatCard, DataTable, StatusBadge, QsoForm, QslCard y estados de carga/vacío/error.
- [x] Navegación privada, menú móvil, diseño responsive y carga diferida de pantallas.
- [x] Escenario de demostración completo con contactos confirmados, pendientes y tarjeta.
- [x] Flujo de dos usuarios verificado por API y por Chrome.
- [x] Revisión administrativa y navegación móvil verificadas en Chrome.
- [x] Axe sin infracciones en landing, cuenta y dashboards evaluados.
- [x] Build TypeScript/Vite, formateo y documentación final.

## Evidencia

- Backend PostgreSQL 18: 56 pruebas aprobadas; incluye dos pruebas de concurrencia y una de SMTP/worker.
- Suite SQLite: misma suite funcional; omite las tres integraciones que requieren PostgreSQL/red local.
- Playwright/Chrome: tres recorridos aprobados. Archivos de captura en `frontend/test-results/`.
- PNG real validado por firma binaria; descarga privada y revocación pública verificadas.
- No se modificó el `.env` del usuario ni se migró su base. Las verificaciones utilizaron PostgreSQL temporal y SQLite de navegador aislado.
- No se realizaron commits. El montaje actual no expone un repositorio Git utilizable; Pint se ejecuta completo.

## Límites de esta entrega

El MVP funcional no incluye radioclubes, pagos, comprobación automática con registros externos ni un despliegue de producción. El motor serializa escrituras del dominio mediante una fila: es una decisión de simplicidad para el volumen de un MVP. Una carga grande requerirá particionar bloqueos y optimizar la reconciliación. La entrega SMTP tiene semántica al menos una vez: un corte después de aceptar el servidor el mensaje y antes del commit podría repetirlo. La accesibilidad automatizada cubre las páginas indicadas, no todos los casos de asistencia o todos los dispositivos.

## Extensión: directorio internacional y perfiles sociales

- [x] CallbookProvider y adaptadores QRZ XML / HamQTH v2 con credenciales backend.
- [x] Búsqueda autenticada, normalización compartida, límites, errores y datos mínimos.
- [x] Registro obligatorio con indicativo único, correo y contraseña; transacción con perfil privado.
- [x] Correo y TOTP reutilizados; tres estados de confianza separados.
- [x] Solicitudes privadas de titularidad, revisión administrativa y auditoría existentes integradas.
- [x] Perfiles optativos, biografía, foto propia, país y enlaces HTTPS.
- [x] Historial QSL compartido por consentimiento, seguimiento y publicaciones nativas de texto.
- [x] Pruebas de proveedor inexistente/caído, duplicados y accesos no autorizados.
- [x] Recorridos de Chrome para registro y perfil público, sin datos externos automáticos.

Pendiente de configuración del servidor: credenciales autorizadas de proveedores y verificación autenticada real. Las pruebas de adaptadores utilizan respuestas controladas. Detalles y alcance inicial en [callbook.md](callbook.md).

Verificación de la extensión: 76 pruebas backend PostgreSQL y cinco recorridos Chrome aprobados; build y formateo correctos. Migración aditiva aplicada localmente sin recrear la base ni modificar `.env`. Los números de la sección inicial de evidencia corresponden al MVP anterior a esta extensión.
