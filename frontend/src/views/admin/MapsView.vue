<script setup>
import {ref,computed,onMounted,watch,nextTick} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import Swal from 'sweetalert2'
import {can} from '../../utils/andarmaPermissions'
import {confirmAction as confirmAdminAction} from '../../utils/adminAlerts'
import {api,post,put,remove} from '../../services/api'
const route=useRoute(),router=useRouter()
// ANDARMA_FASE10_PAN_MAPA
const mapDragging=ref(false)
let mapPan=null
let mapPanSuppressUntil=0
function mapPanDown(event){
  if(drawing.value || (event.pointerType==='mouse' && event.button!==0))return
  const scroll=event.currentTarget
  mapPan={id:event.pointerId,x:event.clientX,y:event.clientY,left:scroll.scrollLeft,top:scroll.scrollTop,moved:false}
}
function mapPanMove(event){
  if(!mapPan || drawing.value || mapPan.id!==event.pointerId)return
  const scroll=event.currentTarget
  const dx=event.clientX-mapPan.x,dy=event.clientY-mapPan.y
  if(!mapPan.moved && Math.hypot(dx,dy)<7)return
  if(!mapPan.moved){
    mapPan.moved=true
    mapDragging.value=true
    try{scroll.setPointerCapture(event.pointerId)}catch(_e){}
  }
  scroll.scrollLeft=mapPan.left-dx
  scroll.scrollTop=mapPan.top-dy
  if(event.cancelable)event.preventDefault()
}
function mapPanEnd(event){
  if(!mapPan || mapPan.id!==event.pointerId)return
  if(mapPan.moved)mapPanSuppressUntil=Date.now()+400
  mapPan=null
  mapDragging.value=false
  const scroll=event.currentTarget
  try{if(scroll.hasPointerCapture(event.pointerId))scroll.releasePointerCapture(event.pointerId)}catch(_e){}
}

const mapSearch=ref('') // filtro local: no agrega solicitudes al servidor
const plans=ref([]),developments=ref([]),superadmin=ref(false),plan=ref(null),polygons=ref([]),available=ref([]),loading=ref(false),modal=ref(false),saving=ref(false),imageEl=ref(null),viewport=ref(null)
const selected=ref(null),drawing=ref(false),draft=ref([]),lotId=ref(''),editGeometryId=ref(null)
const selectedDialog=ref(null),selectedCloseButton=ref(null)
let selectedReturnFocus=null
// ANDARMA_FASE9_ZOOM_STATE
const zoomPercent=ref(100)
const mapZoomStyle=computed(()=>({width:zoomPercent.value+'%', minWidth:zoomPercent.value>=100?'480px':'0px'}))
async function setMapZoom(next){
  const target=Math.max(75,Math.min(250,Math.round(Number(next)/25)*25))
  if(target===zoomPercent.value)return
  const scroll=viewport.value?.parentElement
  const previousWidth=scroll?.scrollWidth||0
  const previousHeight=scroll?.scrollHeight||0
  const centerX=scroll?(scroll.scrollLeft+scroll.clientWidth/2)/Math.max(1,previousWidth):0
  const centerY=scroll?(scroll.scrollTop+scroll.clientHeight/2)/Math.max(1,previousHeight):0
  zoomPercent.value=target
  await nextTick()
  if(scroll){
    scroll.scrollLeft=centerX*scroll.scrollWidth-scroll.clientWidth/2
    scroll.scrollTop=centerY*scroll.scrollHeight-scroll.clientHeight/2
  }
}
const planForm=ref({development_id:'',title:'',image:null})
// ANDARMA_V36_MAPS
const renameModal=ref(false),renameTarget=ref(null),renameTitle=ref(''),renameSaving=ref(false)
const filterDevelopmentId=computed(()=>String(route.query.desarrollo||''))
const visiblePlans=computed(()=>plans.value.filter(p=>{
  const matchesDev=!filterDevelopmentId.value||String(p.development_id)===filterDevelopmentId.value
  const q=mapSearch.value.trim().toLocaleLowerCase('es-MX')
  return matchesDev && (!q || [p.title,p.development_name].some(v=>String(v||'').toLocaleLowerCase('es-MX').includes(q)))
}))

const theme={customClass:{popup:'andarma-swal',title:'andarma-swal__title',htmlContainer:'andarma-swal__text',actions:'andarma-swal__actions',confirmButton:'andarma-swal__confirm',cancelButton:'andarma-swal__cancel'},buttonsStyling:false}
const statusLabel={available:'Disponible',reserved:'Apartado',sold:'Vendido',inactive:'Inactivo'}
const mappedCount=computed(()=>polygons.value.length)
const pointsString=p=>(p||[]).map(v=>v.x+','+v.y).join(' ')
const fmt=x=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',maximumFractionDigits:0}).format(Number(x||0))
const statusColor=s=>({available:'#12A677',reserved:'#EA9C34',sold:'#D3545B',inactive:'#7C90A5'}[s]||'#0066E0')
const center=p=>{const pts=p.points||[];return pts.length?{x:pts.reduce((a,c)=>a+Number(c.x),0)/pts.length,y:pts.reduce((a,c)=>a+Number(c.y),0)/pts.length}:{x:0,y:0}}
function err(e){Swal.fire({...theme,icon:'error',title:'No se pudo completar',text:e.message||'Revisa los datos e inténtalo nuevamente.'})}
function ok(s){Swal.fire({...theme,toast:true,position:'top-end',timer:2400,showConfirmButton:false,icon:'success',title:s})}
async function confirmAction(title,text){return confirmAdminAction({title,text,confirmText:title.startsWith('Eliminar')?'Sí, eliminar':'Sí, desvincular',cancelText:'Conservar',variant:'danger'})}
async function loadList(){loading.value=true;try{const [a,b,c]=await Promise.all([api('/admin/im/maps'),api('/admin/im/developments'),api('/admin/im/permissions')]);plans.value=a.data||[];developments.value=b.data||[];superadmin.value=!!c.superadmin}catch(e){err(e)}finally{loading.value=false}}
async function openPlan(id){loading.value=true;try{const r=await api('/admin/im/maps/'+id);plan.value=r.data;polygons.value=r.polygons||[];available.value=r.available_lots||[];drawing.value=false;editGeometryId.value=null;draft.value=[];lotId.value='';selected.value=null;const target=Number(route.query.lote);if(target){selected.value=polygons.value.find(p=>p.lot_id===target)||null}await nextTick()}catch(e){err(e)}finally{loading.value=false}}
watch(()=>route.params.id,(id)=>{if(id)openPlan(id);else{plan.value=null;selected.value=null;drawing.value=false;draft.value=[];loadList()}},{immediate:false})
function openCreate(){planForm.value={development_id:developments.value[0]?.id||'',title:'',image:null};modal.value=true}
function imageChosen(event){planForm.value.image=event.target.files?.[0]||null}
function openRename(p){if(!superadmin.value)return;renameTarget.value=p;renameTitle.value=p.title||'';renameModal.value=true}
async function saveRename(){
  if(renameSaving.value||!renameTarget.value)return
  const title=renameTitle.value.trim()
  if(title.length<3){err(new Error('Escribe un nombre de al menos 3 caracteres.'));return}
  renameSaving.value=true
  try{
    const r=await put('/admin/im/maps/'+renameTarget.value.id+'/title',{title})
    if(plan.value?.id===renameTarget.value.id)plan.value={...plan.value,title:r.data.title}
    const row=plans.value.find(p=>p.id===renameTarget.value.id)
    if(row)row.title=r.data.title
    renameModal.value=false
    ok('Nombre del mapa actualizado')
  }catch(e){err(e)}finally{renameSaving.value=false}
}

