<script setup>
import {ref,computed,onMounted} from 'vue'
import {api} from '../../services/api'
import {Eye,MousePointerClick,Users,CalendarDays,House,MessageSquareQuote,CheckCircle2,Building2,RefreshCw,ArrowUpRight,Plus,Wallet,Clock3,ChartNoAxesColumn,AlertCircle,UserRoundPlus,Image} from 'lucide-vue-next'
const data=ref({}), analytics=ref(null),loading=ref(false),error=ref('')
const cards=computed(()=>[
 {label:'Vistas de la página',value:analytics.value?.page_views,icon:Eye,note:'Visitas registradas',to:null},
 {label:'Clics en WhatsApp',value:analytics.value?.whatsapp_clicks,icon:MousePointerClick,note:'Interacciones comerciales',to:null},
 {label:'Usuarios',value:data.value.users,icon:Users,note:'Cuentas del sistema',to:'/admin/usuarios'},
 {label:'Prospectos',value:data.value.leads,icon:UserRoundPlus,note:'Contactos recibidos',to:'/admin/prospectos'},
 {label:'Propiedades activas',value:data.value.available_lots,icon:House,note:'Lotes disponibles',to:'/admin/propiedades'},
 {label:'Propiedades vendidas',value:data.value.sold_lots,icon:CheckCircle2,note:'Lotes vendidos',to:'/admin/propiedades'},
 {label:'Testimonios',value:data.value.testimonials,icon:MessageSquareQuote,note:'Reseñas registradas',to:'/admin/testimonios'},
 {label:'Desarrollos',value:data.value.developments,icon:Building2,note:'Proyectos inmobiliarios',to:'/admin/desarrollos'}
])
const shortcuts=[{title:'Nueva propiedad',desc:'Consultar y registrar lotes',to:'/admin/propiedades',icon:Plus},{title:'Nuevo prospecto',desc:'Capturar un contacto',to:'/admin/prospectos',icon:UserRoundPlus},{title:'Actualizar banners',desc:'Editar la portada',to:'/admin/banners',icon:Image},{title:'Ver cobranza',desc:'Movimientos y periodos',to:'/admin/pagos',icon:Wallet}]
async function load(){loading.value=true;error.value='';try{const [d,l,a]=await Promise.allSettled([api('/admin/dashboard'),api('/admin/lots'),api('/admin/analytics?period=7d')]);if(d.status==='fulfilled')data.value=d.value.data||{};else error.value=d.reason.message;if(l.status==='fulfilled'){const rows=l.value.data||[];data.value={...data.value,sold_lots:rows.filter(x=>x.status==='sold').length}}analytics.value=a.status==='fulfilled'?a.value.data||a.value:null}catch(e){error.value=e.message}finally{loading.value=false}}
onMounted(load)
const number=v=>v===null||v===undefined?'—':Number(v).toLocaleString('es-MX')
</script>
<template>
<section class="v28-heading"><div><span class="admin-kicker">CENTRO DE CONTROL</span><h1>Bienvenido a ANDARMA</h1><p>Actividad de tu inmobiliaria, inventario y accesos frecuentes.</p></div><button class="admin-outline" :disabled="loading" @click="load"><RefreshCw :size="16"/> Actualizar</button></section>
<div v-if="error" class="admin-alert error"><AlertCircle :size="18"/> {{error}}</div>
<section class="v28-metrics"><component :is="c.to?'RouterLink':'div'" v-for="c in cards" :key="c.label" :to="c.to||undefined" class="v28-metric"><div class="v28-metric-top"><span class="v28-metric-icon"><component :is="c.icon" :size="19"/></span><ArrowUpRight v-if="c.to" :size="16"/></div><div class="v28-number">{{loading?'…':number(c.value)}}</div><strong>{{c.label}}</strong><small>{{c.note}}</small></component></section>
<div class="v28-dual"><section class="v28-panel"><div class="v28-panel-head"><div><h2><ChartNoAxesColumn :size="19"/> Visitas de los últimos días</h2><p>Seguimiento de tráfico de la página pública</p></div><span class="v28-tag">Últimos 7 días</span></div><div v-if="Array.isArray(analytics?.daily_views)&&analytics.daily_views.length" class="v28-bars"><div v-for="(d,i) in analytics.daily_views" :key="i" class="v28-bar"><span :style="{height:Math.max(5,Math.round(Number(d.views||0)/Math.max(1,...analytics.daily_views.map(x=>Number(x.views||0)))*135))+'px'}"></span><small>{{d.day||d.date}}</small></div></div><div v-else class="v28-empty-chart"><ChartNoAxesColumn :size="35"/><strong>Analítica pendiente de conexión</strong><p>La API actual no registra vistas ni clics de WhatsApp. Estos indicadores se mostrarán cuando Laravel proporcione estadísticas verificables.</p></div></section>
<section class="v28-panel"><div class="v28-panel-head"><div><h2><Clock3 :size="19"/> Accesos rápidos</h2><p>Las acciones habituales del administrador</p></div></div><div class="v28-shortcuts"><RouterLink v-for="item in shortcuts" :key="item.to" :to="item.to"><span class="v28-shortcut-icon"><component :is="item.icon" :size="18"/></span><span><strong>{{item.title}}</strong><small>{{item.desc}}</small></span><ArrowUpRight :size="16"/></RouterLink></div></section></div>
<section class="v28-panel v28-overview"><div class="v28-panel-head"><div><h2><CalendarDays :size="19"/> Resumen operativo</h2><p>Información disponible en ANDARMA</p></div></div><div class="v28-operations"><RouterLink to="/admin/propiedades"><House :size="22"/><span>Inventario</span><b>{{number(data.lots)}}</b><small>Propiedades y lotes</small></RouterLink><RouterLink to="/admin/banners"><Image :size="22"/><span>Banners</span><b>{{number(data.banners)}}</b><small>Elementos de portada</small></RouterLink><RouterLink to="/admin/testimonios"><MessageSquareQuote :size="22"/><span>Opiniones</span><b>{{number(data.testimonials)}}</b><small>Testimonios</small></RouterLink></div></section>
</template>
