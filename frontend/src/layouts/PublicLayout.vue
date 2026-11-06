<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import '../assets/andarma-public-interiors.css'
import '../assets/andarma-public-map.css'
const route = useRoute()
const menuOpen = ref(false)
const scrolled = ref(false)
function onScroll(){ scrolled.value = window.scrollY > 48 }
function onKeydown(event){ if(event.key === 'Escape') menuOpen.value = false }
watch(() => route.fullPath, () => { menuOpen.value = false })
onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, {passive:true})
  window.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  window.removeEventListener('scroll', onScroll)
  window.removeEventListener('keydown', onKeydown)
})
</script>
<template>
  <div class="adp-page">
    <header class="adp-navbar" :class="{'adp-navbar--scrolled':scrolled,'adp-navbar--open':menuOpen}">
      <div class="adp-navbar__inner">
        <RouterLink class="adp-brand" to="/" aria-label="ANDARMA, ir al inicio" @click="menuOpen=false">
          <span class="adp-brand__symbol" aria-hidden="true">
            <img class="adp-brand__white" src="/andarma-logo-blanco.png" alt="" />
            <img class="adp-brand__blue" src="/andarma-logo-original.png" alt="" />
          </span>
          <span class="adp-brand__name"><strong>ANDARMA</strong><small>DESARROLLOS</small></span>
        </RouterLink>
        <nav class="adp-nav" aria-label="Navegación principal">
          <RouterLink to="/">Inicio</RouterLink>
          <RouterLink to="/#nosotros">Nosotros</RouterLink>
          <RouterLink to="/desarrollos" :aria-current="route.path.startsWith('/desarrollos') ? 'page' : undefined">Desarrollos</RouterLink>
          <RouterLink to="/#como-comprar">Cómo comprar</RouterLink>
          <RouterLink to="/#contacto">Contacto</RouterLink>
        </nav>
        <RouterLink class="adp-contact" to="/#contacto">Hablemos de tu proyecto <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></RouterLink>
        <button type="button" class="adp-mobile-toggle" aria-controls="adpMobileNav" :aria-expanded="menuOpen" :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'" @click="menuOpen=!menuOpen">
          <i :class="menuOpen?'fa-solid fa-xmark':'fa-solid fa-bars'" aria-hidden="true"></i>
        </button>
      </div>
      <nav id="adpMobileNav" class="adp-mobile-nav" :class="{'is-open':menuOpen}" aria-label="Menú móvil" :inert="!menuOpen">
        <RouterLink to="/" @click="menuOpen=false">Inicio</RouterLink>
        <RouterLink to="/#nosotros" @click="menuOpen=false">Nosotros</RouterLink>
        <RouterLink to="/desarrollos" @click="menuOpen=false">Desarrollos</RouterLink>
        <RouterLink to="/#como-comprar" @click="menuOpen=false">Cómo comprar</RouterLink>
        <RouterLink to="/#contacto" @click="menuOpen=false">Contacto</RouterLink>
      </nav>
    </header>
    <main class="adp-main" id="contenido-principal"><RouterView /></main>
    <footer class="adp-footer">
      <div><strong>ANDARMA</strong><span>Espacios para construir nuevas historias.</span></div>
      <RouterLink to="/">Volver al inicio <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></RouterLink>
      <small>© {{new Date().getFullYear()}} ANDARMA</small>
    </footer>
  </div>
</template>
