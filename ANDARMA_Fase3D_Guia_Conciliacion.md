# ANDARMA — Fase 3D | Conciliación controlada del lote M51/L011

## Situación verificada

La auditoría local informó que el lote ID 1, **M51 / L011**, del desarrollo
**El caballito**, está `sold` sin `client_id`; hay **un polígono**, **cero
financiamientos** y **cero pagos registrados**. Los permisos heredados del rol
Administrador ya fueron eliminados en la Fase 3C.

No sabemos si la venta fue real o si se capturó un estado incorrecto. **No se
corrige automáticamente** ni se asume que un terreno está disponible.

## Instalación desde la raíz de ANDARMA

```bash
python3 ANDARMA_Fase3D_Conciliacion_Controlada_Lote.py . --check
python3 ANDARMA_Fase3D_Conciliacion_Controlada_Lote.py .
python3 ANDARMA_Fase3D_Conciliacion_Controlada_Lote.py . --verify
```

El script instala **un comando Artisan** y esta guía, con un manifiesto local.
No accede a la base de datos, no ejecuta migraciones y no modifica archivos
existentes. Es idempotente y se detiene si el archivo ya existe con contenido
diferente.

## Inspección de solo lectura (recomendado)

```bash
cd backend
php artisan andarma:preflight:reconcile-lot --lot=1
```

## Si se confirmó documentalmente que NO se vendió

**Solo si confirmaste con ANDARMA que fue un error de captura**, y después de
verificar la base de datos local y realizar un respaldo:

```bash
php artisan andarma:preflight:reconcile-lot --lot=1 \
  --apply --action=available \
  --reason='Corrección autorizada: la venta no ocurrió' \
  --confirm=CORREGIR_LOTE_1_DISPONIBLE
```

Esta operación conserva el polígono y cambia únicamente `status` a `available`.

## Si se confirmó documentalmente que SÍ se vendió

Busca primero el ID correcto del comprador desde **Clientes** en el panel.
Si ese cliente ya existe y está **activo**, anota su ID y verifica con ANDARMA
que es el propietario correcto. Sin crear nuevos clientes:

```bash
php artisan andarma:preflight:reconcile-lot --lot=1 \
  --apply --action=assign --client=ID_REAL \
  --reason='Asignación autorizada conforme al expediente' \
  --confirm=ASIGNAR_LOTE_1_CLIENTE_ID_REAL
```

Reemplaza `ID_REAL` en ambos lugares por el mismo entero positivo (por ejemplo:
si el ID real fuera 8, usa `--client=8` y
`--confirm=ASIGNAR_LOTE_1_CLIENTE_8`). **No uses un ID supuesto.**

La acción mantiene `status=sold`, modifica únicamente `client_id` y escribe
una entrada de auditoría. El comando **no registra contratos ni pagos**.

## Validaciones y prohibiciones de seguridad

- Comprobación exacta de lote ID 1, M51, L011 y nombre de desarrollo.
- Se permite escribir únicamente si sigue `sold` sin comprador, con cero
  financiamientos, cero pagos (incluidos cancelados) y un polígono.
- Una escritura requiere `--apply`, `--action`, motivo de 15 a 300 caracteres
  y texto exacto en `--confirm`.
- La asignación solo admite un cliente existente y activo.
- Se bloquea cualquier escritura con `APP_ENV=production`.
- La actualización es transaccional con bloqueo de fila, verificación interna
  y registro de auditoría en `andarma_client_audit`. Si falla algo se revierte.
- No se toca la geometría, el mapa, los planes de pago, los contratos ni los
  registros contables; tampoco se modifica Git ni se despliega al servidor.

## Después de conciliar

```bash
php artisan andarma:preflight:details
php artisan andarma:preflight --strict
php artisan test
cd ../frontend && npm run build
```

La auditoría puede seguir fallando por **LEGAL_PLACEHOLDERS**. El aviso de
privacidad, los términos y la política de cookies siguen pendientes de datos
reales y aprobación del responsable jurídico. No sustituirlos por texto ficticio.

## Pendientes antes de producción

1. Conciliar documentalmente el lote M51/L011 antes de cualquier corrección.
2. Aprobar y publicar documentos legales reales de ANDARMA.
3. Ejecutar pruebas funcionales de API con roles y pagos en entorno de pruebas.
4. Preparar revisión del despliegue y copias de seguridad de HestiaCP sin
   modificar las aplicaciones ya instaladas en ese VPS.
