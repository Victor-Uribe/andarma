import {reactive} from 'vue'
export const access=reactive({user:null,permissions:[],loaded:false})
export function setAccess(user){access.user=user||null;access.permissions=user?.permissions||[];access.loaded=true}
export function clearAccess(){access.user=null;access.permissions=[];access.loaded=false}
export function can(permission){return !!permission && access.permissions.includes(permission)}
export function canAny(prefix){return access.permissions.some(p=>p.startsWith(prefix+'.'))}
