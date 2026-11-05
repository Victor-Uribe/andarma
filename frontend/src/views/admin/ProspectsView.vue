<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { api, post, put, remove } from '../../services/api'
import { confirmAction, notifyError, notifySuccess } from '../../utils/adminAlerts'
import { useRouter } from 'vue-router'
import {can} from '../../utils/andarmaPermissions'
import { UserRoundPlus, Search, RefreshCw, Plus, X, Phone, MessageCircle, Pencil, Trash2, ChevronLeft, ChevronRight, AlertCircle, CheckCircle2, UsersRound, UserCheck, Clock3, Check, ChevronDown, ClipboardList, ArrowRight, ShieldAlert } from 'lucide-vue-next'

const router = useRouter()
const rows = ref([])
const developments = ref([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const error = ref('')
const notification = ref('')
const query = ref('')
const statusFilter = ref('all')
const page = ref(1)
const perPage = 10
const showModal = ref(false)
const showExtra = ref(false)
const editingId = ref(null)
const formError = ref('')

const form = ref(emptyForm())
const statuses = [
  { value: 'new', label: 'Nuevo' },
  { value: 'contacted', label: 'Contactado' },
  { value: 'qualified', label: 'Calificado' },
  { value: 'closed', label: 'Cerrado' },
]
const statusText = value => statuses.find(s => s.value === value)?.label || value || 'Nuevo'
const statusClass = value => ({new:'new',contacted:'contacted',qualified:'qualified',closed:'closed'}[value] || 'new')
const filtered = computed(() => rows.value.filter(item => {
  const matchesStatus = statusFilter.value === 'all' || item.status === statusFilter.value
  const text = [item.name, item.phone, item.email, item.message, item.notes].map(v => String(v || '')).join(' ').toLocaleLowerCase('es-MX')
  return matchesStatus && text.includes(query.value.trim().toLocaleLowerCase('es-MX'))
}))
const pageCount = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paged = computed(() => filtered.value.slice((page.value-1)*perPage,page.value*perPage))
const counts = computed(() => ({
  total: rows.value.length,
  new: rows.value.filter(x => x.status === 'new').length,
  contacted: rows.value.filter(x => x.status === 'contacted').length,
  qualified: rows.value.filter(x => x.status === 'qualified').length,
}))
watch([query,statusFilter],() => page.value = 1)
watch(pageCount, total => {if(page.value > total) page.value = total})
function emptyForm() {return {name:'',phone:'',email:'',development_id:'',message:'',status:'new',notes:''}}
function initials(name) {return String(name || '?').trim().split(/\s+/).slice(0,2).map(x=>x[0]?.toUpperCase() || '').join('')}
function displayDate(date) {if(!date) return '—'; const v=new Date(date); return Number.isNaN(v.getTime())?'—':v.toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'})}
function waUrl(phone) {const digits=String(phone || '').replace(/\D/g,'');return digits.length === 10 ? `https://wa.me/52${digits}` : digits.length >= 11 && digits.length <= 15 ? `https://wa.me/${digits}` : null}
function developmentName(id) {return developments.value.find(d=>String(d.id)===String(id))?.name || (id ? `Desarrollo #${id}` : 'Sin asignar')}
function converted(row){return Number(row?.converted_client_id||0)>0}
function openConverted(row){if(!converted(row))return;router.push({path:'/admin/clientes',query:{client:row.converted_client_id}})}
async function convertToClient(row){
  if(converted(row)){openConverted(row);return}
  if(!can('clients.create') || !can('prospects.edit'))return
  if(!row.phone){notifyError('Falta teléfono','Agrega el teléfono al prospecto antes de convertirlo.');return}
  if(saving.value)return
  const confirmed=await confirmAction({
    title:'Convertir prospecto en cliente',
    text:`Se creará el expediente de ${row.name}, conservando sus datos de contacto. Después podrás asignarle un terreno.`,
    confirmText:'Sí, convertir'
  })
  if(!confirmed)return
  saving.value=true
  try{
    const response=await post(`/admin/leads/${row.id}/convert`,{})
    const id=response?.data?.id
    if(!id)throw new Error('La conversión no devolvió el identificador del cliente.')
    notifySuccess(response.already_converted?'Cliente encontrado':'Prospecto convertido en cliente')
    router.push({path:'/admin/clientes',query:{client:String(id)}})
  }catch(e){notifyError('No se pudo convertir el prospecto',e.message||'Revisa los datos.')}finally{saving.value=false}
}

async function load() {
  loading.value = true;error.value = ''
  try {
    const response = await api('/admin/leads')
    rows.value = Array.isArray(response.data) ? response.data : []
    try {const d=await api('/admin/developments'); developments.value=Array.isArray(d.data)?d.data:[]} catch {developments.value=[]}
  } catch(e) {error.value = e.message || 'No fue posible cargar los prospectos.'}
  finally {loading.value = false}
}
function openNew(){editingId.value=null;form.value=emptyForm();showExtra.value=false;formError.value='';showModal.value=true}
function openEdit(row){if(converted(row)){notifyError('Prospecto convertido','Su información se administra desde el expediente del cliente.');return}editingId.value=row.id;form.value={...emptyForm(),...row,development_id:row.development_id ?? ''};showExtra.value=Boolean(row.email||row.development_id||row.message||row.notes);formError.value='';showModal.value=true}
function close(){if(!saving.value)showModal.value=false}
async function save(){
  if(saving.value) return
  formError.value=''
  const name=String(form.value.name||'').trim()
  const phone=String(form.value.phone||'').replace(/[^\d+]/g,'')
  const digits=phone.replace(/\D/g,'')
  if(name.length < 2){formError.value='Escribe el nombre del prospecto (mínimo 2 caracteres).';return}
  if(digits.length < 10 || digits.length > 15){formError.value='Ingresa un teléfono válido de 10 a 15 dígitos.';return}
  if(form.value.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)){formError.value='Revisa el formato del correo electrónico.';return}
  const payload = {
    name,
    phone,
    email:String(form.value.email||'').trim() || null,
    development_id:form.value.development_id ? Number(form.value.development_id) : null,
    message:String(form.value.message||'').trim() || null,
    status:form.value.status || 'new',
    notes:String(form.value.notes||'').trim() || null,
  }
  saving.value=true
  try {
    if(editingId.value) await put(`/admin/leads/${editingId.value}`, payload)
    else await post('/admin/leads', payload)
    notification.value = editingId.value ? 'Cambios guardados correctamente.' : 'Prospecto registrado correctamente.'
    showModal.value=false
    await load()
  } catch(e) {formError.value=e.message || 'No fue posible guardar el prospecto.'}
  finally {saving.value=false}
}
async function updateStatus(row,next){
  if(row.status===next)return
  if(converted(row)){notifyError('Prospecto convertido','El seguimiento pertenece ahora al expediente del cliente.');await load();return}
  error.value='';notification.value=''
  try{
    await put(`/admin/leads/${row.id}`,{
      name:row.name,phone:row.phone || null,email:row.email || null,
      development_id:row.development_id || null,message:row.message || null,
      status:next,notes:row.notes || null,
    })
    notification.value=`${row.name}: estado actualizado a ${statusText(next).toLowerCase()}.`
    await load()
  }catch(e){error.value=e.message || 'No se pudo actualizar el estado.'}
}
async function confirmDelete(row){
  if(deleting.value)return
  if(converted(row)){
    notifyError('No se puede eliminar','El prospecto ya fue convertido a cliente. El expediente y su origen deben conservarse.')
    return
  }
  const accepted=await confirmAction({title:'Eliminar prospecto',text:`Se eliminará el registro de ${row.name}. Esta acción no se puede deshacer.`,confirmText:'Sí, eliminar',variant:'danger'})
  if(!accepted)return
  deleting.value=true
  try{
    await remove(`/admin/leads/${row.id}`)
    notifySuccess('Prospecto eliminado correctamente')
    await load()
  }catch(e){notifyError('No se pudo eliminar',e?.message||'Inténtalo nuevamente.')}
  finally{deleting.value=false}
}
function onKey(e){if(e.key==='Escape'&&!saving.value&&!deleting.value){showModal.value=false}}
onMounted(()=>{load();window.addEventListener('keydown',onKey)})
onUnmounted(()=>window.removeEventListener('keydown',onKey))
</script>

<template>
  <div class="prospects-v29">
    <header class="pv-page-header">
      <div><span class="pv-eyebrow">GESTIÓN COMERCIAL / PROSPECTOS</span><h1>Prospectos</h1><p>Registra interesados, da seguimiento y prepara oportunidades de venta.</p></div>
      <button v-can="'prospects.create'" class="pv-btn pv-btn-primary" type="button" @click="openNew"><Plus :size="17"/> Nuevo prospecto</button>
    </header>

    <div v-if="error" class="pv-alert pv-alert-error" role="alert"><AlertCircle :size="18"/>{{error}}</div>
    <div v-if="notification" class="pv-alert pv-alert-success" role="status"><CheckCircle2 :size="18"/>{{notification}}<button aria-label="Cerrar aviso" @click="notification=''">×</button></div>

    <section class="pv-stats" aria-label="Resumen de prospectos">
      <button v-for="stat in [{key:'all',label:'Total de prospectos',value:counts.total,icon:UsersRound},{key:'new',label:'Nuevos',value:counts.new,icon:UserRoundPlus},{key:'contacted',label:'Contactados',value:counts.contacted,icon:Clock3},{key:'qualified',label:'Calificados',value:counts.qualified,icon:UserCheck}]" :key="stat.key" class="pv-stat" :class="{'is-selected':statusFilter===stat.key}" @click="statusFilter=stat.key"><span class="pv-stat-icon"><component :is="stat.icon" :size="19"/></span><span><small>{{stat.label}}</small><strong>{{stat.value}}</strong></span></button>
    </section>

    <section class="pv-panel">
      <div class="pv-panel-title"><div><h2>Seguimiento comercial</h2><p>Consulta, contacta o actualiza el estado de cada interesado.</p></div><span class="pv-counter">{{filtered.length}} registros</span></div>
      <div class="pv-toolbar"><label class="pv-search"><Search :size="18"/><input v-model="query" type="search" placeholder="Buscar por nombre, teléfono o correo" aria-label="Buscar prospectos"/></label><select v-model="statusFilter" aria-label="Filtrar por estado"><option value="all">Todos los estados</option><option v-for="s in statuses" :key="s.value" :value="s.value">{{s.label}}</option></select><button class="pv-btn pv-btn-secondary" :disabled="loading" @click="load"><RefreshCw :size="16"/> Actualizar</button></div>
      <div class="pv-table-wrapper"><table class="pv-table"><thead><tr><th>PROSPECTO</th><th>INTERÉS</th><th>SEGUIMIENTO</th><th>REGISTRO</th><th class="pv-actions-header">ACCIONES</th></tr></thead><tbody><tr v-for="row in paged" :key="row.id"><td><div class="pv-person"><span class="pv-avatar">{{initials(row.name)}}</span><div><strong>{{row.name}}</strong><small>{{row.phone||'Sin teléfono'}}<template v-if="row.email"> · {{row.email}}</template></small><span v-if="converted(row)" class="pv-converted">✓ Convertido a cliente #{{row.converted_client_id}}</span></div></div></td><td><span class="pv-interest">{{developmentName(row.development_id)}}</span></td><td><select class="pv-status" :disabled="converted(row)" :class="`pv-status-${statusClass(row.status)}`" :value="row.status" :aria-label="`Estado de ${row.name}`" @change="updateStatus(row,$event.target.value)"><option v-for="s in statuses" :key="s.value" :value="s.value">{{s.label}}</option></select></td><td class="pv-date">{{displayDate(row.created_at)}}</td><td><div class="pv-actions"><a v-if="waUrl(row.phone)" class="pv-whatsapp" :href="waUrl(row.phone)" target="_blank" rel="noopener noreferrer" :aria-label="`Contactar a ${row.name} por WhatsApp`"><MessageCircle :size="16"/><span>WhatsApp</span></a><button v-if="converted(row)" type="button" class="pv-converted-link" @click="openConverted(row)">Ver cliente</button><button v-if="!converted(row) && can('clients.create') && can('prospects.edit')" type="button" class="pv-converted-link" :disabled="saving" :aria-label="'Convertir en cliente a '+row.name" @click="convertToClient(row)">Convertir en cliente</button><button v-can="'prospects.edit'" v-else type="button" class="pv-icon-btn" :aria-label="`Editar a ${row.name}`" title="Editar" @click="openEdit(row)"><Pencil :size="16"/></button><button v-can="'prospects.delete'" type="button" class="pv-icon-btn pv-delete" :disabled="converted(row)" :title="converted(row) ? 'No se puede eliminar: convertido a cliente' : 'Eliminar prospecto'" :aria-label="`Eliminar a ${row.name}`" @click="confirmDelete(row)"><Trash2 :size="16"/></button></div></td></tr><tr v-if="!paged.length"><td colspan="5"><div class="pv-empty"><UsersRound :size="30"/><strong>{{loading?'Cargando prospectos…':'No hay prospectos para mostrar'}}</strong><p>{{query||statusFilter!=='all'?'Prueba con otros filtros.':'Registra al primer interesado para comenzar el seguimiento.'}}</p><button v-can="'prospects.create'" v-if="!loading&&!query&&statusFilter==='all'" class="pv-btn pv-btn-primary" @click="openNew"><Plus :size="16"/> Agregar prospecto</button></div></td></tr></tbody></table></div>
      <div v-if="filtered.length>perPage" class="pv-pagination"><span>Mostrando {{(page-1)*perPage+1}}–{{Math.min(page*perPage,filtered.length)}} de {{filtered.length}}</span><div><button :disabled="page===1" aria-label="Página anterior" @click="page--"><ChevronLeft :size="18"/></button><span>{{page}} / {{pageCount}}</span><button :disabled="page===pageCount" aria-label="Página siguiente" @click="page++"><ChevronRight :size="18"/></button></div></div>
    </section>

    <div v-if="showModal" class="pv-backdrop" @click.self="close">
      <div class="pv-dialog" role="dialog" aria-modal="true" aria-labelledby="pv-dialog-title">
        <header class="pv-dialog-header"><div><span class="pv-eyebrow">PROSPECTOS / {{editingId?'EDITAR':'NUEVO REGISTRO'}}</span><h2 id="pv-dialog-title">{{editingId?'Editar prospecto':'Nuevo prospecto'}}</h2><p>Solo necesitamos los datos de contacto para comenzar.</p></div><button class="pv-close" type="button" aria-label="Cerrar formulario" @click="close"><X :size="20"/></button></header>
        <form @submit.prevent="save" novalidate>
          <div class="pv-dialog-body"><div v-if="editingId && converted(rows.find(r=>r.id===editingId))" class="pv-converted-notice">Este registro ya pertenece a un cliente. Para editar su información, abre el expediente correspondiente.</div>
            <div v-if="formError" class="pv-alert pv-alert-error" role="alert"><AlertCircle :size="17"/>{{formError}}</div>
            <div class="pv-form-section"><div class="pv-section-heading"><span>01</span><div><h3>Datos de contacto</h3><p>Información indispensable para dar seguimiento.</p></div></div>
              <div class="pv-form-grid"><label class="pv-field"><span>Nombre completo <b>*</b></span><input v-model.trim="form.name" maxlength="255" autocomplete="name" placeholder="Ej. María Hernández" required/></label><label class="pv-field"><span>Teléfono / WhatsApp <b>*</b></span><input v-model.trim="form.phone" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="Ej. 55 1234 5678" required/><small>Utilizaremos este número para contactar al prospecto.</small></label></div>
            </div>
            <div class="pv-form-section"><div class="pv-section-heading"><span>02</span><div><h3>Interés y seguimiento</h3><p>Asocia al prospecto con un desarrollo, si ya lo conoces.</p></div></div>
              <div class="pv-form-grid"><label class="pv-field"><span>Desarrollo de interés</span><select v-model="form.development_id"><option value="">Sin asignar todavía</option><option v-for="d in developments" :key="d.id" :value="d.id">{{d.name}}</option></select></label><label class="pv-field"><span>Estado</span><select v-model="form.status"><option v-for="s in statuses" :key="s.value" :value="s.value">{{s.label}}</option></select></label></div>
            </div>
            <div class="pv-extras"><button class="pv-extras-toggle" type="button" :aria-expanded="showExtra" @click="showExtra=!showExtra"><span><ClipboardList :size="17"/> Información adicional <small>(opcional)</small></span><ChevronDown :size="18" :class="{'pv-rotated':showExtra}"/></button><div v-if="showExtra" class="pv-extras-content"><label class="pv-field"><span>Correo electrónico</span><input v-model.trim="form.email" type="email" maxlength="255" autocomplete="email" placeholder="nombre@correo.com"/></label><label class="pv-field"><span>¿Qué está buscando?</span><textarea v-model="form.message" maxlength="5000" rows="3" placeholder="Ej. Quiere conocer los lotes de Prados del Rosario…"/></label><label class="pv-field"><span>Notas internas</span><textarea v-model="form.notes" maxlength="5000" rows="3" placeholder="Ej. Llamar el viernes por la mañana…"/></label></div></div>
            <div v-if="editingId && form.status==='qualified'" class="pv-conversion-note"><ShieldAlert :size="19"/><div><strong>Listo para la siguiente etapa</strong><p>Este prospecto está calificado. La conversión a cliente se habilitará cuando exista el módulo de clientes en Laravel. No se crea un cliente automáticamente al cambiar el estado.</p></div></div>
          </div>
          <footer class="pv-dialog-footer"><small><b>*</b> Campos obligatorios</small><div><button class="pv-btn pv-btn-secondary" type="button" :disabled="saving" @click="close">Cancelar</button><button class="pv-btn pv-btn-primary" type="submit" :disabled="saving"><Check :size="17"/> {{saving?'Guardando…':editingId?'Guardar cambios':'Crear prospecto'}}</button></div></footer>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prospects-v29{--pv-blue:#0066e0;--pv-dark:#13314d;--pv-border:#e0e8f1;--pv-muted:#788da3;color:var(--pv-dark);font-family:'DM Sans',system-ui,sans-serif;min-width:0}
.prospects-v29 *{box-sizing:border-box}.pv-page-header{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:23px}.pv-eyebrow{display:block;font-size:10px;font-weight:800;letter-spacing:.16em;color:#1674bb;margin-bottom:8px}.pv-page-header h1{font:800 clamp(24px,2.7vw,32px)/1.2 'Manrope',sans-serif;letter-spacing:-.045em;margin:0 0 7px}.pv-page-header p{font-size:13px;color:var(--pv-muted);margin:0;line-height:1.6}.pv-btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;min-height:41px;border-radius:9px;padding:10px 16px;font-weight:750;font-size:12px;border:1px solid transparent;cursor:pointer;white-space:nowrap;font-family:inherit}.pv-btn:disabled{opacity:.58;cursor:not-allowed}.pv-btn-primary{color:#fff;background:#0066e0;box-shadow:0 5px 14px #0066e020}.pv-btn-primary:hover:not(:disabled){background:#0054bd}.pv-btn-secondary{border-color:#d8e3ee;color:#365676;background:white}.pv-btn-secondary:hover{background:#f4f8fc}.pv-btn-danger{background:#d72e3b;color:#fff}.pv-alert{display:flex;align-items:center;gap:10px;border-radius:9px;padding:12px 14px;font-size:12px;line-height:1.5;margin-bottom:15px}.pv-alert-error{background:#fff1f2;color:#aa2439;border:1px solid #ffd3d8}.pv-alert-success{background:#e8f9f1;color:#147249;border:1px solid #b8eacf}.pv-alert button{margin-left:auto;border:0;background:none;color:inherit;cursor:pointer}.pv-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:0 0 19px}.pv-stat{display:flex;align-items:center;gap:13px;min-width:0;text-align:left;background:#fff;border:1px solid var(--pv-border);border-radius:13px;padding:17px;cursor:pointer;box-shadow:0 6px 18px #083b6e08;font-family:inherit}.pv-stat.is-selected{border-color:#78b8ef;box-shadow:0 0 0 2px #0066e012}.pv-stat-icon{width:43px;height:43px;flex-shrink:0;display:grid;place-items:center;background:#eaf4ff;border-radius:11px;color:#0066e0}.pv-stat:nth-child(3) .pv-stat-icon{background:#fef1e4;color:#b87524}.pv-stat:nth-child(4) .pv-stat-icon{background:#e7f8f0;color:#139165}.pv-stat small{font-size:11px;color:var(--pv-muted);display:block;margin-bottom:5px}.pv-stat strong{font:800 25px/1 'Manrope',sans-serif;letter-spacing:-.04em}.pv-panel{background:white;border:1px solid var(--pv-border);border-radius:14px;overflow:hidden;box-shadow:0 8px 24px #173b6608}.pv-panel-title{padding:21px 22px 14px;display:flex;justify-content:space-between;align-items:center;gap:12px}.pv-panel-title h2{font:800 16px 'Manrope',sans-serif;margin:0 0 5px}.pv-panel-title p{font-size:12px;color:var(--pv-muted);margin:0}.pv-counter{font-size:11px;color:#53769a;background:#eff6fd;border-radius:50px;padding:7px 11px;font-weight:750}.pv-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:0 22px 20px}.pv-search{display:flex;align-items:center;gap:9px;border:1px solid #d8e3ef;border-radius:9px;padding:0 12px;flex:1;min-width:220px;color:#8198ac}.pv-search input{width:100%;height:41px;border:0;outline:none;background:transparent;color:#203d5a;font-size:12px;font-family:inherit}.pv-toolbar select{border:1px solid #d8e3ef;border-radius:9px;background:#fff;padding:0 11px;height:42px;color:#355470;font-size:12px;min-width:160px}.pv-table-wrapper{overflow-x:auto}.pv-table{width:100%;border-collapse:collapse;min-width:830px}.pv-table thead{background:#f6f9fc}.pv-table th{font-size:10px;letter-spacing:.08em;color:#8093a6;text-align:left;font-weight:800;padding:14px 19px;white-space:nowrap;border-top:1px solid #edf1f5}.pv-table td{padding:15px 19px;font-size:12px;color:#4b657f;border-top:1px solid #edf1f5}.pv-table tbody tr:hover{background:#fbfdff}.pv-person{display:flex;gap:11px;align-items:center}.pv-person strong{font-size:12px;color:#193651;display:block;margin-bottom:5px}.pv-person small{font-size:10px;color:#8194a8;display:block;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pv-avatar{display:grid;place-items:center;width:37px;height:37px;flex-shrink:0;border-radius:11px;background:#e9f2ff;color:#0765bd;font-weight:800;font-size:12px}.pv-interest{font-size:11px}.pv-status{font-size:11px;font-weight:800;padding:8px 10px;border:1px solid #d9e5ee;border-radius:8px;max-width:136px;cursor:pointer}.pv-status-new{color:#1c67a1;background:#edf6ff}.pv-status-contacted{color:#9b6721;background:#fff6e6}.pv-status-qualified{color:#137b4e;background:#e8f8ee}.pv-status-closed{color:#667789;background:#f1f4f6}.pv-date{white-space:nowrap}.pv-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px}.pv-actions-header{text-align:right!important}.pv-whatsapp{display:inline-flex;align-items:center;gap:6px;border:1px solid #b7e7cd;background:#eaf9f0;color:#16824e;border-radius:8px;padding:8px 10px;font-size:11px;font-weight:800;text-decoration:none}.pv-icon-btn{width:34px;height:34px;border:1px solid #dce6ef;background:#fff;border-radius:8px;color:#4d718f;display:grid;place-items:center;cursor:pointer}.pv-icon-btn:hover{background:#edf5ff}.pv-delete{color:#c14b55}.pv-empty{text-align:center;display:flex;flex-direction:column;align-items:center;gap:10px;padding:36px 15px;color:#8ca1b5}.pv-empty strong{font-size:13px;color:#2e5273}.pv-empty p{margin:0 0 7px;font-size:12px}.pv-pagination{display:flex;align-items:center;justify-content:space-between;padding:15px 22px;border-top:1px solid #edf1f5;color:#8196ab;font-size:11px}.pv-pagination div{display:flex;align-items:center;gap:12px}.pv-pagination button{border:1px solid #dce6ee;background:white;border-radius:7px;padding:5px;display:flex;color:#55738e;cursor:pointer}.pv-pagination button:disabled{opacity:.4;cursor:default}.pv-backdrop{position:fixed;inset:0;z-index:1200;background:#081d37a6;display:flex;align-items:center;justify-content:center;padding:18px;backdrop-filter:blur(3px)}.pv-dialog{width:min(100%,750px);max-height:min(92vh,960px);display:flex;flex-direction:column;border-radius:17px;background:white;box-shadow:0 35px 100px #071e3a38;overflow:hidden}.pv-dialog-header{padding:22px 28px 18px;display:flex;align-items:flex-start;justify-content:space-between;border-bottom:1px solid var(--pv-border);gap:15px}.pv-dialog-header h2{font:800 23px 'Manrope',sans-serif;letter-spacing:-.04em;margin:0 0 6px}.pv-dialog-header p{font-size:12px;color:var(--pv-muted);margin:0}.pv-close{display:grid;place-items:center;width:36px;height:36px;border:1px solid var(--pv-border);background:white;border-radius:9px;color:#526c83;cursor:pointer;flex-shrink:0}.pv-dialog form{display:flex;flex-direction:column;min-height:0;overflow:hidden}.pv-dialog-body{padding:24px 28px 30px;overflow-y:auto;min-height:0;display:grid;gap:25px}.pv-form-section{display:grid;gap:14px}.pv-section-heading{display:flex;align-items:center;gap:12px}.pv-section-heading>span{display:grid;place-items:center;width:32px;height:32px;flex-shrink:0;background:#edf6ff;color:#0066e0;border-radius:9px;font-size:11px;font-weight:850}.pv-section-heading h3{font:800 13px 'Manrope',sans-serif;margin:0 0 3px}.pv-section-heading p{font-size:11px;color:#889aaa;margin:0}.pv-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 18px;align-items:start}.pv-field{display:grid;gap:8px;min-width:0;font-size:12px;color:#32516d;font-weight:700}.pv-field b,.pv-dialog-footer b{color:#d64646}.pv-field input,.pv-field select,.pv-field textarea{display:block;width:100%;min-width:0;min-height:43px;background:#fff;border:1px solid #d7e2ed;border-radius:9px;padding:10px 12px;color:#1f3952;font:400 13px 'DM Sans',sans-serif;outline:none;transition:border-color .15s,box-shadow .15s}.pv-field textarea{resize:vertical;line-height:1.5}.pv-field input:focus,.pv-field select:focus,.pv-field textarea:focus{border-color:#3891ed;box-shadow:0 0 0 3px #0066e015}.pv-field input::placeholder,.pv-field textarea::placeholder{color:#a0afbd}.pv-field small{color:#93a2b0;font-size:10px;font-weight:400;line-height:1.5}.pv-extras{border:1px solid var(--pv-border);border-radius:11px;overflow:hidden}.pv-extras-toggle{border:0;background:#f8fbfe;display:flex;align-items:center;justify-content:space-between;width:100%;padding:16px 17px;color:#355774;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit}.pv-extras-toggle>span{display:flex;align-items:center;gap:8px}.pv-extras-toggle small{font-weight:400;color:#879bad}.pv-extras-toggle>svg{transition:transform .2s}.pv-rotated{transform:rotate(180deg)}.pv-extras-content{padding:16px;display:grid;gap:16px}.pv-conversion-note{display:flex;align-items:start;gap:11px;background:#eef7ff;padding:14px 16px;border:1px solid #d1e6fa;border-radius:9px;color:#477299;font-size:11px;line-height:1.55}.pv-conversion-note svg{flex-shrink:0}.pv-conversion-note strong{color:#1c517d;font-size:12px}.pv-conversion-note p{margin:3px 0 0}.pv-dialog-footer{display:flex;align-items:center;justify-content:space-between;gap:15px;padding:16px 28px;border-top:1px solid var(--pv-border);background:#fff}.pv-dialog-footer small{font-size:10px;color:#93a2af}.pv-dialog-footer>div{display:flex;gap:9px}.pv-confirm{background:#fff;border-radius:15px;width:min(100%,410px);padding:27px;box-shadow:0 25px 80px #091e3750}.pv-confirm-icon{display:grid;place-items:center;background:#fff0f1;color:#ce3443;width:43px;height:43px;border-radius:11px}.pv-confirm h2{font-size:18px;margin:17px 0 9px;color:#1e3955}.pv-confirm p{font-size:13px;line-height:1.6;color:#70869b;margin:0 0 22px}.pv-confirm>div:last-child{display:flex;justify-content:end;gap:9px}
@media(max-width:850px){.pv-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.pv-panel-title,.pv-toolbar{padding-left:16px;padding-right:16px}}@media(max-width:600px){.pv-page-header{align-items:flex-start;flex-direction:column}.pv-stats{gap:9px}.pv-stat{padding:12px;gap:9px}.pv-stat-icon{width:35px;height:35px}.pv-stat strong{font-size:21px}.pv-stat small{font-size:10px}.pv-panel-title{align-items:flex-start}.pv-toolbar{flex-direction:column;align-items:stretch}.pv-search{width:100%}.pv-toolbar select{width:100%}.pv-dialog{max-height:95vh;border-radius:13px}.pv-dialog-header{padding:20px}.pv-dialog-body{padding:20px;gap:20px}.pv-form-grid{grid-template-columns:1fr}.pv-dialog-footer{padding:14px 18px;flex-direction:column;align-items:stretch}.pv-dialog-footer>div{justify-content:stretch}.pv-dialog-footer .pv-btn{flex:1}.pv-backdrop{padding:8px}}
</style>
