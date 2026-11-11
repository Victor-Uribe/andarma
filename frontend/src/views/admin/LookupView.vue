<script setup>
import {computed,ref,watch,onMounted,onBeforeUnmount,nextTick} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import {api} from '../../services/api'
import {can} from '../../utils/andarmaPermissions'
import {Search,RefreshCw,UserRound,MapPin,WalletCards,AlertCircle,ArrowRight,Phone,CalendarDays,CheckCircle2} from '../../components/admin/AndarmaFaIcons.js'

// ANDARMA_FASE5_CONSULTA
const router=useRouter(),route=useRoute()
const searchInput=ref(null)
const q=ref(String(route.query.q||'')),results=ref({lots:[],clients:[]}),loading=ref(false),error=ref('')
const selection=ref(null),client=ref(null),finance=ref(null),loadingDetail=ref(false),detailError=ref('')
const canSeeClients=computed(()=>can('clients.view'))
const canSeePayments=computed(()=>can('payments.view'))
// ANDARMA_F12_PAGO_DIRECTO_CONSULTA: el permiso de creacion es independiente del permiso de consulta.
const canRegisterPayments=computed(()=>can('payments.create'))
let timer=null,sequence=0,detailSequence=0
const money=n=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n||0))
const dateText=d=>d?String(d).slice(0,10).split('-').reverse().join('/'):'—'
const nameFor=r=>[r.development_name,r.block&&'Manzana '+r.block,r.code&&'Lote '+r.code].filter(Boolean).join(' · ')
const clientLots=computed(()=>client.value?.properties||[])
const nextDue=computed(()=>finance.value?.periods?.find(p=>Number(p.pending)>0 && !p.is_overdue)||null)
const overdue=computed(()=>finance.value?.summary?.overdue_periods||0)
const matchedClients=computed(()=>results.value.clients.filter(c=>!results.value.lots.some(l=>Number(l.client_id)===Number(c.client_id))))
async function search(){
 const query=q.value.trim(),id=++sequence
 if(query.length<2){results.value={lots:[],clients:[]};error.value='';loading.value=false;return}
 loading.value=true;error.value=''
 try{const response=await api('/admin/lookup?'+new URLSearchParams({q:query}));if(id===sequence)results.value=response.data||{lots:[],clients:[]}}
 catch(e){if(id===sequence){error.value=e.message;results.value={lots:[],clients:[]}}}
 finally{if(id===sequence)loading.value=false}
}
watch(q,()=>{
  clearTimeout(timer)
  // Nunca mantener a la vista importes de una persona después de escribir otra búsqueda.
  detailSequence++
  selection.value=null
  client.value=null
  finance.value=null
  loadingDetail.value=false
  detailError.value=''
  results.value={lots:[],clients:[]}
  error.value=''
  sequence++ // invalidar una búsqueda anterior en vuelo inmediatamente
  timer=setTimeout(search,320)
})
watch(()=>route.query.q,(next)=>{
  const value=String(next||'')
  if(q.value!==value)q.value=value
  if(value.trim().length>=2)nextTick(()=>searchInput.value?.focus())
})
onMounted(()=>nextTick(()=>searchInput.value?.focus()))
async function openLot(lot){
 const id=++detailSequence;selection.value=lot;client.value=null;finance.value=null;detailError.value=''
 if(!lot.client_id)return
 loadingDetail.value=true
 try{
  const jobs=[api('/admin/clients/'+lot.client_id),canSeePayments.value?api('/admin/finance/lots/'+lot.lot_id):Promise.resolve(null)]
  const settled=await Promise.allSettled(jobs)
  if(id!==detailSequence)return
  if(settled[0].status==='fulfilled')client.value=settled[0].value
  else detailError.value='No fue posible cargar el expediente: '+settled[0].reason.message
  if(settled[1].status==='fulfilled')finance.value=settled[1].value
  else if(canSeePayments.value)detailError.value+=' No fue posible consultar la cobranza: '+settled[1].reason.message
 }finally{if(id===detailSequence)loadingDetail.value=false}
}
async function openClient(item){
 const id=++detailSequence;selection.value={client_id:item.client_id,client_name:item.client_name,client_phone:item.client_phone}
 client.value=null;finance.value=null;detailError.value='';loadingDetail.value=true
 try{const c=await api('/admin/clients/'+item.client_id);if(id===detailSequence)client.value=c}
 catch(e){if(id===detailSequence)detailError.value=e.message}
 finally{if(id===detailSequence)loadingDetail.value=false}
}
function goLot(id){router.push({path:'/admin/pagos',query:{lot:String(id)}})}
function goPay(id){if(!canRegisterPayments.value || !id)return;router.push({path:'/admin/pagos',query:{lot:String(id),cobrar:'1'}})}
function goClient(id){router.push({path:'/admin/clientes',query:{client:String(id)}})}
onBeforeUnmount(()=>{clearTimeout(timer);sequence++;detailSequence++})
if(q.value.trim().length>=2)search()
</script>
<template>
<section class="ad-lookup ad-u-list">
  <header class="ad-lookup__heading"><div><span class="admin-kicker">GESTIÓN INMOBILIARIA / CONSULTA</span><h1>Consulta rápida<span class="heading-dot">.</span></h1><p>Encuentra un comprador, teléfono, desarrollo, manzana o terreno desde un solo lugar.</p></div></header>
  <div class="ad-card ad-lookup__searchbox">
    <label class="andarma-field-label" for="ad-lookup-query">Buscar en ANDARMA</label>
    <div class="ad-lookup__search"><Search :size="19"/><input id="ad-lookup-query" ref="searchInput" v-model="q" class="ad-input" type="search" maxlength="120" autocomplete="off" placeholder="Ej. María González, 5512345678, lote 0181, M5 o Prados del Rosario"/><button class="ad-btn" type="button" :disabled="loading||q.trim().length<2" @click="search"><RefreshCw :size="15"/> Buscar</button></div>
    <p class="ad-lookup__hint">Escribe al menos dos caracteres. La búsqueda no distingue entre mayúsculas y minúsculas en configuraciones SQL habituales.</p>
  </div>
  <p v-if="error" class="ad-lookup__error" role="alert"><AlertCircle :size="17"/>{{error}}</p>
  <div class="ad-lookup__layout">
    <div class="ad-card">
      <div class="ad-lookup__sectionhead"><h2>Resultados</h2><span class="ad-badge">{{(results.lots?.length||0)+(matchedClients.length||0)}}</span></div>
      <p v-if="loading" class="ad-lookup__muted">Buscando registros…</p>
      <p v-else-if="q.trim().length<2" class="ad-lookup__muted">Introduce un nombre, teléfono o terreno para comenzar.</p>
      <p v-else-if="!results.lots?.length&&!matchedClients.length" class="ad-lookup__muted">No encontramos coincidencias. Revisa el nombre o número de lote.</p>
      <div v-for="lot in results.lots" :key="'lot-'+lot.lot_id" class="ad-lookup__result">
        <button class="ad-lookup__resultbutton" type="button" @click="openLot(lot)"><MapPin :size="18"/><span><strong>{{nameFor(lot)}}</strong><small>{{lot.client_name||'Sin comprador asignado'}} · {{lot.status==='sold'?'Vendido':lot.status==='reserved'?'Apartado':lot.status==='available'?'Disponible':lot.status}}</small></span><ArrowRight :size="16"/></button>
        <button v-if="lot.client_id && canRegisterPayments" class="ad-lookup__quickpay" type="button" :aria-label="'Registrar pago del terreno '+nameFor(lot)" @click="goPay(lot.lot_id)"><WalletCards :size="15"/> Registrar pago</button>
      </div>
      <div v-for="person in matchedClients" :key="'client-'+person.client_id" class="ad-lookup__result">
        <button class="ad-lookup__resultbutton" type="button" @click="openClient(person)"><UserRound :size="18"/><span><strong>{{person.client_name}}</strong><small>{{person.client_phone||'Sin teléfono'}} · Cliente</small></span><ArrowRight :size="16"/></button>
      </div>
      <p v-if="results.lots?.length>=35||results.clients?.length>=15" class="ad-lookup__hint">Se muestran las primeras coincidencias. Escribe más caracteres para precisar.</p>
    </div>
    <div class="ad-card ad-lookup__detail">
      <div class="ad-lookup__sectionhead"><h2>Expediente</h2><span v-if="selection" class="ad-badge">Consulta</span></div>
      <p v-if="!selection" class="ad-lookup__muted">Selecciona un resultado para consultar su información.</p>
      <template v-else>
        <h3>{{selection.client_name||'Terreno sin comprador'}}</h3>
        <p class="ad-lookup__muted">{{selection.lot_id?nameFor(selection):'Expediente del cliente'}}</p>
        <p v-if="selection.client_phone" class="ad-lookup__contact"><Phone :size="15"/>{{selection.client_phone}}</p>
        <p v-if="loadingDetail" class="ad-lookup__muted">Cargando expediente y cobranza…</p>
        <p v-if="detailError" class="ad-lookup__error" role="alert">{{detailError}}</p>
        <template v-if="client">
          <div class="ad-lookup__actions"><button v-if="canSeeClients" class="ad-btn" type="button" @click="goClient(selection.client_id)"><UserRound :size="15"/> Expediente del cliente</button><button v-if="selection.lot_id&&canSeePayments" class="ad-btn" type="button" @click="goLot(selection.lot_id)"><WalletCards :size="15"/> Ver estado de cuenta</button><button v-if="selection.lot_id&&selection.client_id&&canRegisterPayments" class="ad-btn ad-btn--primary" type="button" @click="goPay(selection.lot_id)"><WalletCards :size="15"/> Registrar pago</button></div>
          <div v-if="!selection.lot_id&&clientLots.length" class="ad-lookup__lots"><strong>Propiedades del cliente</strong><button v-for="l in clientLots" :key="l.id" class="ad-lookup__lotlink" @click="openLot({lot_id:l.id,code:l.code,development_name:l.development_name,client_id:selection.client_id,client_name:selection.client_name,client_phone:selection.client_phone})">{{l.development_name}} · Lote {{l.code}} <ArrowRight :size="15"/></button></div>
          <div v-if="selection.lot_id&&!canSeePayments" class="ad-lookup__notice">Puedes consultar el cliente, pero tu rol no tiene acceso a información de cobranza.</div>
          <template v-if="finance?.summary">
            <div class="ad-lookup__metrics">
              <article><span>Precio pactado</span><strong>{{money(finance.summary.sale_price)}}</strong></article>
              <article><span>Total pagado</span><strong>{{money(finance.summary.total_paid)}}</strong></article>
              <article><span>Saldo pendiente</span><strong>{{money(finance.summary.total_balance)}}</strong></article>
              <article :class="{'is-overdue':Number(overdue)>0}"><span>Mensualidades vencidas</span><strong>{{overdue}}</strong><small>{{money(finance.summary.overdue_amount)}} vencido</small></article>
            </div>
            <div class="ad-lookup__dues"><div><CalendarDays :size="17"/><strong>Próximo vencimiento</strong></div><span v-if="nextDue">{{dateText(nextDue.due_date)}} · {{money(nextDue.pending)}}</span><span v-else>Sin próximo vencimiento pendiente</span></div>
            <p v-if="Number(finance.summary.unallocated_monthly_amount)>0" class="ad-lookup__notice">Hay pagos históricos sin periodo asignado: {{money(finance.summary.unallocated_monthly_amount)}}. Revisar conciliación.</p>
            <div class="ad-lookup__sectionhead"><h4>Mensualidades</h4><span class="ad-badge">{{finance.periods?.length||0}}</span></div>
            <div class="ad-lookup__periods"><div v-for="p in finance.periods" :key="p.period" class="ad-lookup__period"><span>{{p.period}} · Límite {{dateText(p.due_date)}}</span><span :class="{'ad-lookup__late':p.is_overdue}">{{money(p.pending)}} pendiente · {{p.status==='paid'?'Pagado':p.is_overdue?'Vencido':p.status==='partial'?'Parcial':'Pendiente'}}</span></div></div>
          </template>
          <p v-else-if="selection.lot_id&&!loadingDetail&&canSeePayments&&!detailError" class="ad-lookup__notice">Este terreno todavía no tiene un plan de pagos. Puedes configurarlo desde Pagos.</p>
        </template>
        <p v-else-if="!selection.client_id" class="ad-lookup__notice">El terreno no tiene comprador asignado. Asígnalo desde el módulo Clientes.</p>
      </template>
    </div>
  </div>
