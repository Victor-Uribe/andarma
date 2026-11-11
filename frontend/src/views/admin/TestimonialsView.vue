<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { api, post, put, remove } from '../../services/api'
import ImageCropUpload from '../../components/ImageCropUpload.vue'
import { confirmAction, notifyError, notifySuccess } from '../../utils/adminAlerts'

// ANDARMA_V45_TESTIMONIALS
const entries=ref([]), loading=ref(false), saving=ref(false), changing=ref(null), sorting=ref(false)
const query=ref(''), visibility=ref('all'), open=ref(false), editing=ref(null), error=ref('')
const page=ref(1), perPage=8
const clean=()=>({name:'',role:'',quote:'',image_url:'',property_image_url:'',rating:5,is_active:true,sort_order:0})
const form=ref(clean())
const sorted=computed(()=>[...entries.value].sort((a,b)=>(Number(a.sort_order||0)-Number(b.sort_order||0))||(Number(a.id)-Number(b.id))))
const filtered=computed(()=>sorted.value.filter(x=>{
 const ok=visibility.value==='all'||Boolean(Number(x.is_active))===(visibility.value==='active')
 return ok&&[x.name,x.role,x.quote].join(' ').toLocaleLowerCase('es').includes(query.value.toLocaleLowerCase('es').trim())
}))
const pageCount=computed(()=>Math.max(1,Math.ceil(filtered.value.length/perPage)))
const visible=computed(()=>filtered.value.slice((page.value-1)*perPage,page.value*perPage))
watch([query,visibility],()=>page.value=1)
watch(pageCount,n=>{if(page.value>n)page.value=n})
const enabled=x=>Boolean(Number(x?.is_active))
const avatarLetter=x=>String(x?.name||'?').trim().slice(0,1).toUpperCase()
function startNew(){editing.value=null;form.value={...clean(),sort_order:sorted.value.length+1};error.value='';open.value=true}
function edit(row){editing.value=row.id;form.value={...clean(),...row,is_active:enabled(row)};error.value='';open.value=true}
function close(){if(!saving.value)open.value=false}
async function load(){loading.value=true;try{const response=await api('/admin/testimonials');entries.value=response.data||[]}catch(e){notifyError('No se pudieron cargar los testimonios',e.message)}finally{loading.value=false}}
async function save(){if(saving.value)return;error.value=''
 const name=String(form.value.name||'').trim(), quote=String(form.value.quote||'').trim()
 if(name.length<2||quote.length<10){error.value='Escribe el nombre del cliente y un testimonio de al menos 10 caracteres.';return}
 const payload={name,role:String(form.value.role||'').trim()||null,quote,image_url:form.value.image_url||null,property_image_url:form.value.property_image_url||null,rating:Number(form.value.rating)||5,is_active:!!form.value.is_active,sort_order:Number(form.value.sort_order)||0}
 saving.value=true
 try{if(editing.value)await put('/admin/testimonials/'+editing.value,payload);else await post('/admin/testimonials',payload)
   open.value=false;await load();notifySuccess(editing.value?'Testimonio actualizado':'Testimonio agregado')
 }catch(e){error.value=e.message||'No fue posible guardar.'}finally{saving.value=false}
}
async function toggle(row){if(changing.value||sorting.value)return;changing.value=row.id
 try{await put('/admin/testimonials/'+row.id,{...row,is_active:!enabled(row),rating:Number(row.rating)||5});await load();notifySuccess(enabled(row)?'Testimonio ocultado':'Testimonio publicado')}
 catch(e){notifyError('No se pudo cambiar la visibilidad',e.message)}finally{changing.value=null}
}
async function discard(row){const ok=await confirmAction({title:'¿Eliminar testimonio?',text:`Se eliminará el testimonio de ${row.name}. Esta acción no se puede deshacer.`,confirmText:'Sí, eliminar',cancelText:'Conservar',variant:'danger'})
 if(!ok)return
 try{await remove('/admin/testimonials/'+row.id);await load();notifySuccess('Testimonio eliminado')}
 catch(e){notifyError('No se pudo eliminar',e.message)}
}
async function move(row,step){if(sorting.value||changing.value||query.value||visibility.value!=='all')return
 const old=sorted.value,ix=old.findIndex(x=>x.id===row.id),j=ix+step
 if(j<0||j>=old.length)return
 const next=[...old];[next[ix],next[j]]=[next[j],next[ix]]
 sorting.value=true
 try{
   // Se normalizan posiciones consecutivas porque registros antiguos pueden tener orden 0.
   for (let i=0;i<next.length;i++) {
     await put('/admin/testimonials/'+next[i].id,{...next[i],sort_order:i+1})
   }
   await load()
 }catch(e){notifyError('No se pudo cambiar el orden',e.message);await load()}finally{sorting.value=false}
}
onMounted(load)
</script>

