# ANDARMA · Fase 3B · Detalle de incidencias

Este módulo se instala con el script `ANDARMA_Fase3B_Detalle_Incidencias.py`.

## Uso

```bash
cd backend
php artisan andarma:preflight:details
php artisan andarma:preflight:details --json
```

**Solo lectura:** NO realiza `UPDATE`, `DELETE`, migraciones ni reparaciones automáticas.
No muestra nombres, teléfonos, correos o importes de compradores.

## Interpretación

- **Lote vendido sin comprador:** revisar en ANDARMA si existe venta real, reserva, contrato,
  pagos, financiamiento o dato histórico. No cambiar a disponible sin verificar.
- **Permisos heredados:** son filas de `andarma_role_permissions`, NO número de roles.
  Comparar con política de superadministrador exclusivo, ajustar después de aprobación.
- **Documentos legales:** la ausencia de marcadores no confirma validez jurídica; se
  necesita contenido aprobado antes de publicar.

La auditoría anterior `php artisan andarma:preflight --strict` sigue disponible.