</section>
</template>
<style scoped>
.ad-lookup{display:grid;gap:20px}.ad-lookup__heading h1{font-size:clamp(26px,3vw,35px);letter-spacing:-.04em;color:var(--ad-ink);font-weight:800;margin:5px 0}.ad-lookup__heading p,.ad-lookup__muted,.ad-lookup__hint{color:var(--ad-muted);font-size:13px;line-height:1.6}.ad-lookup__searchbox{display:grid;gap:9px}.ad-lookup__search{display:flex;align-items:center;gap:10px}.ad-lookup__search>svg{flex-shrink:0;color:var(--ad-primary)}.ad-lookup__search .ad-input{flex:1;min-width:0}.ad-lookup__layout{display:grid;grid-template-columns:minmax(260px,.9fr) minmax(0,1.4fr);gap:18px;align-items:start}.ad-lookup__sectionhead{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;gap:12px}.ad-lookup__sectionhead h2{font-size:17px;font-weight:750;color:var(--ad-ink)}.ad-lookup__result{border-top:1px solid var(--ad-border)}.ad-lookup__resultbutton{display:flex;align-items:center;gap:10px;width:100%;padding:14px 2px;background:transparent;border:0;cursor:pointer;text-align:left;color:var(--ad-text)}.ad-lookup__resultbutton:hover{color:var(--ad-primary)}.ad-lookup__resultbutton>span{flex:1;min-width:0}.ad-lookup__resultbutton strong,.ad-lookup__resultbutton small{display:block}.ad-lookup__resultbutton strong{font-size:13px;line-height:1.5}.ad-lookup__resultbutton small{font-size:11px;color:var(--ad-muted);margin-top:3px}.ad-lookup__detail h3{font-size:20px;font-weight:750;color:var(--ad-ink)}.ad-lookup__contact,.ad-lookup__actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap;margin:12px 0}.ad-lookup__contact{color:var(--ad-text);font-size:12px}.ad-lookup__metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}.ad-lookup__metrics article{display:grid;gap:7px;padding:15px;border:1px solid var(--ad-border);border-radius:12px;background:#FAFCFF;min-width:0}.ad-lookup__metrics article span{font-size:11px;color:var(--ad-muted)}.ad-lookup__metrics article strong{font-size:clamp(16px,1.8vw,21px);color:var(--ad-ink);overflow-wrap:anywhere}.ad-lookup__metrics article small{font-size:11px;color:var(--ad-muted)}.ad-lookup__metrics article.is-overdue strong,.ad-lookup__late{color:var(--ad-danger)!important}.ad-lookup__dues{display:flex;justify-content:space-between;flex-wrap:wrap;gap:9px;padding:15px;border:1px solid var(--ad-border);border-radius:12px;margin-bottom:17px;color:var(--ad-text);font-size:12px}.ad-lookup__dues>div{display:flex;align-items:center;gap:8px}.ad-lookup__periods{max-height:270px;overflow:auto;border:1px solid var(--ad-border);border-radius:12px}.ad-lookup__period{display:flex;justify-content:space-between;gap:12px;padding:12px;border-bottom:1px solid var(--ad-border);font-size:12px}.ad-lookup__period span:last-child{text-align:right}.ad-lookup__period:last-child{border:0}.ad-lookup__error{display:flex;gap:8px;align-items:center;padding:12px;border:1px solid #F0CDD2;background:#FFF1F3;border-radius:10px;color:#BE4050;font-size:12px}.ad-lookup__notice{background:#EEF6FF;border:1px solid #DDE7F1;border-radius:10px;padding:12px;margin:12px 0;font-size:12px;color:var(--ad-text)}.ad-lookup__lots{display:grid;gap:9px;margin:16px 0;font-size:12px}.ad-lookup__lotlink{display:flex;justify-content:space-between;align-items:center;gap:8px;background:#fff;border:1px solid var(--ad-border);border-radius:9px;padding:11px;text-align:left;color:var(--ad-text);cursor:pointer}.ad-lookup__lotlink:hover{border-color:var(--ad-primary);color:var(--ad-primary)}@media(max-width:960px){.ad-lookup__layout{grid-template-columns:1fr}}@media(max-width:600px){.ad-lookup__search{flex-wrap:wrap}.ad-lookup__search>svg{display:none}.ad-lookup__search .ad-input{flex-basis:100%}.ad-lookup__metrics{grid-template-columns:1fr 1fr}.ad-lookup__period{flex-direction:column}.ad-lookup__period span:last-child{text-align:left}}@media(max-width:370px){.ad-lookup__metrics{grid-template-columns:1fr}}

/* ANDARMA_F12_PAGO_DIRECTO_CONSULTA: acceso rapido sin anidar botones */
.ad-lookup__result {display:flex;align-items:center;gap:10px;min-width:0}
.ad-lookup__resultbutton {flex:1;min-width:0}
.ad-lookup__quickpay {
  display:inline-flex;align-items:center;justify-content:center;gap:7px;
  min-height:38px;flex:none;padding:8px 11px;border:1px solid #bcdaf7;
  border-radius:9px;background:#edf6ff;color:#075fbb;font:700 11px 'DM Sans',sans-serif;
  white-space:nowrap;cursor:pointer;transition:background .15s,border-color .15s;
}
.ad-lookup__quickpay:hover {background:#dbeeff;border-color:#7cb8f2}
.ad-lookup__quickpay:focus-visible {outline:3px solid #89bfff;outline-offset:2px}
@media(max-width:650px) {
 .ad-lookup__result {flex-wrap:wrap;padding:9px 0}
 .ad-lookup__resultbutton {flex-basis:100%;padding:5px 2px}
 .ad-lookup__quickpay {margin-left:auto;min-height:42px}
 .ad-lookup__actions .ad-btn {flex:1;justify-content:center;min-height:44px}
}
</style>
