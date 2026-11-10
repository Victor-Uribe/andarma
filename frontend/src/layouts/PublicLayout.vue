<script setup>
import { RouterView } from 'vue-router'
import AndarmaPublicNavbar from '../components/public/AndarmaPublicNavbar.vue'
import '../assets/andarma-public-interiors.css'
import '../assets/andarma-public-map.css'
import '../assets/andarma-public-ui-system-fase2a.css'
import '../assets/andarma-public-composicion-fase2b.css'
import '../assets/andarma-public-motion-fase2c.css'
import '../assets/andarma-public-responsive-fase3b.css'
</script>
<template>
  <div class="adp-page">
    <AndarmaPublicNavbar />
    <main class="adp-main" id="contenido-principal"><RouterView /></main>
    
  </div>
</template>
