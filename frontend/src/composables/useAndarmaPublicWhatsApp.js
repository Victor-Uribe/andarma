// ANDARMA_FASE3G_IDENTIDAD · Solo configuración pública del servidor.
import { ref, onMounted } from 'vue'
let cached=null
let inflight=null
async function fetchSettings(){
  if(cached!==null)return cached
  if(!inflight){
    const api=(import.meta.env.VITE_API_BASE||'/api').replace(/\/$/,'')
    inflight=fetch(`${api}/public/settings`,{headers:{Accept:'application/json'}})
      .then(r=>r.ok?r.json():null).then(body=>{cached=body?.data||{};return cached})
      .catch(()=>({})).finally(()=>{inflight=null})
  }
  return inflight
}
export function useAndarmaPublicWhatsApp(){
  const whatsAppUrl=ref('')
  onMounted(async()=>{
    const settings=await fetchSettings()
    const raw=String(settings?.whatsapp||'')
    const digits=raw.replace(/\D/g,'')
    if(digits.length<10||digits.length>15)return
    const message=String(settings?.whatsapp_message||'Hola, me gustaría recibir información sobre los desarrollos de ANDARMA.')
    whatsAppUrl.value=`https://wa.me/${digits}?text=${encodeURIComponent(message)}`
  })
  return {whatsAppUrl}
}
