import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './assets/admin.css'
import './assets/admin-premium.css'
import './assets/admin-sweetalert.css'
// ANDARMA_DESIGN_SYSTEM_V34
import '@fortawesome/fontawesome-free/css/all.min.css'
import './assets/admin-tailwind.css'
import './assets/admin-design-system.css'
import './assets/admin-testimonials-v45.css'

import './assets/admin-inventory-maps.css'
import './assets/admin-developments-v36.css'
import './assets/admin-developments-v37.css'
// ANDARMA_RBAC_V48: directiva opcional para controles administrativos
import {can} from './utils/andarmaPermissions'
const app=createApp(App)
app.directive('can',{mounted(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'},updated(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'}})
app.use(router).mount('#app')

import './assets/admin-v28.css'
import './assets/admin-crm-unificado-v32.css'
