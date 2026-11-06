// ANDARMA_ANALYTICS_F1_20261105
// Mide cargas de la portada y clics reales en enlaces externos de WhatsApp.
// No usa cookies ni envía datos personales.
const ANALYTICS_API = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
const WHATSAPP_HOSTS = new Set(['wa.me', 'api.whatsapp.com', 'web.whatsapp.com', 'www.whatsapp.com', 'whatsapp.com'])
function reportAnalytics(kind) {
  try {
    const url = `${ANALYTICS_API}/public/analytics/${kind}`
    fetch(url, { method: 'POST', keepalive: true, headers: {Accept:'application/json'} }).catch(() => {})
  } catch (_) { /* Nunca bloquear la navegación pública */ }
}
export function trackHomeVisit() {
  // Evita doble contabilización por re-montaje inmediato de Vue, sin cookies.
  try {
    const key = 'andarma_home_view_last_at'
    const now = Date.now()
    const last = Number(sessionStorage.getItem(key) || 0)
    if (now - last < 30000) return
    sessionStorage.setItem(key, String(now))
  } catch (_) { /* Sin acceso a sessionStorage: registrar vista normalmente */ }
  reportAnalytics('visit')
}
export function trackWhatsAppClick(event) {
  const anchor = event.target?.closest?.('a[href]')
  if (!anchor) return
  try {
    const url = new URL(anchor.href, window.location.href)
    if (url.protocol !== 'https:' || !WHATSAPP_HOSTS.has(url.hostname.toLowerCase())) return
    reportAnalytics('whatsapp')
  } catch (_) { /* Enlace no válido */ }
}
