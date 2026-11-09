<script setup>
import '../../assets/andarma-fase3j-hero-texto.css'
import '../../assets/andarma-fase3u-hero-contacto.css'
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
import '../../assets/andarma-fase3v-hero-home.css'
import '../../assets/andarma-fase3x-hero-centrado.css'
import '../../assets/andarma-fase3y-boton-fondo-editorial.css' // ANDARMA_FASE3Y_BOTON_FONDO
</script>
<template>
  <!-- ANDARMA_FASE3V_HERO_HOME: composicion exacta por zonas del hero editorial -->
  <section class="ad3a-hero ad3e-hero ad3v-hero" aria-label="Presentacion del desarrollo"
    @mouseenter="motion=false;stop()" @mouseleave="motion=true;start()"
    @focusin="motion=false;stop()" @focusout="motion=true;start()"
    @touchstart.passive="touchBegin" @touchend.passive="touchEnd">
    <div class="ad3a-hero__slides ad3v-hero__slides" aria-hidden="true">
      <Transition name="ad3a-photo">
        <div v-if="photos.length" :key="`${development?.id}-${index}`"
          class="ad3a-hero__photo"
          :style="{backgroundImage:`url(${JSON.stringify(photos[index])})`}"></div>
      </Transition>
      <div v-if="!photos.length" class="ad3a-hero__fallback"></div>
      <div class="ad3a-hero__shade ad3v-hero__shade"></div>
      <div class="ad3v-hero__grid" aria-hidden="true"></div>
    </div>

    <div class="ad3v-hero__shell">
      <div class="ad3v-hero__headline" :key="development?.id">
        <span class="ad3v-hero__eyebrow">{{ development?.location || 'ANDARMA / DESARROLLOS' }}</span>
        <h1 class="ad3v-hero__title">{{ development?.name || 'Nuestros desarrollos' }}</h1>
        <!-- ANDARMA_FASE3X_HERO_CENTRADO -->
        <p class="ad3x-hero__description">{{ development?.description || 'Conoce nuestros desarrollos y explora los terrenos disponibles.' }}</p>
        <a class="primary-cta mt-5" href="#mapa-de-terrenos">
          Explorar terrenos
          <i class="fa-solid fa-arrow-down" aria-hidden="true"></i>
        </a>

      </div>

      <div class="ad3v-hero__bottom">
        <div class="ad3v-hero__controls" v-if="photos.length > 1" aria-label="Controles de la galeria">
          <span class="ad3v-hero__count">{{ String(index+1).padStart(2,'0') }} <small>/ {{ String(photos.length).padStart(2,'0') }}</small></span>
          <div class="ad3a-hero__progress" aria-hidden="true"><span :key="index" :style="{'animationPlayState':paused?'paused':'running'}"></span></div>
          <button type="button" aria-label="Fotografia anterior" @click="prev"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
          <button type="button" aria-label="Fotografia siguiente" @click="go(index+1)"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
          <button type="button" :aria-label="paused?'Reanudar galeria':'Pausar galeria'" @click="togglePause"><i :class="paused?'fa-solid fa-play':'fa-solid fa-pause'" aria-hidden="true"></i></button>
        </div>
        <div v-else class="ad3v-hero__signature" aria-hidden="true">ANDARMA <span>/</span> ARQUITECTURA PARA VIVIR</div>

        <div class="ad3v-hero__feature">
          <p class="ad3v-hero__feature-text">{{ development?.description || 'Conoce los terrenos disponibles en nuestro desarrollo.' }}</p>
          <a class="ad3v-hero__feature-cta" href="#mapa-de-terrenos" aria-label="Explorar terrenos del desarrollo">
            <span>EXPLORAR<br>TERRENOS</span>
            <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>
