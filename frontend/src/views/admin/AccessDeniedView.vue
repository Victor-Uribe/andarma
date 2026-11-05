<script setup>
import {ShieldAlert, ArrowLeft} from 'lucide-vue-next'
</script>
<template>
  <section class="ad-access-denied" role="status">
    <div class="ad-access-denied__icon"><ShieldAlert :size="30" aria-hidden="true" /></div>
    <span class="admin-kicker">ADMINISTRACIÓN / PERMISOS</span>
    <h1>Acceso restringido</h1>
    <p>Tu cuenta no tiene permisos para consultar los módulos del administrador. Solicita acceso a la persona responsable del sistema.</p>
    <RouterLink class="ad-btn ad-btn--primary" to="/"><ArrowLeft :size="16" /> Volver al sitio público</RouterLink>
  </section>
</template>
