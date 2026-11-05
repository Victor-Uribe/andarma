import { createRouter,createWebHistory } from 'vue-router'
import { getToken,api,setToken } from '../services/api'
const routes=[
 {path:'/',name:'home',component:()=>import('../views/HomeView.vue')},
 {path:'/admin/login',name:'login',component:()=>import('../views/admin/LoginView.vue'),meta:{guest:true}},
 {path:'/admin',component:()=>import('../layouts/AdminLayout.vue'),meta:{requiresAuth:true},children:[
   {path:'',redirect:'/admin/dashboard'},
   {path:'dashboard',name:'dashboard',component:()=>import('../views/admin/DashboardView.vue')},
   {path:'contenido',name:'contenido',component:()=>import('../views/admin/ContentView.vue')},
   {path:'banners',name:'banners',component:()=>import('../views/admin/ResourceView.vue'),props:{resource:'banners'}},
   {path:'desarrollos',name:'desarrollos',component:()=>import('../views/admin/DevelopmentsView.vue')},
   {path:'propiedades',name:'propiedades',component:()=>import('../views/admin/InventoryView.vue')},
   {path:'mapas',name:'mapas',component:()=>import('../views/admin/MapsView.vue')},
   {path:'mapas/:id',name:'mapa-detalle',component:()=>import('../views/admin/MapsView.vue')},
   {path:'lotes',redirect:'/admin/propiedades'},
   {path:'clientes',name:'clientes',component:()=>import('../views/admin/ClientsView.vue')},
   {path:'usuarios',name:'usuarios',component:()=>import('../views/admin/UsersView.vue')},
   {path:'permisos',name:'permisos',component:()=>import('../views/admin/PermissionsView.vue')},
   {path:'testimonios',name:'testimonios',component:()=>import('../views/admin/ResourceView.vue'),props:{resource:'testimonials'}},
{path:'prospectos',name:'prospectos',component:()=>import('../views/admin/ProspectsView.vue')},
   {path:'multimedia',name:'multimedia',component:()=>import('../views/admin/MediaView.vue')},
   {path:'pagos',name:'pagos',component:()=>import('../views/admin/PaymentsView.vue')},
   {path:'configuracion',name:'configuracion',component:()=>import('../views/admin/SettingsView.vue')}
 ]},
 {path:'/:pathMatch(.*)*',redirect:'/'}
]
const router=createRouter({history:createWebHistory(),routes,scrollBehavior(to){if(to.hash)return {el:to.hash,behavior:'smooth'}; return {top:0}}})
router.beforeEach(async to=>{
 if(to.meta.requiresAuth){
   if(!getToken()) return {name:'login',query:{redirect:to.fullPath}}
   try{await api('/admin/me')}catch(e){if(e.status===401||e.status===403){setToken(null);return {name:'login',query:{redirect:to.fullPath}}}throw e}
 }
 if(to.meta.guest&&getToken())return '/admin/dashboard'
})
export default router
