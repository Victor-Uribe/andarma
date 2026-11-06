<script setup>
import { ref, computed, watch, nextTick } from 'vue'
const props = defineProps({ development: { type: Object, required: true } })
const API = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
const plans = ref([]), plan = ref(null), polygons = ref([]), loading = ref(true), error = ref('')
const selected = ref(null), query = ref(''), zoom = ref(100), viewport = ref(null)
const dragging = ref(false), dragOccurred = ref(false), dragState = { pointerId: null, x: 0, y: 0, left: 0, top: 0, moved: false }
const mapIsZoomed = computed(() => zoom.value > 100)
const statusNames = { available: 'Disponible', reserved: 'Apartado', sold: 'Vendido' }
const statusColors = { available: '#1e9b70', reserved: '#d9a33a', sold: '#9ca7b5' }
const availableCount = computed(() => polygons.value.filter(p => p.status === 'available').length)
const queryResults = computed(() => { const q = query.value.trim().toLocaleLowerCase('es-MX'); return q ? polygons.value.filter(p => (`${p.block || ''} ${p.code || ''}`).toLocaleLowerCase('es-MX').includes(q)).slice(0, 12) : [] })
const money = v => v === null || v === undefined || v === '' ? 'Precio por consultar' : new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(Number(v))
const points = p => (p.points || []).map(v => `${v.x},${v.y}`).join(' ')
const safeMessage = computed(() => `Hola, me gustaría recibir información sobre ${props.development.name}${selected.value ? `, terreno ${selected.value.code}${selected.value.block ? ' de la manzana ' + selected.value.block : ''}` : ''}.`)
const waUrl = computed(() => `https://api.whatsapp.com/send?text=${encodeURIComponent(safeMessage.value)}`)
async function get(url) { const res = await fetch(`${API}${url}`, { headers: { Accept: 'application/json' } }); if (!res.ok) throw new Error(res.status === 404 ? 'Plano no disponible.' : 'No fue posible cargar el plano.'); return res.json() }
async function loadPlan(id) { loading.value = true; error.value = ''; selected.value = null; zoom.value = 100; dragging.value = false; dragOccurred.value = false; try { const data = await get(`/public/maps/${encodeURIComponent(id)}`); plan.value = data.data; polygons.value = Array.isArray(data.polygons) ? data.polygons : [] } catch (e) { error.value = e.message; plan.value = null; polygons.value = [] } finally { loading.value = false } }
async function load() { loading.value = true; error.value = ''; plans.value = []; plan.value = null; selected.value = null; polygons.value = []; query.value = ''; try { const data = await get(`/public/maps?development_id=${encodeURIComponent(props.development.id)}`); plans.value = Array.isArray(data.data) ? data.data : []; if (plans.value.length) await loadPlan(plans.value[0].id) } catch (e) { error.value = e.message } finally { loading.value = false } }
watch(() => props.development.id, load, { immediate: true })
function choose(p) {
  if (dragging.value || dragOccurred.value) return
  selected.value = p
}
function startDrag(e) {
  if (zoom.value <= 100 || !viewport.value || e.button !== 0) return
  // No interceptar los controles ni un gesto de teclado.
  dragState.pointerId = e.pointerId; dragState.x = e.clientX; dragState.y = e.clientY
  dragState.left = viewport.value.scrollLeft; dragState.top = viewport.value.scrollTop
  dragState.moved = false; dragOccurred.value = false; dragging.value = true
  // No capturar el puntero aquí: así cada clic puede llegar a su polígono.
}
function moveDrag(e) {
  if (!dragging.value || e.pointerId !== dragState.pointerId || !viewport.value) return
  const dx = e.clientX - dragState.x, dy = e.clientY - dragState.y
  if (Math.hypot(dx, dy) > 5) { dragState.moved = true; dragOccurred.value = true }
  if (!dragState.moved) return
  viewport.value.scrollLeft = dragState.left - dx
  viewport.value.scrollTop = dragState.top - dy
}
function stopDrag(e) {
  if (!dragging.value || e.pointerId !== dragState.pointerId) return
  const moved = dragState.moved
  dragging.value = false; dragState.pointerId = null
  // Sin captura del puntero: los clics siguen alcanzando otros terrenos.
  if (moved) {
    // El click sintético posterior al pointerup no debe abrir otro terreno.
    dragOccurred.value = true
    window.setTimeout(() => { dragOccurred.value = false }, 140)
  } else dragOccurred.value = false
}
function changeZoom(amount) {
  const node = viewport.value
  const old = zoom.value
  const next = Math.max(100, Math.min(250, old + amount))
  if (next === old) return
  const ratio = next / old
  const cx = node ? node.scrollLeft + node.clientWidth / 2 : 0
  const cy = node ? node.scrollTop + node.clientHeight / 2 : 0
  zoom.value = next
  nextTick(() => { if (!node) return; node.scrollLeft = cx * ratio - node.clientWidth / 2; node.scrollTop = cy * ratio - node.clientHeight / 2 })
}
function resetZoom() { zoom.value = 100; nextTick(() => { if (viewport.value) { viewport.value.scrollTop = 0; viewport.value.scrollLeft = 0 } }) }
function search() { if (queryResults.value.length === 1) { choose(queryResults.value[0]); nextTick(() => document.getElementById('adp-lot-info')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })) } }
</script>
<template>
  <section class="adp-public-map" aria-labelledby="adp-public-map-title">
    <div class="adp-public-map__heading">
      <div><span class="adp-kicker">EXPLORA EL PLANO</span>
        <h2 id="adp-public-map-title">Encuentra tu <em>terreno ideal.</em></h2>
        <p>Haz clic en un lote coloreado para ver sus datos. Puedes cambiar de terreno en cualquier momento; usa el zoom
          para explorar el plano.</p>
      </div>
      <div v-if="plan" class="adp-map-availability"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>
        {{ availableCount }} {{ availableCount === 1 ? 'terreno disponible' : 'terrenos disponibles' }} en este plano</div>
    </div>
    <div v-if="error" class="adp-map-message" role="alert">{{ error }} <button type="button"
        @click="load">Reintentar</button></div>
    <div v-else-if="loading" class="adp-map-message" role="status">Cargando mapa interactivo…</div>
    <div v-else-if="!plans.length" class="adp-map-message">Este desarrollo aún no tiene un plano público activo. <a
        href="/#contacto">Solicitar información</a></div>
    <template v-else>
      <div v-if="plans.length > 1" class="adp-map-plans" aria-label="Seleccionar etapa o plano"><button v-for="p in plans"
          :key="p.id" type="button" :class="{ 'is-current': plan?.id === p.id }" @click="loadPlan(p.id)">{{ p.title }}</button>
      </div>
      <div class="adp-map-toolbar">
        <label class="adp-map-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input
            v-model="query" placeholder="Buscar terreno o manzana (ej. 0181)"
            aria-label="Buscar terreno por número o manzana" @keydown.enter.prevent="search" /></label>

      </div>
      <div v-if="query.trim()" class="adp-map-results" aria-live="polite"><span v-if="!queryResults.length">No hay lotes
          ubicados que coincidan con la búsqueda.</span><button v-for="lot in queryResults" :key="lot.lot_id"
          type="button" @click="choose(lot)">{{ lot.block ? 'Manzana ' + lot.block + ' · ' : '' }}Lote {{ lot.code }}
          <small>{{ statusNames[lot.status] }}</small></button></div>
      <div class="adp-map-layout">
        <div class="adp-map-picture">
          <div class="adp2d-map-stage">
            <div class="adp-map-viewport" ref="viewport"
              :class="{ 'adp2b-can-drag': mapIsZoomed, 'adp2b-is-dragging': dragging }" @pointerdown="startDrag"
              @pointermove="moveDrag" @pointerup="stopDrag" @pointercancel="stopDrag" @lostpointercapture="stopDrag">
              <div class="adp-map-canvas" :style="{ width: zoom + '%' }">
                <img :src="plan.image_url" :alt="`Plano del desarrollo ${development.name}: ${plan.title}`" />
                <svg viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-label="Terrenos ubicados dentro del plano">
                  <!-- ANDARMA_FASE2D_FIX_POLIGONOS_VUE -->
                  <template v-for="lot in polygons" :key="lot.lot_id">
                    <polygon class="adp2d-lot-depth" :class="{ 'is-selected': selected?.lot_id === lot.lot_id }"
                      :points="points(lot)" :fill="statusColors[lot.status]"
                      :fill-opacity="selected?.lot_id === lot.lot_id ? .74 : .08" stroke="none" transform="translate(0 7)"
                      pointer-events="none" aria-hidden="true" />
                    <polygon class="adp2d-lot-face" :class="{ 'is-selected': selected?.lot_id === lot.lot_id }"
                      :points="points(lot)" :fill="statusColors[lot.status]"
                      :fill-opacity="selected && selected.lot_id === lot.lot_id ? 0.68 : 0.33"
                      :stroke="selected?.lot_id === lot.lot_id ? '#0066e0' : '#ffffffc9'"
                      :stroke-width="selected?.lot_id === lot.lot_id ? 5 : 2" vector-effect="non-scaling-stroke" tabindex="0"
                      role="button" :aria-label="`Lote ${lot.code}, ${statusNames[lot.status]}`"
                      @pointerdown.stop="startDrag($event)" @click.stop="choose(lot)"
                      @keydown.enter.prevent="choose(lot)" @keydown.space.prevent="choose(lot)" />
                  </template>
                </svg>
              </div>
            </div>
            <div class="adp-map-zoom" role="group" aria-label="Zoom del mapa"><button type="button" aria-label="Alejar"
                :disabled="zoom <= 100" @click="changeZoom(-25)"><i class="fa-solid fa-minus"
                  aria-hidden="true"></i></button><output>{{ zoom }}%</output><button type="button" aria-label="Acercar"
                :disabled="zoom >= 250" @click="changeZoom(25)"><i class="fa-solid fa-plus"
                  aria-hidden="true"></i></button><button type="button" class="adp2b-zoom-reset"
                aria-label="Restablecer zoom" :disabled="zoom === 100" @click="resetZoom"><i
                  class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button></div>
          </div>
          <div v-if="mapIsZoomed" class="adp2b-drag-help"><i class="fa-solid fa-hand" aria-hidden="true"></i> Arrastra
            el plano
            para explorar todas las zonas.</div>
          <div class="adp-map-legend"><span><b style="background:#1e9b70"></b>Disponible</span><span><b
                style="background:#d9a33a"></b>Apartado</span><span><b style="background:#9ca7b5"></b>Vendido</span>
          </div>
        </div>
        <aside class="adp-map-sidebar" id="adp-lot-info" aria-live="polite">
          <template v-if="selected">
            <div class="adp2c-selected-head"><span class="adp-kicker">TERRENO SELECCIONADO</span><button type="button"
                class="adp2c-clear" aria-label="Cerrar ficha del terreno" @click="selected = null"><i
                  class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
            <h3>Lote {{ selected.code }}</h3><span class="adp-map-state"
              :class="'is-' + selected.status">{{ statusNames[selected.status] }}</span>
            <div class="adp2d-property-intro"><span><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i>
                Ubicación dentro del plano</span>
              <p>Este es el terreno que seleccionaste. Comprueba sus características y solicita asesoría directamente
                por WhatsApp.</p>
            </div>
            <dl class="adp2d-data">
              <div>
                <dt><i class="fa-solid fa-hashtag" aria-hidden="true"></i> Referencia</dt>
                <dd>{{ selected.code }}</dd>
              </div>
              <div v-if="selected.block">
                <dt><i class="fa-solid fa-layer-group" aria-hidden="true"></i> Manzana</dt>
                <dd>{{ selected.block }}</dd>
              </div>
              <div>
                <dt><i class="fa-solid fa-ruler-combined" aria-hidden="true"></i> Superficie</dt>
                <dd>{{ selected.area_m2 ?? 'Por consultar' }}{{ selected.area_m2 !== null ? ' m²' : '' }}</dd>
              </div>
              <div v-if="selected.status === 'available'">
                <dt><i class="fa-solid fa-tag" aria-hidden="true"></i> Precio</dt>
                <dd>{{ money(selected.price) }}</dd>
              </div>
            </dl>
            <div class="adp2d-status-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Información
                comercial sujeta a confirmación. La selección del plano no constituye una reserva.</span></div>
            <a class="adp-map-whatsapp adp2d-wa-main" :href="waUrl" target="_blank" rel="noopener noreferrer"><i
                class="fa-brands fa-whatsapp" aria-hidden="true"></i> Consultar este terreno por WhatsApp <i
                class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
            <p v-if="selected.status !== 'available'" class="adp-map-help">Este terreno no aparece como disponible. Puedes
              consultar otras opciones con un asesor.</p>
          </template>
          <template v-else><span class="adp-kicker">INFORMACIÓN DEL PROYECTO</span>
            <h3>{{ development.name }}</h3>
            <p>{{ development.description || 'Explora el plano y elige el terreno que mejor se adapte a tu proyecto.' }}</p>
            <p v-if="development.location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              {{ development.location }}</p>
            <div class="adp-map-sidebar__hint"><i class="fa-solid fa-hand-pointer" aria-hidden="true"></i> Toca un
              terreno coloreado para conocer sus detalles.</div><a class="adp-map-whatsapp" :href="waUrl"
              target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Hablar
              con un asesor</a>
          </template>
        </aside>
      </div>
    </template>
  </section>
</template>
