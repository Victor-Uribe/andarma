# ANDARMA V24 — Paso 1: HTML V23 a Vue 3 + Laravel API

Esta entrega contiene **código Vue 3 real**, compilado por Vite, además de un módulo Laravel/MySQL reutilizable. Es una migración inicial, no el ERP inmobiliario completo.

## Contenido

- `frontend/src/views/HomeView.vue`: página V23 pasada a un componente Vue (HTML como template Vue, NO iframe ni v-html).
- `frontend/src/assets/andarma.css`: CSS original completo y responsive.
- `frontend/public/andarma-legacy.js`: animaciones V23 migradas como compatibilidad temporal, activadas después de montar Vue.
- `frontend/src/views/AndarmaContentAdmin.vue`: editor CMS para integrar en la ruta administrativa autenticada.
- `backend/`: modelo, controlador y migración Laravel de contenidos del sitio, más snippet de rutas.

## Ejecutar el frontend

```bash
cd frontend
npm install
npm run dev
```

Abrir http://localhost:5173. Para API usar Laravel en `http://127.0.0.1:8000` y el proxy configurado en Vite.

## Integrar en Laravel existente (inmobiliaria-ejemplo)

1. En una COPIA LOCAL del proyecto, transferir los archivos `backend/app/...` y `backend/database/...` a su backend Laravel.
2. Añadir únicamente las rutas indicadas en `backend/routes_api_snippet.php` dentro de `backend/routes/api.php` existente; no reemplazar sus rutas.
3. Ejecutar `php artisan migrate` con `.env` configurado en MySQL.
4. Montar `AndarmaContentAdmin.vue` bajo una ruta protegida de la aplicación administrativa existente y pasarle el JWT existente y `apiBase="/api"`.
5. Servir frontend y backend bajo el mismo dominio, o configurar `VITE_API_BASE` y CORS de forma explícita.

## Qué funciona en este paso

- Home convertido a Vue con el diseño de V23, sus secciones y efectos conservados con puente JS.
- Carga GET de textos publicados vía `/api/public/site-content`, con fallback a textos originales.
- Laravel: lectura de contenidos, edición por usuarios admin, tabla MySQL y valores iniciales.

## Lo que falta y NO se afirma implementado

- Reemplazar cada animación heredada por composables/componentes Vue con limpieza correcta de listeners y timers.
- Permitir cambiar imágenes, sliders, videos y orden de secciones desde admin.
- Integrar lotes/desarrollos, mapas interactivos, compradores, expedientes y pagos del repositorio inmobiliario.
- Configurar login y rutas de administración a nivel de aplicación completa.
- Probar visualmente en navegador y ajustar posibles diferencias de CSS por compilación Vue.
- Verificar que el contenido legal y los testimonios sean aprobados antes de publicar.

**No se ha modificado ningún repositorio GitHub ni se ha realizado despliegue.**
# andarma
