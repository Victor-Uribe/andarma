<script setup>
import { Money3Directive } from 'v-money3'
import { mxnMask, decimalMask } from '../../config/numberMasks'
const vMoney3 = Money3Directive
import {computed, onMounted, ref, watch} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import {can} from '../../utils/andarmaPermissions'
import Swal from 'sweetalert2'
import {api, post, put} from '../../services/api'
import '../../assets/admin-payments-v38.css'
import '../../assets/admin-cobranza-fase3.css'
// ANDARMA_COBRANZA_CLARA_F3

const route = useRoute()

const router = useRouter()// ANDARMA_V39_MONEY_NORMALIZATION
// Las máscaras son únicamente de presentación. Laravel recibe cantidades decimales sin separadores.
function parseAmount(value) {
  if (typeof value === 'number') return Number.isFinite(value) ? value : NaN
  let raw = String(value ?? '').trim().replace(/[^0-9,.-]/g, '')
  if (!raw || raw === '-') return NaN
  const comma = raw.lastIndexOf(',')
  const dot = raw.lastIndexOf('.')
  if (comma > dot) raw = raw.replace(/\./g, '').replace(',', '.')
  else raw = raw.replace(/,/g, '')
  const number = Number(raw)
  return Number.isFinite(number) ? number : NaN
}
function amountForApi(value) {
  const n = parseAmount(value)
  return Number.isFinite(n) ? Number(n.toFixed(2)) : null
}
function normalizeMoneyPayload(data, names) {
  const payload = {...data}
  for (const name of names) payload[name] = amountForApi(payload[name])
  return payload
}
const paymentTrafficLight = computed(() => {
  if (!detail.value?.plan || !detail.value?.summary) return {tone:'neutral', text:'Sin plan de pagos'}
  if (Number(detail.value.summary.unallocated_monthly_amount || 0) > 0)
    return {tone:'neutral', text:'Requiere conciliación'}
  const overdue = Number(detail.value.summary.overdue_periods || 0)
  if (overdue >= 4) return {tone:'red', text:`${overdue} meses de atraso`}
  if (overdue >= 1) return {tone:'yellow', text:`${overdue} ${overdue === 1 ? 'mes' : 'meses'} de atraso`}
  return {tone:'green', text:'Al corriente'}
})

const loading = ref(false), busy = ref(false), loadingDetail = ref(false)
const rows = ref([]), stats = ref(null), page = ref(1), lastPage = ref(1), total = ref(0)
const tab = ref('month'), search = ref(''), statusFilter = ref('all')
const from = ref(''), to = ref(''), clientsLots = ref([]), lotSearch = ref('')
const chosenLot = ref(''), detail = ref(null), selectedMovement = ref(null), modal = ref(''), error = ref('')
const lastPayment = ref(null)
const terms = ref(emptyTerms()), receipt = ref(emptyReceipt()), receiptKey = ref('')
const periods = computed(() => detail.value?.periods || [])
const unpaid = computed(() => periods.value.filter(p => Number(p.pending) > 0))
const selectedPeriod = computed(() => periods.value.find(p => p.period === receipt.value.applied_period))
const periodOptions = computed(() => unpaid.value)
// Resumen para operaciones cotidianas. Las cantidades vienen del mismo endpoint de
// cobranza; NO se calculan ni modifican saldos financieros en el navegador.
const overdueBalance = computed(() => Number(detail.value?.summary?.overdue_amount || 0))
const pendingBalance = computed(() => Number(detail.value?.summary?.total_balance || 0))
const nextPeriod = computed(() => unpaid.value.find(p => !p.is_overdue) || null)
const paidMonths = computed(() => periods.value.filter(p => p.status === 'paid').length)
const pendingMonths = computed(() => unpaid.value.length)
const needsReconciliation = computed(() => Number(detail.value?.summary?.unallocated_monthly_amount || 0) > 0)
const receiptPreview = computed(() => ({
  client: detail.value?.lot?.client_name || '—',
  lot: [detail.value?.lot?.development_name,detail.value?.lot?.block,detail.value?.lot?.code].filter(Boolean).join(' · '),
  period: receipt.value.payment_type === 'monthly' ? (receipt.value.applied_period ? labelPeriod(receipt.value.applied_period) : 'Sin periodo') : 'Enganche',
  amount: amountForApi(receipt.value.amount),
  date: formatDate(receipt.value.paid_at),
  method: methods[receipt.value.payment_method] || receipt.value.payment_method || '—'
}))

const methods = {cash:'Efectivo',transfer:'Transferencia',deposit:'Depósito',card:'Tarjeta',other:'Otro'}
const types = {monthly:'Mensualidad',down_payment:'Enganche'}
const statuses = {applied:'Aplicado',cancelled:'Cancelado'}

