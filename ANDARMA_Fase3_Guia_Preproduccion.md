# ANDARMA · Fase 3 — Auditoría de preproducción

Este paquete **no migra, modifica ni elimina datos**. El script Python solo
agrega un comando Laravel de lectura y una prueba unitaria aislada.

## Aplicación

```bash
python3 ANDARMA_Fase3_Auditoria_Preproduccion.py . --check
python3 ANDARMA_Fase3_Auditoria_Preproduccion.py .
python3 ANDARMA_Fase3_Auditoria_Preproduccion.py . --verify
```

## Pruebas locales (sin datos de producción)

```bash
cd backend
php -l app/Console/Commands/AndarmaPreflightCommand.php
php -l tests/Unit/AndarmaSecurityPreprodTest.php
php artisan list --raw | grep andarma:preflight
php artisan test --filter=AndarmaSecurityPreprodTest
php artisan route:list --path=api
php artisan migrate:status
cd ../frontend
npm run build
```

**IMPORTANTE:** no ejecutar `migrate:fresh`, `db:wipe`, `migrate:rollback`,
`db:seed` ni `RefreshDatabase` sobre una base con información real. Las pruebas
incluidas NO crean tablas, NO ejecutan migraciones y NO modifican la BD.

## Inspección de integridad (solo consultas SELECT)

Usar con una copia de prueba o staging que tenga las migraciones instaladas:

```bash
cd backend
php artisan andarma:preflight
php artisan andarma:preflight --strict --json
```

Para revisar también requisitos de despliegue en el entorno de producción:

```bash
php artisan andarma:preflight --strict --production
```

**`--production` no cambia de entorno:** únicamente comprueba APP_ENV,
APP_DEBUG, APP_KEY y HTTPS de APP_URL. El comando solo consulta la base de datos
que esté configurada en ese entorno. No incluir salidas completas de `.env`
en incidencias ni tickets.

## Qué detecta el comando

- Terrenos vendidos sin comprador o asignados pero disponibles/inactivos.
- Financiamiento con otro comprador o sobre un terreno que no está vendido/apartado.
- Pagos asociados a un comprador diferente del dueño del terreno.
- Pagos no positivos y condiciones financieras incompatibles.
- Pagos aplicados que exceden el precio de venta neto.
- Mensualidades históricas sin periodo y pagos sin un plan (advertencias).
- Polígonos vinculados a planos de un desarrollo distinto.
- Roles antiguos con permisos de usuarios/roles que ya se bloquearon en Fase 1.
- Tablas pendientes y documentos legales públicos provisionales.

Los resultados son conteos y códigos; **no incluyen información de clientes**.
No corrige automáticamente registros porque representan operaciones comerciales
que deben revisarse antes de modificar contratos o movimientos.

## Pendientes obligatorios antes del despliegue

1. Validar con cuentas de superadministrador y de permisos restringidos.
2. Registrar/cancelar pagos, doble clic, recarga y solicitud repetida con el
   mismo `request_key`. Confirmar que los saldos y la bitácora cuadren.
3. Probar febrero, cambio de año y día 29/30/31 en el calendario de cobro.
4. Inspeccionar móvil y escritorio: desarrollos, inventario, mapas y formularios.
5. Reemplazar avisos de privacidad, términos y política de cookies provisionales.
6. Verificar respaldo recuperable de BD y archivos antes de cualquier producción.
7. Ejecutar compilación real y pruebas del backend: el `--verify` Python no las
   sustituye. Evitar despliegues mientras exista un ERROR en el preflight.
