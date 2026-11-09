<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
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

// ANDARMA_FASE3L_MAPA_PREMIUM: filtros y valores comerciales, solo API publica.
const statusFilter = ref('all')
const publicLotPrices = ref({})
const publicWhatsapp = ref('')
const currentPrice = computed(() => {
  if (!selected.value || selected.value.status !== 'available') return null
  const id = String(selected.value.lot_id)
  const raw = selected.value.price ?? publicLotPrices.value[id] ?? null
  if (raw === null || raw === undefined || String(raw).trim() === '') return null
  const normalized = String(raw).replace(/[^0-9.,-]/g, '').replace(/,/g, '')
  const n = Number(normalized)
  return Number.isFinite(n) && n >= 0 ? n : null
})
const displayPrice = computed(() => currentPrice.value === null
  ? 'Precio por consultar'
  : new Intl.NumberFormat('es-MX', {style:'currency',currency:'MXN',maximumFractionDigits:0}).format(currentPrice.value) + ' MXN')
const visibleStatusCount = computed(() => statusFilter.value === 'all'
  ? polygons.value.length : polygons.value.filter(p => p.status === statusFilter.value).length)
function setStatusFilter(value) {
  statusFilter.value = value
  if (selected.value && value !== 'all' && selected.value.status !== value) selected.value = null
}
function isVisibleLot(lot) { return statusFilter.value === 'all' || lot.status === statusFilter.value }
const directWhatsapp = computed(() => {
  if (!publicWhatsapp.value) return '/#contacto'
  return `https://wa.me/${publicWhatsapp.value}?text=${encodeURIComponent(safeMessage.value)}`
})
async function loadPublicPriceData() {
  if (!props.development?.id) return
  try {
    const r = await fetch(`${API}/public/lots?development_id=${encodeURIComponent(props.development.id)}`,
      {headers:{Accept:'application/json'}})
    if (!r.ok) return
    const result = await r.json()
    const data = Array.isArray(result.data) ? result.data : []
    // La API publica omite lotes vendidos y sus importes por privacidad.
    const next = {}
    data.forEach(lot => {
      if (lot.status === 'available' && lot.id !== undefined && lot.price !== null && lot.price !== '') {
        next[String(lot.id)] = lot.price
      }
    })
    publicLotPrices.value = next
  } catch (_) { /* si la consulta complementaria falla se conserva el precio del mapa */ }
}
onMounted(async () => {
  try {
    const r = await fetch(`${API}/public/settings`, {headers:{Accept:'application/json'}})
    if (!r.ok) return
    const body = await r.json()
    const settings = body.data || body.settings || body
    const raw = String(settings.whatsapp || '').replace(/\D/g, '')
    if (raw.length >= 10 && raw.length <= 15) publicWhatsapp.value = raw
  } catch (_) { /* no inventar un destinatario */ }
})
watch(() => props.development?.id, () => {
  publicLotPrices.value = {}
  statusFilter.value = 'all'
  loadPublicPriceData()
}, {immediate:true})