function localISO(date){const pad = v => String(v).padStart(2,'0');return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}`}
function localMonth(date){return localISO(date).slice(0,7)}
function money(value){return new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',minimumFractionDigits:2}).format(parseAmount(value) || 0)}
function formatDate(value){if(!value)return '—';const d=String(value).slice(0,10);const parts=d.split('-');return parts.length===3?`${parts[2]}/${parts[1]}/${parts[0]}`:d}
function labelPeriod(value){if(!value)return '—';const d=new Date(`${value.slice(0,7)}-01T12:00:00`);return new Intl.DateTimeFormat('es-MX',{month:'long',year:'numeric'}).format(d)}
function emptyTerms(){return {sale_price:'',discount_amount:'0',down_payment_amount:'0',monthly_payment_amount:'',payment_due_day:1,first_payment_period:localMonth(new Date()),sale_date:''}}
function emptyReceipt(){return {paid_at:localISO(new Date()),payment_type:'monthly',applied_period:'',amount:'',payment_method:'transfer',reference:'',folio:'',notes:''}}

function currentDates(){
 const now = new Date(),start = new Date(now)
 if(tab.value==='week'){
   const weekday=(now.getDay()+6)%7;start.setDate(now.getDate()-weekday)
   return {from:localISO(start),to:localISO(now)}
 }
 if(tab.value==='month'){
   start.setDate(1);return {from:localISO(start),to:localISO(now)}
 }
 return {from:from.value,to:to.value}
}
function swalStyle(){return {buttonsStyling:false,reverseButtons:true,background:'#fff',color:'#17324E',customClass:{popup:'andarma-swal',title:'andarma-swal__title',htmlContainer:'andarma-swal__text',actions:'andarma-swal__actions',confirmButton:'andarma-swal__confirm',cancelButton:'andarma-swal__cancel'}}}
async function confirmDialog(title,text,confirmButtonText='Continuar',danger=false){
 const result=await Swal.fire({...swalStyle(),icon:danger?'warning':'question',title,text,showCancelButton:true,confirmButtonText,cancelButtonText:'Cancelar',customClass:{...swalStyle().customClass,confirmButton:danger?'andarma-swal__confirm is-danger':'andarma-swal__confirm'}})
 return result.isConfirmed
}
async function notice(title,text='',kind='success'){
 return Swal.fire({...swalStyle(),icon:kind,title,text,confirmButtonText:'Entendido'})
}
async function load(){
 if(tab.value==='range' && from.value && to.value && from.value>to.value){error.value='La fecha inicial debe ser anterior a la final.';return}
 error.value='';loading.value=true
 try{
   const params = new URLSearchParams({page:String(page.value),per_page:'20',status:statusFilter.value})
   const range=currentDates()
   if(range.from)params.set('from',range.from)
   if(range.to)params.set('to',range.to)
   if(search.value.trim())params.set('q',search.value.trim())
   const r=await api(`/admin/finance/payments?${params.toString()}`)
   rows.value=r.data||[];stats.value=r.summary||null
   page.value=r.current_page||1;lastPage.value=r.last_page||1;total.value=r.total||0
 }catch(e){error.value=e.message||'No se pudo cargar el historial.';rows.value=[];stats.value=null}
 finally{loading.value=false}
}

let debounce
watch([tab,statusFilter],()=>{page.value=1;load()})
watch(search,()=>{page.value=1;clearTimeout(debounce);debounce=setTimeout(load,380)})
watch([from,to],()=>{if(tab.value==='range'){page.value=1;clearTimeout(debounce);debounce=setTimeout(load,420)}})

async function findLots(){
 try{
   const params=new URLSearchParams({q:lotSearch.value,per_page:'50'})
   const r=await api(`/admin/finance/lots?${params.toString()}`)
   clientsLots.value=r.data||[]
 }catch(e){error.value=e.message||'No se pudieron cargar las propiedades.'}
}
let lotsDebounce
watch(lotSearch,()=>{clearTimeout(lotsDebounce);lotsDebounce=setTimeout(findLots,350)})

async function openSummary(lotId){
 if(!lotId)return
 chosenLot.value=String(lotId)
 loadingDetail.value=true
 try{
   detail.value=await api(`/admin/finance/lots/${lotId}`)
   modal.value='summary'
 }catch(e){await notice('No se pudo consultar la propiedad',e.message,'error')}
 finally{loadingDetail.value=false}
}

function openTerms(){
 if(!detail.value)return
 const plan=detail.value.plan
 if(plan){terms.value={sale_price:plan.sale_price,discount_amount:plan.discount_amount,down_payment_amount:plan.down_payment_amount,monthly_payment_amount:plan.monthly_payment_amount,payment_due_day:plan.payment_due_day,first_payment_period:plan.first_payment_period?.slice(0,7)||'',sale_date:plan.sale_date?.slice(0,10)||''}}
 else{terms.value={...emptyTerms(),sale_price:detail.value.lot.price||''}}
 modal.value='terms'
}
async function saveTerms(){
 if(!detail.value||busy.value)return
 const ok=await confirmDialog('Guardar condiciones de venta','Se establecerá el precio pactado, enganche, mensualidad y calendario de cobro. No se registrará ningún pago automáticamente.','Guardar condiciones')
 if(!ok)return
 busy.value=true
 try{
   const payload = normalizeMoneyPayload(terms.value, ['sale_price','discount_amount','down_payment_amount','monthly_payment_amount'])
    if (['sale_price','discount_amount','down_payment_amount','monthly_payment_amount'].some(key => payload[key] === null))
      throw new Error('Verifica los importes del plan de pagos.')
    await put(`/admin/finance/lots/${detail.value.lot.id}/terms`, payload)
   await openSummary(detail.value.lot.id)
   await notice('Plan guardado','Ya puedes registrar enganches y mensualidades.')
 }catch(e){await notice('No se guardó el plan',e.message,'error')}
 finally{busy.value=false}
}
function setSuggestedAmount(){
 if(receipt.value.payment_type==='monthly'){
   const first=unpaid.value.find(p=>p.is_overdue)||unpaid.value[0]
   if(first){receipt.value.applied_period=first.period;receipt.value.amount=first.pending}
   else{receipt.value.applied_period='';receipt.value.amount=''}
 }else{
   receipt.value.applied_period=''
   receipt.value.amount=detail.value?.summary?.down_payment_pending||''
 }
}
function onPeriodChange(){if(selectedPeriod.value)receipt.value.amount=selectedPeriod.value.pending}
function goQuickLookup(){modal.value='';router.push('/admin/consulta')}
function openReceipt(){
 if(!detail.value?.plan){notice('Configura las condiciones de venta','Necesitamos un plan antes de aplicar pagos.','info');return}
 receipt.value=emptyReceipt()
 receiptKey.value=globalThis.crypto?.randomUUID?.() || ''
 setSuggestedAmount()
 modal.value='payment'
}
async function saveReceipt(){
 if(busy.value||!detail.value)return
 const numericAmount = amountForApi(receipt.value.amount)
 if(numericAmount === null || numericAmount <= 0){await notice('Captura el importe','El pago debe ser un importe válido mayor que cero.','warning');return}
 if(receipt.value.payment_type==='monthly'&&!receipt.value.applied_period){await notice('Selecciona un periodo','Elige la mensualidad a la que se aplicará el cobro.','warning');return}
 const ok=await confirmDialog('Confirmar registro de pago',`Comprador: ${receiptPreview.value.client} · Terreno: ${receiptPreview.value.lot} · Concepto: ${receiptPreview.value.period} · Importe: ${money(receiptPreview.value.amount)} · Fecha: ${receiptPreview.value.date} · Método: ${receiptPreview.value.method}. Confirma que los datos correspondan al comprobante.`, 'Confirmar y registrar')
 if(!ok)return
 busy.value=true
 try{
   const key=receiptKey.value
   if(!key)throw new Error('Tu navegador no permite generar una clave segura de operación.')
   const confirmedPayment={...receiptPreview.value,amount:numericAmount}
   await post(`/admin/finance/lots/${detail.value.lot.id}/payments`,{...receipt.value,amount:numericAmount,request_key:key})
   lastPayment.value=confirmedPayment
   modal.value='success'
   // La actualización de lectura jamás reenvía el pago.
   const refreshed=await Promise.allSettled([api(`/admin/finance/lots/${detail.value.lot.id}`),load()])
   if(refreshed[0].status==='fulfilled')detail.value=refreshed[0].value
  }catch(e){await notice('No se registró el pago',e.message,'error')}
 finally{busy.value=false}
}
async function cancelReceipt(payment){
 if(busy.value||payment.status!=='applied')return
 const result=await Swal.fire({...swalStyle(),title:`Cancelar pago #${payment.id}`,text:'El movimiento permanecerá en el historial, pero dejará de contar en los saldos y mensualidades.',icon:'warning',input:'textarea',inputLabel:'Motivo de cancelación (obligatorio)',inputPlaceholder:'Explica por qué debe anularse este registro...',inputAttributes:{maxLength:1000},showCancelButton:true,confirmButtonText:'Anular movimiento',cancelButtonText:'Volver',customClass:{...swalStyle().customClass,confirmButton:'andarma-swal__confirm is-danger',popup:'andarma-swal andarma-delete-dialog'},inputValidator:v=>!v||v.trim().length<10?'Escribe un motivo de al menos 10 caracteres.':undefined})
 if(!result.isConfirmed)return
 busy.value=true
 try{
   await post(`/admin/finance/payments/${payment.id}/cancel`,{reason:result.value.trim()})
   if(detail.value?.lot?.id)await openSummary(detail.value.lot.id)
   else modal.value=''
   await load()
   await notice('Pago cancelado','Se conservó la operación original y el motivo de la cancelación.')
 }catch(e){await notice('No se pudo cancelar',e.message,'error')}
 finally{busy.value=false}
}
function closeModal(){modal.value=''}
function pageTo(newPage){if(newPage<1||newPage>lastPage.value)return;page.value=newPage;load()}
function openFromList(row){if(row.lot_id)openSummary(row.lot_id);else{selectedMovement.value=row;modal.value='legacy'}}

