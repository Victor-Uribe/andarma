<script setup>
import { Money3Directive } from 'v-money3'
import { mxnMask, decimalMask } from '../../config/numberMasks'
const vMoney3 = Money3Directive
import {ref,computed,onMounted,watch} from 'vue'
import {useRouter,useRoute} from 'vue-router'
import Swal from 'sweetalert2'
import {confirmAction as confirmAdminAction} from '../../utils/adminAlerts'
import {api,post,put,remove} from '../../services/api'
import {can} from '../../utils/andarmaPermissions'
// ANDARMA_FASE4_CAPTURA_RAPIDA
const router=useRouter(),route=useRoute()
// ANDARMA_V36_INVENTORY
const rows=ref([]),devs=ref([]),q=ref(''),dev=ref(String(route.query.desarrollo||'')),status=ref(''),mapped=ref(''),paymentFilter=ref(''),page=ref(1),last=ref(1),total=ref(0)
const loading=ref(false),saving=ref(false),modal=ref(false),editing=ref(null)
const saveAndContinue=ref(false)
const lastCreated=ref('')
const empty=()=>({development_id:'',code:'',block:'',area_m2:'',front_m:'',depth_m:'',price:'',status:'available',description:''})
const form=ref(empty())
// ANDARMA_FASE6_FILTRO_COBRANZA
const statusLabels={available:'Disponible',reserved:'Apartado',sold:'Vendido',inactive:'Inactivo'}
// ANDARMA_FASE2B_FILTROS_20261105: controles locales, no alteran la API.
const activeFilters=computed(()=>[
  q.value.trim() ? {key:'q',label:'Búsqueda',value:q.value.trim()} : null,
  dev.value ? {key:'dev',label:'Desarrollo',value:devs.value.find(d=>String(d.id)===String(dev.value))?.name||'Seleccionado'} : null,
  status.value ? {key:'status',label:'Estado',value:statusLabels[status.value]||status.value} : null,
  mapped.value ? {key:'mapped',label:'Plano',value:mapped.value==='yes'?'Con mapa':'Sin mapa'} : null,
  paymentFilter.value ? {key:'payment',label:'Cobranza',value:({overdue:'Con atrasos',severe:'Atraso grave',current:'Al corriente'})[paymentFilter.value]||paymentFilter.value} : null
].filter(Boolean))
const hasFilters=computed(()=>activeFilters.value.length>0)
function clearFilters(){
  q.value='';dev.value='';status.value='';mapped.value='';paymentFilter.value='';page.value=1
  // Los watchers existentes recargan automáticamente el listado una sola vez.
}
function removeFilter(key){
  if(key==='q')q.value=''
  else if(key==='dev')dev.value=''
  else if(key==='status')status.value=''
  else if(key==='mapped')mapped.value=''
  else if(key==='payment')paymentFilter.value=''
}

