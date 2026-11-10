# ANDARMA · Fase 3C · Revisión y limpieza de permisos heredados

## Hallazgos verificados en la Fase 3B

- El rol **Administrador**, ID **2**, aún tiene cuatro permisos históricos:
  `users.create`, `users.delete`, `users.edit`, `users.view`.
- La Fase 1 ya impide que usuarios no superadministradores ejerzan estos permisos.
- El lote **M51 / L011**, desarrollo **El caballito**, ID **1**, figura como vendido sin comprador.
  No tiene financiamientos ni pagos; tiene un polígono. **Esta fase NO cambia su estado.**
- Aviso de privacidad, términos y política de cookies aún son provisionales.

## Instrucciones

Desde la raíz del repositorio:

```bash
python3 ANDARMA_Fase3C_Limpieza_Permisos_Heredados.py . --check
python3 ANDARMA_Fase3C_Limpieza_Permisos_Heredados.py .
python3 ANDARMA_Fase3C_Limpieza_Permisos_Heredados.py . --verify
```

El script **solo instala** un nuevo comando de Artisan y esta guía.

Por defecto el comando lee el estado de la BD sin modificarlo:

```bash
cd backend
php artisan andarma:preflight:permissions
```

Si y solo si deseas eliminar las cuatro asignaciones heredadas del rol Administrador
— y estás usando la base de datos correcta — ejecuta explícitamente:

```bash
php artisan andarma:preflight:permissions --apply --confirm=QUITAR_4_PERMISOS_ADMIN
```

La escritura está limitada a cuatro filas de `andarma_role_permissions` del rol
con ID 2, nombre `Administrador` y slug `administrator`.
Si el estado real no coincide exactamente, se detiene sin cambiar nada.
No altera usuarios ni sesiones, ni habilita o deshabilita cuentas.

Para comprobar el resultado:

```bash
php artisan andarma:preflight:permissions
php artisan andarma:preflight:details
php artisan andarma:preflight --strict
php artisan test
```

## Incidencias que requieren una decisión externa

**Lote M51/L011:** confirma primero si realmente se vendió.
Si está vendido, registra al cliente correcto en el expediente por el flujo normal,
verificando antes su documentación y condiciones comerciales. Si se marcó vendido
por error, documenta y corrige el estatus mediante una operación autorizada.
NO se debe cambiar automáticamente a `available`.

**Avisos legales:** solicitar los datos de la entidad responsable, el domicilio,
contacto de privacidad, finalidades de tratamiento, mecanismos ARCO y detalles
reales de cookies/terceros, para redactar y aprobar textos definitivos.

**Producción:** aún no autorizada. Requiere revisar pruebas completas y compilar Vue.
