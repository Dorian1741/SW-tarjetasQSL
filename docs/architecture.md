# Arquitectura

Laravel 13 MVC y SPA React 19 independiente, con PostgreSQL para dominio, colas y sesiones. No requiere Docker. Vite reenvía `/api` y `/sanctum` al backend para conservar mismo origen, cookies y CSRF. React Router controla la navegación; TanStack Query maneja estado remoto; React Hook Form y Zod validan formularios. Las pantallas de cuenta y estación se cargan de forma diferida.

Servicios:

- AuthService valida credenciales de cuentas activas con tiempo mínimo y rehash; Fortify gestiona registro, correo, contraseñas y TOTP.
- CallsignService normaliza, reclama, revisa, cambia principal y libera reservas; mantiene historial de revisiones.
- QsoService registra contactos de indicativos verificados y retira pendientes.
- QsoMatchingService busca candidatos recíprocos no ambiguos y recibe las decisiones bilaterales.
- QslService emite de forma idempotente; QslRenderer usa BaconQrCode e ImageMagick para producir PNG.
- NotificationService crea entregas idempotentes y despacha DeliverNotification tras commit.

Requests validan entradas; Resources seleccionan datos; Policies limitan lectura/escritura a propietarios, participantes o revisores autorizados. `RequireActiveAccount` invalida sesiones desactualizadas y `operational` exige correo y segundo factor de la sesión actual.

`domain_locks.qsos` serializa las escrituras de indicativos, QSO, matching y QSL dentro de transacciones. Es deliberadamente sencillo para este MVP: evita carreras entre llegada de contactos, invalidación y emisión. El crecimiento exigirá bloqueos por pareja y reconciliación incremental antes de aumentar volumen. No hay microservicios ni proveedores externos obligatorios.

## Directorio modular

CallbookProvider separa QRZ XML y HamQTH v2. CallsignLookupService recibe los proveedores etiquetados en el contenedor; CallbookTransport gestiona HTTP, sesiones cifradas, límites y XML seguro. Ninguna dependencia del proveedor llega a React. OperatorProfileService publica únicamente datos elegidos por el titular; las revisiones siguen en CallsignService y las solicitudes privadas completan el flujo existente. Ver [callbook.md](callbook.md).
