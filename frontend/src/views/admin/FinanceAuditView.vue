<script setup>
// ANDARMA_F19_AUDITORIA_VISUAL · Solo lectura de eventos registrados en Laravel.
import { ref, onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../../services/api'
import { ArrowLeft, RefreshCw, ShieldCheck, Search, CalendarDays, ChevronLeft, ChevronRight, AlertCircle } from 'lucide-vue-next'

const rows = ref([])
const loading = ref(false)
const error = ref('')
const action = ref('all')
const from = ref('')
const to = ref('')
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const detail = ref(null)
const hasData = computed(() => rows.value.length > 0)
let requestNumber = 0
const money = v => new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN' }).format(Number(v || 0))
const dateTime = v => {
  if (!v) return '—'
  // El backend devuelve fecha SQL sin zona horaria; no asumir UTC ni desplazar el día.
  return String(v).replace('T', ' ').slice(0, 16)
}
const dateText = v => v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—'
const concept = v => ({monthly:'Mensualidad',down_payment:'Enganche'})[v] || v || '—'
const actor = r => r.actor_name || (r.actor_id ? `Usuario #${r.actor_id}` : 'Usuario no disponible')

async function load(reset = false) {
  if (reset) page.value = 1
  if (from.value && to.value && from.value > to.value) {
    error.value = 'La fecha inicial no puede ser posterior a la fecha final.'
    return
  }
  const id = ++requestNumber
  loading.value = true
  error.value = ''
  detail.value = null
  const params = new URLSearchParams({action:action.value, page:String(page.value), per_page:'20'})
  if (from.value) params.set('from', from.value)
  if (to.value) params.set('to', to.value)
  try {
    const data = await api('/admin/finance/audit?' + params.toString())
    if (id !== requestNumber) return
    rows.value = Array.isArray(data.data) ? data.data : []
    total.value = Number(data.total || 0)
    lastPage.value = Math.max(1, Number(data.last_page || 1))
  } catch (e) {
    if (id !== requestNumber) return
    rows.value = []
    error.value = e.status === 403
      ? 'No tienes autorización para consultar la auditoría financiera.'
      : e.status === 404
        ? 'El endpoint de auditoría no está disponible. Comprueba que aplicaste la Fase 18 en el backend.'
        : e.message || 'No fue posible consultar la auditoría.'
  } finally {
    if (id === requestNumber) loading.value = false
  }
}
function goPage(next) {
  if (loading.value || next < 1 || next > lastPage.value) return
  page.value = next
  load()
}
function clearFilters() {
  action.value = 'all'; from.value = ''; to.value = ''; load(true)
}
onMounted(() => load())
</script>

<template>
<section class="ad-f19-page" aria-label="Auditoría financiera">
  <header class="ad-f19-heading">
    <div>
      <span class="ad-f19-kicker">GESTIÓN INMOBILIARIA / CONTROL INTERNO</span>
      <h1>Auditoría financiera<span class="ad-f19-dot">.</span></h1>
      <p>Consulta quién registró o canceló movimientos. Historial de solo lectura.</p>
    </div>
    <div class="ad-f19-actions">
      <RouterLink class="ad-btn" to="/admin/pagos"><ArrowLeft :size="16"/> Volver a Cobranza</RouterLink>
      <button class="ad-btn" type="button" :disabled="loading" @click="load()"><RefreshCw :size="15"/> Actualizar</button>
    </div>
  </header>

  <div class="ad-f19-disclaimer"><ShieldCheck :size="18"/><p>Los eventos se obtienen del historial financiero existente. Esta pantalla no permite modificar, anular ni eliminar pagos.</p></div>

  <section class="ad-f19-card" aria-label="Filtros de auditoría">
    <form class="ad-f19-filters" @submit.prevent="load(true)">
      <label>Tipo de operación
        <select v-model="action" class="ad-input"><option value="all">Todas las operaciones</option><option value="registered">Pagos registrados</option><option value="cancelled">Pagos cancelados</option></select>
      </label>
      <label>Desde
        <input v-model="from" class="ad-input" type="date" :max="to || undefined" />
      </label>
      <label>Hasta
        <input v-model="to" class="ad-input" type="date" :min="from || undefined" />
      </label>
      <div class="ad-f19-filteractions">
        <button type="submit" class="ad-btn ad-btn--primary" :disabled="loading"><Search :size="16"/> Consultar</button>
        <button type="button" class="ad-btn" :disabled="loading" @click="clearFilters">Limpiar</button>
      </div>
    </form>
    <p v-if="error" class="ad-f19-error" role="alert"><AlertCircle :size="17"/> {{error}}</p>
  </section>

  <section class="ad-f19-card" aria-label="Movimientos de auditoría">
    <header class="ad-f19-cardhead"><div><strong>Historial de operaciones</strong><small>Ordenado por fecha del evento, de más reciente a más antiguo.</small></div><span class="ad-f19-count">{{total}} eventos</span></header>
    <p v-if="loading" class="ad-f19-empty" role="status">Consultando operaciones…</p>
    <p v-else-if="!hasData && !error" class="ad-f19-empty">No encontramos operaciones para los filtros seleccionados.</p>
    <div v-if="hasData" class="ad-f19-tablewrap">
      <table class="ad-f19-table">
        <thead><tr><th>Operación</th><th>Movimiento</th><th>Responsable</th><th>Fecha del evento</th><th class="ad-f19-right">Importe</th><th>Detalle</th></tr></thead>
        <tbody><tr v-for="(item,index) in rows" :key="`${item.action}-${item.payment_id}-${index}`">
          <td><span class="ad-f19-status" :class="item.action==='cancelled'?'is-cancelled':'is-registered'">{{item.action==='cancelled'?'Cancelación':'Registro'}}</span></td>
          <td><strong>#{{item.payment_id}}</strong><small>{{concept(item.payment_type)}} · {{dateText(item.paid_at)}}</small></td>
          <td><strong>{{actor(item)}}</strong><small v-if="item.actor_id">ID {{item.actor_id}}</small></td>
          <td>{{dateTime(item.occurred_at)}}</td>
          <td class="ad-f19-right ad-f19-amount">{{money(item.amount)}}</td>
          <td><button class="ad-f19-detailbutton" type="button" @click="detail = item">Ver detalle</button></td>
        </tr></tbody>
      </table>
    </div>
    <footer class="ad-f19-pagination"><span>Página {{page}} de {{lastPage}}</span><div><button type="button" class="ad-btn" :disabled="loading || page<=1" @click="goPage(page-1)"><ChevronLeft :size="15"/> Anterior</button><button type="button" class="ad-btn" :disabled="loading || page>=lastPage" @click="goPage(page+1)">Siguiente <ChevronRight :size="15"/></button></div></footer>
  </section>

  <div v-if="detail" class="ad-f19-backdrop" @click.self="detail=null">
    <section class="ad-f19-modal" role="dialog" aria-modal="true" aria-label="Detalle de la operación">
      <header><div><span class="ad-f19-kicker">AUDITORÍA / EVENTO</span><h2>{{detail.action==='cancelled'?'Pago cancelado':'Pago registrado'}} #{{detail.payment_id}}</h2></div><button type="button" class="ad-f19-close" aria-label="Cerrar detalle" @click="detail=null">×</button></header>
      <dl class="ad-f19-dl">
        <div><dt>Responsable</dt><dd>{{actor(detail)}}</dd></div>
        <div><dt>Fecha del evento</dt><dd>{{dateTime(detail.occurred_at)}}</dd></div>
        <div><dt>Concepto</dt><dd>{{concept(detail.payment_type)}}</dd></div>
        <div><dt>Fecha real de pago</dt><dd>{{dateText(detail.paid_at)}}</dd></div>
        <div><dt>Importe</dt><dd>{{money(detail.amount)}}</dd></div>
        <div><dt>Estado actual</dt><dd>{{detail.status==='cancelled'?'Cancelado':detail.status==='applied'?'Aplicado':detail.status||'—'}}</dd></div>
        <div><dt>ID de terreno</dt><dd>{{detail.lot_id || '—'}}</dd></div>
        <div><dt>ID de cliente</dt><dd>{{detail.client_id || '—'}}</dd></div>
      </dl>
      <div v-if="detail.action==='cancelled'" class="ad-f19-reason"><strong>Motivo de cancelación</strong><p>{{detail.reason || 'No registrado'}}</p></div>
      <footer><button class="ad-btn ad-btn--primary" type="button" @click="detail=null">Cerrar detalle</button></footer>
    </section>
  </div>
</section>
</template>

<style scoped>
.ad-f19-page{display:grid;gap:16px;max-width:1500px;color:#17324e;font-family:var(--ad-font,'DM Sans',sans-serif)}
.ad-f19-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:18px;flex-wrap:wrap}
.ad-f19-heading h1{font-size:clamp(25px,3vw,34px);font-weight:800;letter-spacing:-.04em;margin:4px 0;color:#12314d}
.ad-f19-heading p,.ad-f19-disclaimer,.ad-f19-cardhead small{color:#6e8298;font-size:12px;line-height:1.6}
.ad-f19-kicker{color:#0066e0;font-weight:800;font-size:10px;letter-spacing:.13em}.ad-f19-dot{color:#0891ff}
.ad-f19-actions,.ad-f19-filteractions{display:flex;gap:9px;flex-wrap:wrap}
.ad-f19-actions .ad-btn,.ad-f19-filteractions .ad-btn,.ad-f19-pagination .ad-btn{display:inline-flex;gap:7px;align-items:center;justify-content:center;text-decoration:none}
.ad-f19-card{background:#fff;border:1px solid #dce8f3;box-shadow:0 12px 36px rgba(16,46,78,.05);border-radius:18px;padding:18px;min-width:0}
.ad-f19-disclaimer{display:flex;align-items:flex-start;gap:10px;border:1px solid #d5e8fb;border-radius:12px;background:#f4f9ff;padding:12px 15px;color:#355a7a}.ad-f19-disclaimer svg{flex-shrink:0;color:#0066e0}
.ad-f19-filters{display:grid;grid-template-columns:minmax(180px,1.25fr) repeat(2,minmax(150px,1fr)) auto;gap:12px;align-items:end}
.ad-f19-filters label{display:grid;gap:6px;font-size:12px;font-weight:700;color:#446078}.ad-f19-filters .ad-input{width:100%;min-width:0;min-height:42px}
.ad-f19-error{margin-top:12px;color:#a53045;background:#fff3f5;border:1px solid #f2ced5;border-radius:10px;padding:11px;display:flex;gap:9px;align-items:center;font-size:12px}
.ad-f19-cardhead{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px}.ad-f19-cardhead strong{display:block;font-size:16px}.ad-f19-cardhead small{display:block;margin-top:3px}.ad-f19-count{background:#eef6ff;border:1px solid #d4e8fc;color:#0066e0;padding:6px 10px;border-radius:20px;font-weight:700;font-size:12px}
.ad-f19-tablewrap{overflow-x:auto}.ad-f19-table{width:100%;min-width:840px;border-collapse:collapse;text-align:left;font-size:12px}.ad-f19-table th{padding:11px 12px;background:#f5f9fd;color:#637d95;font-size:11px;font-weight:800}.ad-f19-table td{padding:13px 12px;border-top:1px solid #e9eff5;vertical-align:middle}.ad-f19-table td strong,.ad-f19-table td small{display:block}.ad-f19-table td small{margin-top:4px;color:#7b91a6}.ad-f19-right{text-align:right}.ad-f19-amount{font-weight:800;color:#143a5d;font-variant-numeric:tabular-nums}
.ad-f19-status{display:inline-flex;padding:6px 9px;border-radius:20px;font-weight:750;font-size:11px}.ad-f19-status.is-registered{background:#e9f8f1;color:#17704b}.ad-f19-status.is-cancelled{background:#fff0f1;color:#aa3c4d}.ad-f19-detailbutton{color:#0066e0;font-weight:800;border:0;background:transparent;cursor:pointer;font-size:12px;padding:8px}.ad-f19-detailbutton:hover{text-decoration:underline}
.ad-f19-empty{padding:32px 12px;text-align:center;color:#72869d;font-size:13px}
.ad-f19-pagination{border-top:1px solid #e9eff5;padding-top:14px;margin-top:12px;display:flex;justify-content:space-between;gap:10px;align-items:center;color:#637d95;font-size:12px}.ad-f19-pagination>div{display:flex;gap:8px;flex-wrap:wrap}
.ad-f19-backdrop{position:fixed;inset:0;z-index:3000;background:rgba(8,27,47,.54);display:flex;align-items:center;justify-content:center;padding:20px}.ad-f19-modal{width:min(650px,100%);max-height:92dvh;overflow:auto;background:#fff;border:1px solid #dfe8f2;border-radius:20px;padding:22px;box-shadow:0 28px 100px rgba(0,0,0,.22)}.ad-f19-modal>header{display:flex;align-items:start;justify-content:space-between;gap:12px;margin-bottom:20px}.ad-f19-modal h2{font-size:21px;font-weight:800;margin-top:6px}.ad-f19-close{border:1px solid #dbe6f1;border-radius:9px;width:36px;height:36px;font-size:24px;color:#526c83}.ad-f19-dl{display:grid;grid-template-columns:1fr 1fr;gap:11px}.ad-f19-dl>div{border:1px solid #e0e9f2;background:#f9fbfe;padding:12px;border-radius:11px;min-width:0}.ad-f19-dl dt{font-size:11px;color:#72879b}.ad-f19-dl dd{font-size:13px;font-weight:750;margin-top:5px;overflow-wrap:anywhere}.ad-f19-reason{margin-top:13px;border:1px solid #f0d8dc;background:#fff7f8;border-radius:12px;padding:14px;font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere}.ad-f19-reason p{margin-top:7px}.ad-f19-modal>footer{display:flex;justify-content:flex-end;margin-top:18px}
@media(max-width:1100px){.ad-f19-filters{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.ad-f19-card{padding:12px}.ad-f19-filters{grid-template-columns:1fr}.ad-f19-filteractions .ad-btn{flex:1}.ad-f19-pagination{align-items:flex-start;flex-direction:column}.ad-f19-dl{grid-template-columns:1fr}.ad-f19-modal{padding:17px}}
</style>
