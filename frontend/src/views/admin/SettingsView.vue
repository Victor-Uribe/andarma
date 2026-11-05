<script setup>
import {ref,computed} from 'vue'
import {Settings2,Phone,Share2,MapPin,ShieldCheck,Info,ExternalLink} from 'lucide-vue-next'
const active=ref('contact')
const tabs=[{key:'contact',title:'Contacto',icon:Phone,help:'Teléfonos, WhatsApp y correo institucional'},{key:'social',title:'Redes sociales',icon:Share2,help:'Enlaces y perfiles oficiales'},{key:'location',title:'Ubicación y mapa',icon:MapPin,help:'Dirección y datos de ubicación'}]
const current=computed(()=>tabs.find(x=>x.key===active.value))
</script>
<template>
<section class="v28-heading"><div><span class="admin-kicker">ADMINISTRACIÓN DEL SITIO</span><h1>Configuración general</h1><p>Datos corporativos, canales de contacto y publicación.</p></div></section>
<div class="v28-settings-layout"><aside class="v28-settings-tabs"><button v-for="tab in tabs" :key="tab.key" :class="{active:active===tab.key}" @click="active=tab.key"><component :is="tab.icon" :size="17"/> {{tab.title}}</button></aside>
<section class="v28-panel v28-settings-content"><div class="v28-panel-head"><div><span class="admin-kicker">AJUSTES DEL SITIO</span><h2><component :is="current.icon" :size="19"/> {{current.title}}</h2><p>{{current.help}}</p></div></div><div class="v28-settings-note"><Info :size="21"/><div><strong>Configuración pendiente de persistencia</strong><p>Esta sección tiene navegación independiente, pero la API actual todavía no incluye campos ni endpoints para guardar contacto, redes y mapas. Por seguridad, no se presentan campos con un botón Guardar que no funcionaría.</p></div></div><div class="v28-settings-preview"><ShieldCheck :size="22"/><div><strong>Identidad ANDARMA protegida</strong><p>Se conservarán los colores, la tipografía y el diseño actual de la página pública. La futura edición solo afectará datos autorizados.</p></div></div><RouterLink class="admin-outline" to="/admin/contenido"><ExternalLink :size="16"/> Editar textos que ya están conectados</RouterLink></section></div>
</template>