<template>
<section class="ad-testimonials space-y-5">
 <header class="ad-test-head">
  <div><span class="ad-kicker">PÁGINA WEB / OPINIONES</span><h1 class="ad-heading">Testimonios</h1><p class="ad-muted">Comparte las experiencias de tus clientes, sin complicaciones.</p></div>
  <button v-can="'testimonials.create'" class="ad-btn ad-btn--primary" type="button" @click="startNew"><i class="fa-solid fa-plus"></i> Agregar testimonio</button>
 </header>
 <section class="ad-card ad-test-list">
  <div class="ad-test-toolbar">
   <label class="ad-label ad-test-search">Buscar<input v-model="query" class="ad-input" placeholder="Cliente o comentario" /></label>
   <label class="andarma-field-label">Mostrar<select v-model="visibility" class="ad-select"><option value="all">Todos</option><option value="active">Visibles</option><option value="inactive">Ocultos</option></select></label>
   <span class="ad-badge">{{filtered.length}} testimonios</span>
   <button type="button" class="ad-btn" :disabled="loading" @click="load"><i class="fa-solid fa-rotate"></i> Actualizar</button>
  </div>
  <div class="ad-test-table-wrap">
   <table class="ad-table ad-test-table"><thead><tr><th>Orden</th><th>Cliente</th><th>Testimonio</th><th>Calificación</th><th>Publicación</th><th>Acciones</th></tr></thead>
    <tbody>
      <tr v-for="r in visible" :key="r.id">
       <td><div class="ad-test-order"><strong>{{sorted.findIndex(x=>x.id===r.id)+1}}</strong><div class="ad-test-order-buttons"><button v-can="'testimonials.edit'" class="ad-icon-btn" type="button" title="Subir" :disabled="sorting||!!changing||!!query||visibility!=='all'||sorted[0]?.id===r.id" @click="move(r,-1)"><i class="fa-solid fa-chevron-up"></i></button><button v-can="'testimonials.edit'" class="ad-icon-btn" type="button" title="Bajar" :disabled="sorting||!!changing||!!query||visibility!=='all'||sorted[sorted.length-1]?.id===r.id" @click="move(r,1)"><i class="fa-solid fa-chevron-down"></i></button></div></div></td>
       <td><div class="ad-test-person"><img v-if="r.image_url" :src="r.image_url" :alt="'Foto de '+r.name"/><span v-else class="ad-test-initial">{{avatarLetter(r)}}</span><div><strong>{{r.name}}</strong><small>{{r.role||'Cliente'}}</small></div></div></td>
       <td><p class="ad-test-quote">{{r.quote}}</p></td>
       <td><span class="ad-test-stars" :aria-label="r.rating+' de 5 estrellas'">{{'★'.repeat(Math.min(5,Math.max(1,Number(r.rating)||5)))}}</span></td>
       <td><span class="ad-badge" :class="enabled(r)?'ad-badge--success':'ad-badge--danger'">{{enabled(r)?'Visible':'Oculto'}}</span></td>
       <td><div class="ad-actions ad-test-actions"><button v-can="'testimonials.edit'" class="ad-btn" type="button" @click="edit(r)"><i class="fa-solid fa-pen"></i> Editar</button><button v-can="'testimonials.edit'" class="ad-btn" type="button" :disabled="changing===r.id||sorting" @click="toggle(r)"><i :class="enabled(r)?'fa-solid fa-eye-slash':'fa-solid fa-eye'"></i>{{enabled(r)?'Ocultar':'Publicar'}}</button><button v-can="'testimonials.delete'" class="ad-btn ad-btn--danger ad-icon-btn" type="button" :aria-label="'Eliminar testimonio de '+r.name" @click="discard(r)"><i class="fa-solid fa-trash"></i></button></div></td>
      </tr>
      <tr v-if="!loading&&!visible.length"><td colspan="6" class="ad-test-empty"><i class="fa-regular fa-comment-dots"></i><strong>No hay testimonios para mostrar</strong><span>Agrega un comentario real autorizado para comenzar.</span><button v-can="'testimonials.create'" class="ad-btn ad-btn--primary" type="button" @click="startNew">Agregar testimonio</button></td></tr>
      <tr v-if="loading"><td colspan="6" class="ad-test-empty">Cargando testimonios…</td></tr>
    </tbody>
   </table>
  </div>
  <footer v-if="filtered.length>perPage" class="ad-test-pagination"><span>Página {{page}} de {{pageCount}}</span><div><button class="ad-btn" :disabled="page<=1" @click="page--">Anterior</button><button class="ad-btn" :disabled="page>=pageCount" @click="page++">Siguiente</button></div></footer>
 </section>
 <div v-if="open" class="ad-modal-backdrop" @click.self="close">
  <section class="ad-modal ad-test-dialog" role="dialog" aria-modal="true" aria-labelledby="ad-test-form-title">
   <header class="ad-modal__header"><div><span class="ad-kicker">TESTIMONIOS / {{editing?'EDITAR':'NUEVO'}}</span><h2 id="ad-test-form-title" class="ad-modal-title">{{editing?'Editar testimonio':'Nuevo testimonio'}}</h2><p class="ad-muted">Completa la experiencia del cliente y guarda.</p></div><button type="button" class="ad-icon-btn" aria-label="Cerrar" @click="close"><i class="fa-solid fa-xmark"></i></button></header>
   <form class="ad-test-form" @submit.prevent="save"><div class="ad-modal__body">
    <div class="ad-test-fields">
     <label class="andarma-field-label">Nombre del cliente *<input v-model.trim="form.name" required minlength="2" maxlength="255" class="ad-input" placeholder="Ej. Familia Ramírez"/></label>
     <label class="andarma-field-label">Tipo de experiencia<input v-model.trim="form.role" maxlength="255" class="ad-input" placeholder="Ej. Compra de terreno"/></label>
     <label class="ad-label ad-test-full">¿Qué comentó el cliente? *<textarea v-model.trim="form.quote" class="ad-textarea" required minlength="10" maxlength="1400" rows="4" placeholder="Escribe aquí sus palabras…"></textarea><span class="ad-test-count">{{form.quote.length}} / 1400</span></label>
     <div class="ad-test-property-photo ad-test-full"><span class="ad-test-label">Imagen de la propiedad o terreno (opcional)</span><p class="ad-muted">Es la fotografía horizontal que aparece arriba del testimonio público.</p><ImageCropUpload v-model="form.property_image_url" label="Fotografía del terreno o vivienda" :ratio="16/9"/><small>Recorta a 16:9. Si no agregas una, se conservará la imagen arquitectónica ilustrativa.</small></div>
     <div class="ad-test-photo"><span class="ad-test-label">Fotografía del cliente (opcional)</span><ImageCropUpload v-model="form.image_url" label="Fotografía del cliente" :ratio="1"/><small>Usa una fotografía autorizada. Si no tienes una, aparecerá su inicial.</small></div>
     <div class="ad-test-score"><span class="ad-test-label">Calificación</span><div class="ad-test-rating" role="group" aria-label="Calificación del cliente"><button v-for="n in 5" :key="n" type="button" :class="{'is-selected':n<=form.rating}" :aria-label="n+' estrellas'" :aria-pressed="form.rating===n" @click="form.rating=n">★</button></div><span class="ad-test-count">{{form.rating}} de 5 estrellas</span><div class="ad-test-visibility"><label><input v-model="form.is_active" type="checkbox"/> Publicar en la página web</label></div></div>
    </div>
    <p v-if="error" class="ad-test-error" role="alert">{{error}}</p>
   </div><footer class="ad-modal__footer"><button type="button" class="ad-btn" @click="close">Cancelar</button><button type="submit" :disabled="saving" class="ad-btn ad-btn--primary"><i class="fa-solid fa-floppy-disk"></i> {{saving?'Guardando…':editing?'Guardar cambios':'Agregar testimonio'}}</button></footer></form>
  </section>
 </div>
</section>
</template>
