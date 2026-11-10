# Seguridad

Sanctum autentica la SPA mediante cookies HttpOnly; el grupo web exige CSRF. No se guardan tokens de sesión en localStorage. Fortify gestiona verificación de correo firmada, contraseñas y TOTP. AuthService normaliza correo, rechaza cuentas suspendidas, mantiene un tiempo mínimo de validación y actualiza hashes cuando corresponde.

Los secretos TOTP y códigos se cifran con el encrypter de Fortify y se ocultan de la serialización. Leer/renovar/desactivar códigos exige confirmación reciente de contraseña. El desafío bloquea la fila del usuario antes de refrescar y consumir un código de recuperación: dos procesos simultáneos no pueden usarlo con éxito. Fortify también controla la reutilización temporal de TOTP.

`RequireActiveAccount` compara el estado y una huella del hash de contraseña guardada en sesión. Suspender la cuenta, cambiar o recuperar contraseña invalida la sesión en su próxima solicitud, incluida la actual tras cambiar contraseña. `operational` exige además correo verificado, TOTP confirmado y prueba del segundo factor en esa sesión. Reconfigurar el secreto invalida la prueba anterior; una cookie recordada por sí sola no habilita operaciones.

Los QSO necesitan indicativo propio y titularidad verificada. Policies impiden leer contactos, decidir coincidencias o descargar tarjetas ajenas, devolviendo 404 sin revelar existencia. El listado siempre limita por usuario. El perfil permite solo campos explícitos; no permite elevar permisos ni marcar correo como verificado.

Administración requiere cuenta operativa con permiso administrativo y prohíbe autoaprobar indicativos. Cada revisión conserva responsable, fecha y motivo. Disputar o rechazar una titularidad invalida coincidencias afectadas y revoca tarjetas. La aprobación manual verifica evidencia: una expresión regular no acredita licencia ni país. CallsignVerificationProvider es solo el contrato de una futura fuente autorizada.

La verificación pública usa UUID y consulta el estado actual sin caché; omite nombres, correos, notas, IDs internos y rutas de archivo. Los PNG privados no se sirven desde un enlace público de Storage. El renderer utiliza argumentos de proceso separados, SVG generado internamente y texto escapado; no ejecuta comandos proporcionados por usuarios.

Los eventos login/logout/fallo se auditan sin credenciales. Las rutas tienen límites generales y los intentos sensibles límites propios. Los correos se envían por jobs con reintentos; failed_jobs conserva la evidencia del fallo. SMTP puede aceptar un mensaje justo antes de un fallo de commit, por lo que una repetición de entrega sigue siendo posible.

Para producción: HTTPS, cookies seguras, APP_DEBUG=false, credenciales propias, dominio/proxy correcto, backups de base y APP_KEY, y supervisión del worker. No regenerar APP_KEY sobre secretos existentes. El checklist de MVP no equivale a despliegue o auditoría exhaustiva de producción.

## Directorio y perfiles

Credenciales y tokens de proveedores permanecen en backend; tokens cacheados cifrados, sin resultados personales persistidos. Perfil e historial son privados por defecto y no se rellenan desde proveedores. Se exige aprobación de titularidad para publicar. Los endpoints de fotos y perfiles vuelven a comprobar visibilidad en cada solicitud. Las fotos aportadas por el usuario se recodifican y pierden metadatos. Evidencia de titularidad solo para propietario/administrador. Ver [callbook.md](callbook.md).
