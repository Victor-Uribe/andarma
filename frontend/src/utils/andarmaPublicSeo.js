// ANDARMA_FASE4A_SEO_PREVIEW_V1
// Sitio temporal. No generar canonical/sitemap ni liberar la indexacion.
const HOME_TITLE = 'ANDARMA | Espacios para lo que viene'
const HOME_DESCRIPTION = 'Conoce los desarrollos inmobiliarios de ANDARMA, terrenos y opciones de vivienda para construir tu siguiente etapa.'
const DESARROLLOS_TITLE = 'Desarrollos y terrenos | ANDARMA'
const DESARROLLOS_DESCRIPTION = 'Explora los desarrollos de ANDARMA, consulta los planos de terrenos y conoce las opciones disponibles para tu patrimonio.'
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
  setName('robots', admin ? 'noindex, nofollow' : 'noindex, follow')
  setProperty('og:type', 'website')
  setProperty('og:site_name', 'ANDARMA')
  setProperty('og:locale', 'es_MX')
  setProperty('og:title', title)
  setProperty('og:description', description)
  setName('twitter:card', 'summary')
  setName('twitter:title', title)
  setName('twitter:description', description)
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
}

export function installAndarmaSeo(router) {
  router.afterEach((to, _from, failure) => {
    if (!failure) applyAndarmaSeo(to)
  })
  router.isReady().then(() => applyAndarmaSeo(router.currentRoute.value))
}
