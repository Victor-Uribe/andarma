<script setup>
// ANDARMA_FASE3G_FIX_CARGADOR_ORIGINAL
// El diseño y la animación del cargador pertenecen a HomeView.vue y andarma.css.
// Las anclas (#seccion) NO cuentan como cambios de interior.
import { ref, watch, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import './assets/andarma-navigation.css'
const route = useRoute()
const showLoader = ref(false)
let loaderTimer = null
const publicInterior = (path) => path.startsWith('/desarrollos')
function clearLoaderTimer() {
  if (loaderTimer !== null) { clearTimeout(loaderTimer); loaderTimer = null }
}
watch(() => route.path, (newPath) => {
  clearLoaderTimer()
  // Home tiene su cargador ORIGINAL, gestionado por andarma-legacy.js.
  // Solo mostramos esta copia para Desarrollos e interiores públicos.
  showLoader.value = publicInterior(newPath)
  if (showLoader.value) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    loaderTimer = window.setTimeout(() => {
      showLoader.value = false
      loaderTimer = null
    }, reduceMotion ? 180 : 1150)
  }
}, { immediate: true })
onBeforeUnmount(clearLoaderTimer)
</script>
<template>
  <RouterView />
  <Transition name="ad-original-loader-exit">
    <div v-if="showLoader" aria-label="Cargando ANDARMA" aria-live="polite" class="brand-loader" id="adRouteBrandLoader" role="status">
    <div aria-hidden="true" class="brand-loader__grid"></div>
    <div class="brand-loader__center">
    <div class="brand-loader__mark"><img alt="" height="80" src="/andarma-logo-original.webp" width="152"/></div>
    <div class="brand-loader__name">ANDARMA</div>
    <div class="brand-loader__sub">D E S A R R O L L O S</div>
    <div aria-hidden="true" class="brand-loader__track"><span></span></div>
    <div class="brand-loader__number">ESPACIOS PARA LO QUE VIENE <span>·</span> MÉXICO</div>
    </div>
    <div aria-hidden="true" class="brand-loader__corner brand-loader__corner--top">01 / 03</div>
    <div aria-hidden="true" class="brand-loader__corner brand-loader__corner--bottom">ANDARMA © 2026</div>
    </div>
  </Transition>
</template>
