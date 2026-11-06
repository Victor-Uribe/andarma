<script setup>
import '../../assets/andarma-fase3j-hero-texto.css'
import '../../assets/andarma-development-gallery.css'
import { ref, computed, watch, onUnmounted } from 'vue'
const props = defineProps({development:{type:Object,default:null}})
const API = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
const gallery=ref([]),index=ref(0),paused=ref(false),motion=ref(true)
let interval=null, requestId=0
const photos=computed(()=>{
  const list=gallery.value.map(i=>i.url).filter(Boolean)
  if(list.length)return list
  return props.development?.image_url?[props.development.image_url]:[]
})
const heroTitle=computed(()=>props.development?.name || 'Encuentra un lugar para construir tu futuro.')
function stop(){if(interval){clearInterval(interval);interval=null}}
function start(){stop();if(!paused.value && motion.value && photos.value.length>1){interval=setInterval(()=>next(),6500)}}
function go(n){if(photos.value.length<2)return;index.value=(n+photos.value.length)%photos.value.length;start()}
function next(){if(photos.value.length<2)return;index.value=(index.value+1)%photos.value.length}
function prev(){go(index.value-1)}
function togglePause(){paused.value=!paused.value;start()}
async function load(id){
  const token=++requestId;stop();gallery.value=[];index.value=0
  if(!id)return
  try{
    const r=await fetch(`${API}/public/development-gallery?development_id=${encodeURIComponent(id)}`,{headers:{Accept:'application/json'}})
    if(!r.ok) return
    const data=await r.json()
    if(token===requestId)gallery.value=Array.isArray(data.data)?data.data:[]
  }catch{/* Fotografía principal de respaldo si falla la galería. */}
  finally{if(token===requestId)start()}
}
watch(()=>props.development?.id,load,{immediate:true})
watch(()=>photos.value.length,()=>{if(index.value>=photos.value.length)index.value=0;start()})
onUnmounted(()=>{requestId++;stop()})
let touchStart=null
function touchBegin(e){touchStart=e.changedTouches?.[0]?.clientX??null}
function touchEnd(e){if(touchStart===null)return;const diff=(e.changedTouches?.[0]?.clientX??touchStart)-touchStart;touchStart=null;if(Math.abs(diff)>55)go(index.value+(diff<0?1:-1))}
</script>
<template>
  <!-- ANDARMA_FASE3E_HERO_PUBLICO: datos reales del desarrollo, mismo lenguaje institucional -->
  <section class="ad3a-hero ad3e-hero" aria-label="Presentación del desarrollo" @mouseenter="motion=false;stop()" @mouseleave="motion=true;start()" @focusin="motion=false;stop()" @focusout="motion=true;start()" @touchstart.passive="touchBegin" @touchend.passive="touchEnd">
    <div class="ad3a-hero__slides" aria-hidden="true">
      <Transition name="ad3a-photo">
        <div v-if="photos.length" :key="`${development?.id}-${index}`" class="ad3a-hero__photo" :style="{backgroundImage:`url(${JSON.stringify(photos[index])})`}"></div>
      </Transition>
      <div v-if="!photos.length" class="ad3a-hero__fallback"></div>
      <div class="ad3a-hero__shade"></div>
    </div>
    <div class="ad3a-hero__inner">
      <!-- ANDARMA_FASE3J_TEXTO_HERO: solo contenido editorial, sin tocar carrusel -->
      <div class="ad3j-hero__copy" :key="development?.id">
        <div class="ad3j-hero__kicker"><span aria-hidden="true"></span> DESARROLLOS INMOBILIARIOS</div>
        <h1 class="ad3j-hero__title">{{ development?.name || 'Nuestros desarrollos' }}</h1>
        <div v-if="development?.location" class="ad3j-hero__location">
          <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
          <span>{{ development.location }}</span>
        </div>
        <div class="ad3j-hero__message">
          <span class="ad3j-hero__message-line">El lugar donde comienza</span>
          <strong>tu próximo capítulo.</strong>
        </div>
        <p class="ad3j-hero__description">{{ development?.description || 'Conoce el proyecto, explora sus terrenos disponibles y encuentra el espacio ideal para tu futuro.' }}</p>
        <div class="ad3j-hero__actions">
          <a class="ad3j-hero__primary" href="#mapa-de-terrenos">
            <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i>
            <span>Explorar terrenos</span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </a>
          <a class="ad3j-hero__secondary" href="#como-comprar">
            <span>Cómo comprar</span>
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
    <div class="ad3a-hero__bottom">
      <div class="ad3e-hero__bottom-label"><span class="ad3e-hero__bottom-mark"></span><span>PROYECTOS QUE INSPIRAN</span></div>
      <div v-if="photos.length>1" class="ad3a-hero__nav" aria-label="Control del carrusel">
        <span class="ad3a-hero__count">{{String(index+1).padStart(2,'0')}} <i>/</i> {{String(photos.length).padStart(2,'0')}}</span>
        <div class="ad3a-hero__progress" aria-hidden="true"><span :key="index" :style="{'animationPlayState':paused?'paused':'running'}"></span></div>
        <button type="button" aria-label="Fotografía anterior" @click="prev"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
        <button type="button" aria-label="Fotografía siguiente" @click="go(index+1)"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
        <button type="button" :aria-label="paused?'Reanudar carrusel':'Pausar carrusel'" @click="togglePause"><i :class="paused?'fa-solid fa-play':'fa-solid fa-pause'" aria-hidden="true"></i></button>
      </div>
    </div>
  </section>
</template>
