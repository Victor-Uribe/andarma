<script setup>
import {ref,watch} from 'vue'
import {useRoute,RouterLink} from 'vue-router'
import { updateAndarmaDevelopmentSeo } from '../../utils/andarmaPublicSeo'
import AndarmaPublicMap from '../../components/public/AndarmaPublicMap.vue'
const route=useRoute(),API=(import.meta.env.VITE_API_BASE||'/api').replace(/\/$/,'')
const development=ref(null),loading=ref(true),error=ref('')
async function load(){loading.value=true;error.value='';try{const r=await fetch(`${API}/public/developments`,{headers:{Accept:'application/json'}});if(!r.ok)throw new Error('No fue posible consultar el desarrollo.');const j=await r.json();development.value=(j.data||[]).find(d=>String(d.slug||d.id)===String(route.params.slug))||null;if(!development.value)error.value='Este desarrollo no está publicado.'}catch(e){error.value=e.message}finally{loading.value=false}}
watch(()=>route.params.slug,load,{immediate:true})
// Solo actualiza el SEO cuando el desarrollo solicitado ya fue cargado.
watch([()=>route.path,development,loading],([path,item,isLoading])=>{
  if(isLoading || !item) return
  if(String(item.slug || item.id)!==String(route.params.slug)) return
  updateAndarmaDevelopmentSeo(route.path, item)
})
</script>
<template><div class="adp-map-page"><section class="adp-map-hero" :style="development?.image_url?{'--adp-map-hero-image':`url('${String(development.image_url).replace(/'/g,'%27')}')`}:{}"><div class="adp-map-hero__inner"><RouterLink class="adp-back" to="/desarrollos"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Todos los desarrollos</RouterLink><span class="adp-kicker">ANDARMA / DESARROLLO</span><h1>{{development?.name||'Explora nuestros desarrollos'}}</h1><p>{{development?.location||'Selecciona un terreno en el plano interactivo.'}}</p></div></section><section class="adp-map-page__content"><div v-if="loading" class="adp-map-message">Cargando…</div><div v-else-if="error" role="alert" class="adp-map-message">{{error}} <RouterLink to="/desarrollos">Ver desarrollos</RouterLink></div><template v-else-if="development"><AndarmaPublicMap :development="development"/><div class="adp-map-bottom"><div><span class="adp-kicker">EL LUGAR PARA TU HISTORIA</span><h2>{{development.name}}</h2><p>{{development.description}}</p></div><RouterLink to="/#contacto" class="adp-button">Contactar al equipo <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></RouterLink></div></template></section></div></template>
