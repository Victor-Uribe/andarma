<!-- ANDARMA_BANNERS_V43 -->
<script setup>
import {ref,computed,onMounted} from 'vue'
import {api,post,put,remove} from '../../services/api'
import ImageCropUpload from '../../components/ImageCropUpload.vue'
import Swal from 'sweetalert2'
import { confirmAction as confirmAdminAction } from '../../utils/adminAlerts'
import '../../assets/admin-banners-v41.css'

const rows=ref([]),loading=ref(false),saving=ref(false),modal=ref(false),editing=ref(null),ordering=ref(false),toggling=ref(null)
const fieldError=ref('')
const defaults={eyebrow:'ANDARMA / UNA NUEVA VISIÓN',overline:'EL FUTURO NO SE ESPERA. SE CONSTRUYE.',footnote:'ANDARMA / DISEÑANDO POSIBILIDADES',tagline:'ARQUITECTURA · VISIÓN · FUTURO',editorial_label:'UNA NUEVA FORMA DE HABITAR',side_title:'ESPACIOS CON PROPÓSITO',side_description:'Una visión inmobiliaria para nuevas historias.'}
function blank(){return {title:'',title_accent:'',subtitle:'',button_text:'Explorar desarrollos',button_url:'#desarrollos',image_url:'',sort_order:0,is_active:true,...defaults}}
const form=ref(blank())
const sorted=computed(()=>[...rows.value].sort((a,b)=>Number(a.sort_order||0)-Number(b.sort_order||0)||a.id-b.id))
const preview=computed(()=>form.value)
const swalTheme={buttonsStyling:false,customClass:{popup:'andarma-swal',title:'andarma-swal__title',confirmButton:'andarma-swal__confirm',cancelButton:'andarma-swal__cancel'}}
async function load(){loading.value=true;try{rows.value=(await api('/admin/banners')).data||[]}catch(e){alertError(e.message)}finally{loading.value=false}}
function add(){editing.value=null;form.value={...blank(),sort_order:rows.value.length+1};fieldError.value='';modal.value=true}
function edit(row){editing.value=row.id;form.value={...blank(),...row,is_active:Number(row.is_active)===1};fieldError.value='';modal.value=true}
function close(){if(!saving.value)modal.value=false}
async function save(){
 if(saving.value)return
 fieldError.value=''
 if(!form.value.title?.trim())return fieldError.value='Escribe el título principal.'
 if(!form.value.image_url)return fieldError.value='Selecciona y recorta una imagen para este banner.'
 if(form.value.button_text && !form.value.button_url)return fieldError.value='Indica a dónde llevará el botón.'
 saving.value=true
 try{
   const keys=['title','title_accent','subtitle','eyebrow','overline','footnote','tagline','editorial_label','side_title','side_description','image_url','button_text','button_url','sort_order','is_active']
   const data=Object.fromEntries(keys.map(k=>[k,form.value[k]??null]))
   data.sort_order=Number(data.sort_order||0);data.is_active=!!data.is_active
   if(editing.value)await put('/admin/banners/'+editing.value,data)
   else await post('/admin/banners',data)
   modal.value=false;await load()
   Swal.fire({...swalTheme,toast:true,position:'top-end',timer:2300,showConfirmButton:false,icon:'success',title:'Banner guardado correctamente'})
 }catch(e){fieldError.value=e.message||'No se pudo guardar el banner.'}
 finally{saving.value=false}
}
async function del(row){
 const accepted=await confirmAdminAction({title:'Eliminar banner',text:'Se quitará el banner de la administración y del sitio público. Esta acción no se puede deshacer.',confirmText:'Sí, eliminar',cancelText:'Conservar',variant:'danger'})
 if(!accepted)return
 try{await remove('/admin/banners/'+row.id);await load()}catch(e){alertError(e.message)}
}
function alertError(message){Swal.fire({...swalTheme,icon:'error',title:'No se pudo completar',text:message})}
function active(row){return Number(row.is_active)===1}
async function toggle(row){
 if(toggling.value || ordering.value)return
 toggling.value=row.id
 try{
  const keys=['title','title_accent','subtitle','eyebrow','overline','footnote','tagline','editorial_label','side_title','side_description','image_url','button_text','button_url','sort_order']
  const data=Object.fromEntries(keys.map(k=>[k,row[k]??null]))
  data.is_active=!active(row)
  await put('/admin/banners/'+row.id,data)
  row.is_active=data.is_active?1:0
 }catch(e){alertError(e.message)}finally{toggling.value=null}
}
async function move(row,delta){
 if(ordering.value || toggling.value)return
 const items=sorted.value
 const index=items.findIndex(item=>item.id===row.id)
 const target=index+delta
 if(target<0 || target>=items.length)return
 const ids=items.map(item=>item.id)
 ;[ids[index],ids[target]]=[ids[target],ids[index]]
 ordering.value=true
 try{
  await post('/admin/banners/reorder',{ids})
  await load()
 }catch(e){alertError(e.message)}finally{ordering.value=false}
}
onMounted(load)
</script>
<template>
<section class="ad-banner-page ad-module">
  <header class="ad-banner-head">
    <div><span class="ad-kicker">PÁGINA WEB / PORTADA</span><h1 class="ad-heading">Banners del inicio</h1><p class="ad-muted">Edita cada escena de la portada: fotografía, títulos, descripción y botones. Los cambios se verán en el carrusel público.</p></div>
    <div class="ad-actions"><a class="ad-btn" href="/" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver página</a><button v-can="'banners.create'" class="ad-btn ad-btn--primary" @click="add"><i class="fa-solid fa-plus"></i> Nuevo banner</button></div>
  </header>
  <div class="ad-banner-guide"><i class="fa-solid fa-circle-info"></i><span>Cada banner es una diapositiva del hero. Solo se publican los activos. Si no hay ninguno activo, la portada conservará sus fotografías predeterminadas.</span></div>
  <div class="ad-banner-sort-hint"><i class="fa-solid fa-arrow-down-1-9"></i> El orden de las tarjetas es el orden de aparición en la portada. Usa las flechas para cambiarlo.</div>
  <!-- ANDARMA_BANNERS_LIST_V44: administración primero; imagen como miniatura -->
  <div class="ad-banner-list" aria-label="Administración de banners del inicio">
    <div class="ad-banner-list-head">
      <div>
        <h2>Banners registrados</h2>
        <p>Organiza, edita y publica las escenas del inicio.</p>
      </div>
      <span class="ad-badge">{{sorted.length}} {{sorted.length===1?'banner':'banners'}}</span>
    </div>
    <div v-if="loading" class="ad-banner-list-message">Cargando banners…</div>
    <article v-for="(banner,i) in sorted" :key="banner.id" class="ad-banner-list-row" :class="{'is-inactive':!active(banner)}">
      <div class="ad-banner-list-rank ad-banner-list-rank--v46" :aria-label="'Posición '+(i+1)">
        <strong>{{i+1}}</strong>
        <div class="ad-banner-rank-controls">
          <button v-can="'banners.edit'" class="ad-icon-btn" type="button" :disabled="i===0||ordering||!!toggling" :aria-label="'Subir banner '+(i+1)" title="Subir" @click="move(banner,-1)"><i class="fa-solid fa-chevron-up"></i></button>
          <button v-can="'banners.edit'" class="ad-icon-btn" type="button" :disabled="i===sorted.length-1||ordering||!!toggling" :aria-label="'Bajar banner '+(i+1)" title="Bajar" @click="move(banner,1)"><i class="fa-solid fa-chevron-down"></i></button>
        </div>
      </div>
      <div class="ad-banner-list-thumb">
        <img v-if="banner.image_url" :src="banner.image_url" :alt="'Imagen del banner '+(i+1)" loading="lazy"/>
        <i v-else class="fa-regular fa-image" aria-hidden="true"></i>
      </div>
      <div class="ad-banner-list-details">
        <h3>{{banner.title||'Sin título'}} <span v-if="banner.title_accent">{{banner.title_accent}}</span></h3>
        <p>{{banner.subtitle||'Sin descripción'}}</p>
        <small v-if="banner.button_text"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> {{banner.button_text}}</small>
      </div>
      <div class="ad-banner-list-status">
        <span class="ad-badge" :class="active(banner)?'ad-badge--success':'ad-badge--danger'">{{active(banner)?'Publicado':'Desactivado'}}</span>
      </div>

      <div class="ad-banner-list-actions">
        <button v-can="'banners.edit'" class="ad-btn ad-btn--primary" type="button" @click="edit(banner)"><i class="fa-solid fa-pen" aria-hidden="true"></i> Editar</button>
        <button v-can="'banners.edit'" class="ad-btn" type="button" :disabled="toggling===banner.id||ordering" @click="toggle(banner)">
          <i :class="active(banner)?'fa-solid fa-eye-slash':'fa-solid fa-eye'" aria-hidden="true"></i> {{toggling===banner.id?'Guardando…':active(banner)?'Desactivar':'Activar'}}
        </button>
        <button v-can="'banners.delete'" class="ad-btn ad-btn--danger ad-icon-btn" type="button" :disabled="!!toggling||ordering" :aria-label="'Eliminar banner '+(i+1)" title="Eliminar banner" @click="del(banner)"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
      </div>
    </article>
    <div v-if="!loading&&!sorted.length" class="ad-banner-list-message">
      Aún no hay banners registrados. <button v-can="'banners.create'" class="ad-btn ad-btn--primary" type="button" @click="add">Crear primer banner</button>
    </div>
  </div>
  <div v-if="modal" class="ad-modal-backdrop ad-banner-overlay" @click.self="close">
    <section class="ad-modal ad-banner-modal" role="dialog" aria-modal="true" aria-label="Editor de banner">
      <header class="ad-modal__header"><div><span class="ad-kicker">EDITOR VISUAL / HERO</span><h2>{{editing?'Editar banner':'Nuevo banner'}}</h2><p class="ad-muted">Personaliza la escena y revisa el resultado antes de publicar.</p></div><button class="ad-icon-btn" type="button" aria-label="Cerrar" @click="close"><i class="fa-solid fa-xmark"></i></button></header>
      <div class="ad-banner-edit-layout">
        <form id="bannerForm" class="ad-banner-edit-form" @submit.prevent="save">
          <section class="ad-banner-section"><h3><span>01</span> Texto del hero</h3><div class="ad-banner-fields">
            <label class="ad-label ad-banner-wide">Frase superior<input class="ad-input" maxlength="255" v-model="form.overline" placeholder="EL FUTURO NO SE ESPERA. SE CONSTRUYE."/></label>
            <label class="ad-label">Primera línea del título *<input class="ad-input" required maxlength="255" v-model="form.title" placeholder="Aquí empieza"/></label>
            <label class="ad-label">Segunda línea destacada<input class="ad-input" maxlength="255" v-model="form.title_accent" placeholder="lo que sigue."/></label>
            <label class="ad-label ad-banner-wide">Descripción<textarea class="ad-textarea" maxlength="3000" rows="3" v-model="form.subtitle" placeholder="Describe el mensaje principal de la escena"/></label>
          </div></section>
          <section class="ad-banner-section"><h3><span>02</span> Fotografía de portada</h3><p>Selecciona una fotografía y encuádrala a 16:9. El editor genera WebP optimizado.</p><ImageCropUpload v-model="form.image_url" label="Imagen de fondo del hero" :ratio="16/9" :max-kb="450"/></section>
          <section class="ad-banner-section"><h3><span>03</span> Botón principal</h3><div class="ad-banner-fields"><label class="ad-label">Texto del botón<input class="ad-input" maxlength="255" v-model="form.button_text" placeholder="Explorar desarrollos"/></label><label class="ad-label">Destino del botón<input class="ad-input" maxlength="1024" v-model="form.button_url" placeholder="#desarrollos"/></label></div><small>Utiliza #desarrollos, #contacto o una URL completa.</small></section>
          <section class="ad-banner-section"><h3><span>04</span> Etiqueta de la escena</h3><label class="ad-label">Texto pequeño inferior<input v-model="form.eyebrow" class="ad-input" maxlength="255" placeholder="ANDARMA / TU PRÓXIMA ETAPA"/></label></section>
          <section class="ad-banner-section ad-banner-publish"><h3><span>05</span> Publicación</h3><label class="ad-banner-toggle"><input type="checkbox" v-model="form.is_active"/><span><strong>Banner activo</strong><small>Si desactivas este banner, dejará de aparecer en la portada.</small></span></label><p class="ad-muted">Para cambiar la posición, utiliza las flechas del listado de banners.</p></section>
          <p v-if="fieldError" class="ad-banner-error" role="alert">{{fieldError}}</p>
        </form>
        <aside class="ad-banner-preview-col"><div class="ad-banner-preview-heading"><strong>Vista previa</strong><small>Referencia visual · 16:9</small></div><div class="ad-banner-preview" :style="preview.image_url?{backgroundImage:`linear-gradient(90deg,rgba(2,16,37,.94),rgba(3,22,46,.3)),url(${preview.image_url})`}:{}"><div class="ad-banner-preview-text"><small>{{preview.overline}}</small><h3>{{preview.title||'Título principal'}} <em>{{preview.title_accent}}</em></h3><p>{{preview.subtitle||'Aquí aparecerá la descripción.'}}</p><span v-if="preview.button_text" class="ad-banner-preview-button">{{preview.button_text}} <i class="fa-solid fa-arrow-up-right-from-square"></i></span><small class="ad-banner-preview-eyebrow">{{preview.eyebrow}}</small></div></div><p class="ad-muted">Esta vista muestra la composición aproximada. La página pública conserva sus propias transiciones y diseño responsive.</p></aside>
      </div>
      <footer class="ad-modal__footer"><button class="ad-btn" type="button" @click="close">Cancelar</button><button form="bannerForm" type="submit" class="ad-btn ad-btn--primary" :disabled="saving"><i class="fa-solid fa-floppy-disk"></i> {{saving?'Guardando...':'Guardar banner'}}</button></footer>
    </section>
  </div>
</section>
</template>