async function create(){if(saving.value)return;if(!planForm.value.development_id||!planForm.value.title.trim()||!planForm.value.image){err(new Error('Selecciona desarrollo, título e imagen del plano.'));return}saving.value=true;try{const fd=new FormData();fd.append('development_id',planForm.value.development_id);fd.append('title',planForm.value.title);fd.append('image',planForm.value.image);const r=await post('/admin/im/maps',fd);modal.value=false;ok('Mapa creado');await loadList();router.push('/admin/mapas/'+r.data.id)}catch(e){err(e)}finally{saving.value=false}}
async function delPlan(p){if(!await confirmAction('Eliminar plano','Solo podrás eliminar mapas sin polígonos. El inventario de lotes nunca se eliminará.'))return;try{await remove('/admin/im/maps/'+p.id);ok('Mapa eliminado');await loadList()}catch(e){err(e)}}
function begin(){if(!superadmin.value)return;drawing.value=true;draft.value=[];editGeometryId.value=null;selected.value=null;lotId.value=''}
function redraw(){if(!superadmin.value||!selected.value)return;editGeometryId.value=selected.value.id;draft.value=[...selected.value.points.map(p=>({...p}))];lotId.value=String(selected.value.lot_id);drawing.value=true}
function cancelDraw(){drawing.value=false;draft.value=[];editGeometryId.value=null;lotId.value=''}
function clickImage(e){if(!drawing.value || !superadmin.value || !viewport.value || draft.value.length>=40)return;const bounds=viewport.value.getBoundingClientRect();if(!bounds.width||!bounds.height)return;const x=Math.max(0,Math.min(1000,((e.clientX-bounds.left)/bounds.width)*1000));const y=Math.max(0,Math.min(1000,((e.clientY-bounds.top)/bounds.height)*1000));draft.value=[...draft.value,{x:Math.round(x*10)/10,y:Math.round(y*10)/10}]}
async function savePolygon(){if(saving.value)return;if(draft.value.length<3){err(new Error('Marca al menos tres vértices del terreno.'));return}if(!editGeometryId.value&&!lotId.value){err(new Error('Selecciona el terreno que corresponde a este polígono.'));return}saving.value=true;try{if(editGeometryId.value)await put('/admin/im/maps/'+plan.value.id+'/polygons/'+editGeometryId.value,{points:draft.value});else await post('/admin/im/maps/'+plan.value.id+'/polygons',{lot_id:Number(lotId.value),points:draft.value});ok(editGeometryId.value?'Polígono actualizado':'Lote vinculado al mapa');await openPlan(plan.value.id)}catch(e){err(e)}finally{saving.value=false}}
async function delPolygon(){if(!selected.value)return;if(!await confirmAction('Desvincular polígono','Solo se elimina la ubicación visual; la propiedad, su comprador y pagos se conservan.'))return;try{await remove('/admin/im/maps/'+plan.value.id+'/polygons/'+selected.value.id);ok('Polígono desvinculado');await openPlan(plan.value.id)}catch(e){err(e)}}
function selectedPolygon(poly){if(drawing.value || Date.now()<mapPanSuppressUntil)return;selectedReturnFocus=typeof document!=='undefined'?document.activeElement:null;selected.value=poly}
function closeSelected(){selected.value=null}
function onMapDetailKeydown(event){
  if(event.key==='Escape'){event.preventDefault();closeSelected();return}
  if(event.key!=='Tab'||!selectedDialog.value)return
  const selectors='a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'
  const nodes=Array.from(selectedDialog.value.querySelectorAll(selectors)).filter(el=>el.getClientRects().length && !el.hidden && getComputedStyle(el).visibility!=='hidden')
  if(!nodes.length){event.preventDefault();selectedDialog.value.focus();return}
  const first=nodes[0],last=nodes[nodes.length-1]
  if(event.shiftKey && (document.activeElement===first||document.activeElement===selectedDialog.value)) {event.preventDefault();last.focus()}
  else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus()}
}
watch(selected,async (value,previous)=>{
  if(value && !drawing.value){
    await nextTick()
    selectedCloseButton.value?.focus({preventScroll:true})
  } else if(previous && !value){
    await nextTick()
    if(selectedReturnFocus && typeof selectedReturnFocus.focus==='function' && selectedReturnFocus.isConnected){selectedReturnFocus.focus({preventScroll:true})}
    selectedReturnFocus=null
  }
})
onMounted(async()=>{await loadList();if(route.params.id)await openPlan(route.params.id)})
</script>
<template>
<section class="ad-module ad-maps-v8 space-y-5">
<header class="flex flex-wrap items-start justify-between gap-3"><div><span class="ad-kicker">INVENTARIO / PLANOS INTERACTIVOS</span><h1 class="ad-heading">{{plan ? plan.title : 'Mapas'}}</h1><p class="ad-muted">{{plan ? plan.development_name+' · '+mappedCount+' polígonos vinculados':'Los desarrollos pueden tener cero, uno o varios planos; el inventario funciona de manera independiente.'}}</p></div><div class="flex flex-wrap gap-2"><button class="ad-btn" @click="router.push('/admin/propiedades')"><i class="fa-solid fa-list"></i> Propiedades</button><button v-if="plan" class="ad-btn" @click="router.push('/admin/mapas')"><i class="fa-solid fa-arrow-left"></i> Volver</button><button v-can="'maps.create'" v-if="can('maps.create')&&!plan" class="ad-btn ad-btn--primary" @click="openCreate"><i class="fa-solid fa-plus"></i> Nuevo mapa</button></div></header>
<div v-if="!plan" class="ad-card ad-maps-v8__list">
  <div class="ad-maps-v8__list-head">
    <div class="ad-maps-v8__list-heading"><span class="ad-kicker">BIBLIOTECA DE PLANOS</span><h2>Planos registrados</h2><p class="ad-muted">Consulta los planos de tus desarrollos y administra sus lotes mapeados.</p></div>
    <span class="ad-badge ad-maps-v8__count">{{visiblePlans.length}} {{visiblePlans.length===1?'mapa':'mapas'}}</span>
  </div>
  <div class="ad-maps-v8__toolbar">
    <label class="ad-maps-v8__search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input v-model="mapSearch" type="search" placeholder="Buscar plano o desarrollo" aria-label="Buscar plano o desarrollo"/></label>
    <span class="ad-maps-v8__helper"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Abre un plano para consultar o editar sus lotes.</span>
  </div><div v-if="!plans.length" class="ad-empty">Todavía no hay mapas registrados. Los terrenos seguirán apareciendo en Propiedades.</div><div v-else-if="!visiblePlans.length" class="ad-empty">No hay planos que coincidan con tu búsqueda.</div><div v-else class="ad-mapcards" :class="{'ad-maps-v8__single':visiblePlans.length===1}"><article v-for="p in visiblePlans" :key="p.id" class="ad-mapcard ad-maps-v8__item"><div class="ad-maps-v8__thumb"><img :src="p.image_url" :alt="p.title" loading="lazy"/></div><div class="ad-maps-v8__item-body"><span class="ad-maps-v8__item-kicker">PLANO DEL DESARROLLO</span><h3 class="font-bold">{{p.title}}</h3><p class="ad-muted">{{p.development_name}}</p><span class="ad-badge">{{p.geometry_count}} lotes mapeados</span><div class="ad-actions ad-maps-v8__actions"><button class="ad-btn ad-btn--primary" @click="router.push('/admin/mapas/'+p.id)"><i class="fa-solid fa-eye"></i> Ver mapa</button><button v-can="'maps.edit'" v-if="superadmin" class="ad-btn" @click="openRename(p)"><i class="fa-solid fa-pen"></i> Renombrar</button><button v-can="'maps.delete'" v-if="can('maps.delete')" class="ad-btn ad-btn--danger" @click="delPlan(p)"><i class="fa-solid fa-trash"></i> Eliminar</button></div></div></article></div></div>
