import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './assets/admin.css'
import '@fortawesome/fontawesome-free/css/all.min.css'
import './assets/admin-tailwind.css'
// ANDARMA_DESIGN_SYSTEM_V34

// ANDARMA_RBAC_V48: directiva opcional para controles administrativos
import {can} from './utils/andarmaPermissions'
const app=createApp(App)
app.directive('can',{mounted(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'},updated(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'}})
app.use(router).mount('#app')


// ANDARMA_FASE2_TIPOGRAFIA: capa final común del panel

// ANDARMA_FASE11_CLIENTES_COBRANZA_UI

// ANDARMA_FASE1_VISUAL_20261105

// ANDARMA_FASE1B_CONTROLES_20261105
