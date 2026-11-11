<script setup>
import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import '../../assets/andarma.css'
import '../../assets/andarma-public-ui-system-fase2a.css'
import '../../assets/andarma-public-composicion-fase2b.css'
import '../../assets/andarma-public-motion-fase2c.css'
import '../../assets/andarma-public-responsive-fase3b.css'
import '../../assets/andarma-public-testimonios-fase3c.css'
import { useAndarmaPublicWhatsApp } from '../../composables/useAndarmaPublicWhatsApp'
import { renderPublicTestimonials } from '../../utils/andarmaPublicTestimonials'
const { whatsAppUrl } = useAndarmaPublicWhatsApp()
const route = useRoute()
const homeAnchor = id => route.path === '/' ? `#${id}` : `/#${id}`
const props = defineProps({ enhance: { type: Boolean, default: false } })
let requestController
let observer
let cleanup = []
const api = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
async function hydrateShared() {
  if (!props.enhance) return
  requestController = new AbortController()
  const signal = requestController.signal
  try {
    const res = await fetch(`${api}/public/site-content`, { signal, headers: { Accept: 'application/json' } })
    if (res.ok) {
      const body = await res.json(); const content = body.data || {}
      document.querySelectorAll('[data-andarma-content]').forEach(el => {
        const key = el.getAttribute('data-andarma-content')
        if (Object.prototype.hasOwnProperty.call(content, key)) el.textContent = content[key]
      })
    }
  } catch (err) { if (err.name !== 'AbortError') console.warn('Contenido comercial no disponible:', err) }
  try {
    const res = await fetch(`${api}/public/testimonials`, {
      signal, headers: { Accept: 'application/json' }
    })
    if (res.ok) {
      const body = await res.json()
      const items = Array.isArray(body.data) ? body.data : []
      if (items.length) renderPublicTestimonials(document.getElementById('storiesTrack'), items)
    }
  } catch (err) {
    if (err.name !== 'AbortError') console.warn('Testimonios no disponibles:', err)
  }
}
function enhanceShared() {
  if (!props.enhance) return
  const viewport=document.getElementById('storiesViewport'), track=document.getElementById('storiesTrack')
  const prev=document.getElementById('storiesPrev'), next=document.getElementById('storiesNext')
  if (viewport && track && prev && next) {
    const update=()=>{prev.disabled=viewport.scrollLeft<=3;next.disabled=viewport.scrollLeft+viewport.clientWidth>=viewport.scrollWidth-3}
    const move=dir=>{const card=track.querySelector('.story'); const width=card?.getBoundingClientRect().width||viewport.clientWidth;viewport.scrollBy({left:dir*(width+20),behavior:'smooth'})}
    const back=()=>move(-1), forward=()=>move(1)
    prev.addEventListener('click',back);next.addEventListener('click',forward);viewport.addEventListener('scroll',update,{passive:true})
    cleanup.push(()=>{prev.removeEventListener('click',back);next.removeEventListener('click',forward);viewport.removeEventListener('scroll',update)})
    update()
  }
  const backTop=document.getElementById('adBackTop')
  if (backTop) {const go=()=>window.scrollTo({top:0,behavior:'smooth'});backTop.addEventListener('click',go);cleanup.push(()=>backTop.removeEventListener('click',go))}
  const legalDialog=document.getElementById('adLegalDialog'), legalClose=document.getElementById('adLegalClose')
  // ANDARMA_FASE1A_LEGAL_INTERIORES: Home usa legacy.js; interiores usan Vue.
  const legalTitle=document.getElementById('adLegalTitle')
  const legalBody=document.getElementById('adLegalBody')
  if (legalDialog && legalTitle && legalBody) {
    const legal = {
      privacidad: {
        title: 'Aviso de privacidad',
        html: `<p class="ad-legal-dialog__warning"><strong>Documento pendiente de validación.</strong> Antes de publicar, ANDARMA debe facilitar los datos de la entidad responsable, domicilio, medios de contacto y prácticas reales de tratamiento de datos.</p>
        <p>El aviso definitivo deberá informar qué datos personales se recaban, con qué finalidades, cómo pueden ejercerse los derechos que correspondan y cómo se comunicarán las modificaciones.</p>
        <p>Esta ventana es un marcador de implementación y <strong>no sustituye un aviso de privacidad aprobado.</strong></p>`
      },
      terminos: {
        title: 'Términos y condiciones',
        html: `<p class="ad-legal-dialog__warning"><strong>Documento pendiente de aprobación.</strong> Deben confirmarse la entidad comercial, la información de los desarrollos, los alcances del sitio y las condiciones aplicables antes de publicar.</p>
        <p>La información de terrenos, viviendas, disponibilidad, precios y financiamiento debe verificarse en cada operación. El sitio no debe presentar condiciones contractuales no autorizadas.</p>
        <p>Este contenido es provisional y <strong>no constituye un contrato ni términos legales definitivos.</strong></p>`
      },
      cookies: {
        title: 'Política de cookies',
        html: `<p class="ad-legal-dialog__warning"><strong>Política por completar.</strong> Se requiere inventariar las cookies, almacenamiento local y servicios de terceros realmente usados en la versión publicada.</p>
        <p>Antes de habilitar analítica, publicidad o rastreo no esencial, deberán definirse la información y los controles de consentimiento adecuados.</p>
        <p>Este texto se incluye únicamente como referencia de implementación.</p>`
      }
    };
    for (const trigger of document.querySelectorAll('[data-legal]')) {
      const openLegal = () => {
        const doc=legal[trigger.dataset.legal]
        if (!doc) return
        legalTitle.textContent=doc.title
        legalBody.innerHTML=doc.html // Contenido estatico existente, no entrada de usuario.
        if (!legalDialog.open) legalDialog.showModal()
      }
      trigger.addEventListener('click', openLegal)
      cleanup.push(() => trigger.removeEventListener('click', openLegal))
    }
    const closeOnBackdrop = event => { if (event.target === legalDialog) legalDialog.close() }
    legalDialog.addEventListener('click', closeOnBackdrop)
    cleanup.push(() => legalDialog.removeEventListener('click', closeOnBackdrop))
  }
  if (legalDialog && legalClose) {const close=()=>legalDialog.close();legalClose.addEventListener('click',close);cleanup.push(()=>legalClose.removeEventListener('click',close))}
  const stages=[...document.querySelectorAll('.purchase-step,.stories-heading,.contact-feature__copy')]
  if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('ad-shared-revealed');observer.unobserve(e.target)}}),{threshold:.13})
    stages.forEach(el=>{el.classList.add('ad-shared-ready');observer.observe(el)})
  }
  // ANDARMA_FASE3U_HERO_CONTACTO: activar el reveal ya existente en andarma.css.
  // En Home se activa desde su propio script; en /desarrollos faltaba la señal.
  const contact = document.querySelector('#contacto.contact-feature')
  if (contact) {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (!reduced && 'IntersectionObserver' in window) {
      const alreadyMotion = document.documentElement.classList.contains('ad-contact-motion')
      document.documentElement.classList.add('ad-contact-motion')
      contact.classList.remove('ad-contact-visible')
      const contactObserver = new IntersectionObserver(entries => {
        if (entries.some(entry => entry.isIntersecting)) {
          contact.classList.add('ad-contact-visible')
          contactObserver.disconnect()
        }
      }, { threshold: .12 })
      contactObserver.observe(contact)
      cleanup.push(() => {
        contactObserver.disconnect()
        contact.classList.remove('ad-contact-visible')
        if (!alreadyMotion) document.documentElement.classList.remove('ad-contact-motion')
      })
    } else {
      contact.classList.add('ad-contact-visible')
    }
  }
  const year=document.getElementById('adFooterYear');if(year) year.textContent=String(new Date().getFullYear())
}
onMounted(async()=>{await nextTick();await hydrateShared();await nextTick();enhanceShared()})
onBeforeUnmount(()=>{requestController?.abort();observer?.disconnect();cleanup.forEach(fn=>fn());cleanup=[]})
</script>