onMounted(async()=>{
  await Promise.all([load(),findLots()])
  if(route.query.lot && /^\d+$/.test(String(route.query.lot))){
    await openSummary(route.query.lot)
    if(String(route.query.cobrar||'')==='1' && detail.value?.plan && can('payments.create'))openReceipt()
  }
})
</script>

<template>
<section class="andarma-payments ad-u-list space-y-5">
  <header class="ad-pay-heading flex flex-wrap justify-between items-end gap-4">
    <div>
      <span class="ad-pay-kicker">GESTIÓN INMOBILIARIA / COBRANZA</span>
      <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 mt-1">Pagos y cobranza</h1>
      <p class="ad-pay-muted mt-1">Consulta movimientos registrados y el estado financiero de cada propiedad.</p>
    </div>
    <div class="flex gap-2 flex-wrap">
      <!-- ANDARMA_F19_AUDITORIA_VISUAL: acceso a historial protegido por permisos -->
      <RouterLink v-if="can('payments.delete')" class="ad-btn" to="/admin/auditoria-financiera"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Auditoría</RouterLink>
      <button class="ad-btn" :disabled="loading" @click="load"><i class="fa-solid fa-rotate-right"></i> Actualizar</button>
      <button class="ad-btn ad-btn--primary" @click="modal='picker';findLots()"><i class="fa-solid fa-wallet"></i> Consultar o registrar pago</button>
    </div>
  </header>

  <div v-if="error" class="ad-pay-error"><i class="fa-solid fa-circle-exclamation"></i> {{error}}</div>

  <div class="ad-pay-kpis grid gap-3 md:grid-cols-3">
    <article class="ad-card ad-pay-kpi"><div class="ad-pay-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div><span>Total aplicado en el periodo</span><strong>{{stats?money(stats.applied_amount):'—'}}</strong></article>
    <article class="ad-card ad-pay-kpi"><div class="ad-pay-icon"><i class="fa-regular fa-circle-check"></i></div><span>Movimientos aplicados</span><strong>{{stats?Number(stats.applied_count||0):'—'}}</strong></article>
    <article class="ad-card ad-pay-kpi"><div class="ad-pay-icon"><i class="fa-solid fa-ban"></i></div><span>Movimientos cancelados</span><strong>{{stats?Number(stats.cancelled_count||0):'—'}}</strong></article>
  </div>

  <section class="ad-card space-y-4">
    <div class="flex justify-between items-start gap-4 flex-wrap">
      <div><span class="ad-pay-kicker">HISTORIAL DE MOVIMIENTOS</span><h2 class="text-lg font-bold text-slate-900 mt-1">Pagos realizados</h2><p class="ad-pay-muted">Importes efectivamente registrados, filtrados por su fecha real de pago.</p></div>
      <span class="ad-badge">{{total}} movimientos</span>
    </div>
    <div class="ad-pay-filters flex flex-wrap gap-3 items-end">
      <div class="ad-pay-segments" role="group" aria-label="Periodo de consulta">
        <button :class="{active:tab==='week'}" @click="tab='week'">Semana</button>
        <button :class="{active:tab==='month'}" @click="tab='month'">Mes</button>
        <button :class="{active:tab==='range'}" @click="tab='range'">Rango</button>
      </div>
      <label v-if="tab==='range'" class="andarma-field-label">Desde <input v-model="from" type="date" class="ad-input"/></label>
      <label v-if="tab==='range'" class="andarma-field-label">Hasta <input v-model="to" type="date" class="ad-input"/></label>
      <label class="ad-label ad-pay-search">Buscar <input v-model="search" class="ad-input" placeholder="Cliente, terreno, folio o referencia" /></label>
      <label class="andarma-field-label">Estado <select v-model="statusFilter" class="ad-select"><option value="all">Todos</option><option value="applied">Aplicados</option><option value="cancelled">Cancelados</option></select></label>
    </div>
    <div class="ad-table-wrap">
      <table class="ad-table ad-pay-table">
        <thead><tr><th>Fecha</th><th>Cliente / terreno</th><th>Concepto</th><th>Método / referencia</th><th>Estado</th><th class="text-right">Importe</th><th class="text-right">Acciones</th></tr></thead>
        <tbody>
          <tr v-for="p in rows" :key="p.id">
            <td>{{formatDate(p.paid_at)}}</td>
            <td><strong>{{p.client_name}}</strong><small class="block ad-pay-muted">{{p.development_name || 'Sin desarrollo'}} {{p.lot_code ? '· '+(p.lot_block||'')+' '+p.lot_code : '· Sin lote asociado'}}</small></td>
            <td>{{types[p.payment_type]||p.payment_type}}<small v-if="p.applied_period" class="block ad-pay-muted">{{labelPeriod(p.applied_period)}}</small></td>
            <td>{{methods[p.payment_method]||p.payment_method||'—'}}<small class="block ad-pay-muted">{{p.reference||p.folio||'Sin referencia'}}</small></td>
            <td><span class="ad-badge" :class="p.status==='applied'?'ad-badge--success':'ad-badge--danger'">{{statuses[p.status]||p.status}}</span></td>
            <td class="text-right"><strong>{{money(p.amount)}}</strong></td>
            <td><div class="ad-actions">
              <button class="ad-btn" @click="openFromList(p)"><i class="fa-solid fa-eye"></i> Ver</button>
              <button v-can="'payments.delete'" v-if="p.status==='applied'" class="ad-btn ad-btn--danger" :disabled="busy" @click="cancelReceipt(p)"><i class="fa-solid fa-ban"></i> Anular</button>
            </div></td>
          </tr>
          <tr v-if="!loading&&!rows.length"><td colspan="7" class="ad-pay-empty"><i class="fa-regular fa-folder-open"></i><p>No hay movimientos para los filtros seleccionados.</p></td></tr>
          <tr v-if="loading"><td colspan="7" class="ad-pay-empty">Consultando movimientos...</td></tr>
        </tbody>
      </table>
    </div>
    <div class="flex items-center justify-between text-xs ad-pay-muted gap-4"><span>Página {{page}} de {{lastPage}}</span><div class="flex gap-2"><button class="ad-btn" :disabled="page<=1||loading" @click="pageTo(page-1)"><i class="fa-solid fa-chevron-left"></i> Anterior</button><button class="ad-btn" :disabled="page>=lastPage||loading" @click="pageTo(page+1)">Siguiente <i class="fa-solid fa-chevron-right"></i></button></div></div>
  </section>

  <!-- Un solo patrón de modal centrado para todas las operaciones del módulo. -->
  <div v-if="modal" class="ad-modal-backdrop" @click.self="closeModal">
    <section class="ad-modal ad-pay-modal" :class="{'ad-pay-modal--wide':modal==='summary'}" role="dialog" aria-modal="true" aria-labelledby="ad-pay-modal-title">
      <header class="ad-modal__header">
        <div>
          <span class="ad-pay-kicker">COBRANZA / ANDARMA</span>
          <h2 id="ad-pay-modal-title" class="ad-pay-modal-title">{{modal==='picker'?'Seleccionar propiedad':modal==='summary'?'Resumen de cobranza':modal==='terms'?'Condiciones de venta':modal==='legacy'?'Detalle del movimiento':modal==='success'?'Pago registrado':'Registrar pago'}}</h2>
          <p class="ad-pay-muted">{{modal==='summary'?(detail?.lot?.client_name||'Expediente del comprador'):'Información financiera de propiedades con cliente asignado'}}</p>
        </div>
        <button class="ad-icon-btn" aria-label="Cerrar modal" @click="closeModal"><i class="fa-solid fa-xmark"></i></button>
      </header>
      <div class="ad-modal__body space-y-4">
        <!-- ANDARMA_FASE13B_POSTPAGO: confirmacion administrativa -->
        <div v-if="modal==='success' && lastPayment" class="ad-f13b-success" role="status" aria-live="polite">
          <span class="ad-f13b-icon"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
          <span class="ad-pay-kicker">MOVIMIENTO REGISTRADO</span>
          <h3>Pago registrado correctamente</h3>
          <p>El pago se guardó en ANDARMA. Continúa con el estado de cuenta o con otra propiedad.</p>
          <div class="ad-f13b-details">
            <div><small>Comprador</small><strong>{{lastPayment.client}}</strong></div>
            <div><small>Terreno</small><strong>{{lastPayment.lot}}</strong></div>
            <div><small>Concepto</small><strong>{{lastPayment.period}}</strong></div>
            <div><small>Importe</small><strong>{{money(lastPayment.amount)}}</strong></div>
            <div><small>Fecha</small><strong>{{lastPayment.date}}</strong></div>
            <div><small>Método</small><strong>{{lastPayment.method}}</strong></div>
          </div>
          <div class="ad-f13b-actions">
            <button class="ad-btn ad-btn--primary" type="button" @click="modal='summary'"><i class="fa-solid fa-file-invoice-dollar"></i> Ver estado de cuenta</button>
            <button class="ad-btn" type="button" @click="modal='picker';findLots()"><i class="fa-solid fa-magnifying-glass"></i> Elegir otra propiedad</button>
            <button class="ad-btn" type="button" @click="goQuickLookup"><i class="fa-solid fa-arrow-left"></i> Consulta rápida</button>
          </div>
          <small class="ad-f13b-disclaimer">Confirmación administrativa. No es comprobante fiscal ni confirma transferencia bancaria.</small>
        </div>
        <template v-else-if="modal==='picker'">
          <label class="andarma-field-label">Buscar propiedad o comprador<input class="ad-input" v-model="lotSearch" placeholder="Cliente, manzana, lote o desarrollo" /></label>
          <div class="ad-pay-picker-list">
            <button v-for="lot in clientsLots" :key="lot.id" class="ad-pay-picker-item" @click="openSummary(lot.id)">
              <span><strong>{{lot.development_name}} · {{lot.block||''}} {{lot.code}}</strong><small>{{lot.client_name}} · {{lot.status==='sold'?'Vendida':'Apartada'}}</small></span>
              <span class="flex items-center gap-2"><span class="ad-badge" :class="lot.financing_id?'ad-badge--success':''">{{lot.financing_id?'Con plan':'Sin plan'}}</span><i class="fa-solid fa-chevron-right"></i></span>
            </button>
            <p v-if="!clientsLots.length" class="ad-pay-muted p-4">No encontramos propiedades con comprador para esa búsqueda.</p>
          </div>
        </template>

        <template v-else-if="modal==='summary' && detail">
          <div class="ad-pay-property"><div><span class="ad-pay-kicker">PROPIEDAD</span><h3>{{detail.lot.development_name}} · {{detail.lot.block||''}} {{detail.lot.code}}</h3><p>{{detail.lot.client_name}}</p></div><span class="ad-badge">{{detail.lot.status==='sold'?'Vendida':'Apartada'}}</span></div>
          <template v-if="detail.plan && detail.summary">
            <div class="ad-pay-traffic" :class="'ad-pay-traffic--'+paymentTrafficLight.tone" role="status">
              <i class="fa-solid fa-circle" aria-hidden="true"></i>
              <strong>Estado de cobranza: {{paymentTrafficLight.text}}</strong>
              <span v-if="paymentTrafficLight.tone==='yellow'">1 a 3 mensualidades vencidas</span>
              <span v-else-if="paymentTrafficLight.tone==='red'">4 o más mensualidades vencidas</span>
            </div>
            <div class="ad-pay-f3-balance" aria-label="Estado de cuenta resumido">
               <article><span>Saldo total por pagar</span><strong>{{money(pendingBalance)}}</strong><small>Saldo restante de la compra, no necesariamente vencido.</small></article>
               <article :class="{'is-overdue':overdueBalance>0}"><span>Importe vencido</span><strong>{{money(overdueBalance)}}</strong><small>{{detail.summary.overdue_periods}} mensualidad(es) atrasada(s)</small></article>
               <article><span>Próxima mensualidad pendiente</span><strong>{{nextPeriod ? money(nextPeriod.pending) : '—'}}</strong><small>{{nextPeriod ? 'Vence '+formatDate(nextPeriod.due_date) : 'Sin próximo periodo programado'}}</small></article>
               <article><span>Mensualidades cubiertas</span><strong>{{paidMonths}} / {{periods.length}}</strong><small>{{pendingMonths}} mensualidad(es) con importe pendiente</small></article>
             </div>
             <p v-if="needsReconciliation" class="ad-pay-f3-alert" role="alert">Existen pagos históricos sin periodo asignado. Verifica el calendario antes de comunicar el número de mensualidades vencidas.</p>
             <div class="ad-pay-summarygrid">
               <article><span>Mensualidad</span><strong>{{money(detail.summary.monthly_payment)}}</strong><small>Día {{detail.plan.payment_due_day}} de cada mes</small></article>
              <article><span>Saldo financiado</span><strong>{{money(detail.summary.financed_balance)}}</strong><small>Mensualidades pendientes</small></article>
              <article><span>Total pagado</span><strong>{{money(detail.summary.total_paid)}}</strong><small>Movimientos aplicados</small></article>
              <article :class="detail.summary.overdue_periods?'is-late':''"><span>Mensualidades vencidas</span><strong>{{detail.summary.overdue_periods}}</strong><small>{{money(detail.summary.overdue_amount)}} vencido</small></article>
            </div>
            <div class="ad-pay-meta"><span>Precio pactado: <strong>{{money(detail.summary.sale_price)}}</strong></span><span>Saldo total pendiente: <strong>{{money(detail.summary.total_balance)}}</strong></span><span>Enganche por pagar: <strong>{{money(detail.summary.down_payment_pending)}}</strong></span></div>
            <p v-if="Number(detail.summary.unallocated_monthly_amount)>0" class="ad-pay-warning"><i class="fa-solid fa-triangle-exclamation"></i> Hay {{money(detail.summary.unallocated_monthly_amount)}} de mensualidades históricas sin periodo asignado. Necesitan conciliación antes de interpretar el calendario como definitivo.</p>
            <div class="flex gap-2 flex-wrap"><button v-can="'payments.create'" class="ad-btn ad-btn--primary" @click="openReceipt"><i class="fa-solid fa-plus"></i> Registrar pago</button><button class="ad-btn" :disabled="detail.payments.length>0" @click="openTerms"><i class="fa-solid fa-pen-to-square"></i> Editar condiciones</button><button class="ad-btn" @click="modal='picker'"><i class="fa-solid fa-arrow-left"></i> Elegir otra propiedad</button></div>
            <div class="ad-pay-two-col">
              <section><h4 class="ad-pay-section-title">Calendario de mensualidades</h4><div class="ad-pay-scroll"><div v-for="period in periods" :key="period.period" class="ad-pay-period"><div><strong>{{labelPeriod(period.period)}}</strong><small>Límite {{formatDate(period.due_date)}}</small></div><div class="text-right"><strong>{{money(period.paid)}} / {{money(period.scheduled)}}</strong><small :class="{'ad-pay-red':period.is_overdue}">{{period.status==='paid'?'Pagado':period.status==='overdue'?'Vencido':period.status==='partial'?'Parcial':'Pendiente'}}</small></div></div><p v-if="!periods.length" class="ad-pay-muted">Venta sin mensualidades programadas.</p></div></section>
              <section><h4 class="ad-pay-section-title">Historial de pagos</h4><div class="ad-pay-scroll"><div v-for="payment in detail.payments" :key="payment.id" class="ad-pay-period"><div><strong>{{types[payment.payment_type]||payment.payment_type}}</strong><small>{{formatDate(payment.paid_at)}} · {{methods[payment.payment_method]||payment.payment_method||'Otro'}}</small></div><div class="text-right"><strong>{{money(payment.amount)}}</strong><small :class="{'ad-pay-red':payment.status==='cancelled'}">{{statuses[payment.status]||payment.status}}</small><small v-if="payment.cancel_reason" class="ad-pay-red" :title="payment.cancel_reason">Motivo: {{payment.cancel_reason}}</small></div></div><p v-if="!detail.payments.length" class="ad-pay-muted">Todavía no hay pagos registrados.</p></div></section>
            </div>
          </template>
          <div v-else class="ad-pay-empty-plan"><i class="fa-solid fa-file-circle-plus"></i><h3>Sin condiciones financieras</h3><p>Configura primero el precio pactado, enganche, mensualidad y fecha de inicio. No crearemos pagos ficticios.</p><button v-can="'payments.edit'" class="ad-btn ad-btn--primary" @click="openTerms">Configurar plan de pagos</button></div>
        </template>

        <template v-else-if="modal==='legacy' && selectedMovement">
          <div class="ad-pay-property"><div><span class="ad-pay-kicker">PAGO #{{selectedMovement.id}}</span><h3>{{selectedMovement.client_name}}</h3><p>Movimiento histórico sin lote vinculado</p></div><span class="ad-badge" :class="selectedMovement.status==='applied'?'ad-badge--success':'ad-badge--danger'">{{statuses[selectedMovement.status]||selectedMovement.status}}</span></div>
          <div class="ad-pay-summarygrid">
            <article><span>Fecha del pago</span><strong>{{formatDate(selectedMovement.paid_at)}}</strong></article>
            <article><span>Importe</span><strong>{{money(selectedMovement.amount)}}</strong></article>
            <article><span>Concepto</span><strong>{{types[selectedMovement.payment_type]||selectedMovement.payment_type}}</strong></article>
            <article><span>Método</span><strong>{{methods[selectedMovement.payment_method]||selectedMovement.payment_method||'Sin dato'}}</strong></article>
          </div>
          <p class="ad-pay-muted">Referencia: {{selectedMovement.reference||'—'}} · Folio: {{selectedMovement.folio||'—'}}</p>
          <p v-if="selectedMovement.notes" class="ad-pay-muted">Notas: {{selectedMovement.notes}}</p>
          <p v-if="selectedMovement.cancel_reason" class="ad-pay-warning">Motivo de anulación: {{selectedMovement.cancel_reason}}</p>
          <p class="ad-pay-warning">Este movimiento no tiene propiedad vinculada. No se asigna automáticamente a un periodo de mensualidad.</p>
        </template>

        <form v-else-if="modal==='terms'" id="termsForm" class="space-y-4" @submit.prevent="saveTerms">
          <p class="ad-pay-warning"><i class="fa-solid fa-circle-info"></i> El enganche acordado no se considera pagado hasta registrar su movimiento. Las condiciones se bloquean cuando existe historial de pagos.</p>
          <div class="ad-form-grid">
            <label class="andarma-field-label">Precio de venta (MXN) *<input v-model.lazy="terms.sale_price" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input" required/></label>
            <label class="andarma-field-label">Descuento (MXN)<input v-model.lazy="terms.discount_amount" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input" required/></label>
            <label class="andarma-field-label">Enganche acordado (MXN)<input v-model.lazy="terms.down_payment_amount" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input" required/></label>
            <label class="andarma-field-label">Mensualidad (MXN) *<input v-model.lazy="terms.monthly_payment_amount" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input" required/></label>
            <label class="andarma-field-label">Día límite de pago<input v-model="terms.payment_due_day" type="number" min="1" max="31" required class="ad-input"/></label>
            <label class="andarma-field-label">Primer mes a pagar<input v-model="terms.first_payment_period" type="month" class="ad-input"/></label>
            <label class="ad-label ad-form-span">Fecha de venta (opcional)<input v-model="terms.sale_date" type="date" class="ad-input"/></label>
          </div>
          <p class="ad-pay-muted">Monto a financiar: <strong>{{money(Math.max(0,Number(terms.sale_price||0)-Number(terms.discount_amount||0)-Number(terms.down_payment_amount||0)))}}</strong></p>
        </form>

        <form v-else-if="modal==='payment'" id="receiptForm" class="space-y-4" @submit.prevent="saveReceipt">
          <div class="ad-pay-property"><div><span class="ad-pay-kicker">COMPRADOR</span><h3>{{detail?.lot?.client_name}}</h3><p>{{detail?.lot?.development_name}} · {{detail?.lot?.code}}</p></div><span class="ad-badge ad-badge--success">Registro administrativo</span></div>
          <div class="ad-pay-f3-receipt-heading"><strong>Registrar un movimiento</strong><span>Comprueba la fecha, el mes y el importe antes de confirmar.</span></div>
           <div class="ad-form-grid">
             <label class="andarma-field-label">Fecha real de pago *<input v-model="receipt.paid_at" type="date" required class="ad-input"/></label>
            <label class="andarma-field-label">Concepto *<select v-model="receipt.payment_type" class="ad-select" @change="setSuggestedAmount"><option value="monthly">Mensualidad</option><option value="down_payment">Enganche</option></select></label>
            <label v-if="receipt.payment_type==='monthly'" class="ad-label ad-form-span">Mes al que se aplica *<select v-model="receipt.applied_period" required class="ad-select" @change="onPeriodChange"><option value="">Seleccionar mensualidad</option><option v-for="p in periodOptions" :key="p.period" :value="p.period">{{labelPeriod(p.period)}} · Pendiente {{money(p.pending)}}</option></select></label>
            <label class="andarma-field-label">Monto registrado (MXN) *<input v-model.lazy="receipt.amount" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input" required/></label>
            <label class="andarma-field-label">Método de pago *<select v-model="receipt.payment_method" required class="ad-select"><option v-for="(name,key) in methods" :key="key" :value="key">{{name}}</option></select></label>
            <label class="andarma-field-label">Referencia<input v-model.trim="receipt.reference" class="ad-input" maxlength="150" placeholder="Referencia bancaria"/></label>
            <label class="andarma-field-label">Folio<input v-model.trim="receipt.folio" class="ad-input" maxlength="150" placeholder="Folio interno"/></label>
            <label class="ad-label ad-form-span">Notas<textarea v-model.trim="receipt.notes" class="ad-textarea" maxlength="3000" placeholder="Observaciones del cobro"></textarea></label>
          </div>
          <div class="ad-pay-f3-preview" aria-live="polite">
             <strong>Resumen antes de guardar</strong>
             <span>{{receiptPreview.client}} · {{receiptPreview.lot}}</span>
             <div><span>{{receiptPreview.period}} · {{receiptPreview.date}}</span><strong>{{receiptPreview.amount === null ? 'Importe inválido' : money(receiptPreview.amount)}}</strong></div>
           </div>
           <p v-if="selectedPeriod" class="ad-pay-warning">Para {{labelPeriod(receipt.applied_period)}} quedan {{money(selectedPeriod.pending)}} pendientes. Se admiten abonos parciales.</p>
          <p class="ad-pay-muted">Este registro acredita una operación informada por el administrador. No ejecuta cobros bancarios ni emite comprobantes fiscales.</p>
        </form>
      </div>
      <footer class="ad-modal__footer">
        <button class="ad-btn" @click="['summary','picker','legacy','success'].includes(modal)?closeModal():(detail?modal='summary':closeModal())">{{modal==='summary'||modal==='picker'||modal==='legacy'?'Cerrar':'Volver'}}</button>
        <button v-if="modal==='terms'" form="termsForm" type="submit" :disabled="busy" class="ad-btn ad-btn--primary"><i class="fa-solid fa-floppy-disk"></i> {{busy?'Guardando...':'Guardar condiciones'}}</button>
        <button v-if="modal==='payment'" form="receiptForm" type="submit" :disabled="busy" class="ad-btn ad-btn--primary"><i class="fa-solid fa-check"></i> {{busy?'Registrando...':'Registrar pago'}}</button>
      </footer>
    </section>
  </div>
