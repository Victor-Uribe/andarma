<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { api, post, setToken } from '../services/api'
import { LayoutDashboard, PanelsTopLeft, Image as ImageIcon, MapPinned, Grid2X2, MessageSquareQuote, UsersRound, Images, WalletCards, Settings2, LogOut, Menu, X, ExternalLink, ChevronRight, ShieldCheck, Search, PanelLeftClose, UserCircle2 } from 'lucide-vue-next'
// ANDARMA_V36_NAV
const sections=[
 {label:'Inicio',items:[{label:'Dashboard',url:'/admin/dashboard',icon:'fa-solid fa-gauge-high'}]},
 {label:'Gestión inmobiliaria',items:[
   {label:'Desarrollos',url:'/admin/desarrollos',icon:'fa-solid fa-building'},
   {label:'Propiedades',url:'/admin/propiedades',icon:'fa-solid fa-layer-group'},
   {label:'Mapas',url:'/admin/mapas',icon:'fa-solid fa-map'},
   {label:'Prospectos',url:'/admin/prospectos',icon:'fa-solid fa-user-plus'},
   {label:'Clientes',url:'/admin/clientes',icon:'fa-solid fa-users'},
   {label:'Pagos',url:'/admin/pagos',icon:'fa-solid fa-wallet'}
 ]},
 {label:'Página web',items:[
   {label:'Contenido web',url:'/admin/contenido',icon:'fa-solid fa-pen-to-square'},
   {label:'Banners',url:'/admin/banners',icon:'fa-regular fa-image'},
   {label:'Testimonios',url:'/admin/testimonios',icon:'fa-solid fa-comment-dots'}
 ]},
 {label:'Administración',items:[
   {label:'Usuarios',url:'/admin/usuarios',icon:'fa-solid fa-user-gear'},
   {label:'Roles y permisos',url:'/admin/permisos',icon:'fa-solid fa-shield-halved'},
   {label:'Configuración',url:'/admin/configuracion',icon:'fa-solid fa-gear'}
 ]}
]
const router=useRouter(),route=useRoute(),user=ref(null),open=ref(false),loggingOut=ref(false)
const active=computed(()=>sections.flatMap(x=>x.items).find(x=>x.url===route.path||(x.url==='/admin/mapas'&&route.path.startsWith('/admin/mapas/')))?.label||'Administración')
watch(()=>route.fullPath,()=>{open.value=false})
onMounted(async()=>{try{const r=await api('/admin/me');user.value=r.user||r.data?.user||r.data||null}catch{}})
async function logout(){if(loggingOut.value)return;loggingOut.value=true;try{await post('/admin/logout',{})}catch{}finally{setToken(null);loggingOut.value=false;router.replace('/admin/login')}}
</script>
<template>
<div class="admin-shell admin-v26 andarma-admin">
  <aside :class="['admin-sidebar',{'is-open':open}]" aria-label="Menú administrativo">
    <div class="admin-brand"><div class="admin-brand-mark">A<span class="brand-dot"></span></div><div class="admin-brand-text"><strong>ANDARMA</strong><small>GESTIÓN INMOBILIARIA</small></div><button class="admin-mobile-close" aria-label="Cerrar menú" @click="open=false"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
    <div class="admin-nav-scroll"><div v-for="section in sections" :key="section.label" class="admin-nav-section"><div class="admin-nav-label">{{section.label}}</div><nav class="admin-navigation"><RouterLink v-for="item in section.items" :key="item.url" :to="item.url"><i :class="item.icon" aria-hidden="true" style="font-size:16px;width:18px;text-align:center"></i><span>{{item.label}}</span><ChevronRight class="nav-arrow" :size="15"/></RouterLink></nav></div></div>
    <div class="admin-side-bottom"><a href="/" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Abrir sitio público</a><button :disabled="loggingOut" @click="logout"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Cerrar sesión</button></div>
  </aside>
  <button v-if="open" class="admin-scrim" aria-label="Cerrar menú" @click="open=false"></button>
  <div class="admin-main"><header class="admin-topbar"><div class="admin-top-start"><button class="admin-icon-btn admin-menu-btn" aria-label="Abrir menú" @click="open=!open"><i class="fa-solid fa-bars" aria-hidden="true"></i></button><div class="top-breadcrumb"><span>ANDARMA</span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i><strong>{{active}}</strong></div></div><div class="admin-top-right"><span class="admin-live-tag"><span class="admin-status-dot"></span> Panel seguro</span><div class="admin-profile"><div class="admin-avatar">{{(user?.name||'A').slice(0,1).toUpperCase()}}</div><div class="admin-profile-label"><strong>{{user?.name||'Administrador'}}</strong><small>Administrador</small></div></div></div></header><main id="admin-content" class="admin-workspace"><RouterView/></main><footer class="admin-footer"><span>© 2026 ANDARMA · Plataforma administrativa</span><span>Diseñado para administrar con claridad</span></footer></div>
</div>
</template>