<template>
<section aria-labelledby="purchaseHeading" class="purchase" id="como-comprar">
<div class="purchase-container">
<div class="purchase-top">
<div><span class="purchase-kicker">¿CÓMO PUEDO COMPRAR?</span><h2 class="purchase-heading" id="purchaseHeading">Un proceso sencillo<br/>y seguro</h2></div>
<p class="purchase-intro" data-andarma-content="como-comprar.p.1">Te acompañamos en cada paso, desde la elección del terreno o vivienda hasta la entrega de tu nuevo patrimonio.</p>
</div>
<div class="purchase-track">
<span aria-hidden="true" class="purchase-progress"></span>
<article class="purchase-step"><div class="purchase-step-head"><span aria-hidden="true" class="purchase-icon"><i class="fa-solid fa-magnifying-glass-location" aria-hidden="true"></i></span><span class="purchase-num">01</span></div><h3 data-andarma-content="como-comprar.h3.2">Conoce</h3><p data-andarma-content="como-comprar.p.3">Explora nuestros desarrollos y elige la opción ideal para tus necesidades.</p><span aria-hidden="true" class="purchase-arrow"><svg viewbox="0 0 24 24"><path d="M3 12h17m-7-7 7 7-7 7"></path></svg></span></article>
<article class="purchase-step"><div class="purchase-step-head"><span aria-hidden="true" class="purchase-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span><span class="purchase-num">02</span></div><h3 data-andarma-content="como-comprar.h3.4">Aparta</h3><p data-andarma-content="como-comprar.p.5">Reserva el terreno o vivienda que elegiste con un pago inicial.</p><span aria-hidden="true" class="purchase-arrow"><svg viewbox="0 0 24 24"><path d="M3 12h17m-7-7 7 7-7 7"></path></svg></span></article>
<article class="purchase-step"><div class="purchase-step-head"><span aria-hidden="true" class="purchase-icon"><i class="fa-solid fa-wallet" aria-hidden="true"></i></span><span class="purchase-num">03</span></div><h3 data-andarma-content="como-comprar.h3.6">Financiamiento</h3><p data-andarma-content="como-comprar.p.7">Conoce las opciones y condiciones de pago disponibles para tu proyecto.</p><span aria-hidden="true" class="purchase-arrow"><svg viewbox="0 0 24 24"><path d="M3 12h17m-7-7 7 7-7 7"></path></svg></span></article>
<article class="purchase-step"><div class="purchase-step-head"><span aria-hidden="true" class="purchase-icon"><i class="fa-solid fa-helmet-safety" aria-hidden="true"></i></span><span class="purchase-num">04</span></div><h3>Construcción <small style="font-size:.65em;font-weight:500">(viviendas)</small></h3><p data-andarma-content="como-comprar.p.8">En proyectos de vivienda, construimos con atención a la calidad y cada detalle.</p><span aria-hidden="true" class="purchase-arrow"><svg viewbox="0 0 24 24"><path d="M3 12h17m-7-7 7 7-7 7"></path></svg></span></article>
<article class="purchase-step"><div class="purchase-step-head"><span aria-hidden="true" class="purchase-icon"><i class="fa-solid fa-house-circle-check" aria-hidden="true"></i></span><span class="purchase-num">05</span></div><h3 data-andarma-content="como-comprar.h3.9">Tu patrimonio</h3><p data-andarma-content="como-comprar.p.10">Recibe tu terreno o vivienda y comienza una nueva etapa.</p></article>
</div><div class="purchase-foot">Nuestro equipo te orienta en cada etapa. Las condiciones pueden variar según el desarrollo y el tipo de propiedad.</div>
</div>
</section>
<section aria-labelledby="storiesTitle" class="stories" id="testimonios">
<div class="stories-shell">
<div class="stories-heading">
<span class="stories-kicker">HISTORIAS QUE NOS INSPIRAN</span>
<h2 class="stories-title" id="storiesTitle">Familias que<br/>confían en<br/><span>ANDARMA</span></h2>
<p class="stories-subnote" data-andarma-content="testimonios.p.1">Cada nueva etapa comienza con una decisión importante. Queremos acompañarte en ese camino.</p>
</div>
<div class="stories-right">
<div aria-label="Controles de testimonios" class="stories-toolbar">
<button aria-label="Testimonios anteriores" class="stories-arrow" disabled="" id="storiesPrev" type="button"><svg viewbox="0 0 24 24"><path d="M19 12H5m6-6-6 6 6 6"></path></svg></button>
<button aria-label="Testimonios siguientes" class="stories-arrow" id="storiesNext" type="button"><svg viewbox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"></path></svg></button>
</div>
<div role="region" aria-label="Comentarios ilustrativos de compradores" class="stories-viewport" id="storiesViewport" tabindex="0">
<div class="stories-track" id="storiesTrack">
<article class="story"><img alt="Familia observando un paisaje natural" class="story-photo" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1511895426328-dc8714191300?auto=format&amp;fit=crop&amp;w=720&amp;q=80"/><div class="story-content"><div aria-label="Calificación de 5 estrellas" class="story-rating"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg></div><p class="story-quote" data-andarma-content="testimonios.p.2">“Buscábamos un espacio donde imaginar nuestro futuro. Tener información clara hizo más sencilla nuestra elección.”</p><div class="story-person"><img alt="Retrato de compradora" class="story-avatar" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&amp;fit=crop&amp;w=160&amp;q=80"/><div><div class="story-name">Familia Ramírez</div><div class="story-type">Compra de terreno</div></div></div></div></article>
<article class="story"><img alt="Vivienda contemporánea con vista al exterior" class="story-photo" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=720&amp;q=80"/><div class="story-content"><div aria-label="Calificación de 5 estrellas" class="story-rating"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg></div><p class="story-quote" data-andarma-content="testimonios.p.3">“Para nosotros era importante conocer cada etapa del proceso y saber qué opciones teníamos disponibles.”</p><div class="story-person"><img alt="Retrato de comprador" class="story-avatar" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&amp;fit=crop&amp;w=160&amp;q=80"/><div><div class="story-name">Alejandra Torres</div><div class="story-type">Proyecto de vivienda</div></div></div></div></article>
<article class="story"><img alt="Entrega simbólica de una vivienda" class="story-photo" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1523217582562-09d0def993a6?auto=format&amp;fit=crop&amp;w=720&amp;q=80"/><div class="story-content"><div aria-label="Calificación de 5 estrellas" class="story-rating"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg></div><p class="story-quote" data-andarma-content="testimonios.p.4">“Elegir dónde construir es una decisión grande. Nos ayudó comparar las alternativas con calma.”</p><div class="story-person"><img alt="Retrato de compradora" class="story-avatar" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&amp;fit=crop&amp;w=160&amp;q=80"/><div><div class="story-name">Carlos Méndez</div><div class="story-type">Compra de terreno</div></div></div></div></article>
<article class="story"><img alt="Interior contemporáneo con iluminación natural" class="story-photo" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&amp;fit=crop&amp;w=720&amp;q=80"/><div class="story-content"><div aria-label="Calificación de 5 estrellas" class="story-rating"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m12 3.8 2.54 5.16 5.7.83-4.12 4.02.97 5.67L12 16.8 6.91 19.48l.97-5.67-4.12-4.02 5.7-.83L12 3.8Z"></path></svg></div><p class="story-quote" data-andarma-content="testimonios.p.5">“Lo que más valoramos fue poder conocer las características del proyecto antes de decidir.”</p><div class="story-person"><img alt="Retrato de compradora" class="story-avatar" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&amp;fit=crop&amp;w=160&amp;q=80"/><div><div class="story-name">Familia González</div><div class="story-type">Interés en vivienda</div></div></div></div></article>
</div>
</div>
<p class="stories-note" data-andarma-content="testimonios.p.6">* Textos demostrativos para visualizar la sección; sustituir por testimonios y fotografías autorizados de clientes reales antes de publicar.</p>
</div>
</div>
</section>
<section aria-labelledby="contactTitle" class="contact-feature" id="contacto">
<div class="contact-feature__media"><img alt="Arquitectura residencial contemporánea rodeada de jardines" class="contact-feature__image" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=2200&amp;q=85"/></div>
<div aria-hidden="true" class="contact-feature__tone"></div>
<div aria-hidden="true" class="contact-feature__edge"></div>
<div class="contact-feature__shell"><div class="contact-feature__copy">
<span class="contact-feature__kicker">HABLEMOS DE TU PROYECTO</span>
<h2 class="contact-feature__title" data-andarma-content="contacto.h2.1" id="contactTitle">Es momento de construir tu futuro</h2>
<p v-if="whatsAppUrl" class="contact-feature__intro" data-andarma-content="contacto.p.2">Escríbenos por WhatsApp y con gusto te asesoramos para encontrar el terreno o vivienda ideal.</p>
<p v-else class="contact-feature__intro">Conoce nuestros desarrollos y las opciones disponibles. Próximamente podrás contactar a nuestro equipo por WhatsApp.</p>
<a v-if="whatsAppUrl" aria-label="Abrir WhatsApp para consultar sobre ANDARMA" class="contact-feature__cta" :href="whatsAppUrl" target="_blank" rel="noopener noreferrer">
<i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
<span>Contactar por WhatsApp</span>
<svg aria-hidden="true" class="contact-feature__arrow" viewbox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"></path></svg>
</a>
<a v-else class="contact-feature__cta" href="/desarrollos"><span>Explorar desarrollos</span><svg aria-hidden="true" class="contact-feature__arrow" viewBox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"></path></svg></a>
<p v-if="!whatsAppUrl" class="contact-feature__note" role="status">El canal oficial de WhatsApp todavía no está configurado. Consulta nuestros desarrollos mientras se habilita la atención.</p>
</div></div>
</section>
<footer aria-label="Pie de página ANDARMA" class="ad-footer" id="pie-de-pagina">
<div aria-hidden="true" class="ad-footer__topline"></div>
<div class="ad-footer__inner">
<div class="ad-footer__grid">
<div class="ad-footer__identity">
<a aria-label="ANDARMA — regresar al inicio" class="ad-footer__logo" :href="homeAnchor('inicio')">
<img alt="" class="ad-footer__mark" height="58" loading="lazy" src="/andarma-logo-original.webp" width="68"/>
<span><span class="ad-footer__word">ANDARMA</span><span class="ad-footer__subword">DESARROLLOS</span></span>
</a>
<p class="ad-footer__description">Espacios para construir nuevas historias. Conoce nuestras opciones de terrenos y viviendas, y recibe acompañamiento en tu próximo proyecto.</p>
<div aria-hidden="true" class="ad-footer__rule"></div>
</div>
<nav aria-label="Enlaces del sitio">
<h2 class="ad-footer__heading">Explora</h2>
<ul class="ad-footer__links">
<li><a :href="homeAnchor('inicio')">Inicio</a></li>
<li><a :href="homeAnchor('nosotros')">Nosotros</a></li>
<li><a :href="homeAnchor('desarrollos')">Desarrollos</a></li>
<li><a :href="homeAnchor('opciones')">Viviendas y terrenos</a></li>
<li><a :href="homeAnchor('como-comprar')">Cómo comprar</a></li>
<li><a :href="homeAnchor('testimonios')">Testimonios</a></li>
<li><a :href="homeAnchor('contacto')">Contacto</a></li>
</ul>
</nav>
<nav aria-label="Información legal">
<h2 class="ad-footer__heading">Información</h2>
<ul class="ad-footer__links">
<li><button data-legal="privacidad" type="button">Aviso de privacidad</button></li>
<li><button data-legal="terminos" type="button">Términos y condiciones</button></li>
<li><button data-legal="cookies" type="button">Política de cookies</button></li>
</ul>
</nav>
<div>
<h2 class="ad-footer__heading">Síguenos</h2>
<div aria-label="Redes sociales pendientes de confirmar" class="ad-footer__socials">
<button aria-label="Facebook — enlace pendiente" disabled="" title="Cuenta oficial pendiente de confirmar" type="button"><svg aria-hidden="true" viewbox="0 0 24 24"><path d="M15.5 20V12h3l.5-4h-3.5V6c0-1.3.5-2 2-2H19V1h-2.8C12.8 1 11 3.1 11 6v2H8v4h3v8"></path></svg></button>
<button aria-label="Instagram — enlace pendiente" disabled="" title="Cuenta oficial pendiente de confirmar" type="button"><svg aria-hidden="true" viewbox="0 0 24 24"><rect height="18" rx="5" width="18" x="3" y="3"></rect><circle cx="12" cy="12" r="4"></circle><path d="M17.4 6.6h.01"></path></svg></button>
<button aria-label="TikTok — enlace pendiente" disabled="" title="Cuenta oficial pendiente de confirmar" type="button"><svg aria-hidden="true" viewbox="0 0 24 24"><path d="M14 3v10.3a4.6 4.6 0 1 1-4.6-4.6M14 3c.8 3.4 2.8 5.2 6 5.6"></path></svg></button>
</div>
<p class="ad-footer__social-note">Se activarán al confirmar los perfiles oficiales.</p>
</div>
</div>
<div class="ad-footer__base">
<span>© <span id="adFooterYear">2026</span> <strong>ANDARMA Desarrollos.</strong> Todos los derechos reservados.</span>
<button class="ad-footer__backtop" id="adBackTop" type="button">Volver al inicio <svg aria-hidden="true" viewbox="0 0 24 24"><path d="M12 20V5m-6 6 6-6 6 6"></path></svg></button>
</div>
</div>
</footer>
<dialog aria-labelledby="adLegalTitle" class="ad-legal-dialog" id="adLegalDialog">
<button aria-label="Cerrar ventana" class="ad-legal-dialog__close" id="adLegalClose" type="button">×</button>
<span class="ad-legal-dialog__eyebrow">ANDARMA / INFORMACIÓN LEGAL</span>
<h2 id="adLegalTitle"></h2>
<div id="adLegalBody"></div>
</dialog>
</template>
<style>
@media (prefers-reduced-motion: no-preference) {
  .ad-shared-ready { opacity: 0; transform: translateY(24px); transition: opacity .65s ease, transform .65s cubic-bezier(.2,.7,.2,1); }
  .ad-shared-ready.ad-shared-revealed { opacity: 1; transform: translateY(0); }
}
</style>
