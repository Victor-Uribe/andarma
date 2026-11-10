# ANDARMA · Fase 4 · Regresión de acceso administrativo

Identificador: `ANDARMA_FASE4_PRUEBAS_REGRESION_V1`.

## Alcance

- Agrega pruebas automatizadas de registro de rutas, autenticación y clasificación RBAC.
- Comprueba que la API genérica heredada no permita escribir terrenos o desarrollos.
- Verifica que las solicitudes anónimas reciban HTTP 401.
- No modifica la base de datos, archivos anteriores ni el estado de los terrenos.
- No sustituye pruebas con usuarios y datos de ensayo en MySQL.

## Instalación

Desde la raíz del proyecto:

```bash
python3 ANDARMA_Fase4_Pruebas_Regresion_Seguridad.py . --check
python3 ANDARMA_Fase4_Pruebas_Regresion_Seguridad.py .
python3 ANDARMA_Fase4_Pruebas_Regresion_Seguridad.py . --verify
```

## Ejecutar y documentar resultados

```bash
cd backend
php artisan test --filter=AndarmaAccessRoutesRegressionTest
php artisan test
php artisan route:list --path=api
php artisan andarma:preflight --strict
cd ../frontend
npm run build
```

**Importante:** La auditoría estricta puede continuar fallando por los avisos
legales provisionales y el lote M51 / L011 sin comprador. Es el comportamiento
esperado hasta que se resuelvan con evidencia. No se debe desactivar la auditoría.

## Recorrido funcional pendiente en entorno de pruebas

1. Administrador sin `users.*` intenta abrir/crear/editar usuarios: debe recibir 403.
2. Superadministrador consulta la gestión de usuarios y roles: permitido.
3. Crear terreno como disponible; intentar registrarlo directamente como vendido
   sin cliente: debe rechazar la operación (422).
4. Asignar terreno a cliente activo desde su expediente y comprobar que aparece en mapa.
5. Intentar asignar un terreno con contrato o pagos históricos: rechazado.
6. Registrar un pago con periodo y clave UUID, repetir la misma petición y
   comprobar que no duplica el ingreso.
7. Cancelar un pago con motivo y revisar que permanece en auditoría.
8. Comprobar que el usuario sin `clients.view` no obtiene nombres, teléfonos
   ni identificadores del comprador en inventario y mapas.
9. Probar la landing y el formulario de contacto en móvil y escritorio.
10. Validar los documentos legales definitivos antes de publicación.

No usar datos personales reales ni ejecutar pruebas destructivas en producción.
No realizar `migrate:fresh` sobre una base con información comercial.

## Avisos

Las pruebas creadas son pruebas de enrutamiento; no afirman que las operaciones
financieras estén cubiertas funcionalmente. La migración MySQL de mapas usa
`information_schema`, por lo que las pruebas con migraciones deben ejecutarse
en una base MySQL aislada, no contra la BD de trabajo.
