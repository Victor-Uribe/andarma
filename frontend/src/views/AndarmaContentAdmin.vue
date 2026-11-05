<script setup>
import { computed, onMounted, ref } from 'vue'

// El JWT debe entregarlo el módulo de autenticación existente (NO guardarlo aquí).
const props = defineProps({ token: { type: String, required: true }, apiBase: { type: String, default: '/api' } })
const values = ref({})
const original = ref({})
const error = ref('')
const success = ref('')
const loading = ref(false)
const saving = ref(false)
const sections = {'inicio':'Inicio','nosotros':'Nosotros','opciones':'Viviendas y terrenos','por-que-andarma':'Por qué ANDARMA','como-comprar':'Cómo comprar','testimonios':'Testimonios','contacto':'Contacto'}
const grouped = computed(() => Object.entries(values.value).reduce((acc,[key,value]) => {
  const section = key.split('.')[0]
  ;(acc[section] ||= []).push({key,value})
  return acc
}, {}))
async function load() {
  loading.value=true;error.value=''
  try {
    const r=await fetch(`${props.apiBase}/site-content`,{headers:{Accept:'application/json',Authorization:`Bearer ${props.token}`}})
    if(!r.ok) throw new Error(`No fue posible consultar el contenido (${r.status}).`)
    const payload=await r.json(); values.value={...payload.data};original.value={...payload.data}
  } catch(e) { error.value=e.message } finally { loading.value=false }
}
async function save() {
  saving.value=true;error.value='';success.value=''
  const changed=Object.fromEntries(Object.entries(values.value).filter(([key,v])=>v!==original.value[key]))
  if (!Object.keys(changed).length) { saving.value=false;success.value='No hay cambios por guardar.';return }
  try {
    const r=await fetch(`${props.apiBase}/site-content`,{method:'PUT',headers:{'Content-Type':'application/json',Accept:'application/json',Authorization:`Bearer ${props.token}`},body:JSON.stringify({data:changed})})
    if(!r.ok) throw new Error(`No fue posible guardar (${r.status}).`)
    const payload=await r.json();values.value={...payload.data};original.value={...payload.data}
    success.value='Contenido guardado. Los cambios se reflejarán al recargar el sitio.'
  } catch(e){ error.value=e.message } finally{ saving.value=false }
}
onMounted(load)
</script>
<template>
  <main class="andarma-cms">
    <header><div><small>ANDARMA · ADMINISTRACIÓN</small><h1>Contenido del sitio</h1><p>Edita los textos sin modificar el HTML ni sus animaciones.</p></div><button @click="save" :disabled="saving || loading">{{ saving ? 'Guardando…' : 'Guardar cambios' }}</button></header>
    <p v-if="error" class="alert alert-danger" role="alert">{{error}}</p>
    <p v-if="success" class="alert alert-success" role="status">{{success}}</p>
    <p v-if="loading">Cargando contenido…</p>
    <section v-for="(items,group) in grouped" :key="group" class="andarma-panel">
      <h2>{{sections[group]||group}}</h2>
      <div v-for="item in items" :key="item.key" class="field">
        <label :for="item.key">{{item.key}}</label>
        <textarea :id="item.key" v-model="values[item.key]" rows="3" maxlength="1500"/>
      </div>
    </section>
  </main>
</template>
<style scoped>
.andarma-cms{max-width:1100px;margin:auto;padding:30px 20px;color:#103357;font:inherit}
header{display:flex;gap:24px;justify-content:space-between;align-items:center;margin-bottom:32px}header h1{margin:7px 0;font-size:30px}header small{color:#0066e0;font-weight:800;letter-spacing:1.5px}header p{color:#64748b}
button{background:#0066e0;color:white;padding:13px 22px;border:0;border-radius:10px;font-weight:700;cursor:pointer}button:disabled{opacity:.5}
.andarma-panel{border:1px solid #d9e5f5;border-radius:16px;background:white;padding:22px;margin:18px 0;box-shadow:0 10px 28px #003a8c0c}
.andarma-panel h2{font-size:20px;margin:0 0 20px}.field{margin-bottom:17px}.field label{display:block;font-size:12px;font-weight:700;color:#506a85;margin-bottom:7px}.field textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #ccdaea;border-radius:9px;padding:12px;color:#103357;background:#fbfdff;font:inherit;resize:vertical}
.field textarea:focus{outline:2px solid #0891ff;outline-offset:1px}@media(max-width:600px){header{display:block}header button{margin-top:14px;width:100%}}
</style>
