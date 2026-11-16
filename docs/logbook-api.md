# Integración JSON con libros de guardia

## Alcance inicial

La integración permite enviar contactos desde un libro de guardia a QSL Nexus y consultar posteriormente su estado de matching, confirmación y tarjetas. Es una API específica de Nexus, documentada en [OpenAPI](openapi-logbook.yaml); no presupone que el sistema externo ya implemente un protocolo concreto.

No se requiere acceso de Nexus al servidor de tu amigo. Su sistema inicia solicitudes HTTPS hacia Nexus. La confirmación bilateral y la emisión de tarjetas siguen requiriendo la acción de los usuarios en Nexus. No se incorporan sincronización bidireccional, webhooks, lotes, edición remota, borrado remoto ni ADIF en esta primera versión.

## Autorizar una conexión

1. El usuario de Nexus verifica correo, configura 2FA y verifica la titularidad de su indicativo mediante revisión administrativa.
2. En **Integraciones JSON** (`/app/integrations`), confirma su contraseña, elige el indicativo, el nombre del sistema y los permisos.
3. Genera una credencial con vigencia de 1–90 días. Se muestra una sola vez y se guarda únicamente su hash en Nexus.
4. Configura esa credencial en el **backend del libro de guardia**, por usuario/estación. No incluirla en JavaScript del navegador, URLs, repositorios ni logs.
5. Para renovar, utilizar **Rotar credencial** sobre la conexión existente. El secreto anterior se invalida y las referencias de QSO se conservan. Revocar elimina su autorización, sin borrar los contactos importados.

Permisos: `qsos:write` permite importar y recibir su resultado; `qsos:read` permite consultar los contactos importados por esa conexión. Ninguno permite confirmar contactos, administrar cuentas, buscar directorios con credenciales ajenas o emitir tarjetas mediante la API externa. Las credenciales están limitadas a un único indicativo y usuario. Máximo 20 conexiones activas por usuario.

La emisión, rotación y revocación se hacen con sesión web, CSRF, segundo factor y confirmación reciente de contraseña. No existe un endpoint público que entregue credenciales por correo/contraseña.

## Enviar un contacto

```http
POST /api/v1/integrations/qsos
Authorization: Bearer <credencial>
Accept: application/json
Content-Type: application/json
```

El token real tiene formato `nexus_<uuid>.<secreto>`; copiarlo exactamente como se entrega.

```json
{
  "external_id": "logbook-000123",
  "contacted_callsign": "TEST0OTHER",
  "frequency_khz": "14074.125",
  "band": "20m",
  "mode": "FT8",
  "started_at": "2026-01-01T12:00:00Z",
  "rst_sent": "-10",
  "rst_received": "-12",
  "notes": "Contacto ficticio para desarrollo"
}
```

`external_id` es el identificador estable del contacto en el libro de guardia. Debe empezar por letra o número y contener hasta 100 caracteres de letras/números ASCII, punto, guion, guion bajo o dos puntos. Se utiliza también para consultar el estado. No generar uno nuevo en cada reintento.

El indicativo propio viene de la credencial; no se acepta escoger otro mediante `callsign_id` o `user_id`. Opcionalmente enviar `station_callsign`, que debe coincidir con el indicativo autorizado. La frecuencia se envía como **string en kHz**, con hasta tres decimales. Las fechas requieren UTC terminado en Z, segundos y fecha no futura. `ended_at`, RST y notas son opcionales. Las bandas y modos admitidos son los mismos que en el formulario/API de Nexus (ver StoreQsoRequest y docs/api.md). No se convierten automáticamente MHz, zonas locales ni modos no admitidos.

Importa mediante QsoService: mismo control de titularidad y motor de matching que la interfaz. El estado no puede imponerse enviando `status=confirmed`. Una coincidencia técnica sigue siendo candidata hasta que ambas estaciones confirmen.

Ejemplo desde el servidor externo, guardando el JSON anterior en `qso.json` y el secreto en una variable de entorno protegida:

```sh
curl --request POST 'https://TU-DOMINIO/api/v1/integrations/qsos' \
  --header "Authorization: Bearer ${QSL_NEXUS_TOKEN}" \
  --header 'Content-Type: application/json' \
  --header 'Accept: application/json' \
  --data-binary @qso.json
```

En desarrollo local puede usarse `http://localhost:8000`; fuera de local/testing el middleware exige HTTPS. El servidor detrás de un proxy TLS debe reconocer correctamente el esquema seguro según su configuración de proxies confiables.

## Idempotencia y reintentos

- Primera importación: **201**, `replayed=false`.
- Misma referencia y datos equivalentes: **200**, `replayed=true`, mismo QSO y estado actual.
- Misma referencia con otros datos: **409**; no se cambia el QSO anterior.

