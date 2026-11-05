<script setup>
import {ref,computed,onMounted,watch,nextTick} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import Swal from 'sweetalert2'
import {can} from '../../utils/andarmaPermissions'
import {confirmAction as confirmAdminAction} from '../../utils/adminAlerts'
import {api,post,put,remove} from '../../services/api'
const route=useRoute(),router=useRouter()
const mapSearch=ref('') // filtro local: no agrega solicitudes al servidor
const plans=ref([]),developments=ref([]),superadmin=ref(false),plan=ref(null),polygons=ref([]),available=ref([]),loading=ref(false),modal=ref(false),saving=ref(false),imageEl=ref(null),viewport=ref(null)
const selected=ref(null),drawing=ref(false),draft=ref([]),lotId=ref(''),editGeometryId=ref(null)
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
function selectedPolygon(poly){if(drawing.value)return;selected.value=poly}
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
<div class="ad-plan-scroll"><div ref="viewport" class="ad-plan-canvas" :class="{drawing:drawing}" @click="clickImage"><img ref="imageEl" :src="plan.image_url" :alt="plan.title" draggable="false"/><svg class="ad-plan-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-label="Polígonos de los terrenos"><g v-for="poly in polygons" :key="poly.id" @click.stop="selectedPolygon(poly)"><polygon :points="pointsString(poly.points)" :fill="statusColor(poly.status)" :fill-opacity="selected?.id===poly.id ? .48 : .25" :stroke="selected?.id===poly.id ? '#003A8C' : statusColor(poly.status)" stroke-width="3" vector-effect="non-scaling-stroke" class="ad-shape"/><text :x="center(poly).x" :y="center(poly).y" text-anchor="middle" dominant-baseline="middle" fill="#102F4B" font-size="20" font-weight="750" style="pointer-events:none">{{poly.code}}</text></g><polyline v-if="drawing && draft.length" :points="pointsString(draft)" fill="none" stroke="#0066E0" stroke-width="3" vector-effect="non-scaling-stroke"/><circle v-for="(pt,i) in draft" :key="i" :cx="pt.x" :cy="pt.y" r="6" fill="#fff" stroke="#0066E0" stroke-width="2" vector-effect="non-scaling-stroke"/></svg></div></div>
<div class="ad-legend ad-maps-v8__legend"><span><i style="background:#12A677"></i> Disponible</span><span><i style="background:#EA9C34"></i> Apartado</span><span><i style="background:#D3545B"></i> Vendido</span><span><i style="background:#7C90A5"></i> Inactivo</span></div></div>
<div v-if="superadmin&&drawing" class="ad-card space-y-3"><h2 class="font-bold">{{editGeometryId?'Modificar trazado':'Nuevo polígono'}}</h2><p class="ad-muted">Marca vértices sobre el plano siguiendo el contorno del lote. Se guardan coordenadas relativas a la imagen.</p><div class="flex flex-wrap gap-3 items-end"><label v-if="!editGeometryId" class="ad-label grow min-w-48">Terreno<select v-model="lotId" class="ad-select"><option value="">Seleccionar lote del desarrollo</option><option v-for="lot in available" :key="lot.id" :value="String(lot.id)">{{lot.block?lot.block+' · ':''}}{{lot.code}} · {{statusLabel[lot.status]}}</option></select></label><div class="ad-badge">{{draft.length}} vértices</div><button class="ad-btn" :disabled="!draft.length" @click="draft=draft.slice(0,-1)"><i class="fa-solid fa-rotate-left"></i> Deshacer punto</button><button class="ad-btn" @click="draft=[]"><i class="fa-solid fa-eraser"></i> Reiniciar</button><button v-can="'maps.edit'" class="ad-btn ad-btn--primary" :disabled="saving || draft.length<3 || (!editGeometryId&&!lotId)" @click="savePolygon"><i class="fa-solid fa-floppy-disk"></i> Guardar polígono</button></div></div>
<div v-if="selected && !drawing" class="ad-card ad-f9-card">
  <div class="ad-f9-head">
    <div class="ad-f9-title">
      <span class="ad-kicker">TERRENO SELECCIONADO</span>
      <h2>{{[selected.block,selected.code].filter(Boolean).join(' · ')}}</h2>
      <span class="ad-badge" :class="selected.status==='available'?'ad-badge--success':selected.status==='sold'?'ad-badge--danger':''">{{statusLabel[selected.status]||'Estado sin definir'}}</span>
    </div>
    <div v-if="superadmin" class="ad-f9-edittools">
      <button v-can="'maps.edit'" class="ad-btn" type="button" @click="redraw"><i class="fa-solid fa-pen"></i> Editar trazado</button>
      <button v-can="'maps.delete'" class="ad-btn ad-btn--danger" type="button" @click="delPolygon"><i class="fa-solid fa-unlink"></i> Desvincular</button>
    </div>
  </div>
  <div class="ad-f9-grid">
    <div class="ad-f9-detail">
      <div><small>Superficie</small><strong>{{ selected.area_m2 ? Number(selected.area_m2).toLocaleString('es-MX')+' m²' : 'No registrada' }}</strong></div>
      <div><small>Precio de referencia</small><strong>{{selected.price !== null && selected.price !== undefined ? fmt(selected.price) : 'Por definir'}}</strong></div>
    </div>
    <div class="ad-f9-buyer">
      <span class="ad-f9-icon"><i class="fa-solid fa-user"></i></span>
      <div class="ad-f9-buyer-info">
        <small>Comprador / titular</small>
        <strong v-if="selected.client_id && can('clients.view')">{{selected.buyer_name || 'Cliente asignado'}}</strong>
        <strong v-else-if="selected.client_id">Propiedad asignada</strong>
        <strong v-else>Sin comprador asignado</strong>
        <small v-if="selected.client_id && can('clients.view') && selected.buyer_phone">{{selected.buyer_phone}}</small>
      </div>
      <div class="ad-f9-buyer-actions">
        <button v-if="selected.client_id && can('clients.view')" class="ad-btn" type="button" @click="router.push({path:'/admin/clientes',query:{client:selected.client_id}})"><i class="fa-solid fa-address-card"></i> Ver expediente</button>
        <button v-if="selected.client_id && can('payments.view')" class="ad-btn ad-btn--primary" type="button" @click="router.push({path:'/admin/pagos',query:{lot:selected.lot_id}})"><i class="fa-solid fa-wallet"></i> Estado de cuenta</button>
      </div>
    </div>
  </div>
</div>
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
