# API v1 implementada

JSON con `Accept: application/json`. Las rutas pertenecen al grupo web: CSRF y cookies son obligatorios. Iniciar con `GET /sanctum/csrf-cookie` y enviar `X-XSRF-TOKEN` usando el valor decodificado de la cookie XSRF-TOKEN (Axios lo hace). No se devuelve bearer token.

Prefijo de autenticación `/api/v1/auth` (Fortify):

| Método/ruta | Datos/resultado |
| --- | --- |
| POST /register | name, email, password, password_confirmation; 201 y sesión, correo pendiente |
| POST /login | email, password; 200, `{ "two_factor": true }` si requiere desafío |
| POST /two-factor-challenge | code o recovery_code; 204 y sesión autenticada |
| POST /logout | 204, invalida sesión |
| POST /forgot-password | email; 200 si el broker envía recuperación |
| POST /reset-password | email, token, password, password_confirmation |
| GET /email/verify/{id}/{hash} | enlace firmado del correo, requiere sesión del destinatario |
| POST /email/verification-notification | reenvía verificación |
| POST /user/confirm-password | password, habilita gestión sensible |
| GET /user/confirmed-password-status | estado de confirmación reciente |
| POST /user/two-factor-authentication | genera secreto cifrado y códigos, aún no confirmado |
| GET /user/two-factor-qr-code | `{ "svg": "...", "url": "..." }` |
| GET /user/two-factor-secret-key | clave para configuración manual |
| POST /user/confirmed-two-factor-authentication | code; confirma configuración |
| GET /user/two-factor-recovery-codes | códigos actuales, requiere contraseña reciente |
| POST /user/two-factor-recovery-codes | reemplaza códigos |
| DELETE /user/two-factor-authentication | deshabilita 2FA; bloquea operaciones |
| PUT /user/password | current_password, password, password_confirmation |
| PUT /user/profile-information | name, email; cambiar email revoca verificación |

Rutas de aplicación bajo `/api/v1`:

| Método/ruta | Acceso |
| --- | --- |
| GET /profile | sesión; Resource `{ "data": { "id": 1, "name": "...", "email": "...", "email_verified_at": null, "two_factor_enabled": false, ... } }` |
| PATCH /profile | sesión; name, preferred_language, timezone, country_code; solo campos presentes |
| GET /callsigns?page=1 | cuenta operativa; solo indicativos propios, 20/página y metadatos Laravel |
| POST /callsigns | cuenta operativa; `{ "callsign": "TEST0ABC/P", "country_code": "EC" }`; 201, `{ "data": { "id": 1, "callsign": "TEST0ABC/P", "verification_status": "pending", ... } }` |
| POST /admin/callsigns/{id}/review | administrador operativo, indicativo ajeno; `{ "status": "verified", "reason": "Licencia revisada manualmente." }`; 200 y Resource |

Estados de revisión admitidos: verified, rejected, disputed. El país es opcional y explícito. Errores Laravel JSON: 401 sin sesión; 403 cuenta sin verificación/2FA; 404 revisión sin permisos; 419 CSRF; 422 validación; 429 límite. Los éxitos Fortify usan sus respuestas oficiales (mensaje/status o cuerpo vacío), los recursos usan `data`.

## Operaciones implementadas

Todas estas rutas requieren `auth:sanctum` y `operational`, salvo la verificación pública. `operational` exige cuenta activa, correo verificado y segundo factor en la sesión actual.

| Método/ruta bajo /api/v1 | Datos y resultado |
| --- | --- |
| PATCH /callsigns/{id}/primary | selecciona un indicativo propio reservado como principal |
| GET /admin/callsigns?status=pending&page=1 | administrador, lista paginada de reservas |
| DELETE /admin/callsigns/{id}/reservation | administrador ajeno, rechazo previo y sin QSO activos; 204, conserva historial |
| POST /qsos | cuerpo del ejemplo siguiente; 201, Resource con `data` |
| GET /qsos | search, band, mode, status, from/to (YYYY-MM-DD), page; 20/página |
| GET /qsos/{id} | detalle propio |
| DELETE /qsos/{id} | retira un pendiente/candidato; 204; 409 para confirmado/disputado |
| GET /matches?status=candidate&page=1 | solo coincidencias del participante |
| PATCH /matches/{id} | `{ "decision": "confirm" }`, `reject` o `dispute`; 200 Resource |
| POST /matches/{id}/qsl-cards | match confirmado bilateralmente; emisión idempotente; 200 Resource, 409 si no es emitible |
| GET /qsl-cards?page=1 | tarjetas como emisor o receptor, paginadas |
| GET /qsl-cards/{uuid} | detalle privado para participantes |
| GET /qsl-cards/{uuid}/download | PNG privado; 409 si está revocado |
| GET /public/qsl-cards/{uuid} | público, datos básicos y estado actual, Cache-Control: no-store |
| GET /dashboard | total_qsos, confirmed_qsos, issued_cards, pending_matches, recent_activity |
| GET /notifications?page=1 | entregas del usuario, estado y lectura; paginador JSON |
| PATCH /notifications/{id} | marca como leída una notificación propia |

Ejemplo de registro (la frecuencia se envía como string, nunca como float):

```json
{
  "callsign_id": 1,
  "contacted_callsign": "TEST0FULLB",
  "frequency_khz": "14074.125",
  "band": "20m",
  "mode": "FT8",
  "started_at": "2026-01-01T12:00:00Z",
  "rst_sent": "-10",
  "rst_received": "-12",
  "notes": "Observación privada"
}
```

`ended_at` es opcional y debe ser UTC posterior al inicio. Inicio/fin no pueden estar en el futuro. Modos: SSB, CW, FM, AM, FT8, FT4, RTTY, PSK31, JS8, DSTAR, DMR, C4FM. La compatibilidad es igualdad exacta. Bandas admitidas en StoreQsoRequest. Un indicativo pendiente/rechazado/disputado no permite crear QSO.

Ejemplo de coincidencia tras la primera firma:

```json
{
  "data": {
    "id": 1,
    "status": "candidate",
    "my_callsign": "TEST0FULLA",
    "other_callsign": "TEST0FULLB",
    "time_difference_seconds": 0,
    "my_confirmation": true,
    "other_confirmation": false
  }
}
```

El Resource incluye además banda, modo, hora y explicación. La segunda firma cambia status a confirmed; recién entonces se puede emitir. Una disputa posterior cambia la verificación pública a revoked/disputed. No se envían notas de la otra estación.

La página pública es `/verify/{uuid}` en el frontend. La API usa el mismo UUID, no un ID secuencial, y no expone datos de cuenta. Los ejemplos usan indicativos artificiales exclusivamente para desarrollo.

