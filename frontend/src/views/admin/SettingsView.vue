<script setup>
import {computed,onMounted,ref} from 'vue'
import {api,put} from '../../services/api'
import {can} from '../../utils/andarmaPermissions'
import {notifyError,notifySuccess} from '../../utils/adminAlerts'
import {Save,RefreshCw,MapPin,Phone,Share2,Clock3,Info,ExternalLink,MessageCircle,Mail} from '../../components/admin/AndarmaFaIcons.js'
const tabs=[{id:'contact',label:'Contacto',icon:Phone},{id:'address',label:'Dirección y mapa',icon:MapPin},{id:'social',label:'Redes sociales',icon:Share2}]
const active=ref('contact'),loading=ref(false),saving=ref(false),error=ref('')
const blank=()=>({business_name:'ANDARMA',phone:'',whatsapp:'',email:'',address:'',neighborhood:'',city:'',state:'',postal_code:'',maps_url:'',facebook_url:'',instagram_url:'',tiktok_url:'',youtube_url:'',website_url:'',hours:'',whatsapp_message:'Hola, me gustaría recibir información sobre sus desarrollos.'})
const values=ref(blank()),original=ref(blank())
const changed=computed(()=>JSON.stringify(values.value)!==JSON.stringify(original.value))
const editable=computed(()=>can('settings.edit'))
async function load(){loading.value=true;error.value='';try{const r=await api('/admin/settings');values.value={...blank(),...(r.data||{})};original.value={...values.value}}catch(e){error.value=e.message}finally{loading.value=false}}
function clean(value){return String(value||'').trim()}
function validUrl(value){if(!clean(value))return true;try{const u=new URL(value);return ['http:','https:'].includes(u.protocol)}catch{return false}}
function validate(){
 const d=values.value
 if(d.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(d.email))return 'Revisa el correo electrónico.'
 for(const k of ['phone','whatsapp']){const digits=clean(d[k]).replace(/\D/g,'');if(d[k]&&(digits.length<10||digits.length>15))return 'El teléfono y WhatsApp deben tener entre 10 y 15 dígitos.'}
 for(const k of ['maps_url','facebook_url','instagram_url','tiktok_url','youtube_url','website_url']) if(!validUrl(d[k]))return 'Revisa el enlace de '+k.replace('_url','')+'. Incluye https://.'
 return ''
}
async function save(){if(!editable.value||saving.value)return;const err=validate();if(err){error.value=err;return}saving.value=true;error.value='';try{const payload=Object.fromEntries(Object.entries(values.value).map(([k,v])=>[k,clean(v)]));const r=await put('/admin/settings',payload);values.value={...blank(),...(r.data||{})};original.value={...values.value};notifySuccess('Configuración actualizada','Los datos institucionales se guardaron correctamente.')}catch(e){error.value=e.message;notifyError('No se pudo guardar',e.message)}finally{saving.value=false}}
const waLink=computed(()=>{const d=clean(values.value.whatsapp).replace(/\D/g,'');return d?`https://wa.me/${d.length===10?'52'+d:d}?text=${encodeURIComponent(values.value.whatsapp_message||'Hola')}`:''})
onMounted(load)
</script>
<template>
<section class="settings-v50">
 <header class="settings-v50-head"><div><span class="admin-kicker">ADMINISTRACIÓN / SITIO WEB</span><h1>Configuración</h1><p>Actualiza los datos de contacto y redes sociales de ANDARMA desde un solo lugar.</p></div><button class="ad-btn" :disabled="loading||saving" @click="load"><RefreshCw :size="15"/> Actualizar</button></header>
 <p v-if="error" class="settings-v50-error" role="alert">{{error}}</p>
 <div class="settings-v50-layout">
  <nav class="settings-v50-nav" aria-label="Secciones de configuración"><button v-for="tab in tabs" :key="tab.id" type="button" :class="{active:active===tab.id}" @click="active=tab.id"><component :is="tab.icon" :size="17"/>{{tab.label}}</button><p><Info :size="15"/> Solo se muestran los datos que el cliente necesita administrar.</p></nav>
  <form class="settings-v50-main" @submit.prevent="save">
   <fieldset :disabled="!editable||saving||loading" class="settings-v50-fields">
    <template v-if="active==='contact'"><header><h2>Datos de contacto</h2><p>Estos datos identifican a ANDARMA y permiten que los clientes se comuniquen.</p></header><div class="ad-form-grid">
     <label class="ad-label ad-form-span">Nombre comercial<input v-model="values.business_name" class="ad-input" maxlength="150" placeholder="ANDARMA"/></label>
     <label class="ad-label">Teléfono de oficina<input v-model="values.phone" type="tel" class="ad-input" maxlength="30" placeholder="55 1234 5678"/></label>
     <label class="ad-label">WhatsApp de atención<input v-model="values.whatsapp" type="tel" class="ad-input" maxlength="30" placeholder="55 1234 5678"/></label>
     <label class="ad-label ad-form-span">Correo de contacto<input v-model="values.email" type="email" class="ad-input" maxlength="180" placeholder="contacto@andarma.mx"/></label>
     <label class="ad-label ad-form-span">Horario de atención<input v-model="values.hours" class="ad-input" maxlength="200" placeholder="Lunes a viernes de 9:00 a 18:00"/></label>
     <label class="ad-label ad-form-span">Mensaje inicial de WhatsApp<textarea v-model="values.whatsapp_message" rows="2" class="ad-textarea" maxlength="350" placeholder="Hola, quiero información sobre sus desarrollos."></textarea></label>
    </div><a v-if="waLink" class="ad-btn settings-v50-verify" :href="waLink" target="_blank" rel="noopener noreferrer"><MessageCircle :size="15"/> Probar enlace de WhatsApp <ExternalLink :size="13"/></a></template>
    <template v-else-if="active==='address'"><header><h2>Dirección y ubicación</h2><p>Escribe la dirección de la oficina, no la de un desarrollo inmobiliario.</p></header><div class="ad-form-grid">
     <label class="ad-label ad-form-span">Calle y número<input v-model="values.address" class="ad-input" maxlength="255" placeholder="Ej. Av. Principal 100"/></label>
     <label class="ad-label">Colonia<input v-model="values.neighborhood" class="ad-input" maxlength="120" placeholder="Colonia"/></label>
     <label class="ad-label">Municipio o ciudad<input v-model="values.city" class="ad-input" maxlength="120" placeholder="Ej. Ixtapaluca"/></label>
     <label class="ad-label">Estado<input v-model="values.state" class="ad-input" maxlength="120" placeholder="Estado de México"/></label>
     <label class="ad-label">Código postal<input v-model="values.postal_code" class="ad-input" maxlength="12" placeholder="56530"/></label>
     <label class="ad-label ad-form-span">Enlace de Google Maps<input v-model="values.maps_url" type="url" class="ad-input" maxlength="1500" placeholder="https://maps.google.com/..."/></label>
    </div><a v-if="values.maps_url&&validUrl(values.maps_url)" class="ad-btn settings-v50-verify" :href="values.maps_url" target="_blank" rel="noopener noreferrer"><MapPin :size="15"/> Comprobar ubicación <ExternalLink :size="13"/></a></template>
    <template v-else><header><h2>Redes sociales</h2><p>Agrega únicamente las redes que ANDARMA utiliza. Las demás pueden quedar vacías.</p></header><div class="ad-form-grid">
      <label class="ad-label">Facebook<input v-model="values.facebook_url" class="ad-input" type="url" maxlength="500" placeholder="https://facebook.com/..."/></label>
      <label class="ad-label">Instagram<input v-model="values.instagram_url" class="ad-input" type="url" maxlength="500" placeholder="https://instagram.com/..."/></label>
      <label class="ad-label">TikTok<input v-model="values.tiktok_url" class="ad-input" type="url" maxlength="500" placeholder="https://tiktok.com/@..."/></label>
      <label class="ad-label">YouTube<input v-model="values.youtube_url" class="ad-input" type="url" maxlength="500" placeholder="https://youtube.com/@..."/></label>
      <label class="ad-label ad-form-span">Sitio web oficial<input v-model="values.website_url" class="ad-input" type="url" maxlength="500" placeholder="https://andarma.mx"/></label>
    </div></template>
   </fieldset>
   <footer class="settings-v50-footer"><div><strong>{{changed?'Cambios sin guardar':'Configuración al día'}}</strong><small>{{editable?'Los datos se guardan en Laravel.':'Tu perfil solo tiene permiso de consulta.'}}</small></div><button v-if="editable" type="submit" class="ad-btn ad-btn--primary" :disabled="loading||saving||!changed"><Save :size="15"/>{{saving?'Guardando…':'Guardar cambios'}}</button></footer>
  </form>
 </div>