<template v-else><div class="ad-card ad-maps-v8__map-panel"><div class="flex flex-wrap items-center justify-between gap-3 mb-4 ad-maps-v8__map-head"><div><span class="ad-kicker">ADMINISTRACIÓN DEL PLANO</span><h2 class="font-bold">Plano interactivo</h2><p class="ad-muted">{{superadmin?'Haz clic sobre un polígono para consultarlo o editarlo.':'Selecciona un lote coloreado para ver su información.'}}</p></div><div v-if="superadmin" class="flex flex-wrap gap-2"><button v-can="'maps.edit'" v-if="!drawing" class="ad-btn" @click="openRename(plan)"><i class="fa-solid fa-pen"></i> Renombrar mapa</button><button v-can="'maps.edit'" v-if="!drawing" class="ad-btn ad-btn--primary" @click="begin"><i class="fa-solid fa-draw-polygon"></i> Dibujar lote</button><button v-if="drawing" class="ad-btn" @click="cancelDraw">Cancelar dibujo</button></div></div>
<div class="ad-maps-v8__map-tip"><i class="fa-solid fa-arrow-pointer" aria-hidden="true"></i><span>Selecciona un lote coloreado para ver sus datos. El dibujo se activa únicamente con «Dibujar lote».</span><span class="ad-maps-v8__mapped">{{mappedCount}} {{mappedCount===1?'lote mapeado':'lotes mapeados'}}</span></div>
<!-- ANDARMA_FASE9_ZOOM_CONTROLS -->
<div class="ad-maps-v9-zoom" role="group" aria-label="Controles de zoom del plano">
  <span class="ad-maps-v9-zoom-label"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i> Zoom</span>
  <button type="button" class="ad-maps-v9-zoom-btn" aria-label="Alejar plano" title="Alejar" :disabled="zoomPercent<=75" @click="setMapZoom(zoomPercent-25)"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
  <output class="ad-maps-v9-zoom-value" aria-live="polite">{{zoomPercent}}%</output>
  <button type="button" class="ad-maps-v9-zoom-btn" aria-label="Acercar plano" title="Acercar" :disabled="zoomPercent>=250" @click="setMapZoom(zoomPercent+25)"><i class="fa-solid fa-plus" aria-hidden="true"></i></button>
  <button type="button" class="ad-maps-v9-zoom-reset" :disabled="zoomPercent===100" @click="setMapZoom(100)">Restablecer</button>
</div>
<div class="ad-plan-scroll ad-pan-scroll" :class="{'ad-pan-scroll--drawing':drawing,'ad-pan-scroll--dragging':mapDragging}"
  @pointerdown="mapPanDown" @pointermove="mapPanMove" @pointerup="mapPanEnd"
  @pointercancel="mapPanEnd" @lostpointercapture="mapPanEnd"><div ref="viewport" class="ad-plan-canvas" :style="mapZoomStyle" :class="{drawing:drawing}" @click="clickImage"><img ref="imageEl" :src="plan.image_url" :alt="plan.title" draggable="false"/><svg class="ad-plan-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-label="Polígonos de los terrenos"><g v-for="poly in polygons" :key="poly.id" @click.stop="selectedPolygon(poly)"><polygon :points="pointsString(poly.points)" :fill="statusColor(poly.status)" :fill-opacity="selected?.id===poly.id ? .48 : .25" :stroke="selected?.id===poly.id ? '#003A8C' : statusColor(poly.status)" stroke-width="3" vector-effect="non-scaling-stroke" class="ad-shape"/><text :x="center(poly).x" :y="center(poly).y" text-anchor="middle" dominant-baseline="middle" fill="#102F4B" font-size="20" font-weight="750" style="pointer-events:none">{{poly.code}}</text></g><polyline v-if="drawing && draft.length" :points="pointsString(draft)" fill="none" stroke="#0066E0" stroke-width="3" vector-effect="non-scaling-stroke"/><circle v-for="(pt,i) in draft" :key="i" :cx="pt.x" :cy="pt.y" r="6" fill="#fff" stroke="#0066E0" stroke-width="2" vector-effect="non-scaling-stroke"/></svg></div></div>
<div class="ad-legend ad-maps-v8__legend"><span><i style="background:#12A677"></i> Disponible</span><span><i style="background:#EA9C34"></i> Apartado</span><span><i style="background:#D3545B"></i> Vendido</span><span><i style="background:#7C90A5"></i> Inactivo</span></div></div>
<div v-if="superadmin&&drawing" class="ad-card space-y-3"><h2 class="font-bold">{{editGeometryId?'Modificar trazado':'Nuevo polígono'}}</h2><p class="ad-muted">Marca vértices sobre el plano siguiendo el contorno del lote. Se guardan coordenadas relativas a la imagen.</p><div class="flex flex-wrap gap-3 items-end"><label v-if="!editGeometryId" class="ad-label grow min-w-48">Terreno<select v-model="lotId" class="ad-select"><option value="">Seleccionar lote del desarrollo</option><option v-for="lot in available" :key="lot.id" :value="String(lot.id)">{{lot.block?lot.block+' · ':''}}{{lot.code}} · {{statusLabel[lot.status]}}</option></select></label><div class="ad-badge">{{draft.length}} vértices</div><button class="ad-btn" :disabled="!draft.length" @click="draft=draft.slice(0,-1)"><i class="fa-solid fa-rotate-left"></i> Deshacer punto</button><button class="ad-btn" @click="draft=[]"><i class="fa-solid fa-eraser"></i> Reiniciar</button><button v-can="'maps.edit'" class="ad-btn ad-btn--primary" :disabled="saving || draft.length<3 || (!editGeometryId&&!lotId)" @click="savePolygon"><i class="fa-solid fa-floppy-disk"></i> Guardar polígono</button></div></div>
<!-- ANDARMA_FASE9_MODAL_ZOOM_MAPA: modal teletransportado al body -->
<Teleport to="body">
  <div v-if="selected && !drawing" class="ad-maplot-v9-backdrop" @click.self="closeSelected" @keydown="onMapDetailKeydown">
    <section ref="selectedDialog" class="ad-maplot-v9-dialog" role="dialog" aria-modal="true" aria-labelledby="ad-maplot-v9-title" tabindex="-1">
      <header class="ad-maplot-v9-header">
        <div class="ad-maplot-v9-heading">
          <span class="ad-kicker">TERRENO SELECCIONADO</span>
          <div class="ad-maplot-v9-titleline">
            <h2 id="ad-maplot-v9-title">{{[selected.block,selected.code].filter(Boolean).join(' · ')}}</h2>
            <span class="ad-badge" :class="selected.status==='available'?'ad-badge--success':selected.status==='sold'?'ad-badge--danger':''">{{statusLabel[selected.status]||'Estado sin definir'}}</span>
          </div>
          <p>Información del terreno dentro del plano interactivo.</p>
        </div>
        <button ref="selectedCloseButton" type="button" class="ad-maplot-v9-close" aria-label="Cerrar ficha del terreno" @click="closeSelected"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </header>

      <div class="ad-maplot-v9-body">
        <div class="ad-maplot-v9-metrics">
          <div class="ad-maplot-v9-metric"><small>Superficie</small><strong>{{selected.area_m2 ? Number(selected.area_m2).toLocaleString('es-MX')+' m²' : 'No registrada'}}</strong></div>
          <div class="ad-maplot-v9-metric"><small>Precio de referencia</small><strong>{{selected.price !== null && selected.price !== undefined ? fmt(selected.price) : 'Por definir'}}</strong></div>
        </div>

        <div class="ad-maplot-v9-client">
          <span class="ad-maplot-v9-avatar"><i class="fa-solid fa-user" aria-hidden="true"></i></span>
          <div class="ad-maplot-v9-client-copy">
            <small>Comprador / titular</small>
            <strong v-if="selected.client_id && can('clients.view')">{{selected.buyer_name||'Cliente asignado'}}</strong>
            <strong v-else-if="selected.client_id">Propiedad asignada</strong>
            <strong v-else>Sin comprador asignado</strong>
            <small v-if="selected.client_id && can('clients.view') && selected.buyer_phone">{{selected.buyer_phone}}</small>
          </div>
          <div class="ad-maplot-v9-client-actions">
            <button v-if="selected.client_id && can('clients.view')" class="ad-btn" type="button" @click="router.push({path:'/admin/clientes',query:{client:selected.client_id}})"><i class="fa-solid fa-address-card"></i> Ver expediente</button>
            <button v-if="selected.client_id && can('payments.view')" class="ad-btn ad-btn--primary" type="button" @click="router.push({path:'/admin/pagos',query:{lot:selected.lot_id}})"><i class="fa-solid fa-wallet"></i> Estado de cuenta</button>
          </div>
        </div>
      </div>
      <footer class="ad-maplot-v9-footer">
        <div class="ad-maplot-v9-manage">
          <button v-if="superadmin && can('maps.edit')" v-can="'maps.edit'" class="ad-btn" type="button" @click="redraw"><i class="fa-solid fa-pen"></i> Editar trazado</button>
          <button v-if="superadmin && can('maps.delete')" v-can="'maps.delete'" class="ad-btn ad-btn--danger" type="button" @click="delPolygon"><i class="fa-solid fa-unlink"></i> Desvincular</button>
        </div>
        <button type="button" class="ad-btn ad-btn--primary ad-maplot-v9-return" @click="closeSelected">Volver al mapa</button>
      </footer>
    </section>
  </div>