import '../../assets/andarma-fase3l-mapa-premium.css'
import '../../assets/andarma-fase3r-mapa-editorial.css' // ANDARMA_FASE3R_MAPA_EDITORIAL
import '../../assets/andarma-fase3s-proyecto-bajo-mapa.css' // ANDARMA_FASE3S_PROYECTO_HOME
import '../../assets/andarma-fase3t-filtros-sticky.css' // ANDARMA_FASE3T_FILTROS_STICKY
</script>
<template>
  <!-- ANDARMA_FASE3L_MAPA_PREMIUM: nuevo layout comercial, plano autentico -->
  <section class="adp-public-map ad3l-map" aria-labelledby="adp-public-map-title">
    <!-- ANDARMA_FASE3P_IDENTIDAD_HOME: misma retícula editorial del Home -->
    <div class="ad3l-intro">
      <div class="ad3l-intro__eyebrow">
        <span class="ad3p-brand"><b aria-hidden="true">/</b> ANDARMA <i>/</i> ARQUITECTURA PARA VIVIR</span>
        <span class="ad3p-descriptor">DESARROLLO INMOBILIARIO · NATURALEZA · PATRIMONIO</span>
      </div>
      <div class="ad3l-intro__rule" aria-hidden="true"></div>
      <div class="ad3l-intro__grid">
        <div class="ad3p-headline">
          <h2 id="adp-public-map-title">Explora el desarrollo,<br /><em>elige tu terreno.</em></h2>
        </div>
        <p>Conoce el plano de {{ development.name }}, explora los terrenos disponibles y consulta sus características para dar el siguiente paso hacia tu patrimonio.</p>
      </div>
    </div>
    <div v-if="error" class="adp-map-message" role="alert">{{ error }} <button type="button" @click="load">Reintentar</button></div>
    <div v-else-if="loading" class="adp-map-message" role="status">Preparando el plano interactivo…</div>
    <div v-else-if="!plans.length" class="adp-map-message">Este desarrollo todavía no tiene un plano público activo. <a href="/#contacto">Solicitar información</a></div>
    <template v-else>
      <div v-if="plans.length > 1" class="adp-map-plans" aria-label="Seleccionar etapa o plano">
        <button v-for="p in plans" :key="p.id" type="button" :class="{'is-current':plan?.id===p.id}" @click="loadPlan(p.id)">{{p.title}}</button>
      </div>
      <div class="ad3l-grid">
        
        <div class="ad3l-center">
          <div class="ad3l-toolbar">
            <label class="adp-map-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input v-model="query" placeholder="Buscar lote o manzana" aria-label="Buscar lote o manzana" @keydown.enter.prevent="search" /></label>
            <span class="ad3l-map-label">{{plan?.title || 'Plano del desarrollo'}}</span>
          
            <div class="ad3l-filters" aria-label="Filtrar por estado">
            <button type="button" :class="{'is-active':statusFilter==='all'}" :aria-pressed="statusFilter==='all'" @click="setStatusFilter('all')">Todos</button>
            <button type="button" :class="{'is-active':statusFilter==='available'}" :aria-pressed="statusFilter==='available'" @click="setStatusFilter('available')"><b class="ad3l-dot is-available"></b> Disponibles</button>
            <button type="button" :class="{'is-active':statusFilter==='reserved'}" :aria-pressed="statusFilter==='reserved'" @click="setStatusFilter('reserved')"><b class="ad3l-dot is-reserved"></b> Apartados</button>
            <button type="button" :class="{'is-active':statusFilter==='sold'}" :aria-pressed="statusFilter==='sold'" @click="setStatusFilter('sold')"><b class="ad3l-dot is-sold"></b> Vendidos</button>
          </div>
          </div>
          <div v-if="query.trim()" class="adp-map-results" aria-live="polite">
            <span v-if="!queryResults.length">No hay terrenos que coincidan con tu búsqueda.</span>
            <button v-for="lot in queryResults" :key="lot.lot_id" type="button" @click="setStatusFilter('all'); choose(lot)">Lote {{lot.code}}<small>{{statusNames[lot.status]}}</small></button>
          </div>
          <div class="adp-map-picture">
            <div class="adp2d-map-stage">
              <div class="adp-map-viewport" ref="viewport" :class="{'adp2b-can-drag':mapIsZoomed,'adp2b-is-dragging':dragging}" @pointerdown="startDrag" @pointermove="moveDrag" @pointerup="stopDrag" @pointercancel="stopDrag" @lostpointercapture="stopDrag">
                <div class="adp-map-canvas" :style="{width:zoom+'%'}">
                  <img :src="plan.image_url" :alt="`Plano del desarrollo ${development.name}: ${plan.title}`" />
                  <svg viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-label="Terrenos ubicados dentro del plano">
                    <template v-for="lot in polygons" :key="lot.lot_id">
                      <polygon v-if="isVisibleLot(lot)" class="adp2d-lot-depth" :class="{'is-selected':selected?.lot_id===lot.lot_id}" :points="points(lot)" :fill="statusColors[lot.status]" :fill-opacity="selected?.lot_id === lot.lot_id ? .74 : .08" stroke="none" transform="translate(0 7)" pointer-events="none" aria-hidden="true" />
                      <polygon v-if="isVisibleLot(lot)" class="adp2d-lot-face" :class="{'is-selected':selected?.lot_id===lot.lot_id}" :points="points(lot)" :fill="statusColors[lot.status]" :fill-opacity="selected?.lot_id === lot.lot_id ? .68 : .33" :stroke="selected?.lot_id===lot.lot_id?'#0066e0':'#ffffffc9'" :stroke-width="selected?.lot_id===lot.lot_id?5:2" vector-effect="non-scaling-stroke" tabindex="0" role="button" :aria-label="`Lote ${lot.code}, ${statusNames[lot.status]}`" @pointerdown.stop="startDrag($event)" @click.stop="choose(lot)" @keydown.enter.prevent="choose(lot)" @keydown.space.prevent="choose(lot)" />
                    </template>
                  </svg>
                </div>
              </div>
              <div class="adp-map-zoom" role="group" aria-label="Zoom del mapa">
                <button type="button" aria-label="Alejar" :disabled="zoom<=100" @click="changeZoom(-25)"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                <output>{{zoom}}%</output>
                <button type="button" aria-label="Acercar" :disabled="zoom>=250" @click="changeZoom(25)"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
                <button type="button" class="adp2b-zoom-reset" aria-label="Restablecer zoom" :disabled="zoom===100" @click="resetZoom"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>
              </div>
            </div>
            <div v-if="mapIsZoomed" class="adp2b-drag-help"><i class="fa-solid fa-hand" aria-hidden="true"></i> Arrastra para explorar el plano.</div>
            <div class="adp-map-legend"><span><b style="background:#1e9b70"></b>Disponible</span><span><b style="background:#d9a33a"></b>Apartado</span><span><b style="background:#9ca7b5"></b>Vendido</span><small>Selecciona un terreno coloreado</small></div>
          </div>
        </div>
        <aside class="adp-map-sidebar ad3l-sidebar" id="adp-lot-info" aria-live="polite">
          <template v-if="selected">
            <div class="ad3l-sidebar__head"><div><span class="ad3l-eyebrow">TERRENO SELECCIONADO</span><h3>Lote <em>{{selected.code}}</em></h3></div><button type="button" class="adp2c-clear" aria-label="Cerrar ficha del terreno" @click="selected=null"><i class="fa-solid fa-xmark"></i></button></div>
            <span class="adp-map-state" :class="'is-'+selected.status"><i class="fa-solid fa-circle"></i> {{statusNames[selected.status]}}</span>
            <div class="ad3l-sidebar__line"></div>
            <dl class="adp2d-data ad3l-details">
              <div><dt><i class="fa-solid fa-hashtag"></i> Referencia</dt><dd>{{selected.code}}</dd></div>
              <div v-if="selected.block"><dt><i class="fa-solid fa-layer-group"></i> Manzana</dt><dd>{{selected.block}}</dd></div>
              <div><dt><i class="fa-solid fa-ruler-combined"></i> Superficie</dt><dd>{{selected.area_m2 ?? 'Por consultar'}}{{selected.area_m2!==null && selected.area_m2!==undefined?' m²':''}}</dd></div>
            </dl>
            <div v-if="selected.status==='available'" class="ad3l-price"><span>Precio</span><strong>{{displayPrice}}</strong><small v-if="currentPrice===null">Consulta el importe con un asesor.</small></div>
            <p v-else class="ad3l-price__notice">Este terreno no está disponible para venta. Consulta otras opciones.</p>
            <a class="adp-map-whatsapp" :href="directWhatsapp" :target="publicWhatsapp?'_blank':undefined" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Solicitar información <i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <p class="ad3l-sidebar__disclaimer">Disponibilidad y condiciones sujetas a confirmación. Seleccionar un lote no constituye una reserva.</p>
          </template>
          <template v-else>
            <span class="ad3l-eyebrow">INFORMACIÓN DEL PROYECTO</span>
            <h3>{{development.name}}</h3>
            <p class="ad3l-sidebar__intro">Selecciona un terreno coloreado para consultar sus características y precio publicado.</p>
            <div class="ad3l-sidebar__line"></div>
            <div class="ad3l-sidebar__empty"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i><span>Explora el plano y elige el espacio para tu futuro.</span></div>
            <div class="ad3l-sidebar__line"></div>
            <span class="ad3l-sidebar__quantity">{{availableCount}} {{availableCount===1?'terreno disponible':'terrenos disponibles'}}</span>
            <a class="adp-map-whatsapp" :href="directWhatsapp" :target="publicWhatsapp?'_blank':undefined" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp"></i> Hablar con un asesor</a>
          </template>
        </aside>
      </div>
      <!-- ANDARMA_FASE3S_PROYECTO_HOME: proyecto ahora DEBAJO del plano -->
      <section class="ad3s-project" aria-label="Información del desarrollo">
        <div class="ad3s-project__media" aria-hidden="true"><img src="https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&amp;fit=crop&amp;w=2200&amp;q=85" alt="" loading="lazy" decoding="async" /></div>
        <div class="ad3s-project__wash" aria-hidden="true"></div>
        <div class="ad3s-project__inner">
          <div class="ad3s-project__top">
            <div class="ad3s-project__heading">
              <span class="ad3s-project__eyebrow">EL PROYECTO / ANDARMA</span>
              <h3>{{ development.name }}</h3>
            </div>
            <p class="ad3s-project__intro">{{ development.description || 'Conoce nuestro desarrollo y encuentra el espacio ideal para tu próximo proyecto.' }}</p>
          </div>
          <div class="ad3s-project__features" aria-label="Características del desarrollo">
            <div v-if="development.location" class="ad3s-project__feature">
              <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
              <strong>Ubicación</strong>
              <span>{{ development.location }}</span>
            </div>
            <div class="ad3s-project__feature">
              <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
              <strong>Plano interactivo</strong>
              <span>Explora los terrenos</span>
            </div>
            <div class="ad3s-project__feature">
              <i class="fa-solid fa-house" aria-hidden="true"></i>
              <strong>Tu próximo patrimonio</strong>
              <span>Conoce tus opciones</span>
            </div>
            <div class="ad3s-project__feature">
              <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
              <strong>{{ availableCount }} {{ availableCount===1?'terreno disponible':'terrenos disponibles' }}</strong>
              <span>Disponibilidad publicada</span>
            </div>
          </div>
        </div>
      </section>

    </template>
  </section>
</template>