</section>
</template>
<style scoped>
.settings-v50{display:grid;gap:20px}.settings-v50-head{display:flex;align-items:end;justify-content:space-between;gap:15px;flex-wrap:wrap}.settings-v50-head h1{font-size:29px;font-weight:800;color:#17324e;letter-spacing:-.035em;margin:5px 0}.settings-v50-head p{color:#72879c;font-size:13px}.settings-v50-layout{display:grid;grid-template-columns:230px minmax(0,1fr);gap:16px;align-items:start}.settings-v50-nav,.settings-v50-main{border:1px solid #dde7f1;border-radius:17px;background:#fff;box-shadow:0 7px 24px #122c4708}.settings-v50-nav{padding:10px;display:grid;gap:4px}.settings-v50-nav button{width:100%;border:0;border-radius:9px;background:transparent;color:#35516c;display:flex;align-items:center;gap:10px;padding:12px;text-align:left;font-size:12px;font-weight:700;cursor:pointer}.settings-v50-nav button:hover{background:#f2f7fd}.settings-v50-nav button.active{color:#0066e0;background:#edf5ff}.settings-v50-nav p{display:flex;gap:8px;color:#72879c;font-size:11px;line-height:1.5;padding:12px;margin:0}.settings-v50-main{overflow:hidden}.settings-v50-fields{border:0;margin:0;padding:22px;min-width:0}.settings-v50-fields header{margin-bottom:22px}.settings-v50-fields header h2{color:#17324e;font-weight:800;font-size:18px}.settings-v50-fields header p{color:#72879c;margin:5px 0;font-size:12px}.settings-v50-verify{margin-top:17px}.settings-v50-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:16px 22px;border-top:1px solid #e8eef5}.settings-v50-footer>div{display:grid;gap:4px}.settings-v50-footer strong{color:#17324e;font-size:12px}.settings-v50-footer small{color:#8194a8;font-size:11px}.settings-v50-error{background:#fff1f3;border:1px solid #f0ccd2;color:#ac3443;padding:12px;border-radius:10px;font-size:12px}@media(max-width:750px){.settings-v50-layout{grid-template-columns:1fr}.settings-v50-nav{display:flex;overflow-x:auto;gap:5px}.settings-v50-nav button{width:auto;flex:0 0 auto}.settings-v50-nav p{display:none}}@media(max-width:600px){.settings-v50-fields{padding:16px}.settings-v50-footer{padding:14px}}
</style>
