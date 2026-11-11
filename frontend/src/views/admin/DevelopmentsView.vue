<script setup>
import '../../assets/andarma-development-gallery.css'
import {computed,onMounted,ref,watch} from 'vue'
import {useRouter} from 'vue-router'
import Swal from 'sweetalert2'
import {api,post,put,remove} from '../../services/api'
import DevelopmentImageCrop from '../../components/admin/DevelopmentImageCrop.vue'

const router=useRouter()
const developments=ref([])
const search=ref('')
const status=ref('')
const loading=ref(false)
const saving=ref(false)
const developmentTab=ref('info') // ANDARMA_FASE3C_TABS
const modal=ref(false)
const editing=ref(null)
const form=ref(blank())
// ANDARMA_FASE3A_GALERIA_HERO: galería del slider del desarrollo
// ANDARMA_FASE3B_GALERIA_CROP: misma herramienta de recorte que la fotografía principal.
// ANDARMA_FASE3D_GALERIA_UNIFICADA: editor único y portada en el primer elemento.
const gallery=ref([]),galleryBusy=ref(false),galleryCropKey=ref(0)
const galleryEditorOpen=ref(false),galleryEditingId=ref(null)
async function loadGallery(id){
  gallery.value=[]
  if(!id)return
  try{const r=await api('/admin/development-gallery/'+id);gallery.value=r.data||[]}
  catch(e){error(e)}
}
function startGalleryPhoto(item=null){
  if(!editing.value||galleryBusy.value)return
  galleryEditingId.value=item?.id??null
  galleryEditorOpen.value=true
  galleryCropKey.value++
}
function closeGalleryEditor(){
  if(galleryBusy.value)return
  galleryEditorOpen.value=false
  galleryEditingId.value=null
  galleryCropKey.value++
}
async function uploadGallery(file){
  if(!editing.value||!file||galleryBusy.value)return
  galleryBusy.value=true
  try{
    if(file.type!=='image/webp'||file.size>450*1024)throw new Error('El recorte debe ser WebP de máximo 450 KB.')
    const fd=new FormData();fd.append('photo',file,file.name)
    const path='/admin/development-gallery/'+editing.value.id+(galleryEditingId.value?'/'+galleryEditingId.value+'/replace':'')
    await post(path,fd)
    await loadGallery(editing.value.id)
    galleryEditorOpen.value=false
    galleryEditingId.value=null
    galleryCropKey.value++
    toast('Fotografía guardada correctamente')
  }catch(e){error(e)}finally{galleryBusy.value=false}
}
async function setGalleryPrimary(item){
  if(!editing.value||galleryBusy.value)return
  galleryBusy.value=true
  try{
    const r=await post('/admin/development-gallery/'+editing.value.id+'/'+item.id+'/primary',{})
    gallery.value=r.data||[]
    toast('Fotografía principal actualizada')
  }catch(e){error(e)}finally{galleryBusy.value=false}
}

