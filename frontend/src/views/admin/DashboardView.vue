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
const totalViews7d=computed(()=>Array.isArray(analytics.value?.daily_views)?analytics.value.daily_views.reduce((acc,row)=>acc+Number(row?.views||0),0):0)
// ANDARMA_FASE6_UI_UX
async function load(){loading.value=true;error.value='';try{const [d,l,a]=await Promise.allSettled([api('/admin/dashboard'),api('/admin/lots'),api('/admin/analytics?period=7d')]);if(d.status==='fulfilled')data.value=d.value.data||{};else error.value=d.reason.message;if(l.status==='fulfilled'){const rows=l.value.data||[];data.value={...data.value,sold_lots:rows.filter(x=>x.status==='sold').length}}analytics.value=a.status==='fulfilled'?a.value.data||a.value:null}catch(e){error.value=e.message}finally{loading.value=false}}
onMounted(load)
const number=v=>v===null||v===undefined?'—':Number(v).toLocaleString('es-MX')
</script>
<template>
<div class="ad-dashboard-v4">
<section class="v28-heading"><div><span class="admin-kicker">CENTRO DE CONTROL</span><h1>Bienvenido a ANDARMA</h1><p>Actividad de tu inmobiliaria, inventario y accesos frecuentes.</p></div><button class="admin-outline" :disabled="loading" @click="load"><RefreshCw :size="16"/> Actualizar</button></section>
<div v-if="error" class="admin-alert error"><AlertCircle :size="18"/> {{error}}</div>
<section class="v28-metrics"><component :is="c.to?'RouterLink':'div'" v-for="c in cards" :key="c.label" :to="c.to||undefined" class="v28-metric"><div class="v28-metric-top"><span class="v28-metric-icon"><component :is="c.icon" :size="19"/></span><ArrowUpRight v-if="c.to" :size="16"/></div><div class="v28-number">{{loading?'…':number(c.value)}}</div><strong>{{c.label}}</strong><small>{{c.note}}</small></component></section>
<div class="v28-dual"><section class="v28-panel"><div class="v28-panel-head"><div><h2><ChartNoAxesColumn :size="19"/> Visitas de los últimos días</h2><p>Seguimiento de tráfico de la página pública</p></div><span class="v28-tag">Últimos 7 días</span></div><div v-if="Array.isArray(analytics?.daily_views)&&analytics.daily_views.length" class="v28-bars"><div v-for="(d,i) in analytics.daily_views" :key="i" class="v28-bar"><span :style="{height:Math.max(5,Math.round(Number(d.views||0)/Math.max(1,...analytics.daily_views.map(x=>Number(x.views||0)))*135))+'px'}"></span><small>{{d.day||d.date}}</small></div></div><div v-else class="v28-empty-chart"><div class="v28-empty-chart__metric"><small>Vistas (si hay datos)</small><strong>{{ Array.isArray(analytics?.daily_views) && analytics.daily_views.length ? number(totalViews7d) : String.fromCharCode(8212) }}</strong><span>Últimos 7 días</span></div><ChartNoAxesColumn :size="35"/><strong>Analítica pendiente de conexión</strong><p>La API actual no registra vistas ni clics de WhatsApp. Estos indicadores se mostrarán cuando Laravel proporcione estadísticas verificables.</p></div></section>
<section class="v28-panel"><div class="v28-panel-head"><div><h2><Clock3 :size="19"/> Accesos rápidos</h2><p>Las acciones habituales del administrador</p></div></div><div class="v28-shortcuts"><RouterLink v-for="item in shortcuts" :key="item.to" :to="item.to"><span class="v28-shortcut-icon"><component :is="item.icon" :size="18"/></span><span><strong>{{item.title}}</strong><small>{{item.desc}}</small></span><ArrowUpRight :size="16"/></RouterLink></div></section></div>
<section class="v28-panel v28-overview"><div class="v28-panel-head"><div><h2><CalendarDays :size="19"/> Resumen operativo</h2><p>Información disponible en ANDARMA</p></div></div><div class="v28-operations"><RouterLink to="/admin/propiedades"><House :size="22"/><span>Inventario</span><b>{{number(data.lots)}}</b><small>Propiedades y lotes</small></RouterLink><RouterLink to="/admin/banners"><Image :size="22"/><span>Banners</span><b>{{number(data.banners)}}</b><small>Elementos de portada</small></RouterLink><RouterLink to="/admin/testimonios"><MessageSquareQuote :size="22"/><span>Opiniones</span><b>{{number(data.testimonials)}}</b><small>Testimonios</small></RouterLink></div></section>
</div>
</template>

<style>
/* ANDARMA_FASE6_UI_UX · Dashboard */
.v28-dual{align-items:stretch}
.v28-panel{height:100%}
.v28-shortcuts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-content:start}
.v28-shortcuts>a,.v28-shortcuts>.v28-shortcut,.v28-shortcuts>.router-link-active{min-height:76px;display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-radius:14px}
.v28-shortcut__meta,.v28-shortcuts a>div:first-child{min-width:0}
.v28-shortcuts a strong{display:block;margin-bottom:4px}
.v28-shortcuts a small,.v28-shortcuts a p{display:block;line-height:1.35}
.v28-empty-chart{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;min-height:250px;text-align:center}
.v28-empty-chart__metric{display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:168px;padding:14px 18px;border:1px solid rgba(9,91,173,.14);background:linear-gradient(180deg,#f7fbff 0%,#eef5ff 100%);border-radius:18px;box-shadow:0 14px 30px rgba(18,52,86,.06)}
.v28-empty-chart__metric small{font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6c85a4}
.v28-empty-chart__metric strong{font-size:34px;line-height:1;color:#163a5f;margin:8px 0 6px;font-weight:800}
.v28-empty-chart__metric span{font-size:12px;color:#6b7f97}
@media (max-width: 980px){
  .v28-shortcuts{grid-template-columns:1fr}
}

/* ANDARMA_FASE4_DASHBOARD_LAYOUT */
.ad-dashboard-v4{
  display:flex;
  flex-direction:column;
  gap:20px;
  min-height:0;
}
.ad-dashboard-v4 .v28-heading,
.ad-dashboard-v4 .v28-metrics,
.ad-dashboard-v4 .v28-dual,
.ad-dashboard-v4 .v28-overview{margin:0}
.ad-dashboard-v4 .v28-metrics{margin-bottom:0}
.ad-dashboard-v4 .v28-dual{align-items:stretch}
.ad-dashboard-v4 .v28-overview{
  flex:none;
  display:block;
}
.ad-dashboard-v4 .v28-overview .v28-operations{
  align-items:stretch;
}
.ad-dashboard-v4 .v28-overview .v28-operations > a{
  min-height:138px;
}
.ad-dashboard-v4 .v28-dual > .v28-panel:last-child{
  display:flex;
  flex-direction:column;
  height:auto;
}
.ad-dashboard-v4 .v28-shortcuts{
  flex:1;
  grid-auto-rows:minmax(84px, 1fr);
  align-content:stretch;
}
.ad-dashboard-v4 .v28-shortcuts a{
  min-height:84px;
  width:100%;
  height:100%;
  box-sizing:border-box;
}
@media (max-width:1100px){
  .ad-dashboard-v4{min-height:0}
}

</style>


