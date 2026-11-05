<script setup>
// ANDARMA_USERS_UX_V52 — formulario y listado unificados con el diseño del administrador.
import {computed,onMounted,ref} from 'vue'
import {api,post,put,remove} from '../../services/api'
import {can} from '../../utils/andarmaPermissions'
import {confirmAction,notifyError,notifySuccess} from '../../utils/adminAlerts'
import {Plus,RefreshCw,Pencil,UserX,Save,X,Eye,EyeOff,ShieldCheck,LockKeyhole,UserRound,Mail,KeyRound,Search,Info} from 'lucide-vue-next'

const users=ref([]),roles=ref([]),error=ref(''),modalError=ref(''),loading=ref(false),modal=ref(false),busy=ref(false),editId=ref(null)
const search=ref(''),statusFilter=ref('all'),showPassword=ref(false),confirmPassword=ref(''),showConfirm=ref(false)
const blank=()=>({name:'',email:'',password:'',andarma_role_id:'',andarma_enabled:true})
const form=ref(blank())
const filtered=computed(()=>users.value.filter(u=>{
 const q=search.value.trim().toLocaleLowerCase('es')
 return (!q||[u.name,u.email,u.role?.name].some(v=>String(v||'').toLocaleLowerCase('es').includes(q)))&&
 (statusFilter.value==='all'||Boolean(u.andarma_enabled)===(statusFilter.value==='active'))
}))
const selectedRole=computed(()=>roles.value.find(r=>Number(r.id)===Number(form.value.andarma_role_id)))
const roleNote=computed(()=>{
 const slug=selectedRole.value?.slug
 if(slug==='superadmin')return 'Acceso total. Este rol solo puede asignarlo un Superadministrador.'
 if(slug==='administrator')return 'Acceso según los permisos definidos para Administrador.'
 return selectedRole.value?'Sus acciones estarán limitadas a los permisos de este rol.':'Selecciona el perfil que tendrá esta cuenta.'
})
const roleName=u=>u.role?.name||'Sin rol'
const initials=name=>String(name||'?').trim().split(/\s+/).slice(0,2).map(x=>x[0]?.toLocaleUpperCase('es')||'').join('')
const passwordHelp=computed(()=>editId.value?'Déjala vacía si deseas conservar la contraseña actual.':'Mínimo 10 caracteres. Utiliza una contraseña única y segura.')
const canSave=computed(()=>!busy.value&&Boolean(form.value.name.trim())&&Boolean(form.value.email.trim())&&Boolean(form.value.andarma_role_id)&&(!form.value.password||form.value.password.length>=10)&&(!form.value.password||form.value.password===confirmPassword.value)&&Boolean(editId.value||form.value.password))
async function load(){loading.value=true;error.value='';try{
 const [u,r]=await Promise.all([api('/admin/users'),api('/admin/user-role-options')]);users.value=u.data||[];roles.value=r.data||[]
}catch(e){error.value=e.message}finally{loading.value=false}}
function clearSecrets(){form.value.password='';confirmPassword.value='';showPassword.value=false;showConfirm.value=false;modalError.value=''}
function add(){editId.value=null;form.value=blank();clearSecrets();modal.value=true}
function edit(user){editId.value=user.id;form.value={name:user.name,email:user.email,password:'',andarma_role_id:user.andarma_role_id,andarma_enabled:Boolean(user.andarma_enabled)};clearSecrets();modal.value=true}
function close(){if(busy.value)return;modal.value=false;clearSecrets()}
async function save(){if(busy.value)return;modalError.value='';
 if(!form.value.name.trim()||!form.value.email.trim()||!form.value.andarma_role_id){modalError.value='Completa nombre, correo electrónico y rol.';return}
 if(!editId.value&&!form.value.password){modalError.value='Establece una contraseña para la nueva cuenta.';return}
 if(form.value.password&&form.value.password.length<10){modalError.value='La contraseña debe tener al menos 10 caracteres.';return}
 if(form.value.password!==confirmPassword.value){modalError.value='Las contraseñas no coinciden.';return}
 busy.value=true
 try{
  const payload={...form.value,name:form.value.name.trim(),email:form.value.email.trim()}
  if(!payload.password&&editId.value)delete payload.password
  if(editId.value)await put('/admin/users/'+editId.value,payload);else await post('/admin/users',payload)
  const editing=Boolean(editId.value);closeAfterSave();await load();notifySuccess(editing?'Usuario actualizado':'Usuario creado','Los cambios se guardaron correctamente.')
 }catch(e){modalError.value=e.message}finally{busy.value=false}
}
function closeAfterSave(){modal.value=false;clearSecrets()}
async function deactivate(user){
 if(!await confirmAction({title:'Desactivar cuenta',text:`${user.name} perderá acceso al panel de ANDARMA. Su información e historial se conservarán.`,confirmText:'Sí, desactivar',variant:'danger'}))return
 try{await remove('/admin/users/'+user.id);await load();notifySuccess('Cuenta desactivada')}catch(e){notifyError('No se pudo desactivar',e.message)}
}
onMounted(load)
</script>
<template>
<section class="ad-users-v52">
 <header class="ad-users-v52__head">
  <div><span class="admin-kicker">ADMINISTRACIÓN / ACCESOS</span><h1>Usuarios<span class="heading-dot">.</span></h1><p>Administra las cuentas y asigna el nivel de acceso adecuado.</p></div>
  <div class="ad-users-v52__head-actions">
   <button class="ad-btn" type="button" :disabled="loading" @click="load"><RefreshCw :size="15" :class="{'ad-users-v52__spin':loading}"/> Actualizar</button>
   <button v-if="can('users.create')" class="ad-btn ad-btn--primary" type="button" @click="add"><Plus :size="16"/> Nuevo usuario</button>
  </div>
 </header>
 <div v-if="error" class="ad-users-v52__alert" role="alert">{{error}}</div>
 <div class="ad-users-v52__panel">
  <div class="ad-users-v52__toolbar">
   <div><h2>Usuarios registrados</h2><p>Consulta quién puede acceder a la plataforma.</p></div>
   <span class="ad-badge">{{filtered.length}} {{filtered.length===1?'usuario':'usuarios'}}</span>
  </div>
  <div class="ad-users-v52__filters">
   <label class="ad-users-v52__search"><Search :size="16"/><input v-model="search" type="search" placeholder="Buscar por nombre, correo o rol…" aria-label="Buscar usuarios"/></label>
   <select v-model="statusFilter" class="ad-select" aria-label="Filtrar por estado"><option value="all">Todos los estados</option><option value="active">Activos</option><option value="inactive">Desactivados</option></select>
  </div>
  <div class="ad-table-wrap"><table class="ad-table ad-users-v52__table"><thead><tr><th>USUARIO</th><th>ROL ASIGNADO</th><th>ESTADO</th><th>ACCIONES</th></tr></thead><tbody>
   <tr v-for="u in filtered" :key="u.id">
    <td><div class="ad-users-v52__person"><div class="ad-users-v52__avatar" aria-hidden="true">{{initials(u.name)}}</div><div><strong>{{u.name}}</strong><small>{{u.email}}</small></div></div></td>
    <td><span class="ad-users-v52__role"><ShieldCheck :size="14"/> {{roleName(u)}}</span></td>
    <td><span class="ad-badge" :class="u.andarma_enabled?'ad-badge--success':'ad-badge--danger'">{{u.andarma_enabled?'Activo':'Desactivado'}}</span></td>
    <td><div class="ad-actions"><button v-if="can('users.edit')" class="ad-btn" type="button" @click="edit(u)"><Pencil :size="14"/> Editar</button><button v-if="u.andarma_enabled&&can('users.delete')" type="button" class="ad-btn ad-btn--danger" @click="deactivate(u)"><UserX :size="14"/> Desactivar</button></div></td>
   </tr>
   <tr v-if="!filtered.length"><td colspan="4" class="ad-users-v52__empty">{{loading?'Cargando usuarios…':search||statusFilter!=='all'?'No se encontraron usuarios con esos filtros.':'Todavía no hay usuarios. Crea la primera cuenta para comenzar.'}}</td></tr>
  </tbody></table></div>
 </div>
 <div v-if="modal" class="ad-modal-backdrop ad-users-v52__backdrop" @click.self="close">
  <div class="ad-modal ad-users-v52__dialog" role="dialog" aria-modal="true" :aria-label="editId?'Editar usuario':'Nuevo usuario'">
   <header class="ad-modal__header ad-users-v52__modal-head"><div class="ad-users-v52__modal-title-icon"><UserRound :size="21"/></div><div class="ad-users-v52__modal-title"><span class="admin-kicker">ADMINISTRACIÓN DE ACCESOS</span><h2>{{editId?'Editar usuario':'Nuevo usuario'}}</h2><p>{{editId?'Actualiza los datos o el acceso de esta cuenta.':'Completa los datos para habilitar una nueva cuenta.'}}</p></div><button class="ad-icon-btn" type="button" aria-label="Cerrar formulario" :disabled="busy" @click="close"><X :size="18"/></button></header>
   <form @submit.prevent="save">
    <div class="ad-modal__body ad-users-v52__body">
     <p v-if="modalError" class="ad-users-v52__alert" role="alert">{{modalError}}</p>
     <div class="ad-users-v52__section-head"><UserRound :size="17"/><div><strong>Información del usuario</strong><small>Datos que identifican la cuenta.</small></div></div>
     <div class="ad-form-grid ad-users-v52__grid">
      <label class="ad-label ad-form-span">Nombre completo <span class="ad-users-v52__required">*</span><input v-model.trim="form.name" class="ad-input" type="text" required maxlength="255" placeholder="Ej. María González" autocomplete="name"/></label>
      <label class="ad-label ad-form-span">Correo electrónico <span class="ad-users-v52__required">*</span><div class="ad-users-v52__input-icon"><Mail :size="17"/><input v-model.trim="form.email" class="ad-input" type="email" required maxlength="255" placeholder="nombre@empresa.com" autocomplete="email"/></div></label>
     </div>
     <div class="ad-users-v52__section-head"><ShieldCheck :size="17"/><div><strong>Acceso a la plataforma</strong><small>Define qué puede hacer este usuario.</small></div></div>
     <div class="ad-form-grid ad-users-v52__grid">
      <label class="ad-label ad-form-span">Rol de usuario <span class="ad-users-v52__required">*</span><select v-model.number="form.andarma_role_id" class="ad-select" required><option value="" disabled>Seleccionar un rol</option><option v-for="r in roles" :key="r.id" :value="r.id">{{r.name}}</option></select></label>
      <div class="ad-users-v52__role-help ad-form-span"><Info :size="17"/><span>{{roleNote}}</span></div>
      <label class="ad-users-v52__active ad-form-span"><input v-model="form.andarma_enabled" type="checkbox"/><span><strong>Cuenta activa</strong><small>{{form.andarma_enabled?'Podrá iniciar sesión según su rol.':'No podrá iniciar sesión mientras esté desactivada.'}}</small></span></label>
     </div>
     <div class="ad-users-v52__section-head"><LockKeyhole :size="17"/><div><strong>{{editId?'Cambiar contraseña':'Contraseña de acceso'}}</strong><small>{{passwordHelp}}</small></div></div>
     <div class="ad-form-grid ad-users-v52__grid">
      <label class="ad-label">{{editId?'Nueva contraseña (opcional)':'Contraseña *'}}<div class="ad-users-v52__input-password"><KeyRound :size="16"/><input v-model="form.password" class="ad-input" :type="showPassword?'text':'password'" :required="!editId" minlength="10" maxlength="200" autocomplete="new-password" :placeholder="editId?'Dejar vacía para conservar':'Mínimo 10 caracteres'"/><button type="button" :aria-label="showPassword?'Ocultar contraseña':'Mostrar contraseña'" @click="showPassword=!showPassword"><EyeOff v-if="showPassword" :size="17"/><Eye v-else :size="17"/></button></div></label>
      <label class="ad-label">Confirmar contraseña <span v-if="!editId" class="ad-users-v52__required">*</span><div class="ad-users-v52__input-password"><LockKeyhole :size="16"/><input v-model="confirmPassword" class="ad-input" :type="showConfirm?'text':'password'" :required="!editId||Boolean(form.password)" minlength="10" maxlength="200" autocomplete="new-password" placeholder="Repite la contraseña"/><button type="button" :aria-label="showConfirm?'Ocultar confirmación':'Mostrar confirmación'" @click="showConfirm=!showConfirm"><EyeOff v-if="showConfirm" :size="17"/><Eye v-else :size="17"/></button></div></label>
      <p v-if="form.password&&confirmPassword&&form.password!==confirmPassword" class="ad-users-v52__hint-error ad-form-span">Las contraseñas no coinciden.</p>
      <p v-if="editId" class="ad-users-v52__password-hint ad-form-span">Si no deseas cambiar la contraseña, deja ambos campos vacíos.</p>
     </div>
    </div>
    <footer class="ad-modal__footer ad-users-v52__footer"><button type="button" class="ad-btn" :disabled="busy" @click="close">Cancelar</button><button class="ad-btn ad-btn--primary" type="submit" :disabled="!canSave"><Save :size="16"/>{{busy?'Guardando…':editId?'Guardar cambios':'Crear usuario'}}</button></footer>
   </form>
  </div>
 </div>
