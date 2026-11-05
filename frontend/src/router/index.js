import { createRouter,createWebHistory } from 'vue-router'
import { getToken,api,setToken } from '../services/api'
import {setAccess,clearAccess,can} from '../utils/andarmaPermissions'
const routes=[
 {path:'/',name:'home',component:()=>import('../views/HomeView.vue')},
 {path:'/admin/login',name:'login',component:()=>import('../views/admin/LoginView.vue'),meta:{guest:true}},
 {path:'/admin',component:()=>import('../layouts/AdminLayout.vue'),meta:{requiresAuth:true},children:[
   {path:'',redirect:'/admin/dashboard'},
   {path:'dashboard',name:'dashboard',meta:{permission:'dashboard.view'},component:()=>import('../views/admin/DashboardView.vue')},
   {path:'contenido',redirect:'/admin/banners'},
   {path:'banners',name:'banners',meta:{permission:'banners.view'},component:()=>import('../views/admin/BannersView.vue')},
   {path:'desarrollos',name:'desarrollos',meta:{permission:'developments.view'},component:()=>import('../views/admin/DevelopmentsView.vue')},
   {path:'propiedades',name:'propiedades',meta:{permission:'properties.view'},component:()=>import('../views/admin/InventoryView.vue')},
   {path:'mapas',name:'mapas',meta:{permission:'maps.view'},component:()=>import('../views/admin/MapsView.vue')},
   {path:'mapas/:id',name:'mapa-detalle',meta:{permission:'maps.view'},component:()=>import('../views/admin/MapsView.vue')},
   {path:'lotes',redirect:'/admin/propiedades'},
   {path:'clientes',name:'clientes',meta:{permission:'clients.view'},component:()=>import('../views/admin/ClientsView.vue')},
   {path:'usuarios',name:'usuarios',meta:{permission:'users.view'},component:()=>import('../views/admin/UsersView.vue')},
   {path:'permisos',name:'permisos',meta:{permission:'roles.view'},component:()=>import('../views/admin/PermissionsView.vue')},
   {path:'testimonios',name:'testimonios',meta:{permission:'testimonials.view'},component:()=>import('../views/admin/TestimonialsView.vue')},
{path:'prospectos',name:'prospectos',meta:{permission:'prospects.view'},component:()=>import('../views/admin/ProspectsView.vue')},
   {path:'multimedia',name:'multimedia',meta:{permission:'media.view'},component:()=>import('../views/admin/MediaView.vue')},
   {path:'pagos',name:'pagos',meta:{permission:'payments.view'},component:()=>import('../views/admin/PaymentsView.vue')},
   {path:'configuracion',name:'configuracion',meta:{permission:'settings.view'},component:()=>import('../views/admin/SettingsView.vue')}
 ]},
 {path:'/:pathMatch(.*)*',redirect:'/'}
]
const router=createRouter({history:createWebHistory(),routes,scrollBehavior(to){if(to.hash)return {el:to.hash,behavior:'smooth'}; return {top:0}}})
router.beforeEach(async to=>{
 if(to.meta.requiresAuth){
   if(!getToken()) return {name:'login',query:{redirect:to.fullPath}}
   try{
      const response=await api('/admin/me');setAccess(response.user)
      const needed=to.meta.permission
      if(needed && !can(needed)){
        const choices=[['dashboard','dashboard.view'],['desarrollos','developments.view'],['propiedades','properties.view'],['mapas','maps.view'],['prospectos','prospects.view'],['clientes','clients.view'],['pagos','payments.view'],['banners','banners.view'],['testimonios','testimonials.view'],['usuarios','users.view'],['permisos','roles.view'],['configuracion','settings.view']]
        const first=choices.find(([,permission])=>can(permission))
        return first?{name:first[0]}:{name:'sin-acceso'}
      }
    }catch(e){if(e.status===401||e.status===403){setToken(null);clearAccess();return {name:'login',query:{redirect:to.fullPath}}}throw e}
 }
 if(to.meta.guest&&getToken())return '/admin/dashboard'
})
export default router