const fmt=x=>x===null||x===undefined||x===''?'Sin registrar':new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',maximumFractionDigits:0}).format(Number(x))
const area=x=>x===null||x===undefined||x===''?'Sin registrar':`${new Intl.NumberFormat('es-MX',{maximumFractionDigits:2}).format(Number(x))} m²`
const alertBase={customClass:{popup:'andarma-swal',title:'andarma-swal__title',htmlContainer:'andarma-swal__text',actions:'andarma-swal__actions',confirmButton:'andarma-swal__confirm',cancelButton:'andarma-swal__cancel'},buttonsStyling:false}
function err(e){Swal.fire({...alertBase,icon:'error',title:'No se pudo realizar la operación',text:e.message||'Ocurrió un error'})}
function success(s){Swal.fire({...alertBase,toast:true,position:'top-end',timer:2400,showConfirmButton:false,icon:'success',title:s})}
async function confirmDelete(){return confirmAdminAction({title:'Eliminar terreno',text:'Solo se puede eliminar si no tiene comprador, pagos ni ubicación mapeada.',confirmText:'Sí, eliminar',cancelText:'Conservar',variant:'danger'})}
async function load(){loading.value=true;try{const p=new URLSearchParams({page:page.value,per_page:20});if(q.value)p.set('q',q.value);if(dev.value)p.set('development_id',dev.value);if(status.value)p.set('status',status.value);if(mapped.value)p.set('mapped',mapped.value);if(paymentFilter.value)p.set('payment_filter',paymentFilter.value);const r=await api('/admin/im/inventory?'+p);rows.value=r.data||[];last.value=r.last_page||1;total.value=r.total||0}catch(e){err(e)}finally{loading.value=false}}
async function loadDevs(){try{devs.value=(await api('/admin/im/developments')).data||[]}catch(e){err(e)}}
let t;watch([q,dev,status,mapped,paymentFilter],()=>{page.value=1;clearTimeout(t);t=setTimeout(load,250)})
function newLot(){editing.value=null;saveAndContinue.value=false;lastCreated.value='';form.value=empty();form.value.development_id=dev.value||devs.value[0]?.id||'';modal.value=true}
function editLot(row){saveAndContinue.value=false;lastCreated.value='';editing.value=row;form.value={development_id:row.development_id,code:row.code||'',block:row.block||'',area_m2:row.area_m2??'',front_m:row.front_m??'',depth_m:row.depth_m??'',price:row.price??'',status:row.status,description:row.description||''};modal.value=true}
// ANDARMA_FIX_PRECIO_VMODEL_NUMERO_20261109
// v-money3 puede entregar un string con formato. Nunca convertir NaN a null
// silenciosamente: JSON.stringify(NaN) lo convierte en null.
function parseCapturedNumber(value, field) {
  if (value === '' || value === null || value === undefined) return null
  if (typeof value === 'number') {
    if (Number.isFinite(value) && value >= 0) return value
    throw new Error(`El campo ${field} debe contener un número válido mayor o igual a cero.`)
  }
  const source = String(value).trim().replace(/(?:MXN|\$)/gi, '').replace(/\s/g, '')
  if (!source) return null
  if (!/^[\d.,]+$/.test(source)) throw new Error(`El campo ${field} contiene caracteres no válidos.`)
  let normalized = source
  if (source.includes('.') && source.includes(',')) {
    // Se aceptan tanto 1,234.56 como 1.234,56.
    normalized = source.lastIndexOf(',') > source.lastIndexOf('.')
      ? source.replace(/\./g, '').replace(',', '.')
      : source.replace(/,/g, '')
  } else if (source.includes(',')) {
    normalized = /^\d{1,3}(,\d{3})+$/.test(source)
      ? source.replace(/,/g, '')
      : source.replace(',', '.')
  }
  if (!/^\d+(?:\.\d+)?$/.test(normalized)) throw new Error(`Revisa el número capturado en ${field}.`)
  const number = Number(normalized)
  if (!Number.isFinite(number) || number < 0) throw new Error(`El campo ${field} debe ser válido y no negativo.`)
  return number
}

