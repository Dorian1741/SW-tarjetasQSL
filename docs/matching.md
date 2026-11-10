# Matching y confirmación bilateral

QsoMatchingService exige indicativos normalizados recíprocos, usuarios distintos, indicativos con titularidad verificada, misma banda, mismo modo y diferencia absoluta de inicio <= 1800 segundos UTC. La compatibilidad de modos es explícita: igualdad exacta dentro de la lista admitida; FT8/FT4, CW/SSB, etc. son incompatibles. No se infiere país ni se empareja solo por hora.

El servicio construye candidatos de los contactos pending/candidate. Un par solo se selecciona si cada extremo tiene exactamente un candidato. Si llega un tercer contacto ambiguo, una coincidencia candidata anterior pasa a rejected y pierde sus firmas parciales. Los contactos siguen pendientes; no se elige el candidato más cercano para ocultar ambigüedad. Retirar uno puede dejar una nueva coincidencia inequívoca.

Cada candidato incluye explicación de criterios y diferencia temporal. Una coincidencia técnica no confirma que el contacto ocurrió. Cada estación firma su lado; únicamente con ambas firmas el match y sus QSO pasan a confirmed. Se reservan ambos QSO en qso_confirmations, con PK por contacto, para impedir confirmaciones incompatibles.

Todas las mutaciones adquieren la misma fila DomainLock dentro de una transacción antes de leer/reconciliar. El par se guarda en orden ascendente con restricción única. La confirmación repetida es idempotente. Los QSO confirmados no participan en nuevos candidatos.

Rechazar una candidata marca ambos contactos rejected. Disputar una candidata o confirmada marca ambos disputed y revoca sus tarjetas; las reservas de confirmación se conservan como evidencia. Los contactos confirmados no se editan ni retiran silenciosamente. Corregir un contacto exige retirar el pendiente y volver a registrarlo, o disputar el confirmado antes de un nuevo registro.

Cobertura: exacta, 10 y 30 minutos, 30 minutos + 1 segundo, diferencia negativa, otras bandas/modos, no reciprocidad, mismo propietario, idempotencia, ambigüedad tardía, firmas parciales, exclusión de confirmados y procesos concurrentes PostgreSQL.