</Teleport>

</template>
<div v-if="modal" class="ad-modal-backdrop" @click.self="modal=false"><section class="ad-modal" role="dialog" aria-modal="true" aria-label="Nuevo mapa"><header class="ad-modal__header"><div><span class="ad-kicker">DESARROLLO / MAPAS</span><h2 class="ad-modal-title">Nuevo mapa</h2><p class="ad-muted">Solo Superadministrador. Puedes agregar varios planos para un mismo desarrollo.</p></div><button class="ad-icon-btn" aria-label="Cerrar" @click="modal=false"><i class="fa-solid fa-xmark"></i></button></header><form class="flex flex-col min-h-0" @submit.prevent="create"><div class="ad-modal__body space-y-4"><label class="ad-label">Desarrollo *<select v-model="planForm.development_id" class="ad-select" required><option value="">Seleccionar desarrollo</option><option v-for="d in developments" :key="d.id" :value="d.id">{{d.name}}</option></select></label><label class="ad-label">Nombre del plano *<input v-model="planForm.title" required maxlength="180" class="ad-input" placeholder="Ej. Plano general — Etapa 1"/></label><label class="ad-label">Imagen del plano *<input type="file" accept="image/jpeg,image/png,image/webp" class="ad-input" required @change="imageChosen"/></label><p class="ad-hint">JPG, PNG o WebP, máximo 5 MB. Usa el mismo plano para dibujar los polígonos y no alteres su encuadre después.</p></div><footer class="ad-modal__footer"><button type="button" class="ad-btn" @click="modal=false">Cancelar</button><button type="submit" class="ad-btn ad-btn--primary" :disabled="saving"><i class="fa-solid fa-upload"></i> {{saving?'Guardando...':'Guardar mapa'}}</button></footer></form></section></div>
<div v-if="renameModal" class="ad-modal-backdrop" @click.self="renameModal=false">
  <section class="ad-modal" role="dialog" aria-modal="true" aria-labelledby="ad-map-rename-title">
    <header class="ad-modal__header"><div><span class="ad-kicker">MAPAS / ADMINISTRACIÓN</span><h2 id="ad-map-rename-title" class="ad-modal-title">Cambiar nombre del mapa</h2><p class="ad-muted">Se conservarán la imagen, la ubicación y los polígonos existentes.</p></div><button class="ad-icon-btn" aria-label="Cerrar" @click="renameModal=false"><i class="fa-solid fa-xmark"></i></button></header>
    <form @submit.prevent="saveRename"><div class="ad-modal__body space-y-4"><label class="ad-label">Nombre del mapa *<input v-model="renameTitle" class="ad-input" minlength="3" maxlength="180" required autofocus placeholder="Ej. Plano general — Etapa 1"/></label><p class="ad-hint"><i class="fa-solid fa-circle-info"></i> Solo el Superadministrador puede cambiar este dato.</p></div><footer class="ad-modal__footer"><button type="button" class="ad-btn" @click="renameModal=false">Cancelar</button><button type="submit" class="ad-btn ad-btn--primary" :disabled="renameSaving"><i class="fa-solid fa-floppy-disk"></i> {{renameSaving?'Guardando...':'Guardar nombre'}}</button></footer></form>
  </section>
</div>
</section>
</template>

