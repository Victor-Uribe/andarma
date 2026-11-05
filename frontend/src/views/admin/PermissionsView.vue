<script setup>
import {computed,onMounted,ref} from 'vue'
import {api,post,put,remove} from '../../services/api'
import {can} from '../../utils/andarmaPermissions'
import {confirmAction,notifyError,notifySuccess} from '../../utils/adminAlerts'
import {Plus,Save,Trash2,ShieldCheck,RefreshCw} from 'lucide-vue-next'
const roles=ref([]),modules=ref({}),selected=ref(''),editing=ref(false),name=ref(''),permissions=ref([]),busy=ref(false),error=ref(''),loading=ref(false)
const actions=[['view','Consultar'],['create','Crear'],['edit','Editar'],['delete','Eliminar']]
const role=computed(()=>roles.value.find(r=>String(r.id)===String(selected.value)))
// ANDARMA_RBAC_V49_EDIT_ADMIN: solo Superadministrador es inmutable.
const system=computed(()=>role.value?.slug==='superadmin')
const selectedCount=computed(()=>permissions.value.length)
async function load(){loading.value=true;error.value='';try{const r=await api('/admin/roles');roles.value=r.data||[];modules.value=r.modules||{};if(!roles.value.some(x=>String(x.id)===String(selected.value))) selected.value=String(roles.value[0]?.id||'');pick()}catch(e){error.value=e.message}finally{loading.value=false}}
function pick(){editing.value=false;name.value=role.value?.name||'';permissions.value=[...(role.value?.permissions||[])];if(role.value?.slug==='superadmin') permissions.value=Object.entries(modules.value).flatMap(([key,def])=>def.actions.map(a=>`${key}.${a}`))}
function create(){selected.value='';name.value='';permissions.value=[];editing.value=true}
function toggle(key){if(!editing.value||system.value)return;permissions.value=permissions.value.includes(key)?permissions.value.filter(p=>p!==key):[...permissions.value,key]}
async function save(){if(!name.value.trim()||name.value.trim().length<3){error.value='Escribe un nombre de al menos 3 caracteres.';return}busy.value=true;error.value='';try{// ANDARMA_V51_2_FRONT: el catálogo es la fuente de verdad.
    // Nunca enviar roles.*, aunque existan en permisos históricos.
    const recognized=new Set(Object.entries(modules.value).flatMap(([key,def])=>
      (def.actions||[]).map(action=>`${key}.${action}`)))
    const safePermissions=[...new Set(permissions.value.filter(p=>recognized.has(p)&&!p.startsWith('roles.')))]
    const payload={name:name.value.trim(),permissions:safePermissions};if(role.value)await put('/admin/roles/'+role.value.id,payload);else {const r=await post('/admin/roles',payload);selected.value=String(r.data.id)}await load();notifySuccess('Permisos guardados');editing.value=false}catch(e){error.value=e.message}finally{busy.value=false}}
