<script setup>
import {computed,onMounted,ref,watch,nextTick} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import {api,post,put,remove} from '../../services/api'
import {confirmAction,notifyError,notifySuccess} from '../../utils/adminAlerts'
import {
  Users,UserRoundPlus,Search,RefreshCw,Phone,Mail,House,Wallet,
  Eye,Pencil,Trash2,X,ChevronLeft,ChevronRight,ArrowUpRight,
  BadgeCheck,BriefcaseBusiness,FileText,Link2
} from 'lucide-vue-next'

const route=useRoute()
const router=useRouter()
const rows=ref([])
const leads=ref([])
const available=ref([])
const loading=ref(false)
const saving=ref(false)
const detailLoading=ref(false)
const q=ref('')
const page=ref(1)
const lastPage=ref(1)
const total=ref(0)
const selectedLead=ref('')
const assignId=ref('')
const assignStatus=ref('reserved')
const editing=ref(null)
const detail=ref(null)
const showForm=ref(false)
const showDetail=ref(false)
const formMode=ref('manual')

const form=ref(empty())
function empty(){
  return {name:'',phone:'',email:'',alternate_phone:'',notes:'',status:'active'}
}
function explain(e){return e?.message||'No fue posible completar la operación.'}
function money(n){return new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN',maximumFractionDigits:0}).format(Number(n||0))}
function wa(phone){const digits=String(phone||'').replace(/\D/g,'');return digits.length===10?'52'+digits:digits}
function safe(v,fb='—'){return v||fb}
function canDelete(client){return Number(client?.properties_count||0)===0 && Number(client?.payments_count||0)===0}
function leadOptions(){return leads.value.filter(l=>l.status!=='closed'&&l.phone)}
const selectedLeadData=computed(()=>leadOptions().find(x=>String(x.id)===String(selectedLead.value))||null)
const propertiesCount=computed(()=>detail.value?.properties?.length||0)
const paymentsCount=computed(()=>detail.value?.payments?.length||0)

async function load(){
  loading.value=true
  try{
    const r=await api('/admin/clients?'+new URLSearchParams({q:q.value,page:String(page.value),per_page:'15'}))
    rows.value=r.data||[]
    total.value=r.total||0
    lastPage.value=r.last_page||1
  }catch(e){
    notifyError('No se pudieron cargar los clientes', explain(e))
  }finally{loading.value=false}
}

async function loadLeads(){
  try{
    const r=await api('/admin/leads?'+new URLSearchParams({per_page:'100'}))
    leads.value=r.data||[]
  }catch(e){leads.value=[]}
}

async function loadAvailable(){
  try{
    const r=await api('/admin/clients/available-lots')
    available.value=r.data||[]
  }catch(e){available.value=[]}
}

let queryTimer
watch(q,()=>{
  page.value=1
  clearTimeout(queryTimer)
  queryTimer=setTimeout(load,320)
})

function resetForm(){
  form.value=empty()
  selectedLead.value=''
  formMode.value='manual'
}

function fillFromLead(){
  const lead=selectedLeadData.value
  if(!lead) return
  form.value={
    name:lead.name||'',
    phone:lead.phone||'',
    email:lead.email||'',
    alternate_phone:'',
    notes:lead.notes||'',
    status:'active'
  }
}

function openCreate(){
  editing.value=null
  resetForm()
  showForm.value=true
  loadLeads()
}

function openEdit(client){
  editing.value=client
  selectedLead.value=''
  formMode.value='manual'
  form.value={
    name:client.name||'',
    phone:client.phone||'',
    email:client.email||'',
    alternate_phone:client.alternate_phone||'',
    notes:client.notes||'',
    status:client.status||'active'
  }
  showDetail.value=false
  showForm.value=true
}

async function save(){
  if(saving.value) return
  if(formMode.value==='convert' && !editing.value && !selectedLead.value){
    notifyError('Selecciona un prospecto', 'Para convertir a cliente primero elige un prospecto con teléfono.')
    return
  }
  if(formMode.value==='manual' && (!form.value.name.trim() || !form.value.phone.trim())){
    notifyError('Faltan datos obligatorios', 'Captura nombre completo y teléfono / WhatsApp.')
    return
  }
  if(formMode.value==='convert' && selectedLead.value){
    const ok=await confirmAction({
      title:'Convertir prospecto en cliente',
      text:'Se creará el expediente del cliente con los datos del prospecto y el prospecto quedará cerrado.',
      confirmText:'Sí, convertir'
    })
    if(!ok) return
  }
  if(editing.value){
    const ok=await confirmAction({
      title:'Guardar cambios del cliente',
      text:'Se actualizará el expediente con la información capturada.',
      confirmText:'Guardar cambios'
    })
    if(!ok) return
  }
  saving.value=true
  try{
    if(formMode.value==='convert' && !editing.value && selectedLead.value){
      const result=await post('/admin/leads/'+selectedLead.value+'/convert',{})
      notifySuccess(result.already_converted?'Este prospecto ya era cliente':'Cliente creado desde prospecto')
      showForm.value=false
      await load()
      if(result?.data?.id) await openDetail(result.data.id)
      return
    }
    const payload={...form.value}
    const result=editing.value
      ? await put('/admin/clients/'+editing.value.id,payload)
      : await post('/admin/clients',payload)
    notifySuccess(editing.value?'Cliente actualizado correctamente':'Cliente creado correctamente')
    showForm.value=false
    await load()
    if(result?.data?.id && detail.value?.data?.id===result.data.id) await openDetail(result.data.id)
  }catch(e){
    notifyError('No se pudieron guardar los cambios', explain(e))
  }finally{saving.value=false}
}

function closeDetail(){showDetail.value=false;if(route.query.client)router.replace({path:'/admin/clientes'})}
async function openDetail(id){
  showForm.value=false
  showDetail.value=true
  detailLoading.value=true
  try{
    detail.value=await api('/admin/clients/'+id)
    await loadAvailable()
  }catch(e){
    showDetail.value=false
    notifyError('No se pudo abrir el expediente', explain(e))
  }finally{detailLoading.value=false}
}

async function assign(){
  if(!detail.value?.data?.id || !assignId.value) return
  const ok=await confirmAction({
    title:'Asignar propiedad al cliente',
    text:'La propiedad se vinculará al cliente y cambiará a su nuevo estado comercial.',
    confirmText:'Asignar propiedad'
  })
  if(!ok) return
  saving.value=true
  try{
    await post('/admin/clients/'+detail.value.data.id+'/lots',{lot_id:Number(assignId.value),status:assignStatus.value})
    assignId.value=''
    notifySuccess('Propiedad vinculada al cliente')
    await openDetail(detail.value.data.id)
    await load()
  }catch(e){
    notifyError('No se pudo asignar la propiedad', explain(e))
  }finally{saving.value=false}
}

async function releaseLot(lot){
  const ok=await confirmAction({
    title:'Liberar propiedad',
    text:`Se desvinculará la propiedad ${lot.code}. Solo puede hacerse si no tiene pagos registrados.`,
    confirmText:'Sí, liberar'
  })
  if(!ok || !detail.value?.data?.id) return
  try{
    await remove('/admin/clients/'+detail.value.data.id+'/lots/'+lot.id)
    notifySuccess('Propiedad liberada')
    await openDetail(detail.value.data.id)
    await load()
  }catch(e){
    notifyError('No se pudo liberar la propiedad', explain(e))
  }
}

async function deleteClient(client){
  if(!canDelete(client)){
    notifyError('No se puede eliminar este cliente', 'Tiene propiedades o pagos relacionados. Primero libera las relaciones o conserva el expediente.')
    return
  }
  const ok=await confirmAction({
    title:'Eliminar cliente',
    text:`Se eliminará el expediente de ${client.name}. Esta acción no se puede deshacer.`,
    confirmText:'Sí, eliminar',
    variant:'danger'
  })
  if(!ok) return
  try{
    await remove('/admin/clients/'+client.id)
    notifySuccess('Cliente eliminado correctamente')
    if(detail.value?.data?.id===client.id){showDetail.value=false;detail.value=null}
    await load()
  }catch(e){
    notifyError('No se pudo eliminar el cliente', explain(e))
  }
}

onMounted(async()=>{
  await load()
  if(route.query.client){await openDetail(Number(route.query.client))}
})
watch(()=>route.query.client,async id=>{
  if(id)await openDetail(Number(id))
})
</script>

<template>
  <section class="crm-page">
    <header class="crm-page__header">
      <div>
        <span class="crm-kicker">CRM / CLIENTES</span>
        <h1>Clientes</h1>
        <p>Administra expedientes, relación con propiedades y seguimiento comercial con una misma línea gráfica premium.</p>
      </div>
      <div class="crm-header__actions">
        <button class="crm-btn crm-btn--ghost" @click="load"><RefreshCw :size="16"/> Actualizar</button>
        <button class="crm-btn crm-btn--soft" @click="openCreate"><UserRoundPlus :size="16"/> Nuevo cliente</button>
      </div>
    </header>

    <div class="crm-stats">
      <article class="crm-stat">
        <div class="crm-stat__icon"><Users :size="18"/></div>
        <span>Clientes registrados</span>
        <strong>{{ total }}</strong>
      </article>
      <article class="crm-stat">
        <div class="crm-stat__icon"><House :size="18"/></div>
        <span>Propiedades en esta página</span>
        <strong>{{ rows.reduce((acc,row)=>acc+Number(row.properties_count||0),0) }}</strong>
      </article>
      <article class="crm-stat">
        <div class="crm-stat__icon"><Wallet :size="18"/></div>
        <span>Pagos en esta página</span>
        <strong>{{ rows.reduce((acc,row)=>acc+Number(row.payments_count||0),0) }}</strong>
      </article>
    </div>

    <section class="crm-card">
      <div class="crm-card__head">
        <div>
          <span class="crm-eyebrow">Listado principal</span>
          <h2>Directorio de clientes</h2>
          <p>Desde aquí puedes consultar, editar, convertir prospectos y eliminar solo expedientes sin relaciones activas.</p>
        </div>
        <div class="crm-toolbar">
          <label class="crm-search">
            <Search :size="16"/>
            <input v-model="q" type="search" placeholder="Buscar por nombre, correo o teléfono" />
          </label>
        </div>
      </div>

      <div class="crm-tablewrap">
        <table class="crm-table">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Contacto</th>
              <th>Estado</th>
              <th>Propiedades</th>
              <th>Pagos</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="client in rows" :key="client.id">
              <td>
                <div class="crm-person">
                  <div class="crm-avatar">{{ (client.name || '?').slice(0,1).toUpperCase() }}</div>
                  <div>
                    <strong>{{ client.name }}</strong>
                    <small>#{{ client.id }} · {{ client.email || 'Sin correo registrado' }}</small>
                  </div>
                </div>
              </td>
              <td>
                <div class="crm-contact">
                  <a v-if="client.phone" :href="'https://wa.me/'+wa(client.phone)" target="_blank" rel="noopener noreferrer"><Phone :size="14"/> {{ client.phone }}</a>
                  <span v-else>Sin teléfono</span>
                  <small>{{ client.alternate_phone || 'Sin teléfono alterno' }}</small>
                </div>
              </td>
              <td>
                <span class="crm-badge" :class="client.status==='active' ? 'is-success' : 'is-muted'">
                  {{ client.status==='active' ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td><span class="crm-pill">{{ client.properties_count || 0 }}</span></td>
              <td><span class="crm-pill">{{ client.payments_count || 0 }}</span></td>
              <td>
                <div class="crm-actions">
                  <button class="crm-btn crm-btn--table" @click="openDetail(client.id)"><Eye :size="14"/> Ver</button>
                  <button class="crm-btn crm-btn--table" @click="openEdit(client)"><Pencil :size="14"/> Editar</button>
                  <button class="crm-btn crm-btn--table" :disabled="!canDelete(client)" :title="canDelete(client) ? 'Eliminar cliente' : 'No disponible: tiene propiedad o pagos'" @click="deleteClient(client)"><Trash2 :size="14"/> Eliminar</button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && !rows.length">
              <td colspan="6" class="crm-empty">No se encontraron clientes. Puedes crear uno nuevo o convertir un prospecto existente.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="crm-pagination">
        <small>Página {{ page }} de {{ lastPage }} · {{ total }} registros</small>
        <div>
          <button class="crm-iconbtn" :disabled="page <= 1" @click="page--; load()"><ChevronLeft :size="16"/></button>
          <button class="crm-iconbtn" :disabled="page >= lastPage" @click="page++; load()"><ChevronRight :size="16"/></button>
        </div>
      </div>
    </section>

    <div v-if="showForm" class="crm-modalbackdrop" @click.self="showForm=false">
      <div class="crm-modal crm-modal--wide" role="dialog" aria-modal="true" aria-labelledby="crm-form-title">
        <header class="crm-modal__header">
          <div>
            <span class="crm-kicker">GESTIÓN DE CLIENTES</span>
            <h2 id="crm-form-title">{{ editing ? 'Editar cliente' : 'Nuevo cliente' }}</h2>
            <p>Usa el mismo patrón visual del sistema: modal centrado, campos claros y flujo simple para convertir prospectos a clientes.</p>
          </div>
          <button class="crm-close" @click="showForm=false" aria-label="Cerrar"><X :size="18"/></button>
        </header>

        <div class="crm-modal__body">
          <section class="crm-panelsoft">
            <div class="crm-sectionhead">
              <span class="crm-step">01</span>
              <div>
                <h3>Origen del registro</h3>
                <p>Puedes capturar un cliente manualmente o convertir un prospecto existente sin complicaciones.</p>
              </div>
            </div>
            <div v-if="!editing" class="crm-segmented">
              <button type="button" :class="{active: formMode==='manual'}" @click="formMode='manual'; selectedLead=''; form=empty()">Captura manual</button>
              <button type="button" :class="{active: formMode==='convert'}" @click="formMode='convert'; loadLeads()">Convertir prospecto</button>
            </div>
            <div v-else class="crm-inlinehint">Estás editando un cliente existente. El origen ya no se puede cambiar.</div>

            <div v-if="!editing && formMode==='convert'" class="crm-formgrid crm-formgrid--single">
              <label>
                Prospecto disponible *
                <select v-model="selectedLead" @change="fillFromLead()">
                  <option value="">Selecciona un prospecto</option>
                  <option v-for="lead in leadOptions()" :key="lead.id" :value="String(lead.id)">
                    {{ lead.name }} · {{ lead.phone }}
                  </option>
                </select>
              </label>
              <div v-if="selectedLeadData" class="crm-previewcard">
                <div><small>Nombre</small><strong>{{ safe(selectedLeadData.name) }}</strong></div>
                <div><small>Teléfono</small><strong>{{ safe(selectedLeadData.phone) }}</strong></div>
                <div><small>Correo</small><strong>{{ safe(selectedLeadData.email,'Sin correo') }}</strong></div>
                <div><small>Notas</small><strong>{{ safe(selectedLeadData.notes,'Sin notas') }}</strong></div>
              </div>
            </div>
          </section>

          <section class="crm-panelsoft">
            <div class="crm-sectionhead">
              <span class="crm-step">02</span>
              <div>
                <h3>Datos del cliente</h3>
                <p>Solo se pide lo necesario para crear el expediente y después continuar con propiedades y pagos.</p>
              </div>
            </div>
            <div class="crm-formgrid">
              <label>
                Nombre completo *
                <input v-model.trim="form.name" maxlength="255" placeholder="Nombre y apellidos" :readonly="!editing && formMode==='convert'" />
              </label>
              <label>
                Teléfono / WhatsApp *
                <input v-model.trim="form.phone" maxlength="50" placeholder="5512345678" :readonly="!editing && formMode==='convert'" />
              </label>
              <label>
                Correo electrónico
                <input v-model.trim="form.email" type="email" maxlength="255" placeholder="cliente@correo.com" :readonly="!editing && formMode==='convert'" />
              </label>
              <label>
                Teléfono alterno
                <input v-model.trim="form.alternate_phone" maxlength="50" placeholder="Opcional" />
              </label>
              <label v-if="editing || formMode==='manual'">
                Estado del expediente
                <select v-model="form.status">
                  <option value="active">Activo</option>
                  <option value="inactive">Inactivo</option>
                </select>
              </label>
              <label class="is-full">
                Notas internas
                <textarea v-model.trim="form.notes" rows="4" maxlength="5000" placeholder="Observaciones comerciales, seguimiento o acuerdos"></textarea>
              </label>
            </div>
          </section>
        </div>

        <footer class="crm-modal__footer">
          <button type="button" class="crm-btn crm-btn--ghost" @click="showForm=false">Cancelar</button>
          <button type="button" class="crm-btn crm-btn--primary" :disabled="saving" @click="save">
            <BadgeCheck :size="16"/> {{ saving ? 'Guardando...' : (!editing && formMode==='convert' ? 'Convertir a cliente' : editing ? 'Guardar cambios' : 'Crear cliente') }}
          </button>
        </footer>
      </div>
    </div>

    <div v-if="showDetail" class="crm-modalbackdrop" @click.self="showDetail=false">
      <div class="crm-modal crm-modal--xl" role="dialog" aria-modal="true" aria-labelledby="crm-detail-title">
        <header class="crm-modal__header">
          <div>
            <span class="crm-kicker">EXPEDIENTE DEL CLIENTE</span>
            <h2 id="crm-detail-title">{{ detail?.data?.name || 'Cliente' }}</h2>
            <p>Consulta datos, propiedades vinculadas y pagos desde un mismo modal consistente con el resto del administrador.</p>
          </div>
          <div class="crm-header__actions">
            <button v-if="detail?.data" class="crm-btn crm-btn--ghost" @click="openEdit(detail.data)"><Pencil :size="14"/> Editar</button>
            <button v-if="detail?.data" class="crm-btn crm-btn--ghost" :disabled="(detail.properties?.length||0)>0 || (detail.payments?.length||0)>0" :title="(detail.properties?.length||0)>0 || (detail.payments?.length||0)>0 ? 'No se puede eliminar: tiene propiedades o pagos' : 'Eliminar cliente'" @click="deleteClient({...detail.data,properties_count:detail.properties?.length||0,payments_count:detail.payments?.length||0})"><Trash2 :size="14"/> Eliminar</button>
            <button class="crm-close" @click="closeDetail" aria-label="Cerrar"><X :size="18"/></button>
          </div>
        </header>

        <div class="crm-modal__body" v-if="detail && !detailLoading">
          <div class="crm-detailhero">
            <div class="crm-detailhero__main">
              <div class="crm-avatar crm-avatar--lg">{{ (detail.data.name || '?').slice(0,1).toUpperCase() }}</div>
              <div>
                <h3>{{ detail.data.name }}</h3>
                <div class="crm-detailhero__meta">
                  <a :href="'https://wa.me/'+wa(detail.data.phone)" target="_blank" rel="noopener noreferrer"><Phone :size="14"/> {{ safe(detail.data.phone) }}</a>
                  <span><Mail :size="14"/> {{ safe(detail.data.email,'Sin correo registrado') }}</span>
                  <span><BadgeCheck :size="14"/> {{ detail.data.status==='active' ? 'Cliente activo' : 'Cliente inactivo' }}</span>
                </div>
              </div>
            </div>
            <div class="crm-detailhero__stats">
              <div><small>Propiedades</small><strong>{{ propertiesCount }}</strong></div>
              <div><small>Pagos</small><strong>{{ paymentsCount }}</strong></div>
              <div><small>Total pagado</small><strong>{{ money(detail.paid_total) }}</strong></div>
            </div>
          </div>

          <div class="crm-detailgrid">
            <section class="crm-card crm-card--inner">
              <div class="crm-card__head crm-card__head--compact">
                <div>
                  <span class="crm-eyebrow">Información general</span>
                  <h2>Datos del expediente</h2>
                </div>
              </div>
              <div class="crm-infogrid">
                <div><small>Teléfono</small><strong>{{ safe(detail.data.phone) }}</strong></div>
                <div><small>Correo</small><strong>{{ safe(detail.data.email,'Sin correo') }}</strong></div>
                <div><small>Teléfono alterno</small><strong>{{ safe(detail.data.alternate_phone,'Sin registro') }}</strong></div>
                <div><small>Origen</small><strong>{{ detail.data.lead_id ? 'Convertido de prospecto #'+detail.data.lead_id : 'Captura manual' }}</strong></div>
              </div>
              <div class="crm-notes" v-if="detail.data.notes">
                <small>Notas internas</small>
                <p>{{ detail.data.notes }}</p>
              </div>
            </section>

            <section class="crm-card crm-card--inner">
              <div class="crm-card__head crm-card__head--compact">
                <div>
                  <span class="crm-eyebrow">Relación comercial</span>
                  <h2>Propiedades vinculadas</h2>
                </div>
              </div>
              <div v-if="detail.properties?.length" class="crm-linkedlist">
                <article v-for="lot in detail.properties" :key="lot.id" class="crm-linkeditem">
                  <div>
                    <strong>{{ lot.development_name }} · {{ lot.code }}</strong>
                    <small>{{ safe(lot.area_m2,'—') }} m² · {{ money(lot.price) }}</small>
                  </div>
                  <div class="crm-linkeditem__actions">
                    <span class="crm-badge" :class="lot.status==='sold' ? 'is-danger' : 'is-warning'">{{ lot.status==='sold' ? 'Vendida' : 'Apartada' }}</span>
                    <button class="crm-btn crm-btn--tiny" @click="releaseLot(lot)">Liberar</button>
                  </div>
                </article>
              </div>
              <div v-else class="crm-emptybox">Este cliente todavía no tiene propiedades asignadas.</div>
              <div class="crm-assignbox">
                <label>
                  Propiedad disponible
                  <select v-model="assignId">
                    <option value="">Selecciona una propiedad</option>
                    <option v-for="lot in available" :key="lot.id" :value="String(lot.id)">
                      {{ lot.development_name }} · {{ lot.code }} ({{ lot.status }})
                    </option>
                  </select>
                </label>
                <label>
                  Asignar como
                  <select v-model="assignStatus">
                    <option value="reserved">Apartada</option>
                    <option value="sold">Vendida</option>
                  </select>
                </label>
                <button class="crm-btn crm-btn--primary" :disabled="!assignId || saving" @click="assign"><Link2 :size="14"/> Asignar propiedad</button>
              </div>
            </section>
          </div>

          <section class="crm-card crm-card--inner">
            <div class="crm-card__head crm-card__head--compact">
              <div>
                <span class="crm-eyebrow">Historial financiero</span>
                <h2>Pagos relacionados</h2>
                <p>En este módulo se consultan pagos asociados al expediente; la captura detallada sigue centralizada en el módulo de Pagos.</p>
              </div>
              <strong class="crm-totalpill">{{ money(detail.paid_total) }}</strong>
            </div>
            <div v-if="detail.payments?.length" class="crm-tablewrap">
              <table class="crm-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Importe</th>
                    <th>Estado</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="payment in detail.payments" :key="payment.id">
                    <td>{{ payment.paid_at || 'Sin fecha' }}</td>
                    <td>{{ payment.payment_type || 'Pago' }}</td>
                    <td>{{ money(payment.amount) }}</td>
                    <td><span class="crm-badge" :class="payment.status==='applied' ? 'is-success' : 'is-muted'">{{ payment.status || 'Pendiente' }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-else class="crm-emptybox">Este cliente todavía no tiene pagos registrados.</div>
          </section>
        </div>

        <div v-else class="crm-loadingstate">
          <div class="crm-loader"></div>
          <p>Cargando expediente del cliente...</p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.crm-page{display:grid;gap:22px;color:#17324e}
.crm-page *{box-sizing:border-box}
.crm-page__header,.crm-card__head,.crm-pagination,.crm-detailhero,.crm-detailhero__main,.crm-header__actions,.crm-actions,.crm-detailhero__meta,.crm-sectionhead{display:flex;align-items:center;gap:14px}
.crm-page__header,.crm-card__head,.crm-pagination{justify-content:space-between}
.crm-kicker,.crm-eyebrow{display:inline-block;font-size:11px;letter-spacing:.18em;text-transform:uppercase;font-weight:800}
.crm-kicker{color:#0a76b8}
.crm-eyebrow{color:#7e96ad}
.crm-page__header h1{margin:8px 0 4px;font-size:34px;line-height:1.05;letter-spacing:-.05em;color:#12314d}
.crm-page__header p,.crm-card__head p,.crm-modal__header p,.crm-sectionhead p{margin:0;color:#6f879f;font-size:13px;line-height:1.6}
.crm-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.crm-stat,.crm-card,.crm-panelsoft{background:#fff;border:1px solid #deebf5;border-radius:24px;box-shadow:0 18px 50px rgba(16,46,78,.06)}
.crm-stat{padding:22px;display:grid;gap:8px}
.crm-stat__icon{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;background:linear-gradient(135deg,#e9f5ff,#f8fcff);color:#0a76b8;border:1px solid #d7e8f8}
.crm-stat span{font-size:13px;color:#7190ab}
.crm-stat strong{font-size:30px;line-height:1;color:#12314d;letter-spacing:-.04em}
.crm-card{padding:24px}
.crm-card__head{margin-bottom:18px}
.crm-card__head h2,.crm-sectionhead h3{margin:5px 0 0;font-size:20px;letter-spacing:-.03em;color:#12314d}
.crm-toolbar{display:flex;align-items:center;gap:12px}
.crm-search{display:flex;align-items:center;gap:10px;min-width:min(420px,100%);padding:0 14px;border:1px solid #d6e6f4;border-radius:16px;background:#fbfdff;color:#83a0b8}
.crm-search input{border:0;outline:0;background:transparent;width:100%;padding:13px 0;font:inherit;color:#183450}
.crm-btn{border:1px solid transparent;border-radius:14px;padding:12px 18px;font:inherit;font-size:13px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.22s ease;text-decoration:none;white-space:nowrap}
.crm-btn:disabled,.crm-iconbtn:disabled{opacity:.45;cursor:not-allowed}
.crm-btn--primary{background:linear-gradient(135deg,#0066e0,#0891ff);border-color:#0066e0;color:#fff;box-shadow:0 12px 26px rgba(8,113,204,.18)}
.crm-btn--primary:hover{transform:translateY(-1px);box-shadow:0 18px 30px rgba(8,113,204,.22)}
.crm-btn--ghost{background:#fff;border-color:#d6e4f1;color:#294a69}
.crm-btn--ghost:hover,.crm-btn--table:hover,.crm-iconbtn:hover,.crm-close:hover{background:#f5f9fd}
.crm-btn--soft{background:#eff8ff;border-color:#d6e8fb;color:#0c6bab}
.crm-btn--table{background:#fff;border-color:#d9e6f2;color:#294a69;padding:9px 12px;border-radius:12px;font-size:12px}
.crm-btn--table.is-disabled{opacity:.55}
.crm-btn--tiny{background:#fff;border:1px solid #d6e5f2;color:#2a4b69;padding:8px 10px;border-radius:12px;font-size:12px;font-weight:700;cursor:pointer}
.crm-tablewrap{overflow:auto}
.crm-table{width:100%;border-collapse:collapse;min-width:860px}
.crm-table th{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#7f95ac;text-align:left;padding:14px 12px;border-bottom:1px solid #e8eef5}
.crm-table td{padding:16px 12px;border-bottom:1px solid #edf2f7;vertical-align:middle;font-size:13px;color:#284763}
.crm-person{display:flex;align-items:center;gap:12px}
.crm-avatar{width:44px;height:44px;border-radius:16px;display:grid;place-items:center;font-weight:800;background:linear-gradient(135deg,#e9f4ff,#f4fbff);border:1px solid #d8e8f8;color:#0a71b0;flex:none}
.crm-avatar--lg{width:64px;height:64px;border-radius:20px;font-size:24px}
.crm-person strong,.crm-linkeditem strong,.crm-previewcard strong,.crm-infogrid strong{display:block;color:#17324e}
.crm-person small,.crm-contact small,.crm-linkeditem small,.crm-previewcard small,.crm-infogrid small,.crm-notes small{display:block;color:#7e96ac;margin-top:4px;font-size:12px}
.crm-contact{display:grid;gap:4px}
.crm-contact a,.crm-detailhero__meta a{display:inline-flex;align-items:center;gap:6px;color:#0b6da8;text-decoration:none;font-weight:600}
.crm-detailhero__meta span{display:inline-flex;align-items:center;gap:6px;color:#5f7e98;font-size:13px}
.crm-badge,.crm-pill,.crm-totalpill{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:700}
.crm-pill{background:#f4f8fc;color:#355472;border:1px solid #dfeaf4;min-width:36px}
.crm-badge.is-success{background:#edf9f2;color:#177548;border:1px solid #c7e9d4}
.crm-badge.is-warning{background:#fff6ea;color:#9a5f16;border:1px solid #f3ddb9}
.crm-badge.is-danger{background:#fff0ef;color:#b44e41;border:1px solid #f0c7c1}
.crm-badge.is-muted{background:#f1f5f9;color:#627b94;border:1px solid #dbe5ef}
.crm-empty{padding:42px 12px!important;text-align:center;color:#7d93a8}
.crm-pagination small{color:#7f95ab}
.crm-iconbtn{width:40px;height:40px;border:1px solid #dbe7f2;background:#fff;border-radius:14px;display:inline-grid;place-items:center;cursor:pointer;margin-left:6px}
.crm-modalbackdrop{position:fixed;inset:0;background:rgba(9,23,39,.56);backdrop-filter:blur(10px);display:grid;place-items:center;padding:24px;z-index:1200}
.crm-modal{width:min(820px,100%);max-height:min(92vh,1100px);background:#fff;border-radius:28px;border:1px solid #d9e7f3;box-shadow:0 30px 90px rgba(0,0,0,.18);display:flex;flex-direction:column;overflow:hidden}
.crm-modal--wide{width:min(920px,100%)}
.crm-modal--xl{width:min(1180px,100%)}
.crm-modal__header,.crm-modal__footer{padding:22px 28px;border-bottom:1px solid #eaf0f5}
.crm-modal__header{display:flex;align-items:flex-start;justify-content:space-between;gap:18px}
.crm-modal__header h2{margin:8px 0 4px;font-size:28px;line-height:1.06;letter-spacing:-.05em;color:#12314d}
.crm-modal__body{padding:26px 28px;overflow:auto;display:grid;gap:18px;background:linear-gradient(180deg,#ffffff 0%,#fbfdff 100%)}
.crm-modal__footer{border-top:1px solid #eaf0f5;border-bottom:0;display:flex;align-items:center;justify-content:flex-end;gap:12px;background:#fff}
.crm-close{width:44px;height:44px;border-radius:14px;border:1px solid #dae7f2;background:#fff;color:#30506e;display:grid;place-items:center;cursor:pointer;flex:none}
.crm-panelsoft{padding:20px;display:grid;gap:18px;border-radius:22px}
.crm-step{width:36px;height:36px;border-radius:12px;display:grid;place-items:center;background:#eff8ff;border:1px solid #d6e8fb;color:#0a76b8;font-weight:800;flex:none}
.crm-segmented{display:flex;gap:10px;flex-wrap:wrap}
.crm-segmented button{border:1px solid #d9e8f5;background:#fff;color:#3b5874;border-radius:999px;padding:10px 14px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.crm-segmented button.active{background:#12314d;color:#fff;border-color:#12314d;box-shadow:0 8px 20px rgba(18,49,77,.16)}
.crm-inlinehint{padding:14px 16px;background:#f6fafc;border:1px solid #dfeaf3;border-radius:16px;color:#607c94;font-size:13px}
.crm-formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.crm-formgrid--single{grid-template-columns:1fr}
.crm-formgrid label,.crm-assignbox label{display:grid;gap:8px;font-size:12px;font-weight:700;color:#335270}
.crm-formgrid .is-full{grid-column:1/-1}
.crm-formgrid input,.crm-formgrid select,.crm-formgrid textarea,.crm-assignbox select{width:100%;border:1px solid #d7e5f2;border-radius:16px;padding:13px 14px;background:#fff;font:inherit;font-size:14px;color:#183450;outline:none;transition:.2s ease}
.crm-formgrid input:focus,.crm-formgrid select:focus,.crm-formgrid textarea:focus,.crm-assignbox select:focus,.crm-search input:focus{border-color:#0b79c1;box-shadow:0 0 0 4px rgba(11,121,193,.1)}
.crm-previewcard{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:16px;border:1px solid #d7e7f4;background:#f8fbfe;border-radius:18px}
.crm-loadingstate{min-height:300px;display:grid;place-items:center;gap:14px;color:#6b8198;padding:40px}
.crm-loader{width:48px;height:48px;border-radius:50%;border:4px solid #d9e8f5;border-top-color:#0b79c1;animation:crmspin .9s linear infinite}
@keyframes crmspin{to{transform:rotate(360deg)}}
.crm-detailhero{justify-content:space-between;gap:18px;padding:20px;border-radius:24px;border:1px solid #dbe8f3;background:linear-gradient(135deg,#f7fbff,#ffffff)}
.crm-detailhero h3{margin:0;font-size:28px;letter-spacing:-.05em;color:#12314d}
.crm-detailhero__meta{margin-top:8px;flex-wrap:wrap}
.crm-detailhero__stats{display:grid;grid-template-columns:repeat(3,minmax(120px,1fr));gap:12px;min-width:min(380px,100%)}
.crm-detailhero__stats>div{padding:16px;border-radius:18px;background:#fff;border:1px solid #dbe8f3;display:grid;gap:6px}
.crm-detailhero__stats small{font-size:12px;color:#7c95ab}.crm-detailhero__stats strong{font-size:20px;color:#12314d}
.crm-detailgrid{display:grid;grid-template-columns:1fr 1.15fr;gap:18px}
.crm-card--inner{padding:22px;margin:0;border-radius:24px}
.crm-card__head--compact{margin-bottom:16px}
.crm-infogrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.crm-infogrid>div{padding:14px;border-radius:16px;background:#f8fbfe;border:1px solid #e3edf5}
.crm-notes{margin-top:14px;padding:16px;border-radius:16px;background:#f8fbfe;border:1px solid #e3edf5}
.crm-notes p{margin:8px 0 0;color:#4f6880;white-space:pre-wrap;line-height:1.6;font-size:13px}
.crm-linkedlist{display:grid;gap:12px}
.crm-linkeditem{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px;border:1px solid #e3edf5;background:#fbfdff;border-radius:18px}
.crm-linkeditem__actions{display:flex;align-items:center;gap:10px}
.crm-emptybox{padding:18px;border:1px dashed #d8e6f2;border-radius:18px;background:#fbfdff;color:#6f879d;font-size:13px}
.crm-assignbox{margin-top:16px;padding-top:16px;border-top:1px solid #e8eef5;display:grid;grid-template-columns:1.5fr 1fr auto;gap:14px;align-items:end}
.crm-totalpill{background:#eff8ff;color:#0b6da8;border:1px solid #d7e8fb}
@media (max-width:1100px){.crm-detailgrid{grid-template-columns:1fr}.crm-detailhero{flex-direction:column;align-items:flex-start}.crm-detailhero__stats{min-width:0;width:100%}}
@media (max-width:860px){.crm-page__header,.crm-card__head,.crm-pagination,.crm-modal__header{flex-direction:column;align-items:flex-start}.crm-stats{grid-template-columns:1fr}.crm-search{min-width:0;width:100%}.crm-formgrid,.crm-previewcard,.crm-infogrid,.crm-assignbox{grid-template-columns:1fr}.crm-modal,.crm-modal--wide,.crm-modal--xl{width:100%;max-height:96vh}.crm-modalbackdrop{padding:10px}.crm-modal__header,.crm-modal__body,.crm-modal__footer{padding-left:18px;padding-right:18px}.crm-header__actions,.crm-actions,.crm-detailhero__meta{flex-wrap:wrap}.crm-detailhero__stats{grid-template-columns:1fr}.crm-table{min-width:740px}}
</style>
