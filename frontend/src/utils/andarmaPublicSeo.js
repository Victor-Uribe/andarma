// ANDARMA_FASE4A_SEO_PREVIEW_V1
// Sitio temporal. No generar canonical/sitemap ni liberar la indexacion.
const HOME_TITLE = 'ANDARMA | Desarrollos inmobiliarios en el Estado de México'
const HOME_DESCRIPTION = 'Conoce los proyectos inmobiliarios de ANDARMA. Explora desarrollos, ubicaciones y planos de terrenos en el Estado de México.'
const DESARROLLOS_TITLE = 'Desarrollos y terrenos en el Estado de México | ANDARMA'
const DESARROLLOS_DESCRIPTION = 'Explora los desarrollos publicados de ANDARMA. Consulta ubicaciones, características y planos para conocer cada proyecto inmobiliario.'
const DETAIL_TITLE = 'Información del desarrollo | ANDARMA'
const DETAIL_DESCRIPTION = 'Consulta la ubicación, la información y el plano interactivo de los desarrollos inmobiliarios de ANDARMA.'

function getOrCreateMeta(attribute, key) {
  const selector = `meta[${attribute}="${key}"]`
  let element = document.head.querySelector(selector)
  if (!element) {
    element = document.createElement('meta')
    element.setAttribute(attribute, key)
    element.setAttribute('data-andarma-seo', 'fase4a')
    document.head.appendChild(element)
  }
  return element
}

function setName(name, content) {
  getOrCreateMeta('name', name).setAttribute('content', content)
}
function setProperty(property, content) {
  getOrCreateMeta('property', property).setAttribute('content', content)
}

function setDocumentMeta(title, description, admin = false) {
  document.title = title
  setName('description', description)
  // Indexacion bloqueada: el dominio ANDARMA actual es un entorno temporal.
  // Sitio publico principal indexable; admin y preview no indexables.
  const isProductionDomain = ['andarma.com.mx', 'www.andarma.com.mx'].includes(window.location.hostname)
  setName('robots', admin ? 'noindex, nofollow' : (isProductionDomain ? 'index, follow' : 'noindex, follow'))
  setProperty('og:type', 'website')
  setProperty('og:site_name', 'ANDARMA')
  setProperty('og:locale', 'es_MX')
  setProperty('og:title', title)
  setProperty('og:description', description)
  setName('twitter:card', 'summary_large_image')
  setName('twitter:title', title)
  setName('twitter:description', description)
  // ANDARMA_SEO_V2_CLIENT: metadatos al navegar sin recargar el sitio.
  // La vista previa del enlace al entrar directamente depende del HTML servido por PHP.
  const base = 'https://andarma.com.mx'
  const pathname = window.location.pathname.replace(/\/$/, '') || '/'
  let canonical = document.head.querySelector('link[rel="canonical"]')
  if (admin || !isProductionDomain) {
    canonical?.remove()
  } else {
    const canonicalUrl = base + (pathname === '/' ? '/' : pathname)
    if (!canonical) {
      canonical = document.createElement('link')
      canonical.rel = 'canonical'
      document.head.appendChild(canonical)
    }
    canonical.href = canonicalUrl
    setProperty('og:url', canonicalUrl)
    const image = pathname === '/desarrollos' ? '/og-andarma.jpg' : '/og-andarma.jpg'
    setProperty('og:image', base + image)
    setName('twitter:image', base + image)
    setProperty('og:image:alt', title)
  }

}

export function applyAndarmaSeo(route) {
  const path = route?.path || window.location.pathname
  if (path.startsWith('/admin')) {
    setDocumentMeta('Administración | ANDARMA', 'Acceso privado al sistema administrativo ANDARMA.', true)
    return
  }
  if (path === '/desarrollos') {
    setDocumentMeta(DESARROLLOS_TITLE, DESARROLLOS_DESCRIPTION)
    return
  }
  if (path.startsWith('/desarrollos/')) {
    // El titulo real se reemplaza al terminar de cargar la API del desarrollo.
    setDocumentMeta(DETAIL_TITLE, DETAIL_DESCRIPTION)
    return
  }
  setDocumentMeta(HOME_TITLE, HOME_DESCRIPTION)
}

export function updateAndarmaDevelopmentSeo(path, development) {
  if (!development || !path?.startsWith('/desarrollos/')) return
  // Evita que la respuesta asincrona de una vista anterior pise el titulo actual.
  if (window.location.pathname !== path) return
  const name = String(development.name || '').trim().slice(0, 90)
  const location = String(development.location || '').trim().slice(0, 100)
  if (!name) return
  const where = location ? ` en ${location}` : ''
  const description = `Conoce ${name}${where}. Explora la información del desarrollo y consulta su plano interactivo de terrenos en ANDARMA.`
  setDocumentMeta(`${name} | Desarrollos ANDARMA`, description)
  // En detalles dinamicos priorizar portada REAL del desarrollo publicada en la API.
  const image = String(development.image_url || development.cover_image_url || development.hero_image_url || '').trim()
  if (image) {
    try {
      const u = new URL(image, window.location.origin)
      if (u.protocol === 'https:' && ['andarma.com.mx','www.andarma.com.mx'].includes(u.hostname)) {
        setProperty('og:image', u.href)
        setName('twitter:image', u.href)
      }
    } catch (_err) { /* conservar imagen de respaldo */ }
  }

}

export function installAndarmaSeo(router) {
  router.afterEach((to, _from, failure) => {
    if (!failure) applyAndarmaSeo(to)
  })
  router.isReady().then(() => applyAndarmaSeo(router.currentRoute.value))
}