<style>
/* ANDARMA_MAPAS_REPARACION_V8: estilos encapsulados solo en Mapas */
.andarma-admin .ad-maps-v8{display:block;width:100%;min-width:0;max-width:none}
.andarma-admin .ad-maps-v8>header{width:100%;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.andarma-admin .ad-maps-v8>header>div:first-child{flex:1 1 280px;min-width:0}
.andarma-admin .ad-maps-v8>header>.flex{flex:0 0 auto;display:flex;align-items:center;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.andarma-admin .ad-maps-v8>.ad-card{display:block;width:100%;min-width:0;padding:22px 24px;background:#fff;border:1px solid var(--ad-border,#dce8f4);border-radius:19px;box-shadow:0 8px 30px rgba(15,46,77,.045)}
/* Lista de planos: tarjeta horizontal, aprovechando el ancho disponible */
.andarma-admin .ad-maps-v8 .ad-mapcards{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,360px),1fr));gap:18px;align-items:start}
.andarma-admin .ad-maps-v8 .ad-mapcard{display:flex;flex-direction:column;width:100%;min-width:0;background:#fff;border:1px solid #dfe8f2;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,46,77,.04);transition:box-shadow .17s,transform .17s}
.andarma-admin .ad-maps-v8 .ad-mapcard:hover{box-shadow:0 12px 35px rgba(15,46,77,.09);transform:translateY(-2px)}
.andarma-admin .ad-maps-v8 .ad-mapcard>img{display:block;width:100%;height:220px;object-fit:contain;object-position:center;background:#f7fafe;border-bottom:1px solid #e7edf5}
.andarma-admin .ad-maps-v8 .ad-mapcard>div{display:flex;flex-direction:column;gap:9px;padding:18px}
.andarma-admin .ad-maps-v8 .ad-mapcard h3{font-size:17px;line-height:1.3;color:#17324e}
.andarma-admin .ad-maps-v8 .ad-mapcard .ad-actions{display:flex;align-items:center;justify-content:flex-start;flex-wrap:wrap;gap:9px;margin-top:6px}
/* Vista de un plano: el lienzo tiene TODO el ancho de su tarjeta */
.andarma-admin .ad-maps-v8 .ad-plan-scroll{display:block;width:100%;max-width:100%;overflow:auto;border:1px solid #dbe6f2;border-radius:15px;background:#fff}
.andarma-admin .ad-maps-v8 .ad-plan-canvas{display:block;position:relative;width:100%;min-width:480px;user-select:none}
.andarma-admin .ad-maps-v8 .ad-plan-canvas>img{display:block;width:100%;height:auto;max-width:none;pointer-events:none}
.andarma-admin .ad-maps-v8 .ad-plan-svg{position:absolute;inset:0;width:100%;height:100%;overflow:visible}
.andarma-admin .ad-maps-v8 .ad-legend{display:flex;flex-wrap:wrap;gap:12px 20px;padding-top:15px;margin-top:15px;border-top:1px solid #e9eff5}
.andarma-admin .ad-maps-v8 .ad-legend span{display:inline-flex;align-items:center;gap:7px;font-size:12px}
/* Ficha de lote seleccionado y panel de edición (debajo del plano, a todo ancho) */
.andarma-admin .ad-maps-v8>.ad-card+ .ad-card{margin-top:18px}
.andarma-admin .ad-maps-v8 .ad-card .ad-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.andarma-admin .ad-maps-v8 .ad-card h2{font-size:17px;line-height:1.3;color:#17324e}
.andarma-admin .ad-maps-v8 .ad-card .ad-kicker{margin-bottom:7px}
@media(min-width:1500px){.andarma-admin .ad-maps-v8 .ad-mapcards{grid-template-columns:repeat(auto-fill,minmax(390px,1fr))}}
@media(max-width:800px){
 .andarma-admin .ad-maps-v8>header>.flex{width:100%;justify-content:flex-start}
 .andarma-admin .ad-maps-v8>.ad-card{padding:16px}
 .andarma-admin .ad-maps-v8 .ad-mapcard>img{height:190px}
 .andarma-admin .ad-maps-v8 .ad-plan-canvas{min-width:420px}
}
@media(max-width:520px){.andarma-admin .ad-maps-v8 .ad-mapcards{grid-template-columns:1fr}}
</style>

<style>
/* ANDARMA_FASE8_MAPAS_PREMIUM — Exclusivo del módulo de mapas */
.andarma-admin .ad-maps-v8{width:100%;min-width:0;display:block!important}
.andarma-admin .ad-maps-v8>.ad-card{width:100%;min-width:0}
.andarma-admin .ad-maps-v8__list{padding:24px;border-radius:20px}
.andarma-admin .ad-maps-v8__list-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:17px}
.andarma-admin .ad-maps-v8__list-heading h2{font-size:21px;font-weight:780;letter-spacing:-.035em;line-height:1.2;margin:5px 0 6px;color:var(--ad-ink,#17324e)}
.andarma-admin .ad-maps-v8__count{font-size:12px;padding:7px 12px;white-space:nowrap}
.andarma-admin .ad-maps-v8__toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;background:#f8fbff;border:1px solid #e4edf7;padding:12px 14px;border-radius:14px;margin-bottom:18px}
.andarma-admin .ad-maps-v8__search{display:flex;align-items:center;gap:10px;min-width:230px;width:min(420px,100%);border:1px solid #d6e5f2;border-radius:10px;background:white;padding:0 12px;color:#7e95ad}
.andarma-admin .ad-maps-v8__search input{width:100%;min-height:39px;background:transparent;border:none;outline:none;color:#163b60;font:inherit;font-size:13px}
.andarma-admin .ad-maps-v8__search:focus-within{border-color:#7aaff2;box-shadow:0 0 0 3px rgba(0,102,224,.11)}
.andarma-admin .ad-maps-v8__helper{display:flex;gap:7px;align-items:center;font-size:12px;color:#718aa2}
.andarma-admin .ad-maps-v8 .ad-mapcards{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
.andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single{grid-template-columns:1fr}
.andarma-admin .ad-maps-v8 .ad-mapcard{border:1px solid #dbe8f3;border-radius:18px;box-shadow:0 8px 24px rgba(12,41,79,.035);transition:box-shadow .17s,transform .17s;min-width:0;overflow:hidden}
.andarma-admin .ad-maps-v8 .ad-mapcard:hover{transform:translateY(-2px);box-shadow:0 15px 32px rgba(12,41,79,.08)}
.andarma-admin .ad-maps-v8__thumb{overflow:hidden;background:#f3f7fb;border-bottom:1px solid #e2ebf4;display:flex;align-items:center;justify-content:center}
.andarma-admin .ad-maps-v8__thumb img{width:100%;height:220px;object-fit:contain;background:#f3f7fb;border:0;display:block}
.andarma-admin .ad-maps-v8__item-body{padding:20px;display:flex;flex-direction:column;gap:9px;min-width:0;flex:1}
.andarma-admin .ad-maps-v8__item-kicker{font-size:10px;color:#497dae;font-weight:800;letter-spacing:.11em}
.andarma-admin .ad-maps-v8__item-body h3{font-size:19px;font-weight:800;letter-spacing:-.025em;line-height:1.2;margin:0;overflow-wrap:anywhere;color:var(--ad-ink,#17324e)}
.andarma-admin .ad-maps-v8__item-body .ad-muted{margin:0;font-size:13px}
.andarma-admin .ad-maps-v8__item-body>.ad-badge{align-self:flex-start;margin:6px 0}
.andarma-admin .ad-maps-v8__actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto;padding-top:12px;justify-content:flex-start!important}
.andarma-admin .ad-maps-v8__actions .ad-btn{min-height:38px}
.andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-mapcard{display:grid;grid-template-columns:minmax(260px,42%) minmax(0,1fr)}
.andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb{border-bottom:0;border-right:1px solid #e2ebf4}
.andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img{height:265px}
.andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body{justify-content:center;padding:25px 28px}
.andarma-admin .ad-maps-v8__map-panel{padding:24px;border-radius:20px}
.andarma-admin .ad-maps-v8__map-head{gap:20px;margin-bottom:14px}
.andarma-admin .ad-maps-v8__map-head h2{font-size:19px;font-weight:800;margin:4px 0 3px;color:var(--ad-ink,#17324e)}
.andarma-admin .ad-maps-v8__map-tip{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;border:1px solid #e2edf7;border-radius:12px;background:#f7fbff;padding:11px 14px;margin-bottom:14px;font-size:12px;color:#627e99}
.andarma-admin .ad-maps-v8__map-tip>i{color:#0066e0}
.andarma-admin .ad-maps-v8__map-tip>span:first-of-type{flex:1;min-width:170px}
.andarma-admin .ad-maps-v8__mapped{font-weight:750;color:#19517f;white-space:nowrap}
.andarma-admin .ad-maps-v8 .ad-plan-scroll{border:1px solid #d9e6f3;border-radius:14px;box-shadow:0 6px 22px rgba(15,48,82,.04);background:#fff}
/* No tocar dimensiones ni transformaciones del canvas/SVG: sus coordenadas deben coincidir. */
.andarma-admin .ad-maps-v8__legend{gap:12px 22px;padding-top:14px;border-top:1px solid #edf2f8;margin-top:15px}
.andarma-admin .ad-maps-v8__legend span{font-size:12px;font-weight:600}
.andarma-admin .ad-maps-v8__selected{margin-top:16px;padding:22px 24px;background:linear-gradient(120deg,#fff,#f8fbff);border-radius:18px;border:1px solid #dfeaf4}
.andarma-admin .ad-maps-v8__selected h2{font-size:25px;font-weight:800;letter-spacing:-.025em;margin:4px 0 6px;color:var(--ad-ink,#17324e)}
.andarma-admin .ad-maps-v8__selected .ad-muted{font-size:13px;margin-bottom:9px}
.andarma-admin .ad-maps-v8__selected .ad-actions{display:flex;gap:9px;align-items:flex-start;justify-content:flex-end}
@media(max-width:1000px){
 .andarma-admin .ad-maps-v8 .ad-mapcards{grid-template-columns:1fr}
}
@media(max-width:680px){
 .andarma-admin .ad-maps-v8__list,.andarma-admin .ad-maps-v8__map-panel{padding:16px}
 .andarma-admin .ad-maps-v8__toolbar{align-items:stretch}
 .andarma-admin .ad-maps-v8__search{width:100%}
 .andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-mapcard{display:flex;flex-direction:column}
 .andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb{border-right:0;border-bottom:1px solid #e2ebf4}
 .andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img{height:180px}
 .andarma-admin .ad-maps-v8 .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body{padding:18px}
 .andarma-admin .ad-maps-v8__selected{padding:18px}
 .andarma-admin .ad-maps-v8__selected .ad-actions{justify-content:flex-start}
}
</style>

<style>
/* ANDARMA_FASE8_TARJETA_COMPACTA_V1
   Ajuste aislado del LISTADO. No afecta el mapa interactivo, el SVG ni sus polígonos. */
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single {
  display: flex;
  justify-content: center;
  align-items: stretch;
  width: 100%;
  grid-template-columns: none;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-mapcard {
  display: grid;
  width: min(100%, 980px);
  min-height: 212px;
  grid-template-columns: minmax(235px, 34%) minmax(0, 1fr);
  margin: 0;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb {
  min-width: 0;
  border-bottom: 0;
  border-right: 1px solid #e2ebf4;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img {
  height: 212px;
  width: 100%;
  object-fit: contain;
  object-position: center;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body {
  padding: 20px 24px;
  gap: 7px;
  justify-content: center;
  align-items: flex-start;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body > .ad-badge {
  margin: 4px 0 2px;
  width: auto;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__actions {
  margin-top: 6px;
  padding-top: 4px;
  gap: 8px;
}
@media (max-width: 760px) {
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-mapcard {
    display: flex;
    width: 100%;
    flex-direction: column;
    min-height: 0;
  }
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb {
    border-right: 0;
    border-bottom: 1px solid #e2ebf4;
  }
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img {
    height: 190px;
  }
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body {
    padding: 17px;
  }
}
</style>

<style>
/* ANDARMA_FASE8_GRID_3_CARDS_V1
   Convierte el listado a 3 cards por fila en desktop,
   2 en tablet y 1 en móvil. */
.andarma-admin .ad-maps-v8__list .ad-mapcards,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
  align-items: stretch;
  justify-content: stretch;
  width: 100%;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single {
  justify-items: stretch;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-mapcard,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-mapcard {
  width: 100%;
  min-width: 0;
  min-height: 100%;
  display: flex;
  flex-direction: column;
  border-radius: 18px;
  overflow: hidden;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__thumb,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb {
  border-right: 0;
  border-bottom: 1px solid #e2ebf4;
  background: linear-gradient(180deg,#f9fbfe 0%,#f4f8fc 100%);
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__thumb img,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img {
  width: 100%;
  height: 188px;
  object-fit: contain;
  object-position: center;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__item-body,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body {
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: flex-start;
  justify-content: space-between;
  flex: 1;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__item-body h3,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__item-body h3 {
  font-size: 17px;
  line-height: 1.2;
  margin: 0;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__actions,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding-top: 8px;
  margin-top: auto;
}
.andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__actions .ad-btn,
.andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__actions .ad-btn {
  min-height: 40px;
}
@media (max-width: 1180px) {
  .andarma-admin .ad-maps-v8__list .ad-mapcards,
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 760px) {
  .andarma-admin .ad-maps-v8__list .ad-mapcards,
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single {
    grid-template-columns: 1fr;
  }
  .andarma-admin .ad-maps-v8__list .ad-mapcards .ad-maps-v8__thumb img,
  .andarma-admin .ad-maps-v8__list .ad-mapcards.ad-maps-v8__single .ad-maps-v8__thumb img {
    height: 190px;
  }
}
</style>


<style>
/* ANDARMA_F9_FICHA_MAPA · panel exclusivo del terreno seleccionado */
.andarma-admin .ad-f9-card{padding:22px 24px}
.andarma-admin .ad-f9-head{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin-bottom:18px}
.andarma-admin .ad-f9-title{display:flex;align-items:center;flex-wrap:wrap;column-gap:13px;row-gap:7px}
.andarma-admin .ad-f9-title .ad-kicker{flex-basis:100%}
.andarma-admin .ad-f9-title h2{font-size:22px;font-weight:800;letter-spacing:-.025em;line-height:1.25;margin:0;color:var(--ad-ink)}
.andarma-admin .ad-f9-edittools{display:flex;flex-wrap:wrap;gap:8px}
.andarma-admin .ad-f9-grid{display:grid;grid-template-columns:minmax(240px,.7fr) minmax(0,1.3fr);gap:14px}
.andarma-admin .ad-f9-detail{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.andarma-admin .ad-f9-detail>div{display:flex;flex-direction:column;gap:7px;justify-content:center;padding:16px 17px;background:#f7faff;border:1px solid var(--ad-border);border-radius:14px;min-width:0}
.andarma-admin .ad-f9-detail small,.andarma-admin .ad-f9-buyer-info small{font-size:11px;color:var(--ad-muted);font-weight:600}
.andarma-admin .ad-f9-detail strong{font-size:16px;color:var(--ad-ink);font-weight:800;overflow-wrap:anywhere}
.andarma-admin .ad-f9-buyer{display:flex;align-items:center;gap:12px;padding:14px 16px;background:#fff;border:1px solid var(--ad-border);border-radius:14px;min-width:0}
.andarma-admin .ad-f9-icon{display:grid;place-items:center;flex:none;width:40px;height:40px;background:var(--ad-primary-light);color:var(--ad-primary);border-radius:12px}
.andarma-admin .ad-f9-buyer-info{display:flex;flex-direction:column;gap:4px;min-width:0;flex:1}
.andarma-admin .ad-f9-buyer-info strong{font-size:14px;font-weight:750;color:var(--ad-ink);overflow-wrap:anywhere}
.andarma-admin .ad-f9-buyer-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
@media(max-width:1150px){.andarma-admin .ad-f9-grid{grid-template-columns:1fr}.andarma-admin .ad-f9-buyer{flex-wrap:wrap}.andarma-admin .ad-f9-buyer-actions{margin-left:auto}}
@media(max-width:650px){.andarma-admin .ad-f9-card{padding:17px}.andarma-admin .ad-f9-head{flex-direction:column}.andarma-admin .ad-f9-detail{grid-template-columns:1fr}.andarma-admin .ad-f9-buyer-actions{width:100%;margin-left:0;justify-content:flex-start}.andarma-admin .ad-f9-buyer-actions .ad-btn{flex:1}}
</style>

<style>
/* ANDARMA_F8_FICHA_TERRENO_MODAL — dialog exclusivo de lectura/acciones del terreno. */
.ad-map-detail-backdrop {
  position:fixed;
  inset:0;
  z-index:1400;
  display:grid;
  place-items:center;
  padding:20px;
  background:rgba(5,22,44,.58);
  backdrop-filter:blur(5px);
  box-sizing:border-box;
}
.andarma-admin.ad-map-detail-backdrop .ad-map-detail-dialog {
  width:min(740px,100%);
  max-height:calc(100dvh - 40px);
  overflow:auto;
  background:#fff;
  border:1px solid #dce7f2;
  border-radius:18px;
  box-shadow:0 28px 88px rgba(5,23,46,.27);
  padding:24px;
  display:block;
  box-sizing:border-box;
  outline:none;
}
.andarma-admin.ad-map-detail-backdrop .ad-f9-head{align-items:flex-start}
.andarma-admin.ad-map-detail-backdrop .ad-f9-title{min-width:0;flex:1}
.andarma-admin.ad-map-detail-backdrop .ad-map-detail-close {
  margin-left:auto;
  min-width:86px;
  min-height:40px;
  display:inline-flex;
  justify-content:center;
  align-items:center;
  gap:7px;
  background:#f5f8fc;
  border:1px solid #dce7f2;
  color:#35516c;
}
.andarma-admin.ad-map-detail-backdrop .ad-f9-grid{
  grid-template-columns:1fr;
  gap:12px;
}
.andarma-admin.ad-map-detail-backdrop .ad-f9-detail{grid-template-columns:repeat(2,minmax(0,1fr))}
.andarma-admin.ad-map-detail-backdrop .ad-f9-buyer{flex-wrap:wrap}
.andarma-admin.ad-map-detail-backdrop .ad-f9-buyer-actions{margin-left:auto}
.andarma-admin.ad-map-detail-backdrop .ad-f9-edittools{
  order:3;
  flex:1 1 100%;
  padding-top:8px;
  border-top:1px solid #e7eef5;
}
.andarma-admin.ad-map-detail-backdrop .ad-map-detail-footer{
  margin-top:16px;
  padding-top:16px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:14px;
  border-top:1px solid #e7eef5;
}
.andarma-admin.ad-map-detail-backdrop .ad-map-detail-footer>span{
  color:#72879c;
  font-size:12px;
  line-height:1.5;
  display:flex;
  gap:8px;
  align-items:flex-start;
}
.andarma-admin.ad-map-detail-backdrop .ad-map-detail-footer>span i{margin-top:3px;color:#0066e0}
.andarma-admin.ad-map-detail-backdrop .ad-btn:focus-visible{
  outline:3px solid rgba(0,102,224,.3);
  outline-offset:2px;
}
@media(max-width:640px){
  .ad-map-detail-backdrop{padding:8px;align-items:center}
  .andarma-admin.ad-map-detail-backdrop .ad-map-detail-dialog{
    max-height:calc(100dvh - 16px);
    padding:18px 16px;
    border-radius:15px;
  }
  .andarma-admin.ad-map-detail-backdrop .ad-f9-head{flex-wrap:wrap}
  .andarma-admin.ad-map-detail-backdrop .ad-f9-title h2{font-size:21px}
  .andarma-admin.ad-map-detail-backdrop .ad-f9-detail{grid-template-columns:1fr}
  .andarma-admin.ad-map-detail-backdrop .ad-f9-buyer-actions{width:100%;margin-left:0}
  .andarma-admin.ad-map-detail-backdrop .ad-f9-buyer-actions .ad-btn{flex:1}
  .andarma-admin.ad-map-detail-backdrop .ad-map-detail-footer{flex-direction:column;align-items:stretch}
  .andarma-admin.ad-map-detail-backdrop .ad-map-detail-footer button{width:100%}
}
@media(prefers-reduced-motion:reduce){.ad-map-detail-backdrop{scroll-behavior:auto}}
</style>

<style>
/* ANDARMA_FASE8B_MODAL_PREMIUM */
.andarma-admin .ad-maplot-modalbackdrop{
  position:fixed;
  inset:0;
  z-index:1350;
  display:grid;
  place-items:center;
  padding:18px;
  background:rgba(8,23,41,.38);
  backdrop-filter:blur(4px);
}
.andarma-admin .ad-maplot-modal{
  width:min(920px,calc(100vw - 28px));
  max-height:calc(100dvh - 34px);
  overflow:hidden;
  display:flex;
  flex-direction:column;
  background:rgba(255,255,255,.98);
  border:1px solid #d8e5f2;
  border-radius:24px;
  box-shadow:0 24px 70px rgba(7,29,54,.24);
  color:#17324e;
}
.andarma-admin .ad-maplot-modal__header{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:18px;
  padding:24px 26px 18px;
  border-bottom:1px solid #e8eff6;
}
.andarma-admin .ad-maplot-modal__intro{display:grid;gap:8px;min-width:0;flex:1;}
.andarma-admin .ad-maplot-modal__titleline{display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
.andarma-admin .ad-maplot-modal__titleline h2{
  margin:0;
  font-size:31px;
  line-height:1.05;
  font-weight:800;
  letter-spacing:-.04em;
  color:#17324e;
  word-break:normal;
}
.andarma-admin .ad-maplot-modal__intro .ad-muted{margin:0;font-size:13px;max-width:620px;}
.andarma-admin .ad-maplot-modal__actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;}
.andarma-admin .ad-maplot-modal__actions .ad-btn{min-height:42px;}
.andarma-admin .ad-maplot-modal__body{padding:22px 26px;display:grid;gap:16px;overflow:auto;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%);}
.andarma-admin .ad-maplot-modal__stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
.andarma-admin .ad-maplot-stat{
  border:1px solid #dbe8f4;
  border-radius:18px;
  padding:18px 18px 16px;
  background:#f8fbff;
  display:grid;
  gap:8px;
}
.andarma-admin .ad-maplot-stat small{font-size:12px;font-weight:700;color:#7890a8;}
.andarma-admin .ad-maplot-stat strong{font-size:28px;line-height:1.1;letter-spacing:-.03em;color:#17324e;overflow-wrap:anywhere;}
.andarma-admin .ad-maplot-buyer{
  display:grid;
  grid-template-columns:auto minmax(0,1fr) auto;
  gap:14px;
  align-items:center;
  padding:16px 18px;
  border:1px solid #dbe8f4;
  border-radius:18px;
  background:#fff;
}
.andarma-admin .ad-maplot-buyer__icon{
  width:44px;height:44px;display:grid;place-items:center;flex:none;
  border-radius:14px;background:#edf5ff;color:#0c66d7;
}
.andarma-admin .ad-maplot-buyer__info{display:grid;gap:4px;min-width:0;}
.andarma-admin .ad-maplot-buyer__info small{font-size:12px;color:#7890a8;}
.andarma-admin .ad-maplot-buyer__info strong{font-size:21px;line-height:1.15;letter-spacing:-.02em;color:#17324e;overflow-wrap:anywhere;}
.andarma-admin .ad-maplot-buyer__actions{display:flex;gap:9px;flex-wrap:wrap;justify-content:flex-end;}
.andarma-admin .ad-maplot-modal__footer{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  padding:16px 26px 22px;
  border-top:1px solid #e8eff6;
  background:#fff;
}
.andarma-admin .ad-maplot-modal__footer p{margin:0;display:flex;align-items:center;gap:8px;font-size:13px;color:#6f879e;}
.andarma-admin .ad-maplot-modal__footer p i{color:#0066e0;}
@media (max-width: 860px) {
  .andarma-admin .ad-maplot-modal{width:min(100vw - 14px,920px);max-height:calc(100dvh - 14px);border-radius:20px;}
  .andarma-admin .ad-maplot-modal__header,
  .andarma-admin .ad-maplot-modal__body,
  .andarma-admin .ad-maplot-modal__footer{padding-left:18px;padding-right:18px;}
  .andarma-admin .ad-maplot-modal__header{flex-direction:column;align-items:flex-start;}
  .andarma-admin .ad-maplot-modal__actions{justify-content:flex-start;}
  .andarma-admin .ad-maplot-modal__stats{grid-template-columns:1fr;}
  .andarma-admin .ad-maplot-buyer{grid-template-columns:1fr;align-items:flex-start;}
  .andarma-admin .ad-maplot-buyer__actions{justify-content:flex-start;}
  .andarma-admin .ad-maplot-modal__footer{flex-direction:column;align-items:stretch;}
  .andarma-admin .ad-maplot-modal__footer .ad-btn{width:100%;}
}
@media (max-width: 560px) {
  .andarma-admin .ad-maplot-modalbackdrop{padding:8px;}
  .andarma-admin .ad-maplot-modal__titleline h2{font-size:24px;}
  .andarma-admin .ad-maplot-stat strong{font-size:23px;}
  .andarma-admin .ad-maplot-buyer__info strong{font-size:18px;}
  .andarma-admin .ad-maplot-modal__actions{width:100%;}
  .andarma-admin .ad-maplot-modal__actions .ad-btn{flex:1 1 100%;justify-content:center;}
}
</style>

<style>
/* ANDARMA_FASE9_MODAL_ZOOM_MAPA */
body .ad-maplot-v9-backdrop{position:fixed;inset:0;z-index:1500;padding:18px;display:grid;place-items:center;background:rgba(7,22,42,.46);backdrop-filter:blur(3px)}
body .ad-maplot-v9-dialog{font-family:'DM Sans',sans-serif;box-sizing:border-box;width:min(800px,calc(100vw - 24px));max-height:calc(100dvh - 36px);display:flex;flex-direction:column;overflow:hidden;background:#fff;border:1px solid #dce7f1;border-radius:19px;box-shadow:0 30px 85px #071d3540;color:#17324e}
body .ad-maplot-v9-dialog *{box-sizing:border-box}
body .ad-maplot-v9-header{padding:23px 26px 18px;border-bottom:1px solid #e5edf5;display:flex;align-items:flex-start;justify-content:space-between;gap:16px}
body .ad-maplot-v9-heading{min-width:0;flex:1}
body .ad-maplot-v9-heading .ad-kicker{font-size:10px;letter-spacing:.13em;font-weight:800;color:#0066e0}
body .ad-maplot-v9-titleline{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:7px}
body .ad-maplot-v9-titleline h2{font-family:Manrope,sans-serif;font-size:27px;line-height:1.2;font-weight:800;letter-spacing:-.03em;margin:0;color:#17324e;word-break:normal}
body .ad-maplot-v9-heading p{font-size:12px;line-height:1.55;color:#71869a;margin:8px 0 0}
body .ad-maplot-v9-close{border:1px solid #dce7f1;background:#f6f9fd;color:#40607c;width:39px;height:39px;border-radius:10px;display:grid;place-items:center;cursor:pointer;flex:none}
body .ad-maplot-v9-close:hover{background:#eaf4ff;color:#0066e0}
body .ad-maplot-v9-body{padding:20px 26px;overflow-y:auto;display:grid;gap:14px}
body .ad-maplot-v9-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
body .ad-maplot-v9-metric{padding:16px;background:#f7faff;border:1px solid #dce8f5;border-radius:12px;display:grid;gap:7px;min-width:0}
body .ad-maplot-v9-metric small,body .ad-maplot-v9-client-copy small{font-size:11px;color:#71869a;font-weight:600}
body .ad-maplot-v9-metric strong{font-size:19px;color:#17324e;letter-spacing:-.02em;overflow-wrap:anywhere}
body .ad-maplot-v9-client{display:flex;gap:12px;align-items:center;padding:16px;border:1px solid #dce8f5;border-radius:12px;min-width:0}
body .ad-maplot-v9-avatar{width:42px;height:42px;background:#edf5ff;color:#0066e0;border-radius:11px;display:grid;place-items:center;flex:none}
body .ad-maplot-v9-client-copy{display:grid;gap:5px;flex:1;min-width:0}
body .ad-maplot-v9-client-copy strong{font-size:14px;line-height:1.3;overflow-wrap:anywhere}
body .ad-maplot-v9-client-actions,body .ad-maplot-v9-manage{display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:wrap}
body .ad-maplot-v9-footer{padding:15px 26px 21px;border-top:1px solid #e5edf5;display:flex;align-items:center;justify-content:space-between;gap:12px}
body .ad-maplot-v9-dialog .ad-badge{display:inline-flex;align-items:center;border-radius:99px;background:#f1f5f9;color:#526b80;border:1px solid #dfe8f0;padding:5px 10px;font-size:11px;font-weight:700;white-space:nowrap}
body .ad-maplot-v9-dialog .ad-badge--success{color:#167c58;background:#e8f8f0;border-color:#bce8d0}
body .ad-maplot-v9-dialog .ad-badge--danger{color:#b63b4a;background:#fff0f1;border-color:#f3c9d0}
body .ad-maplot-v9-dialog .ad-btn{font-family:inherit;min-height:40px;padding:8px 13px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:1px solid #d7e4f0;border-radius:9px;background:#fff;color:#31516f;font-size:12px;font-weight:700;cursor:pointer;white-space:normal;text-decoration:none}
body .ad-maplot-v9-dialog .ad-btn:hover{background:#f1f6fc}
body .ad-maplot-v9-dialog .ad-btn--primary{background:#0066e0;border-color:#0066e0;color:#fff}
body .ad-maplot-v9-dialog .ad-btn--primary:hover{background:#0053bc}
body .ad-maplot-v9-dialog .ad-btn--danger{color:#b43949;border-color:#f0cdd3;background:#fff8f8}
body .ad-maplot-v9-dialog :is(button,a):focus-visible{outline:3px solid #a4ceff;outline-offset:2px}
.andarma-admin .ad-maps-v9-zoom{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:8px;margin:0 0 12px;padding:10px 12px;border:1px solid #e0eaf4;border-radius:12px;background:#f7faff}
.andarma-admin .ad-maps-v9-zoom-label{margin-right:auto;font-size:12px;font-weight:800;color:#355775;display:flex;align-items:center;gap:8px}
.andarma-admin .ad-maps-v9-zoom-btn{width:38px;height:38px;display:grid;place-items:center;border:1px solid #d6e4f1;border-radius:9px;background:white;color:#0066e0;cursor:pointer}
.andarma-admin .ad-maps-v9-zoom-reset{padding:0 12px;min-height:38px;border:1px solid #d6e4f1;border-radius:9px;background:white;color:#31516f;font-size:12px;font-weight:700;cursor:pointer}
.andarma-admin .ad-maps-v9-zoom-value{font-size:12px;color:#17324e;font-weight:800;min-width:43px;text-align:center}
.andarma-admin .ad-maps-v9-zoom button:disabled{opacity:.42;cursor:not-allowed}
@media(max-width:650px){body .ad-maplot-v9-backdrop{padding:8px}body .ad-maplot-v9-dialog{width:100%;max-height:calc(100dvh - 16px);border-radius:14px}body .ad-maplot-v9-header,body .ad-maplot-v9-body,body .ad-maplot-v9-footer{padding-left:16px;padding-right:16px}body .ad-maplot-v9-titleline h2{font-size:22px}body .ad-maplot-v9-metrics{grid-template-columns:1fr}body .ad-maplot-v9-client{flex-wrap:wrap}body .ad-maplot-v9-client-actions{width:100%;justify-content:flex-start}body .ad-maplot-v9-footer{flex-direction:column-reverse;align-items:stretch}body .ad-maplot-v9-footer .ad-btn{flex:1}body .ad-maplot-v9-close{width:42px;height:42px}}
</style>

<style>
/* ANDARMA_FASE10_PAN_MAPA: sólo el área interactiva del plano */
.andarma-admin .ad-maps-v8 .ad-pan-scroll:not(.ad-pan-scroll--drawing){
  cursor:grab;
  touch-action:none;
  overscroll-behavior:contain;
}
.andarma-admin .ad-maps-v8 .ad-pan-scroll.ad-pan-scroll--dragging{
  cursor:grabbing;
}
.andarma-admin .ad-maps-v8 .ad-pan-scroll.ad-pan-scroll--drawing{
  cursor:crosshair;
  touch-action:auto;
}
.andarma-admin .ad-maps-v8 .ad-pan-scroll:not(.ad-pan-scroll--drawing) .ad-shape{
  cursor:pointer;
}
@media(prefers-reduced-motion:reduce){
  .andarma-admin .ad-maps-v8 .ad-pan-scroll .ad-shape{transition:none}
}
</style>