</section>
</template>
<style scoped>
.ad-users-v52{display:grid;gap:20px;color:#17324e}
.ad-users-v52__head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ad-users-v52__head h1{font-size:29px;line-height:1.2;font-weight:800;letter-spacing:-.03em;margin:5px 0}
.ad-users-v52__head p{margin:0;color:#72879c;font-size:13px}
.ad-users-v52__head-actions{display:flex;flex-wrap:wrap;gap:8px}
.ad-users-v52__panel{background:#fff;border:1px solid #dde7f1;box-shadow:0 6px 24px rgba(18,44,71,.045);border-radius:18px;overflow:hidden}
.ad-users-v52__toolbar{display:flex;align-items:center;justify-content:space-between;padding:19px 21px;gap:10px;border-bottom:1px solid #eaf0f5}
.ad-users-v52__toolbar h2{font-size:15px;font-weight:800;margin:0 0 3px}.ad-users-v52__toolbar p{font-size:12px;color:#72879c;margin:0}
.ad-users-v52__filters{padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;border-bottom:1px solid #eaf0f5}
.ad-users-v52__search{flex:1;min-width:210px;display:flex;align-items:center;gap:8px;padding:0 12px;border-radius:10px;border:1px solid #dde7f1;color:#72879c;height:40px}
.ad-users-v52__search input{border:0;outline:0;min-width:0;flex:1;font:inherit;font-size:13px;color:#17324e}
.ad-users-v52__filters select{width:170px;min-height:40px}
.ad-users-v52__person{display:flex;align-items:center;gap:11px;min-width:190px}
.ad-users-v52__avatar{width:37px;height:37px;flex:none;border:1px solid #d8e9fa;background:#edf5ff;border-radius:11px;display:grid;place-items:center;font-size:12px;font-weight:800;color:#0066e0}
.ad-users-v52__person strong{display:block;font-size:12px;color:#17324e}.ad-users-v52__person small{display:block;margin-top:4px;font-size:11px;color:#72879c;overflow-wrap:anywhere}
.ad-users-v52__role{display:inline-flex;align-items:center;gap:7px;color:#35516c;font-size:12px;font-weight:600}
.ad-users-v52__empty{text-align:center!important;padding:30px!important;color:#72879c!important}
.ad-users-v52__alert{padding:12px 14px;background:#fff1f3;border:1px solid #f4d8dd;border-radius:10px;color:#ad394a;font-size:12px}
.ad-users-v52__dialog{width:min(640px,100%);max-height:calc(100dvh - 32px)}
.ad-users-v52__modal-head{align-items:flex-start;justify-content:flex-start}.ad-users-v52__modal-head>.ad-icon-btn{margin-left:auto;flex:none}
.ad-users-v52__modal-title-icon{width:40px;height:40px;flex:none;display:grid;place-items:center;background:#edf5ff;border:1px solid #dceafa;border-radius:12px;color:#0066e0}
.ad-users-v52__modal-title h2{margin:4px 0;font-size:20px;line-height:1.25;font-weight:800;color:#17324e;letter-spacing:-.025em}.ad-users-v52__modal-title p{font-size:12px;color:#72879c;margin:0}
.ad-users-v52__body{padding:18px 24px 22px;overflow-y:auto;max-height:calc(100dvh - 206px)}
.ad-users-v52__section-head{display:flex;align-items:center;gap:10px;margin:6px 0 14px;color:#0066e0}.ad-users-v52__section-head:not(:first-child){padding-top:18px;border-top:1px solid #edf1f6;margin-top:22px}
.ad-users-v52__section-head strong{display:block;font-size:13px;color:#17324e}.ad-users-v52__section-head small{display:block;font-size:11px;color:#72879c;margin-top:3px}
.ad-users-v52__required{color:#be4050}.ad-users-v52__grid{gap:14px}
.ad-users-v52__grid .ad-label{font-weight:700;font-size:12px;color:#35516c}
.ad-users-v52__input-icon,.ad-users-v52__input-password{display:flex;align-items:center;position:relative;color:#7890a5}
.ad-users-v52__input-icon>svg,.ad-users-v52__input-password>svg{position:absolute;left:12px;pointer-events:none;z-index:1}
.ad-users-v52__input-icon .ad-input,.ad-users-v52__input-password .ad-input{padding-left:38px}
.ad-users-v52__input-password .ad-input{padding-right:40px}
.ad-users-v52__input-password button{border:0;background:transparent;display:grid;place-items:center;cursor:pointer;color:#72879c;padding:6px;position:absolute;right:5px}
.ad-users-v52__role-help{display:flex;gap:9px;align-items:flex-start;padding:11px 12px;background:#f3f8fe;border:1px solid #dceafb;border-radius:10px;font-size:11px;color:#486a88;line-height:1.55}
.ad-users-v52__role-help svg{flex:none;color:#0066e0}
.ad-users-v52__active{display:flex;align-items:center;gap:12px;padding:12px 14px;background:#f9fbfe;border:1px solid #dde7f1;border-radius:11px;cursor:pointer}
.ad-users-v52__active input{accent-color:#0066e0;width:17px;height:17px;flex:none}
.ad-users-v52__active strong{display:block;color:#17324e;font-size:12px}.ad-users-v52__active small{display:block;color:#72879c;margin-top:4px;font-size:11px}
.ad-users-v52__password-hint{font-size:11px;margin:0;color:#72879c}.ad-users-v52__hint-error{font-size:11px;color:#be4050;margin:0}
.ad-users-v52__footer{position:relative;z-index:1}
@keyframes ad-users-v52-rotate{to{transform:rotate(360deg)}}.ad-users-v52__spin{animation:ad-users-v52-rotate 1s linear infinite}
@media(max-width:640px){.ad-users-v52__head h1{font-size:25px}.ad-users-v52__toolbar{padding:15px}.ad-users-v52__filters{padding:12px}.ad-users-v52__filters select{width:100%}.ad-users-v52__body{padding:14px;max-height:calc(100dvh - 184px)}.ad-users-v52__dialog{max-height:calc(100dvh - 16px)}.ad-users-v52__modal-title h2{font-size:18px}.ad-users-v52__modal-head{padding:14px}.ad-users-v52__footer{padding:13px}.ad-users-v52__footer .ad-btn{flex:1}}
</style>