async function destroyRole(){if(!role.value||system.value)return;const ok=await confirmAction({title:'Eliminar rol',text:`Se eliminará «${role.value.name}». Solo es posible si no tiene usuarios asignados.`,confirmText:'Sí, eliminar',variant:'danger'});if(!ok)return;busy.value=true;try{await remove('/admin/roles/'+role.value.id);selected.value='';await load();notifySuccess('Rol eliminado')}catch(e){notifyError('No se pudo eliminar',e.message)}finally{busy.value=false}}
onMounted(load)
</script>
<template>
<section class="ad-roles">
  <header class="ad-roles-head"><div><span class="admin-kicker">SEGURIDAD / AUTORIZACIONES</span><h1>Roles y permisos</h1><p>Configura qué puede consultar y modificar cada perfil.</p></div><div class="ad-roles-actions"><button class="ad-btn" @click="load"><RefreshCw :size="15"/> Actualizar</button><button v-if="can('roles.create')" class="ad-btn ad-btn--primary" @click="create"><Plus :size="15"/> Nuevo rol</button></div></header>
  <p v-if="error" class="ad-roles-error" role="alert">{{error}}</p>
  <div class="ad-roles-card">
    <div class="ad-roles-toolbar"><label>Rol <select v-model="selected" class="ad-select" @change="pick"><option value="" disabled>Nuevo rol</option><option v-for="r in roles" :key="r.id" :value="String(r.id)">{{r.name}}</option></select></label><label v-if="editing">Nombre del rol<input v-model="name" class="ad-input" maxlength="100" :disabled="role?.slug==='administrator'" placeholder="Ej. Asesor comercial"/></label><div class="ad-roles-actions"><span class="ad-badge">{{selectedCount}} permisos</span><button v-if="role&&!editing&&!system&&can('roles.edit')" class="ad-btn" @click="editing=true">Editar permisos</button><button v-if="role&&!role.is_system&&can('roles.delete')" class="ad-btn ad-btn--danger" @click="destroyRole"><Trash2 :size="15"/> Eliminar</button></div></div>
    <div v-if="system" class="ad-roles-note"><ShieldCheck :size="17"/> Superadministrador tiene todos los permisos y está protegido. Para configurar otros accesos, selecciona Administrador o crea un nuevo rol.</div>
    <div v-else class="ad-roles-note">Dashboard solo se consulta. Las casillas indisponibles representan acciones que ese módulo no ofrece.</div>
    <div class="ad-table-wrap"><table class="ad-table ad-roles-table"><thead><tr><th>Módulo</th><th v-for="a in actions" :key="a[0]">{{a[1]}}</th></tr></thead><tbody><tr v-for="(def,key) in modules" :key="key"><td><strong>{{def.label}}</strong></td><td v-for="a in actions" :key="a[0]"><input v-if="def.actions.includes(a[0])" type="checkbox" :checked="permissions.includes(key+'.'+a[0])" :disabled="!editing||system||busy||key==='roles'" :aria-label="def.label+' '+a[1]" @change="toggle(key+'.'+a[0])"/><span v-else class="ad-roles-na">—</span></td></tr></tbody></table></div>
    <footer v-if="editing" class="ad-roles-footer"><button class="ad-btn" :disabled="busy" @click="role?pick():create()">Cancelar</button><button class="ad-btn ad-btn--primary" :disabled="busy" @click="save"><Save :size="16"/> {{busy?'Guardando…':'Guardar rol y permisos'}}</button></footer>
  </div>
</section>
</template>
<style scoped>
.ad-roles{display:grid;gap:20px}.ad-roles-head{display:flex;justify-content:space-between;align-items:end;flex-wrap:wrap;gap:18px}.ad-roles-head h1{font-size:29px;font-weight:800;color:#17324e;margin:4px 0}.ad-roles-head p{color:#72879c;font-size:13px}.ad-roles-actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px}.ad-roles-card{background:white;border:1px solid #dce7f2;border-radius:18px;overflow:hidden}.ad-roles-toolbar{display:flex;gap:15px;align-items:end;flex-wrap:wrap;padding:18px 20px;border-bottom:1px solid #e8eef5}.ad-roles-toolbar label{display:grid;gap:7px;font-size:12px;font-weight:650;color:#35516c;flex:1;min-width:195px}.ad-roles-note{display:flex;align-items:center;gap:9px;padding:12px 20px;background:#f4f8fd;border-bottom:1px solid #e8eef5;font-size:12px;color:#58738e}.ad-roles-table th:not(:first-child),.ad-roles-table td:not(:first-child){text-align:center;width:105px}.ad-roles-table strong{font-size:12px}.ad-roles-table input{height:17px;width:17px;accent-color:#0066e0;cursor:pointer}.ad-roles-table input:disabled{cursor:default}.ad-roles-na{color:#c4d0de}.ad-roles-footer{padding:15px 20px;border-top:1px solid #e8eef5;display:flex;justify-content:flex-end;gap:10px}.ad-roles-error{padding:12px;background:#fff1f3;color:#be4050;border-radius:10px}@media(max-width:600px){.ad-roles-table th:not(:first-child),.ad-roles-table td:not(:first-child){width:65px}.ad-roles-toolbar{padding:14px}.ad-roles-table th,.ad-roles-table td{padding:11px 7px}}
</style>
