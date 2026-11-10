# ANDARMA · Fase 5 · Pruebas funcionales aisladas

Versión: `ANDARMA_FASE5_PRUEBAS_FUNCIONALES_V1`.

## Qué se instala

1. `backend/tests/Feature/AndarmaBusinessFlowsIsolatedTest.php`: 8 pruebas de endpoints reales con un esquema mínimo de prueba.
2. Esta guía de ejecución, alcance y precauciones.

Las pruebas utilizan **solo SQLite :memory:**, mediante una conexión creada especialmente,
`andarma_f5_isolated`. No usan la conexión MySQL de ANDARMA, no ejecutan migraciones,
no requieren `migrate:fresh` y no modifican los lotes actuales.

**Requisitos:** extensión PHP `pdo_sqlite` instalada. Si no está presente las pruebas
quedarán marcadas como `SKIPPED`; ese estado NO equivale a pasar las pruebas.

## Aplicar (desde la raíz del proyecto)

```bash
python3 ANDARMA_Fase5_Pruebas_Funcionales_Aisladas.py . --check
python3 ANDARMA_Fase5_Pruebas_Funcionales_Aisladas.py .
python3 ANDARMA_Fase5_Pruebas_Funcionales_Aisladas.py . --verify
```

## Ejecutar (dentro de backend)

```bash
php -m | grep -i pdo_sqlite
php artisan test --filter=AndarmaBusinessFlowsIsolatedTest
php artisan test
```

## Casos evaluados

- Rechazar creación de lote `sold` sin comprador.
- Rechazar transformación de disponible a vendido sin comprador.
- Rechazar asignación a cliente inactivo.
- Permitir venta con comprador activo y rechazar duplicación de asignación.
- Impedir liberación y degradación de venta con financiamiento.
- Rechazar importes con más de dos decimales.
- Preservar idempotencia de pago, cancelar sin borrar y bloquear reintentos con UUID cancelada.
- Ocultar identidad y teléfono de comprador a un rol que solo consulta inventario.

## Límites

- Aunque cubren HTTP y consulta/actualización en SQLite, estas pruebas NO demuestran
  seguridad bajo concurrencia MySQL, bloqueos reales de InnoDB ni equivalencia total
  entre la definición de esquema mínimo y migraciones reales.
- Se recomienda QA en una base MySQL exclusiva de pruebas, nunca en la base de
  clientes. Las pruebas de concurrencia y autorización por usuario son un paso aparte.
- La auditoría `php artisan andarma:preflight --strict` continuará fallando mientras
  el lote M51 / L011 siga vendido sin comprador y los documentos legales sean provisionales.
  No modificar esos registros sin autorización y evidencia comercial.