async function save(){
  if(saving.value)return
  if(!form.value.development_id||!String(form.value.code).trim()){
    return err(new Error('Selecciona un desarrollo y captura un número de lote.'))
  }
  saving.value=true
  try{
    const data={...form.value,development_id:Number(form.value.development_id)}
    for(const k of ['area_m2','front_m','depth_m','price'])data[k]=parseCapturedNumber(data[k],k)
    const wasEditing=Boolean(editing.value)
    if(wasEditing)await put('/admin/im/inventory/'+editing.value.id,data)
    else await post('/admin/im/inventory',data)
    await load()
    if(!wasEditing && saveAndContinue.value){
      lastCreated.value=[data.block,data.code].filter(Boolean).join(' · ')
      // Se conserva el desarrollo y los datos repetidos. El lote siempre debe
      // escribirse de nuevo, evitando crear un duplicado por accidente.
      form.value={...form.value,code:'',status:'available',description:''}
      return
    }
    modal.value=false
    success(wasEditing?'Terreno actualizado':'Terreno creado')
  }catch(e){err(e)}finally{saving.value=false}
}
async function del(row){if(!await confirmDelete())return;try{await remove('/admin/im/inventory/'+row.id);success('Terreno eliminado');await load()}catch(e){err(e)}}
// ANDARMA_V40_WHATSAPP
function whatsappLink(row){
  if(!['yellow','red'].includes(row.payment_tone))return ''
  let phone=String(row.buyer_phone||'').replace(/\D/g,'')
  if(phone.length===10)phone='52'+phone
  else if(phone.length===13&&phone.startsWith('521'))phone='52'+phone.slice(3)
  if(!/^\d{11,15}$/.test(phone))return ''
  const name=String(row.buyer_name||'').trim()
  const property=[row.block,row.code].filter(Boolean).join(' · ')
  const message=`Hola${name?' '+name:''}, te contactamos de ANDARMA para dar seguimiento al estado de cuenta de tu terreno ${property} en ${row.development_name||'nuestro desarrollo'}. ¿Podemos apoyarte a revisar tus pagos?`
  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`
}
function seeMap(row){if(row.mapped_plan_id)router.push('/admin/mapas/'+row.mapped_plan_id+'?lote='+row.id)}
onMounted(async()=>{await loadDevs();await load()})
</script>
<template>
<section class="ad-module ad-u-list space-y-5">
  <header class="flex flex-wrap items-start justify-between gap-4"><div><span class="ad-kicker">GESTIÓN INMOBILIARIA / INVENTARIO</span><h1 class="ad-heading">Propiedades</h1><p class="ad-muted">Terrenos históricos y nuevos en un mismo listado, tengan plano o no.</p></div><div class="flex flex-wrap gap-2"><button class="ad-btn" @click="router.push('/admin/mapas')"><i class="fa-solid fa-map"></i> Mapas</button><button v-can="'properties.create'" class="ad-btn ad-btn--primary" @click="newLot"><i class="fa-solid fa-plus"></i> Nuevo terreno</button></div></header>
  <!-- ANDARMA_UI_LISTADOS_UNIFICADOS_V1: indicadores calculados de datos existentes -->
  <div class="ad-u-stats" aria-label="Resumen del inventario">
    <article class="ad-u-stat"><span class="ad-u-stat__icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><small class="ad-u-stat__label">Propiedades registradas</small><strong>{{ total }}</strong></article>
    <article class="ad-u-stat"><span class="ad-u-stat__icon"><i class="fa-solid fa-list" aria-hidden="true"></i></span><small class="ad-u-stat__label">Mostradas en esta página</small><strong>{{ rows.length }}</strong></article>
    <article class="ad-u-stat"><span class="ad-u-stat__icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></span><small class="ad-u-stat__label">Con comprador en esta página</small><strong>{{ rows.filter(r=>Boolean(r.client_id)).length }}</strong></article>
  </div>
  <div class="ad-card"><div class="flex flex-wrap items-end gap-3"><label class="ad-label grow min-w-48">Buscar<input v-model="q" class="ad-input" placeholder="Lote, manzana o desarrollo"/></label><label class="ad-label grow min-w-40">Desarrollo<select v-model="dev" class="ad-select"><option value="">Todos</option><option v-for="d in devs" :key="d.id" :value="d.id">{{d.name}}</option></select></label><label class="ad-label grow min-w-36">Estado<select v-model="status" class="ad-select"><option value="">Todos</option><option v-for="(label,k) in statusLabels" :key="k" :value="k">{{label}}</option></select></label><label class="ad-label grow min-w-36">Plano<select v-model="mapped" class="ad-select"><option value="">Todos</option><option value="yes">Con mapa</option><option value="no">Sin mapa</option></select></label><label class="ad-label grow min-w-40">Cobranza<select v-model="paymentFilter" class="ad-select" aria-label="Filtrar terrenos por cobranza"><option value="">Todos los estados</option><option value="overdue">Con atrasos</option><option value="severe">Atraso grave (4+ meses)</option><option value="current">Al corriente</option></select></label><button class="ad-btn" @click="load"><i class="fa-solid fa-rotate"></i> Actualizar</button></div></div>
    <!-- ANDARMA_FASE2B_FILTROS_20261105: filtros activos y restablecimiento -->
  <div v-if="hasFilters" class="ad-f2b-active-filters" role="group" aria-label="Filtros activos">
    <span class="ad-f2b-filter-caption">Filtros activos</span>
    <button v-for="filter in activeFilters" :key="filter.key" type="button" class="ad-f2b-filter-chip" :aria-label="'Quitar filtro '+filter.label+': '+filter.value" :title="'Quitar filtro: '+filter.label" @click="removeFilter(filter.key)">
      <span><strong>{{filter.label}}:</strong> {{filter.value}}</span>
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
    <button type="button" class="ad-f2b-clear" @click="clearFilters">Limpiar todos</button>
  </div>
<div class="ad-card ad-f2a-list"><div class="flex items-center justify-between gap-3 mb-3"><h2 class="font-bold text-base">Inventario general</h2><span class="ad-badge">{{total}} registros</span></div><div class="ad-table-wrap"><table class="ad-table ad-inventory-responsive ad-f2a-table"><thead><tr><th>Propiedad</th><th>Desarrollo</th><th>Comprador</th><th>Superficie</th><th>Precio</th><th>Estado</th><th>Cobranza</th><th>Plano</th><th>Acciones</th></tr></thead><tbody><tr v-for="r in rows" :key="r.id"><td data-ad-label="Propiedad"><strong class="ad-f2a-property">{{r.block ? r.block+' · ' : ''}}{{r.code}}</strong></td><td data-ad-label="Desarrollo">{{r.development_name}}</td><td data-ad-label="Comprador"><button v-if="r.client_id && can('clients.view')" class="ad-f2a-buyer-link" type="button" :aria-label="'Abrir expediente de ' + (r.buyer_name||'comprador')" @click="router.push({path:'/admin/clientes',query:{client:r.client_id}})"><i class="fa-solid fa-user" aria-hidden="true"></i><span>{{r.buyer_name||'Ver comprador'}}</span><i class="fa-solid fa-arrow-up-right-from-square ad-f2a-open" aria-hidden="true"></i></button><span v-else :class="['ad-f2a-buyer', {'ad-f2a-buyer--empty':!r.buyer_name}]">{{r.buyer_name||'Sin asignar'}}</span></td><td data-ad-label="Superficie"><span class="ad-f2a-area">{{area(r.area_m2)}}</span></td><td data-ad-label="Precio"><strong class="ad-f2a-price" :class="{'ad-f2a-price--empty':r.price===null||r.price===undefined||r.price===''}">{{fmt(r.price)}}</strong></td><td data-ad-label="Estado"><span class="ad-badge ad-f2a-status" :class="[r.status==='available'?'ad-badge--success':r.status==='sold'?'ad-badge--danger':'','ad-f2a-status--'+r.status]">{{statusLabels[r.status]||r.status}}</span></td><td data-ad-label="Cobranza"><span class="ad-cobranza-badge" :class="'ad-cobranza-badge--'+(r.payment_tone||'neutral')"><i class="fa-solid fa-circle" aria-hidden="true"></i> {{r.payment_label||'No aplica'}}</span></td><td data-ad-label="Plano"><button v-if="r.mapped_plan_id" class="ad-btn" @click="seeMap(r)"><i class="fa-solid fa-location-dot"></i> Ver mapa</button><span v-else class="ad-muted">Sin mapa</span></td><td data-ad-label="Acciones"><div class="ad-actions ad-f2a-actions"><button v-if="r.client_id && can('payments.view')" class="ad-btn" type="button" @click="router.push({path:'/admin/pagos',query:{lot:r.id}})"><i class="fa-solid fa-wallet"></i> Cobranza</button><a v-if="whatsappLink(r)" class="ad-btn ad-btn--whatsapp" :href="whatsappLink(r)" target="_blank" rel="noopener noreferrer" :aria-label="'Contactar por WhatsApp a '+(r.buyer_name||'comprador')" title="Abrir conversación de WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</a><button v-can="'properties.edit'" class="ad-btn" @click="editLot(r)"><i class="fa-solid fa-pen"></i> Editar</button><button v-can="'properties.delete'" class="ad-btn ad-btn--danger" @click="del(r)"><i class="fa-solid fa-trash"></i> Eliminar</button></div></td></tr><tr v-if="loading"><td colspan="9" class="text-center py-10" role="status">Consultando propiedades…</td></tr><tr v-else-if="!rows.length"><td colspan="9" class="text-center py-10">{{hasFilters?'No encontramos propiedades con los filtros seleccionados. Puedes quitar un filtro o limpiar todos.':'Todavía no hay propiedades registradas.'}}</td></tr></tbody></table></div><div class="flex justify-between items-center pt-4"><span class="ad-muted" aria-live="polite">Página {{page}} de {{last}}</span><div class="flex gap-2"><button class="ad-btn" :disabled="page<=1||loading" @click="page--;load()">Anterior</button><button class="ad-btn" :disabled="page>=last||loading" @click="page++;load()">Siguiente</button></div></div></div>
  <div v-if="modal" class="ad-modal-backdrop" @click.self="modal=false"><section class="ad-modal ad-f2c-property-modal" role="dialog" aria-modal="true" aria-label="Formulario de terreno"><header class="ad-modal__header"><div><span class="ad-kicker">PROPIEDADES</span><h2 class="ad-modal-title">{{editing?'Editar terreno':'Nuevo terreno'}}</h2><p class="ad-muted">La ubicación visual es opcional y se asigna desde Mapas.</p></div><button class="ad-icon-btn" aria-label="Cerrar" @click="modal=false"><i class="fa-solid fa-xmark"></i></button></header><form @submit.prevent="save" class="flex flex-col min-h-0"><div class="ad-modal__body space-y-4"><p v-if="lastCreated && !editing" class="ad-f4-notice" role="status"><i class="fa-solid fa-circle-check"></i> Terreno {{lastCreated}} guardado. Captura el siguiente número.</p><div class="ad-form-grid"><div class="ad-f2c-section"><strong>Identificación del terreno</strong><small>Los campos con * son obligatorios</small></div><label class="ad-label ad-form-span">Desarrollo *<select v-model="form.development_id" required class="ad-select"><option value="">Seleccionar</option><option v-for="d in devs" :key="d.id" :value="d.id">{{d.name}}</option></select></label><label class="ad-label">Manzana (opcional)<input v-model.trim="form.block" maxlength="120" class="ad-input" placeholder="Ej. M5"/></label><label class="ad-label">Número de lote *<input v-model.trim="form.code" required maxlength="255" class="ad-input" placeholder="Ej. L01"/></label><div class="ad-f2c-section"><strong>Medidas y precio</strong><small>Datos comerciales del terreno</small></div><label class="ad-label">Superficie (m²)<input v-model="form.area_m2" v-money3="decimalMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input"/></label><label class="ad-label">Precio (MXN)<input v-model="form.price" v-money3="mxnMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input"/></label><label class="ad-label">Frente (m)<input v-model="form.front_m" v-money3="decimalMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input"/></label><label class="ad-label">Fondo (m)<input v-model="form.depth_m" v-money3="decimalMask" type="text" inputmode="decimal" autocomplete="off" class="ad-input"/></label><div class="ad-f2c-section"><strong>Estado y observaciones</strong><small>Información adicional</small></div><label class="ad-label ad-form-span">Disponibilidad<select v-model="form.status" class="ad-select"><option v-for="(label,k) in statusLabels" :key="k" :value="k">{{label}}</option></select></label><label class="ad-label ad-form-span">Observaciones<textarea v-model="form.description" maxlength="5000" class="ad-textarea" rows="3" placeholder="Características u observaciones"></textarea></label></div><p class="ad-hint"><i class="fa-solid fa-circle-info"></i> El comprador y los pagos se administran por separado. No debes cargar una imagen por lote.</p></div><footer class="ad-modal__footer ad-f4-footer"><label v-if="!editing" class="ad-f4-repeat"><input v-model="saveAndContinue" type="checkbox" :disabled="saving"/> Guardar y crear otro</label><button type="button" class="ad-btn" :disabled="saving" @click="modal=false">Cancelar</button><button type="submit" class="ad-btn ad-btn--primary" :disabled="saving"><i class="fa-solid fa-floppy-disk"></i> {{saving?'Guardando...':!editing && saveAndContinue?'Guardar y continuar':'Guardar terreno'}}</button></footer></form></section></div>
</section>
</template>

<style>
/* ANDARMA_FASE2B_FILTROS_20261105: estilo acotado a la vista de inventario. */
.andarma-admin .ad-f2b-active-filters{display:flex;flex-wrap:wrap;align-items:center;gap:7px;min-width:0;margin-top:-6px}
.andarma-admin .ad-f2b-filter-caption{font-size:11px;font-weight:750;color:var(--ad-muted,#72879c);margin-right:3px}
.andarma-admin .ad-f2b-filter-chip{display:inline-flex;align-items:center;gap:9px;max-width:100%;min-height:35px;padding:6px 10px;border:1px solid #bdd9f5;border-radius:9px;background:#f1f7ff;color:var(--ad-ink,#17324e);font:600 11px/1.4 'DM Sans',sans-serif;cursor:pointer}
.andarma-admin .ad-f2b-filter-chip span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.andarma-admin .ad-f2b-filter-chip strong{font-weight:750}
.andarma-admin .ad-f2b-filter-chip:hover{border-color:var(--ad-primary,#0066e0);background:#e7f2ff}
.andarma-admin .ad-f2b-clear{min-height:35px;padding:5px 10px;border:1px solid transparent;border-radius:9px;background:transparent;color:var(--ad-primary,#0066e0);font:750 11px/1.4 'DM Sans',sans-serif;cursor:pointer}
.andarma-admin .ad-f2b-clear:hover{background:#eef6ff}
.andarma-admin :is(.ad-f2b-clear,.ad-f2b-filter-chip):focus-visible{outline:3px solid rgba(0,102,224,.35);outline-offset:2px}
@media(max-width:600px){.andarma-admin .ad-f2b-active-filters{gap:6px}.andarma-admin .ad-f2b-filter-caption{width:100%}.andarma-admin .ad-f2b-filter-chip{max-width:100%}}

/* ANDARMA F4 - complementos con los tokens del administrador */
.andarma-admin .ad-f4-footer{flex-wrap:wrap}
.andarma-admin .ad-f4-repeat{display:inline-flex;align-items:center;gap:9px;margin-right:auto;font-size:12px;font-weight:650;color:var(--ad-text);cursor:pointer}
.andarma-admin .ad-f4-repeat input{width:16px;height:16px;accent-color:var(--ad-primary)}
.andarma-admin .ad-f4-notice{display:flex;align-items:center;gap:9px;padding:11px 13px;background:var(--ad-success-bg);border:1px solid #CDEBDB;border-radius:10px;color:var(--ad-success);font-size:12px;font-weight:650}
@media(max-width:600px){.andarma-admin .ad-f4-repeat{flex-basis:100%}.andarma-admin .ad-f4-footer{justify-content:stretch}.andarma-admin .ad-f4-footer>.ad-btn{flex:1}}

/* ANDARMA_FASE2A_LISTADO_20261105: estilos acotados al inventario */
.andarma-admin .ad-f2a-table td {vertical-align:middle}
.andarma-admin .ad-f2a-property {display:block;color:var(--ad-ink,#17324e);font-weight:800;line-height:1.4;white-space:normal}
.andarma-admin .ad-f2a-buyer-link {display:inline-flex;gap:7px;align-items:center;max-width:100%;padding:5px 0;border:0;background:transparent;color:var(--ad-primary,#0066e0);font:inherit;font-weight:700;cursor:pointer;text-align:left;line-height:1.4}
.andarma-admin .ad-f2a-buyer-link span {min-width:0;overflow-wrap:anywhere}
.andarma-admin .ad-f2a-buyer-link:hover span {text-decoration:underline}
.andarma-admin .ad-f2a-buyer-link:focus-visible {outline:3px solid rgba(0,102,224,.3);outline-offset:3px;border-radius:3px}
.andarma-admin .ad-f2a-buyer-link .ad-f2a-open {opacity:.55;font-size:10px;flex:none}
.andarma-admin .ad-f2a-buyer {font-weight:650;overflow-wrap:anywhere}
.andarma-admin .ad-f2a-buyer--empty {font-weight:450;color:var(--ad-muted,#72879c)}
.andarma-admin .ad-f2a-price {color:#17324e;font-weight:750;white-space:nowrap;font-variant-numeric:tabular-nums}
.andarma-admin .ad-f2a-price--empty {color:var(--ad-muted,#72879c);font-weight:450;white-space:normal}
.andarma-admin .ad-f2a-area {font-variant-numeric:tabular-nums;white-space:nowrap}
.andarma-admin .ad-f2a-status--reserved {background:#fff6e8;color:#946315;border-color:#f2deb5}
.andarma-admin .ad-f2a-status--inactive {background:#f2f5f8;color:#64748b;border-color:#e1e7ee}
.andarma-admin .ad-f2a-actions {gap:6px}
.andarma-admin .ad-f2a-table td[colspan] {white-space:normal}
@media(max-width:767px) {
 .andarma-admin .ad-f2a-table td[data-ad-label="Propiedad"] {grid-column:1/-1}
 .andarma-admin .ad-f2a-table td[data-ad-label="Comprador"] {grid-column:1/-1}
 .andarma-admin .ad-f2a-table .ad-f2a-price {white-space:normal}
 .andarma-admin .ad-f2a-table .ad-f2a-area {white-space:normal}
 .andarma-admin .ad-f2a-table .ad-f2a-actions {justify-content:flex-start}
 .andarma-admin .ad-f2a-table .ad-f2a-actions .ad-btn {min-height:42px}
}

/* ANDARMA_FASE2C_FORMULARIO_PROPIEDADES */
/* Estilos acotados al formulario de terrenos, sin alterar otros modales. */
.andarma-admin .ad-f2c-property-modal {
  width: min(760px, 100%);
}
.andarma-admin .ad-f2c-property-modal .ad-modal__header {
  align-items: flex-start;
}
.andarma-admin .ad-f2c-property-modal .ad-modal__header > div {
  min-width: 0;
}
.andarma-admin .ad-f2c-property-modal .ad-f2c-section {
  grid-column: 1 / -1;
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  min-width: 0;
  padding: 12px 0 2px;
  margin: 0;
  border-top: 1px solid var(--ad-border, #DDE7F1);
}
.andarma-admin .ad-f2c-property-modal .ad-f2c-section:first-child {
  border-top: 0;
  padding-top: 0;
}
.andarma-admin .ad-f2c-property-modal .ad-f2c-section strong {
  color: var(--ad-ink, #17324E);
  font-size: 12px;
  line-height: 1.4;
  font-weight: 750;
}
.andarma-admin .ad-f2c-property-modal .ad-f2c-section small {
  color: var(--ad-muted, #72879C);
  font-size: 11px;
  line-height: 1.4;
  text-align: right;
}
.andarma-admin .ad-f2c-property-modal :is(.ad-input, .ad-select, .ad-textarea) {
  max-width: 100%;
}
.andarma-admin .ad-f2c-property-modal .ad-modal__footer {
  gap: 10px;
}
.andarma-admin .ad-f2c-property-modal .ad-modal__footer .ad-btn--primary {
  min-width: 148px;
}
@media (max-width: 640px) {
  .andarma-admin .ad-f2c-property-modal .ad-f2c-section {
    flex-wrap: wrap;
    gap: 2px 8px;
  }
  .andarma-admin .ad-f2c-property-modal .ad-f2c-section small {
    text-align: left;
  }
  .andarma-admin .ad-f2c-property-modal .ad-modal__footer .ad-btn--primary {
    min-width: 0;
  }
}
</style>