</section>
</template>

<style>
/* ANDARMA_FASE13B_POSTPAGO */
.andarma-admin .ad-f13b-success{display:grid;gap:13px;text-align:center;justify-items:center;padding:12px 4px;color:#17324e}
.andarma-admin .ad-f13b-icon{width:54px;height:54px;display:grid;place-items:center;border:1px solid #c7e9d8;border-radius:15px;background:#edfaf3;color:#19835c;font-size:23px}
.andarma-admin .ad-f13b-success h3{font:800 24px/1.2 Manrope,sans-serif;color:#17324e;letter-spacing:-.03em;margin:0}
.andarma-admin .ad-f13b-success>p{color:#71869a;font-size:13px;line-height:1.6;margin:0;max-width:500px}
.andarma-admin .ad-f13b-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:100%;gap:10px;text-align:left;margin:7px 0}
.andarma-admin .ad-f13b-details>div{border:1px solid #dde8f3;background:#f7fbff;border-radius:11px;padding:13px;min-width:0;display:grid;gap:5px}
.andarma-admin .ad-f13b-details small{font-size:11px;color:#72879c}
.andarma-admin .ad-f13b-details strong{font-size:13px;color:#17324e;overflow-wrap:anywhere}
.andarma-admin .ad-f13b-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;width:100%;margin:8px 0}
.andarma-admin .ad-f13b-actions .ad-btn{min-height:44px}
.andarma-admin .ad-f13b-disclaimer{font-size:11px;color:#8293a6;line-height:1.6}
@media(max-width:600px){.andarma-admin .ad-f13b-details{grid-template-columns:1fr}.andarma-admin .ad-f13b-actions{flex-direction:column}.andarma-admin .ad-f13b-actions .ad-btn{width:100%;justify-content:center}}
</style>
