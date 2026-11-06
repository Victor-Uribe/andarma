import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import './assets/admin.css'
import './assets/admin-premium.css'
import './assets/admin-sweetalert.css'
import '@fortawesome/fontawesome-free/css/all.min.css'
import './assets/admin-tailwind.css'
import './assets/admin-design-system.css'
import './assets/admin-listados-responsive-f3.css'
import './assets/admin-testimonials-v45.css'
import './assets/admin-inventory-maps.css'
import './assets/admin-developments-v36.css'
import './assets/admin-developments-v37.css'
import './assets/admin-v28.css'
import './assets/admin-fase4-dashboard-sidebar.css'
import './assets/admin-crm-unificado-v32.css'
import './assets/admin-fase1-responsive.css'
import './assets/admin-typography-unified.css'
import './assets/admin-accesos-metricas-f4b.css'
import './assets/admin-forms-unified-f5.css'
import './assets/admin-mapas-consulta-f7.css'
import './assets/admin-clientes-cobranza-f11.css'
import './assets/admin-visual-unificado-f1.css'
import './assets/admin-controles-consistentes-f1b.css'
import './assets/admin-listados-unificados-v1.css'
// ANDARMA_DESIGN_SYSTEM_V34

// ANDARMA_RBAC_V48: directiva opcional para controles administrativos
import './assets/admin-listados-detalle-f4b.css'
import {can} from './utils/andarmaPermissions'
const app=createApp(App)
app.directive('can',{mounted(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'},updated(el,b){el.hidden=!can(b.value);el.style.display=can(b.value)?'':'none'}})
app.use(router).mount('#app')


// ANDARMA_FASE2_TIPOGRAFIA: capa final común del panel

// ANDARMA_FASE11_CLIENTES_COBRANZA_UI

// ANDARMA_FASE1_VISUAL_20261105

// ANDARMA_FASE1B_CONTROLES_20261105
