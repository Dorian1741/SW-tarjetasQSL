# Datos y restricciones

- users: credenciales, preferencias internacionales, estado, administración y secretos TOTP/recuperación cifrados por Fortify.
- callsigns: propietario, indicativo normalizado e historial de revisión. `reserved_callsign` es único mientras se reserva; al liberar queda NULL, conservando el indicativo histórico y sus contactos. PostgreSQL limita a un principal por usuario con índice parcial.
- callsign_reviews: registro acumulativo de responsable, estado, motivo y fecha. La fila callsigns conserva además la revisión actual.
- qsos: FK compuesta indicativo/propietario, frecuencia DECIMAL(12,3) en kHz, banda/modo, fechas UTC y estado. El Resource devuelve frecuencia como string.
- qso_matches: par ordenado y único, diferencia de tiempo, firmas independientes y estado. La reciprocidad y ambigüedad las comprueba el servicio.
- qso_confirmations: PK qso_id, FK a match; impide reservar el mismo contacto para dos confirmaciones incompatibles. Las reservas históricas se conservan al disputar.
- qsl_cards: UUID público único, match, participantes, template, archivo privado, estado y fechas; unicidad match/emisor para idempotencia.
- notification_deliveries: evento/usuario único, contenido, intentos, lectura, estado, fecha de entrega y fallo. Las notificaciones de cuenta usan también los jobs estándar de Laravel.
- security_events: actor opcional, evento, IP y fecha, sin credenciales.
- domain_locks: fila de exclusión transaccional del dominio.
- sessions, password_reset_tokens, personal_access_tokens, notifications, jobs, failed_jobs, job_batches y cache: estructuras de soporte Laravel. La SPA no emite tokens personales.

La conexión PostgreSQL fija UTC independientemente de la zona del servidor. Los CHECK adicionales exigen frecuencia positiva, fin posterior al inicio y pares de QSO ordenados. SQLite sirve para pruebas rápidas, no sustituye los tests PostgreSQL de bloqueo/restricciones.

Los borrados de dominio se restringen para mantener trazabilidad. Retirar un QSO cambia su estado; disputar revoca la confirmación y las tarjetas. Liberar un indicativo requiere rechazo previo y ausencia de QSO activos. El historial permanece unido al antiguo propietario.

Las migraciones existentes no se reescribieron para el nuevo dominio. La migración operativa no permite rollback destructivo: después de liberar reservas puede existir historial normalizado duplicado, por lo que restaurar la antigua unicidad perdería información. Aplicar una migración correctiva hacia adelante.

## Extensión de directorio

`operator_profiles` mantiene un perfil por usuario y una FK compuesta que vincula su indicativo al mismo propietario; la publicación y el historial son optativos. `operator_follows` impide duplicar una relación seguidor/seguido. `operator_posts` conserva exclusivamente contenido local aportado por el autor. `ownership_requests` registra evidencia privada y decisión; un índice parcial impide dos solicitudes pendientes para el mismo indicativo. Los resultados de proveedores externos no tienen tabla de persistencia. Ver [callbook.md](callbook.md).
