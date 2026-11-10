// ANDARMA_TESTIMONIOS_FASE3C_V1
// Funcion compartida por Home y /desarrollos. No hace peticiones a la API.
const SVG_NS = 'http://www.w3.org/2000/svg'
const STAR_PATH = 'M12 2.75 14.86 8.55 21.26 9.48 16.63 14 17.73 20.34 12 17.34 6.27 20.34 7.37 14 2.74 9.48 9.14 8.55 12 2.75Z'

function imageUrl(value) {
  const url = String(value || '').trim()
  return /^https?:\/\//i.test(url) || (url.startsWith('/') && !url.startsWith('//')) ? url : ''
}

function initialsOf(value) {
  const words = String(value || '').trim().split(/\s+/).filter(Boolean)
  if (!words.length) return 'A'
  const text = words.length > 1
    ? words[0].slice(0, 1) + words[words.length - 1].slice(0, 1)
    : words[0].slice(0, 2)
  return text.toLocaleUpperCase('es-MX')
}

function makeInitialAvatar(name) {
  const element = document.createElement('span')
  element.className = 'story-avatar story-avatar--initials'
  element.textContent = initialsOf(name)
  element.setAttribute('role', 'img')
  element.setAttribute('aria-label', `Iniciales de ${name}`)
  return element
}

function makePropertyPlaceholder() {
  const element = document.createElement('div')
  element.className = 'story-photo story-photo--fallback'
  element.setAttribute('aria-hidden', 'true')
  const mark = document.createElement('span')
  mark.className = 'story-photo__mark'
  mark.textContent = 'ANDARMA'
  const caption = document.createElement('span')
  caption.className = 'story-photo__caption'
  caption.textContent = 'ESPACIOS PARA LO QUE VIENE'
  element.append(mark, caption)
  return element
}

function starSvg(filled) {
  const svg = document.createElementNS(SVG_NS, 'svg')
  svg.setAttribute('viewBox', '0 0 24 24')
  svg.setAttribute('aria-hidden', 'true')
  svg.classList.add('story-rating__star', filled ? 'is-filled' : 'is-empty')
  const path = document.createElementNS(SVG_NS, 'path')
  path.setAttribute('d', STAR_PATH)
  svg.appendChild(path)
  return svg
}

function setRating(card, value) {
  const element = card.querySelector('.story-rating')
  if (!element) return
  const numeric = Number(value)
  const rating = Number.isFinite(numeric) && numeric >= 1
    ? Math.min(5, Math.max(1, Math.round(numeric))) : 5
  const stars = Array.from({ length: 5 }, (_, index) => starSvg(index < rating))
  const score = document.createElement('span')
  score.className = 'story-rating__value'
  score.textContent = `${rating}/5`
  score.setAttribute('aria-hidden', 'true')
  element.replaceChildren(...stars, score)
  element.setAttribute('role', 'img')
  element.setAttribute('aria-label', `Calificación: ${rating} de 5 estrellas`)
}

function setAvatar(card, item, name) {
  const previous = card.querySelector('.story-avatar')
  if (!previous) return
  const url = imageUrl(item.image_url)
  if (!url) {
    previous.replaceWith(makeInitialAvatar(name))
    return
  }
  const avatar = document.createElement('img')
  avatar.className = 'story-avatar'
  avatar.src = url
  avatar.alt = `Fotografía de ${name}`
  avatar.loading = 'lazy'
  avatar.decoding = 'async'
  avatar.addEventListener('error', () => avatar.replaceWith(makeInitialAvatar(name)), { once: true })
  previous.replaceWith(avatar)
}

function setPropertyImage(card, item) {
  const previous = card.querySelector('.story-photo')
  if (!previous) return
  const url = imageUrl(item.property_image_url)
  if (!url) {
    previous.replaceWith(makePropertyPlaceholder())
    return
  }
  const image = document.createElement('img')
  image.className = 'story-photo'
  image.src = url
  image.alt = 'Fotografía del terreno o vivienda asociada al testimonio'
  image.loading = 'lazy'
  image.decoding = 'async'
  image.addEventListener('error', () => image.replaceWith(makePropertyPlaceholder()), { once: true })
  previous.replaceWith(image)
}

/** Renderiza las tarjetas publicadas con el mismo resultado en todas las rutas. */
export function renderPublicTestimonials(track, items) {
  if (!track || !Array.isArray(items) || !items.length) return 0
  const sample = track.querySelector('.story')
  if (!sample) return 0
  const fragment = document.createDocumentFragment()
  for (const item of items) {
    const card = sample.cloneNode(true)
    const name = String(item.name || 'Cliente ANDARMA').trim() || 'Cliente ANDARMA'
    const quote = card.querySelector('.story-quote')
    if (quote) quote.textContent = `“${String(item.quote || '').trim()}”`
    const nameElement = card.querySelector('.story-name')
    if (nameElement) nameElement.textContent = name
    const role = card.querySelector('.story-type')
    if (role) role.textContent = String(item.role || 'Cliente')
    setRating(card, item.rating)
    setAvatar(card, item, name)
    setPropertyImage(card, item)
    fragment.appendChild(card)
  }
  track.replaceChildren(fragment)
  const section = track.closest('#testimonios')
  const viewport = section?.querySelector('#storiesViewport')
  viewport?.setAttribute('aria-label', 'Testimonios de clientes ANDARMA')
  const note = section?.querySelector('.stories-note')
  if (note) note.textContent = 'Opiniones publicadas en ANDARMA. Las fotografías se muestran cuando están disponibles.'
  return items.length
}