async function deleteGallery(item){
  const result=await Swal.fire({...swalTheme,icon:'warning',title:'Eliminar fotografía',text:'Se quitará esta foto del slider público.',showCancelButton:true,confirmButtonText:'Eliminar',cancelButtonText:'Cancelar'})
  if(!result.isConfirmed)return
  galleryBusy.value=true
  try{await remove('/admin/development-gallery/'+editing.value.id+'/'+item.id);await loadGallery(editing.value.id);toast('Fotografía eliminada')}
  catch(e){error(e)}finally{galleryBusy.value=false}
}
async function moveGallery(i,direction){
  const next=[...gallery.value],j=i+direction
  if(j<0||j>=next.length||galleryBusy.value)return
  ;[next[i],next[j]]=[next[j],next[i]]
  galleryBusy.value=true
  try{const r=await post('/admin/development-gallery/'+editing.value.id+'/reorder',{ids:next.map(x=>x.id)});gallery.value=r.data||next}
  catch(e){await loadGallery(editing.value.id);error(e)}finally{galleryBusy.value=false}
}
const newPhoto=ref(null)
const removePhoto=ref(false)
const draggingId=ref(null)
const dropTargetId=ref(null)
const orderSnapshot=ref([])
const orderSaving=ref(false)
const isFiltering=computed(()=>Boolean(search.value.trim()||status.value))
const orderChanged=computed(()=>orderSnapshot.value.join(',')!==developments.value.map(d=>String(d.id)).join(','))
function photoChosen(file){newPhoto.value=file;removePhoto.value=false}
function photoRemoved(){newPhoto.value=null;removePhoto.value=true;form.value.image_url=''}
function moveDevelopment(fromId,toId){
  if(isFiltering.value || orderSaving.value)return
  const from=developments.value.findIndex(d=>String(d.id)===String(fromId))
  const to=developments.value.findIndex(d=>String(d.id)===String(toId))
  if(from<0||to<0||from===to)return
  const next=[...developments.value]
  const [moving]=next.splice(from,1)
  next.splice(to,0,moving)
  developments.value=next
}
function dragStart(e,id){
  if(isFiltering.value){e.preventDefault();return}
  draggingId.value=id
  e.dataTransfer.effectAllowed='move'
  e.dataTransfer.setData('text/plain',String(id))
}
function dragOver(e,id){
  if(isFiltering.value)return
  e.preventDefault()
  e.dataTransfer.dropEffect='move'
  dropTargetId.value=id
}
function dragDrop(e,id){
  if(isFiltering.value)return
  e.preventDefault()
  const from=draggingId.value||e.dataTransfer.getData('text/plain')
  moveDevelopment(from,id)
  dragEnd()
}
function dragEnd(){draggingId.value=null;dropTargetId.value=null}
function moveStep(id,direction){
  const idx=developments.value.findIndex(d=>d.id===id)
  if(idx<0)return
  const target=developments.value[idx+direction]
  if(target)moveDevelopment(id,target.id)
}
function undoOrder(){
  const positions=new Map(orderSnapshot.value.map((id,index)=>[String(id),index]))
  developments.value=[...developments.value].sort((a,b)=>(positions.get(String(a.id))??9999)-(positions.get(String(b.id))??9999))
}
async function saveOrder(){
  if(!orderChanged.value||orderSaving.value)return
  orderSaving.value=true
  try{
    await post('/admin/im/developments/reorder',{ids:developments.value.map(d=>d.id)})
    orderSnapshot.value=developments.value.map(d=>String(d.id))
    toast('Orden de desarrollos guardado')
  }catch(e){error(e)}finally{orderSaving.value=false}
}

