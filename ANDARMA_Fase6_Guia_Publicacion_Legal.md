# ANDARMA · Fase 6 · Documentos legales controlados

Esta fase **no redacta textos jurídicos ni certifica su validez**. Prepara documentos de revisión
para que la entidad responsable de ANDARMA aporte y apruebe los textos definitivos.

## Secuencia

Desde la raíz de ANDARMA:

```bash
python3 ANDARMA_Fase6_Publicacion_Legal_Controlada.py . --init
```

Edite los tres archivos `.txt` dentro de `ANDARMA_Fase6_Documentos_Revision/` con el **texto
íntegro y ya aprobado** de cada documento. Deben ser archivos UTF-8 de texto plano,
sin etiquetas HTML, sin comentarios de trabajo y sin datos inventados.

La revisión legal debería confirmar al menos lo siguiente según la operación real:

- **Privacidad:** identidad y domicilio del responsable, datos y finalidades reales, derechos
  aplicables, contacto, transferencias y cambios al aviso.
- **Términos:** identificación comercial y condiciones reales del sitio; diferencias entre
  información orientativa y términos contractuales; contacto y modificaciones.
- **Cookies:** inventario efectivo de cookies, almacenamiento local y servicios externos;
  finalidades, duración, controles y consentimiento cuando corresponda.

Estas notas son una lista de recopilación, **no sustituyen asesoría jurídica**.

Comprobar sin modificar el frontend:

```bash
python3 ANDARMA_Fase6_Publicacion_Legal_Controlada.py . --check
```

**Solo cuando exista aprobación documentada y respaldo del proyecto:**

```bash
python3 ANDARMA_Fase6_Publicacion_Legal_Controlada.py . --apply --confirm=DOCUMENTOS_LEGALES_APROBADOS
python3 ANDARMA_Fase6_Publicacion_Legal_Controlada.py . --verify
cd frontend && npm run build
cd ../backend && php artisan andarma:preflight --strict
```

El script convierte el texto plano en párrafos HTML **escapados**, para que nombres y
caracteres especiales no se interpreten como código HTML o JavaScript. Cambia únicamente
el objeto `legal` del archivo público `frontend/public/andarma-legacy.js`; mantiene los
botones, diálogos, eventos y diseño existentes. El instalador guarda respaldo del JS
original en una carpeta `.andarma_respaldo_fase6_legal_*` y nunca crea commits.

## Protección de datos e incidencias

- No se modifica la base de datos ni se cambia el estado de los terrenos.
- `--init` no sobrescribe documentos existentes.
- `--check` valida estructura y hace una simulación; **no** verifica suficiencia legal.
- `--apply` requiere un acto consciente del operador y no acredita por sí mismo
  aprobación por el cliente o un profesional jurídico.
- Si falta un documento, tiene marcadores provisionales, o el JS cambió de estructura,
  **se detiene sin escribir**.
- La auditoría de preproducción seguirá marcando `SOLD_WITHOUT_BUYER` hasta que
  se documente y concilie el lote M51 / L011; no hay que forzar el resultado verde.
