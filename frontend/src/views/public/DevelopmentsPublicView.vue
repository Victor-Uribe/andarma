<script setup>
// ANDARMA_F3F_SCROLL_GUARD: desbloqueo reversible exclusivo de /desarrollos.
import { onMounted as adScrollMounted, onBeforeUnmount as adScrollUnmounted, nextTick as adScrollNextTick } from 'vue'
let adScrollRestore = null
adScrollMounted(async () => {
  await adScrollNextTick()
  const html = document.documentElement
  const body = document.body
  const saved = [html,body].map(el => ({
    el,
    value: el.style.getPropertyValue('overflow'),
    priority: el.style.getPropertyPriority('overflow'),
    y: el.style.getPropertyValue('overflow-y'),
    yp: el.style.getPropertyPriority('overflow-y')
  }))
  body.classList.add('ad-developments-scroll')
  // La animación/precarga de Home puede dejar overflow:hidden al navegar.
  // Se restablece la posibilidad de desplazarse sin modificar el visor del plano.
  for (const {el} of saved) {
    el.style.setProperty('overflow-y','auto','important')
  }
  adScrollRestore = () => {
    body.classList.remove('ad-developments-scroll')
    for (const s of saved) {
      // No pisar un estilo introducido posteriormente por otro componente.
      if (s.el.style.getPropertyValue('overflow-y') !== 'auto' || s.el.style.getPropertyPriority('overflow-y') !== 'important') continue
      if (s.y) s.el.style.setProperty('overflow-y',s.y,s.yp)
      else s.el.style.removeProperty('overflow-y')
    }
  }
})
adScrollUnmounted(() => { adScrollRestore?.(); adScrollRestore = null })

import AndarmaSharedPublicSections from '../../components/public/AndarmaSharedPublicSections.vue'
import '../../assets/andarma-map-fase3f.css'
import '../../assets/andarma-fase3h-reticula.css'
import '../../assets/andarma-fase3k-mapa-editorial.css' // ANDARMA_FASE3K_ESTILO_MAPA // ANDARMA_FASE3H_RETICULA

import {ref,onMounted,computed} from 'vue'
import {RouterLink} from 'vue-router'
import AndarmaPublicMap from '../../components/public/AndarmaPublicMap.vue'
import AndarmaDevelopmentHeroSlider from '../../components/public/AndarmaDevelopmentHeroSlider.vue'
const API=(import.meta.env.VITE_API_BASE||'/api').replace(/\/$/,'')
const developments=ref([]),selectedId=ref(null),loading=ref(true),error=ref('')
const current=computed(()=>developments.value.find(d=>Number(d.id)===Number(selectedId.value))||null)
async function load(){
 loading.value=true;error.value=''
 try{
  const [dr,mr]=await Promise.all([
   fetch(`${API}/public/developments`,{headers:{Accept:'application/json'}}),
   fetch(`${API}/public/maps`,{headers:{Accept:'application/json'}})
  ])
  if(!dr.ok||!mr.ok)throw new Error('No fue posible consultar los desarrollos y planos publicados.')
  const [dj,mj]=await Promise.all([dr.json(),mr.json()])
  const eligibleIds=new Set((Array.isArray(mj.data)?mj.data:[]).map(p=>Number(p.development_id)))
  developments.value=(Array.isArray(dj.data)?dj.data:[]).filter(d=>eligibleIds.has(Number(d.id)))
  if(!developments.value.some(d=>Number(d.id)===Number(selectedId.value)))selectedId.value=developments.value[0]?.id??null
 }catch(e){error.value=e.message}finally{loading.value=false}
}
onMounted(load)
import '../../assets/andarma-fase3n-v2-mapa-arquitectura.css' // ANDARMA_F3N_V2_MAPA_ARQUITECTURA
import '../../assets/andarma-fase3p-identidad-home.css' // ANDARMA_FASE3P_IDENTIDAD_HOME
</script>
<template>
<!-- ANDARMA_SHARED_PUBLIC_F3F -->
<div class="adp-map-page">
  <AndarmaDevelopmentHeroSlider :development="current" />
  <section id="mapa-de-terrenos" class="adp-map-page__content"><div v-if="loading" class="adp-map-message" role="status">Cargando desarrollos…</div><div v-else-if="error" class="adp-map-message" role="alert">{{error}} <button @click="load">Reintentar</button></div><div v-else-if="!developments.length" class="adp-map-message">Por el momento no hay desarrollos con planos disponibles para explorar. <RouterLink to="/#contacto">Solicitar información</RouterLink></div><template v-else>
    <div v-if="developments.length>1" class="adp-development-switch"><label for="adp-development-select">Selecciona desarrollo</label><select id="adp-development-select" v-model.number="selectedId"><option v-for="d in developments" :key="d.id" :value="d.id">{{d.name}}</option></select></div>
    <AndarmaPublicMap v-if="current" :key="current.id" :development="current"/>
      </template></section>  
</div>

<AndarmaSharedPublicSections :enhance="true" />
</template>