const swalTheme={
  buttonsStyling:false,reverseButtons:true,heightAuto:false,
  customClass:{popup:'andarma-swal',title:'andarma-swal__title',htmlContainer:'andarma-swal__text',actions:'andarma-swal__actions',confirmButton:'andarma-swal__confirm',cancelButton:'andarma-swal__cancel'}
}
const statusNames={active:'Activo',upcoming:'Próximamente',inactive:'Inactivo'}
const totalLots=computed(()=>developments.value.reduce((a,d)=>a+Number(d.lots_count||0),0))
const totalMapped=computed(()=>developments.value.reduce((a,d)=>a+Number(d.plans_count||0),0))
const activeCount=computed(()=>developments.value.filter(d=>d.status==='active').length)
const shown=computed(()=>developments.value.filter(d=>{
  const query=search.value.trim().toLocaleLowerCase('es')
  return (!status.value||d.status===status.value)&&(!query||[d.name,d.location,d.slug].join(' ').toLocaleLowerCase('es').includes(query))
}))
function blank(){return {name:'',description:'',location:'',image_url:'',status:'active'}}
function errorMessage(e){return e?.message||'No se pudo completar la operación.'}
function toast(title){return Swal.fire({...swalTheme,toast:true,position:'top-end',timer:2200,timerProgressBar:true,showConfirmButton:false,icon:'success',title})}
function error(e){return Swal.fire({...swalTheme,icon:'error',title:'No se pudo realizar la operación',text:errorMessage(e),confirmButtonText:'Entendido'})}
async function confirmDelete(row){const r=await Swal.fire({...swalTheme,icon:'warning',title:'Eliminar desarrollo',text:`Se eliminará «${row.name}». Solo es posible si no tiene terrenos, planos ni prospectos relacionados.`,showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',customClass:{...swalTheme.customClass,confirmButton:'andarma-swal__confirm is-danger',popup:'andarma-swal andarma-delete-dialog'}});return r.isConfirmed}
async function load(){
  loading.value=true
  try{const result=await api('/admin/im/developments/summary');developments.value=result.data||[];orderSnapshot.value=developments.value.map(d=>String(d.id))}
  catch(e){error(e)}finally{loading.value=false}
}
function add(){galleryEditorOpen.value=false;galleryEditingId.value=null;developmentTab.value='info';editing.value=null;form.value=blank();newPhoto.value=null;removePhoto.value=false;gallery.value=[];galleryCropKey.value+=1;modal.value=true}
function edit(row){galleryEditorOpen.value=false;galleryEditingId.value=null;developmentTab.value='info';editing.value=row;form.value={name:row.name||'',description:row.description||'',location:row.location||'',image_url:row.image_url||'',status:row.status||'active'};newPhoto.value=null;removePhoto.value=false;galleryCropKey.value+=1;modal.value=true;loadGallery(row.id)}
function close(){if(!saving.value)modal.value=false}
async function save(){
  if(saving.value)return
  if(!form.value.name.trim()){developmentTab.value='info';error(new Error('Escribe el nombre del desarrollo.'));return}
  saving.value=true
  try{
    const body=new FormData()
    body.append('name',form.value.name.trim())
    body.append('location',form.value.location?.trim()||'')
    body.append('description',form.value.description?.trim()||'')
    body.append('status',form.value.status)
    body.append('remove_image',removePhoto.value?'1':'0')
    if(newPhoto.value)body.append('photo',newPhoto.value,newPhoto.value.name)
    const path=editing.value?'/admin/im/developments/'+editing.value.id+'/save':'/admin/im/developments/save'
    await post(path,body)
    modal.value=false
    await load()
    toast(editing.value?'Desarrollo actualizado':'Desarrollo creado')
  }catch(e){error(e)}finally{saving.value=false}
}
async function del(row){
  if(row.lots_count>0||row.plans_count>0||row.leads_count>0){
    error(new Error('Este desarrollo está relacionado con terrenos, planos o prospectos. Puedes marcarlo como inactivo para conservar su historial.'))
    return
  }
  if(!await confirmDelete(row))return
  try{await remove('/admin/im/developments/'+row.id);await load();toast('Desarrollo eliminado')}
  catch(e){error(e)}
}
// ANDARMA_FASE4_DESARROLLOS: acceso directo a terrenos desde el desarrollo.
function viewLots(row){router.push({path:'/admin/propiedades',query:{desarrollo:String(row.id)}})}
function viewMaps(row){router.push({path:'/admin/mapas',query:{desarrollo:String(row.id)}})}
onMounted(load)
</script>

<template>
<section class="ad-module ad-dev ad-u-list space-y-5">
  <header class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <span class="ad-kicker">GESTIÓN INMOBILIARIA / DESARROLLOS</span>
      <h1 class="ad-heading">Desarrollos</h1>
      <p class="ad-muted">Consulta tus proyectos, organiza su orden de aparición y accede a terrenos y mapas desde un mismo listado.</p>
    </div>
    <div class="ad-actions">
      <button class="ad-btn" :disabled="loading" @click="load"><i class="fa-solid fa-rotate"></i> Actualizar</button>
      <button v-can="'developments.create'" class="ad-btn ad-btn--primary" @click="add"><i class="fa-solid fa-plus"></i> Nuevo desarrollo</button>
    </div>
  </header>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <article class="ad-card ad-dev-stat"><div class="ad-dev-stat__icon"><i class="fa-solid fa-building"></i></div><div><small>Desarrollos registrados</small><strong>{{developments.length}}</strong></div></article>
    <article class="ad-card ad-dev-stat"><div class="ad-dev-stat__icon"><i class="fa-solid fa-layer-group"></i></div><div><small>Terrenos en inventario</small><strong>{{totalLots}}</strong></div></article>
    <article class="ad-card ad-dev-stat"><div class="ad-dev-stat__icon"><i class="fa-solid fa-map"></i></div><div><small>Mapas registrados</small><strong>{{totalMapped}}</strong></div></article>
  </div>
  <section class="ad-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
      <div><h2 class="font-bold text-base">Directorio de desarrollos</h2><p class="ad-muted">{{activeCount}} activos · {{shown.length}} visibles</p></div>
      <div class="ad-dev-filters">
        <label class="ad-dev-search"><i class="fa-solid fa-magnifying-glass"></i><input v-model="search" placeholder="Buscar desarrollo o ubicación" aria-label="Buscar desarrollo"/></label>
        <select v-model="status" class="ad-select" aria-label="Filtrar por estado"><option value="">Todos los estados</option><option value="active">Activo</option><option value="upcoming">Próximamente</option><option value="inactive">Inactivo</option></select>
      </div>
    </div>
    <div class="ad-dev37-reorder-info">
      <span v-if="isFiltering"><i class="fa-solid fa-circle-info"></i> Limpia los filtros para reorganizar todos los desarrollos.</span>
      <span v-else><i class="fa-solid fa-grip-vertical"></i> Arrastra las filas por el icono de orden o usa las flechas. El orden se aplica al listado y al sitio público.</span>
      <div class="ad-dev37-sort-actions">
        <span v-if="orderChanged" class="ad-badge ad-badge--warning">Orden sin guardar</span>
        <button v-if="orderChanged" type="button" class="ad-btn" @click="undoOrder">Deshacer</button>
        <button v-can="'developments.edit'" type="button" class="ad-btn ad-btn--primary" :disabled="!orderChanged||isFiltering||orderSaving" @click="saveOrder"><i class="fa-solid fa-floppy-disk"></i> {{orderSaving?'Guardando...':'Guardar orden'}}</button>
      </div>
    </div>
    <div class="ad-table-wrap">
      <table class="ad-table ad-dev-table"><thead><tr><th class="ad-dev37-sortcell">Orden</th><th>Desarrollo</th><th>Estado</th><th>Terrenos</th><th>Mapas</th><th>Acciones</th></tr></thead>
        <tbody>
          <tr v-for="d in shown" :key="d.id" :class="{'ad-dev37-dragging':draggingId===d.id,'ad-dev37-drop':dropTargetId===d.id}" @dragover="dragOver($event,d.id)" @dragleave="dropTargetId=null" @drop="dragDrop($event,d.id)">
            <td class="ad-dev37-sortcell"><div>
              <button class="ad-dev37-drag" type="button" :draggable="!isFiltering" :disabled="isFiltering" :title="isFiltering?'Limpia los filtros para ordenar':'Arrastra para cambiar el orden'" :aria-label="'Arrastrar '+d.name" @dragstart="dragStart($event,d.id)" @dragend="dragEnd"><i class="fa-solid fa-grip-vertical"></i></button>
              <span class="ad-dev37-position">{{developments.findIndex(x=>x.id===d.id)+1}}</span>
              <div class="flex flex-col gap-1"><button v-can="'developments.edit'" type="button" class="ad-dev37-move" :disabled="isFiltering||developments[0]?.id===d.id" :aria-label="'Subir '+d.name" @click="moveStep(d.id,-1)"><i class="fa-solid fa-chevron-up"></i></button><button v-can="'developments.edit'" type="button" class="ad-dev37-move" :disabled="isFiltering||developments[developments.length-1]?.id===d.id" :aria-label="'Bajar '+d.name" @click="moveStep(d.id,1)"><i class="fa-solid fa-chevron-down"></i></button></div>
            </div></td>
            <td><div class="ad-dev-name"><div class="ad-dev-thumb"><img v-if="d.image_url" :src="d.image_url" :alt="d.name" loading="lazy"/><i v-else class="fa-regular fa-image"></i></div><div><strong>{{d.name}}</strong><small><i class="fa-solid fa-location-dot"></i> {{d.location||'Ubicación no registrada'}}</small></div></div></td>
            <td><span class="ad-badge" :class="d.status==='active'?'ad-badge--success':d.status==='inactive'?'ad-badge--danger':''">{{statusNames[d.status]||d.status}}</span></td>
            <td><strong>{{d.lots_count||0}}</strong><small class="ad-dev-secondary">{{d.available_count||0}} disponibles</small></td>
            <td><strong>{{d.plans_count||0}}</strong><small class="ad-dev-secondary">{{d.plans_count===1?'1 plano':(d.plans_count||0)+' planos'}}</small></td>
            <td><div class="ad-actions ad-dev-actions">
              <button class="ad-btn" @click="viewLots(d)" title="Consultar o agregar terrenos"><i class="fa-solid fa-list"></i> Ver / agregar terrenos</button>
              <button class="ad-btn" @click="viewMaps(d)" title="Ver planos"><i class="fa-solid fa-map"></i> Mapas</button>
              <button v-can="'developments.edit'" class="ad-btn" @click="edit(d)" title="Editar desarrollo"><i class="fa-solid fa-pen"></i> Editar</button>
              <button v-can="'developments.delete'" class="ad-btn ad-btn--danger" @click="del(d)" title="Eliminar desarrollo"><i class="fa-solid fa-trash"></i> Eliminar</button>
            </div></td>
          </tr>
          <tr v-if="!loading&&!shown.length"><td colspan="6" class="ad-dev-empty">No encontramos desarrollos con esos filtros.</td></tr>
          <tr v-if="loading"><td colspan="6" class="ad-dev-empty">Cargando desarrollos...</td></tr>
        </tbody>
      </table>
    </div>
  </section>

  <div v-if="modal" class="ad-modal-backdrop" @click.self="close">
    <section class="ad-modal ad-dev37-modal" role="dialog" aria-modal="true" aria-labelledby="ad-dev-modal-title">
      <header class="ad-modal__header"><div><span class="ad-kicker">DESARROLLOS / ADMINISTRACIÓN</span><h2 id="ad-dev-modal-title" class="ad-modal-title">{{editing?'Editar desarrollo':'Nuevo desarrollo'}}</h2><p class="ad-muted">Completa los datos, ajusta la fotografía y guarda. Los terrenos y mapas se administran por separado.</p></div><button type="button" class="ad-icon-btn" aria-label="Cerrar" @click="close"><i class="fa-solid fa-xmark"></i></button></header>
      <form class="flex flex-col min-h-0" @submit.prevent="save">
        <div class="ad-modal__body ad-dev3c-body">
          <!-- ANDARMA_FASE3C_TABS: los paneles permanecen montados para conservar recortes y datos pendientes -->
          <div class="ad-dev3c-tabs" role="tablist" aria-label="Secciones del desarrollo">
            <button id="ad-dev3c-tab-info" type="button" role="tab" class="ad-dev3c-tab" :class="{ 'is-active': developmentTab === 'info' }" :aria-selected="developmentTab === 'info'" aria-controls="ad-dev3c-panel-info" @click="developmentTab = 'info'"><i class="fa-regular fa-rectangle-list" aria-hidden="true"></i> Información</button>
            <button id="ad-dev3c-tab-media" type="button" role="tab" class="ad-dev3c-tab" :class="{ 'is-active': developmentTab === 'media' }" :aria-selected="developmentTab === 'media'" aria-controls="ad-dev3c-panel-media" @click="developmentTab = 'media'"><i class="fa-regular fa-images" aria-hidden="true"></i> Multimedia</button>
          </div>
          <div id="ad-dev3c-panel-info" v-show="developmentTab === 'info'" role="tabpanel" aria-labelledby="ad-dev3c-tab-info" class="ad-dev3c-panel space-y-5">
<div>
            <div class="ad-dev37-section"><span class="number">01</span><strong>Información del desarrollo</strong></div>
            <div class="ad-dev37-formgrid">
              <label class="andarma-field-label">Nombre del desarrollo *<input v-model.trim="form.name" class="ad-input" required minlength="2" maxlength="255" placeholder="Ej. Prados del Rosario" /></label>
              <label class="andarma-field-label">Ubicación<input v-model.trim="form.location" class="ad-input" maxlength="255" placeholder="Municipio, estado o localidad" /></label>
              <label class="andarma-field-label full">Descripción<textarea v-model.trim="form.description" class="ad-textarea" rows="3" maxlength="10000" placeholder="Describe las características principales del proyecto"></textarea></label>
            </div>
          </div>
<div>
            <div class="ad-dev37-section"><span class="number">04</span><strong>Estado y publicación</strong></div>
            <div class="ad-dev37-formgrid">
              <label class="andarma-field-label">Estado<select v-model="form.status" class="ad-select"><option value="active">Activo</option><option value="upcoming">Próximamente</option><option value="inactive">Inactivo</option></select><span class="ad-dev37-hint">Solo los desarrollos activos se publican en el sitio público.</span></label>
              <div class="ad-dev37-advanced" role="note"><strong><i class="fa-solid fa-link" aria-hidden="true"></i> Dirección web automática</strong><p class="ad-dev37-hint">El sistema genera la dirección del desarrollo a partir de su nombre. No necesitas configurarla.</p></div>
            </div>
          </div>
          </div>
          <div id="ad-dev3c-panel-media" v-show="developmentTab === 'media'" role="tabpanel" aria-labelledby="ad-dev3c-tab-media" class="ad-dev3c-panel space-y-5">

<div class="ad3a-admin-gallery ad3d-gallery">
            <!-- ANDARMA_FASE3D_GALERIA_UNIFICADA: un único lugar para subir y administrar imágenes -->
            <div class="ad3d-gallery__heading">
              <div><span class="ad-kicker">DESARROLLO / MULTIMEDIA</span><h3>Galería de fotografías</h3><p>Organiza las imágenes del proyecto y elige cuál será la portada.</p></div>
              <span v-if="editing" class="ad3d-gallery__count">{{gallery.length}} {{gallery.length===1?'fotografía':'fotografías'}}</span>
            </div>
            <template v-if="editing">
              <div class="ad3d-gallery__toolbar">
                <button type="button" class="ad-btn ad-btn--primary" :disabled="galleryBusy||saving" @click="startGalleryPhoto()"><i class="fa-solid fa-plus" aria-hidden="true"></i> Agregar fotografía</button>
                <span>Recorte profesional 16:9 · WebP optimizado</span>
              </div>
              <section v-if="galleryEditorOpen" class="ad3d-gallery__editor" aria-label="Editor de fotografía">
                <div class="ad3d-gallery__editor-head"><strong>{{galleryEditingId?'Reemplazar fotografía':'Agregar fotografía'}}</strong><button type="button" class="ad-icon-btn" aria-label="Cerrar editor" :disabled="galleryBusy" @click="closeGalleryEditor"><i class="fa-solid fa-xmark"></i></button></div>
                <p>Selecciona una fotografía, ajusta el encuadre y confirma el recorte.</p>
                <DevelopmentImageCrop :key="'galeria-unica-'+editing.id+'-'+galleryCropKey" :current-url="''" @change="uploadGallery" />
              </section>
              <p v-if="galleryBusy" class="ad3d-gallery__loading" role="status"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Actualizando galería…</p>
              <div v-if="gallery.length" class="ad3d-gallery__grid" aria-label="Fotografías del desarrollo">
                <article v-for="(item,i) in gallery" :key="item.id" class="ad3d-gallery__card" :class="{'is-primary':i===0}">
                  <div class="ad3d-gallery__preview">
                    <img :src="item.url" :alt="'Fotografía '+(i+1)+' de '+form.name" loading="lazy" />
                    <span v-if="i===0" class="ad3d-gallery__primary-badge"><i class="fa-solid fa-star" aria-hidden="true"></i> Principal</span>
                    <span v-else class="ad3d-gallery__index">{{String(i+1).padStart(2,'0')}}</span>
                  </div>
                  <div class="ad3d-gallery__card-body">
                    <button v-if="i!==0" class="ad3d-gallery__set-primary" type="button" :disabled="galleryBusy" @click="setGalleryPrimary(item)"><i class="fa-regular fa-star" aria-hidden="true"></i> Usar como principal</button>
                    <span v-else class="ad3d-gallery__current-primary">Portada del desarrollo y primera imagen del hero</span>
                    <div class="ad3d-gallery__actions">
                      <button type="button" :disabled="galleryBusy" @click="startGalleryPhoto(item)" title="Reemplazar y recortar"><i class="fa-solid fa-crop-simple" aria-hidden="true"></i><span>Reemplazar</span></button>
                      <button type="button" :disabled="galleryBusy||i===0" :aria-label="'Mover imagen '+(i+1)+' a la izquierda'" @click="moveGallery(i,-1)"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                      <button type="button" :disabled="galleryBusy||i===gallery.length-1" :aria-label="'Mover imagen '+(i+1)+' a la derecha'" @click="moveGallery(i,1)"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                      <button type="button" class="ad3d-gallery__delete" :disabled="galleryBusy" :aria-label="'Eliminar imagen '+(i+1)" title="Eliminar fotografía" @click="deleteGallery(item)"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                    </div>
                  </div>
                </article>
              </div>
              <div v-else class="ad3d-gallery__empty"><i class="fa-regular fa-images" aria-hidden="true"></i><strong>Tu desarrollo aún no tiene fotografías</strong><span>Agrega una imagen y automáticamente será la portada principal.</span></div>
            </template>
            <div v-else class="ad3d-gallery__empty"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>Primero crea el desarrollo. Después podrás cargar y organizar su galería.</span></div>
          </div>
          </div>
        </div>
        <footer class="ad-modal__footer"><button type="button" class="ad-btn" @click="close">Cancelar</button><button type="submit" class="ad-btn ad-btn--primary" :disabled="saving"><i class="fa-solid fa-floppy-disk"></i> {{saving?'Guardando...':editing?'Guardar cambios':'Crear desarrollo'}}</button></footer>
      </form>
    </section>
  </div>
</section>
</template>

<style scoped>
/* ANDARMA_FASE3C_TABS · Controles compactos sin alterar el recortador ni la galería */
.ad-dev3c-body{padding-top:14px}
.ad-dev3c-tabs{display:flex;align-items:center;gap:6px;padding:5px;background:#f2f6fc;border:1px solid #e0e9f5;border-radius:13px;margin-bottom:18px}
.ad-dev3c-tab{appearance:none;border:0;border-radius:9px;background:transparent;color:#526984;display:flex;align-items:center;justify-content:center;gap:9px;padding:11px 16px;flex:1;font:inherit;font-weight:700;font-size:13px;cursor:pointer;transition:background .2s,color .2s,box-shadow .2s}
.ad-dev3c-tab:hover{color:#0066e0;background:#e7f0fc}
.ad-dev3c-tab.is-active{background:#fff;color:#0066e0;box-shadow:0 2px 10px rgba(19,57,103,.10)}
.ad-dev3c-tab:focus-visible{outline:2px solid #0891ff;outline-offset:2px}
.ad-dev3c-panel{min-width:0}
@media(max-width:560px){.ad-dev3c-tab{padding:11px 9px;font-size:12px}.ad-dev3c-tabs{margin-bottom:12px}}
@media(prefers-reduced-motion:reduce){.ad-dev3c-tab{transition:none}}
</style>