Se normaliza el indicativo contactado, la representación decimal y campos opcionales para comparar. No se usan floats. Una transacción y restricción única por conexión/external_id evitan duplicación concurrente. Rotar el secreto conserva el ID de la conexión y sus referencias. **Crear otra conexión distinta crea otro espacio de referencias**; no usarlo como método de rotación si se van a reenviar registros anteriores.

Tras timeout, caída de red o 5xx, reenviar la misma referencia y el mismo JSON con pausas crecientes. Para 429 respetar Retry-After. Para 409/422 corregir el flujo; no reintentar indefinidamente. No dar por sincronizado un contacto hasta recibir respuesta válida.

## Consultar el estado

```http
GET /api/v1/integrations/qsos/logbook-000123
Authorization: Bearer <credencial>
Accept: application/json
```

Respuesta esquemática (incluye más campos del QSO y de la coincidencia cuando existen):

```json
{
  "data": {
    "external_id": "logbook-000123",
    "qso": { "id": 123, "status": "pending" },
    "match": null,
    "qsl_cards": []
  }
}
```

`qso.status` puede ser pending, candidate, confirmed, disputed o rejected. `match` contiene el estado y las firmas de ambas estaciones; no incluye sus notas privadas. `qsl_cards` contiene UUID, estado y enlace público de verificación de las tarjetas del contacto. La descarga privada de PNG sigue en la aplicación; esta API inicial expone sus metadatos/enlaces, no una descarga delegada.

`GET /api/v1/integrations/qsos?page=1` lista las referencias importadas por esa conexión, 20 por página, con qso_id y created_at. Para su estado actual consultar cada external_id. No permite explorar contactos de otro usuario, indicativo o conexión, ni contactos creados manualmente que no tengan un recibo de esa integración.

## Seguridad y límites

Las rutas externas son stateless y usan exclusivamente Authorization Bearer. No aceptan cookies para autorizarse y no utilizan CSRF porque no autentican mediante cookies. El grupo web mantiene su protección CSRF sin excepciones globales. El token de integración no autentica las rutas de la SPA.

Cada solicitud vuelve a comprobar usuario activo, correo verificado, 2FA habilitado, vigencia/revocación y titularidad vigente del indicativo. Cambiar contraseña o reconfigurar 2FA invalida el secreto por su huella de seguridad; un usuario que vuelva a autorizar la conexión puede rotarlo desde la interfaz. Desactivar 2FA, suspender la cuenta, liberar o disputar el indicativo bloquea su uso.

60 solicitudes/minuto por credencial, además del límite general de 120/minuto por origen en el grupo externo. Las escrituras comparten el bloqueo de dominio del MVP; la importación es de un QSO por solicitud. last_used_at se actualiza como máximo cada cinco minutos. Las operaciones de creación, rotación y revocación se auditan sin guardar el secreto. Los recibos permiten rastrear el origen de cada QSO.

| HTTP | Significado |
| --- | --- |
| 200 / 201 | Consulta/reintento o creación correctos |
| 401 | Token ausente, incorrecto, vencido, revocado o cuenta ya no habilitada |
| 403 | Falta permiso o se requiere HTTPS |
| 404 | Referencia/conexión no visible para el solicitante |
| 409 | Referencia usada con otros datos o operación incompatible |
| 415 | La importación requiere JSON |
| 422 | Datos inválidos, indicativo no autorizado o vencimiento inválido |
| 423 | Gestión web sin confirmación reciente de contraseña |
| 429 | Límite de solicitudes; esperar Retry-After |

## Gestión web de credenciales

| Ruta bajo /api/v1 | Uso |
| --- | --- |
| GET /integration-credentials | Lista propia paginada; nunca muestra hashes/secretos |
| POST /integration-credentials | name, callsign_id, abilities, expires_in_days; devuelve token una vez |
| POST /integration-credentials/{uuid}/rotate | expires_in_days; nuevo token en la misma conexión |
| DELETE /integration-credentials/{uuid} | Revoca sin borrar recibos ni QSO |

## Verificación

Pruebas automatizadas: importación normal, reintentos equivalentes, conflicto, aislamiento por conexión/usuario, permisos, límite de indicativo, tokens hash/expirados/revocados, rotación sin perder referencias, prohibición de cookies y de acceso a la SPA, JSON, cuota y contraseña reciente. PostgreSQL verifica tres importaciones simultáneas de la misma referencia: un único QSO y un recibo. Chrome comprueba emisión de credencial, envío JSON, repetición, consulta y revocación desde la interfaz.

La conexión con el programa concreto de tu amigo todavía requiere que su backend implemente este contrato y configure la URL/credencial. No se ha probado su programa porque no se proporcionó su código o endpoint; se comprobó el contrato desde pruebas y un cliente HTTP de navegador.

Resultado de las verificaciones de esta entrega: 87 pruebas backend PostgreSQL aprobadas; la regresión de cinco recorridos Chrome y el nuevo recorrido JSON pasaron. El YAML OpenAPI se parseó correctamente y el frontend compiló/formateó correctamente.
