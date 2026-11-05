export const API_BASE = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
const TOKEN_KEY = 'andarma_admin_token'
export const getToken = () => sessionStorage.getItem(TOKEN_KEY)
export const setToken = token => token ? sessionStorage.setItem(TOKEN_KEY, token) : sessionStorage.removeItem(TOKEN_KEY)
export async function api(path, opts={}) {
  const headers={Accept:'application/json',...opts.headers}
  const token=getToken()
  if(token) headers.Authorization=`Bearer ${token}`
  const isForm=opts.body instanceof FormData
  if(opts.body && !isForm) headers['Content-Type']='application/json'
  const response=await fetch(`${API_BASE}${path}`,{...opts,headers})
  const json=await response.json().catch(()=>({}))
  if(!response.ok){
    const details=json.errors ? Object.values(json.errors).flat().join(' · ') : ''
    const err=new Error(details || json.message || `Error HTTP ${response.status}`)
    // ANDARMA_FASE17_TOKEN_SEGURO: un 401 en una ruta protegida invalida
    // el token local. Nunca borrar sesion por un 403 (permisos insuficientes).
    if (response.status === 401 && path.startsWith('/admin/') && path !== '/admin/login' && token) {
      // Evitar que una respuesta antigua de una solicitud invalide un nuevo login.
      if (getToken() === token) {
        setToken(null)
        if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/admin/login')) {
          const current = window.location.pathname + window.location.search + window.location.hash
          const redirect = current.startsWith('/admin/') ? current : '/admin/dashboard'
          window.location.replace('/admin/login?expired=1&redirect=' + encodeURIComponent(redirect))
        }
      }
    }
    err.status=response.status; throw err
  }
  return json
}
export const post=(path,body)=>api(path,{method:'POST',body:body instanceof FormData?body:JSON.stringify(body)})
export const put=(path,body)=>api(path,{method:'PUT',body:JSON.stringify(body)})
export const remove=path=>api(path,{method:'DELETE'})
