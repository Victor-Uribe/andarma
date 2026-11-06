<script setup>
import { RouterView } from 'vue-router'
import AndarmaPublicNavbar from '../components/public/AndarmaPublicNavbar.vue'
import '../assets/andarma-public-interiors.css'
import '../assets/andarma-public-map.css'
</script>
<template>
  <div class="adp-page">
    <AndarmaPublicNavbar />
    <main class="adp-main" id="contenido-principal"><RouterView /></main>
    
  </div>
</template>
