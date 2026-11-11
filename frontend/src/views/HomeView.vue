<script setup>
import AndarmaSharedPublicSections from '../components/public/AndarmaSharedPublicSections.vue'
import AndarmaPublicNavbar from '../components/public/AndarmaPublicNavbar.vue'
import { useAndarmaPublicWhatsApp } from '../composables/useAndarmaPublicWhatsApp'
import { renderPublicTestimonials } from '../utils/andarmaPublicTestimonials'
import { nextTick, onMounted, onBeforeUnmount, ref } from 'vue'
import '../assets/andarma.css'
import '../assets/andarma-public-ui-system-fase2a.css'
import '../assets/andarma-public-composicion-fase2b.css'
import '../assets/andarma-public-motion-fase2c.css'
import '../assets/andarma-public-responsive-fase3b.css'
import { trackHomeVisit, trackWhatsAppClick } from '../utils/andarmaAnalytics'

const { whatsAppUrl } = useAndarmaPublicWhatsApp()
const API = (import.meta.env.VITE_API_BASE || '/api').replace(/\/$/, '')
let legacyScript
let controller
async function hydrateHeroBanners() {
  try {
    const response = await fetch(`${API}/public/banners`, {signal:controller.signal,headers:{Accept:'application/json'}})
    if (!response.ok) return
    const payload = await response.json()
    const banners = (Array.isArray(payload.data)?payload.data:[]).filter(b=>b?.is_active && b.image_url && b.title)
    if (!banners.length) return // El diseño inicial sigue funcionando si todavía no hay banners.
    const section = document.querySelector('#inicio.hero')
    const originals = [...(section?.querySelectorAll('.hero-slide') || [])]
    if (!section || !originals.length) return
    const slides = banners.map(b=>({
      eyebrow:b.eyebrow||'', line1:b.title, line2:b.title_accent||'',
      description:b.subtitle||'',cta:b.button_text||'',href:b.button_url === '#desarrollos' ? '/desarrollos' : (b.button_url || '/desarrollos'),
      overline:b.overline||'',footnote:b.footnote||'',tagline:b.tagline||'',
      editorial_label:b.editorial_label||'',side_title:b.side_title||'',side_description:b.side_description||''
    }))
    const anchor = originals[0]
    for (const [bannerIndex, b] of banners.entries()) {
      const panel=document.createElement('div')
      panel.className='hero-slide'
      panel.setAttribute('aria-hidden','true')
      const img=document.createElement('img')
      img.className='hero-image';img.alt=b.title
      img.src=b.image_url;img.decoding='async';img.loading=bannerIndex===0?'eager':'lazy'
      if (bannerIndex===0) img.fetchPriority='high'
      const shade=document.createElement('div');shade.className='hero-shade'
      panel.append(img,shade)
      anchor.parentNode.insertBefore(panel,anchor)
    }
    originals.forEach(panel=>panel.remove())
    const first=section.querySelector('.hero-slide')
    first.classList.add('active');first.setAttribute('aria-hidden','false')
    const image=first.querySelector('img');image.loading='eager';image.fetchPriority='high'
    const total=section.querySelector('.slide-total')
    if(total)total.textContent='/ '+String(slides.length).padStart(2,'0')
    window.__ANDARMA_HERO_SLIDES__=slides
  } catch(e) {
    if(e.name!=='AbortError')console.warn('Se conservaron los banners originales:',e)
  }
}
async function hydrateContent() {
  try {
    const response = await fetch(`${API}/public/site-content`, {signal: controller.signal, headers: {Accept:'application/json'}})
    if (!response.ok) return
    const payload = await response.json()
    const content = payload.data || {}
    for (const el of document.querySelectorAll('[data-andarma-content]')) {
      const key = el.getAttribute('data-andarma-content')
      if (Object.prototype.hasOwnProperty.call(content, key)) el.textContent = content[key]
    }
  } catch (e) { if (e.name !== 'AbortError') console.warn('Contenido remoto no disponible; se mantiene contenido predeterminado.',e) }
}
// ANDARMA_V45_TESTIMONIALS_PUBLIC: usar únicamente testimonios visibles.
async function hydrateTestimonials() {
  try {
    const response = await fetch(`${API}/public/testimonials`, {
      headers: { Accept: 'application/json' }, signal: controller?.signal
    })
    if (!response.ok) return
    const payload = await response.json()
    const items = Array.isArray(payload.data) ? payload.data : []
    if (items.length) renderPublicTestimonials(document.getElementById('storiesTrack'), items)
  } catch (e) {
    if (e.name !== 'AbortError') console.warn('No se pudieron cargar los testimonios publicados.', e)
  }
}

// ANDARMA_FASE7D_LOGO_BACKTOP: visible sólo después del hero
const showBackTop = ref(false)
let backTopObserver = null
let backTopScroll = null
function initBackTop() {
  const hero = document.getElementById('inicio')
  if (!hero) return
  const update = () => { showBackTop.value = hero.getBoundingClientRect().bottom <= 0 }
  if ('IntersectionObserver' in window) {
    backTopObserver = new IntersectionObserver(() => update(), {threshold:0})
    backTopObserver.observe(hero)
  } else {
    backTopScroll = update
    window.addEventListener('scroll', update, {passive:true})
  }
  update()
}
function cleanupBackTop() {
  backTopObserver?.disconnect()
  if (backTopScroll) window.removeEventListener('scroll', backTopScroll)
  backTopObserver = null
  backTopScroll = null
}

onMounted(async () => {
  trackHomeVisit()
  document.addEventListener('click', trackWhatsAppClick, {capture:true})
  await nextTick()
  initBackTop()
  controller = new AbortController()
  // Contenido y testimonios no deben retrasar el hero ni su loader.
  void hydrateContent()
  void hydrateTestimonials()
  // Conservar el comportamiento de los banners y su inicialización animada.
  await hydrateHeroBanners()
  // Original animation controller retained temporarily for visual fidelity in migration stage 1.
  legacyScript = document.createElement('script')
  legacyScript.src = '/andarma-legacy.js'
  legacyScript.onload = () => window.dispatchEvent(new Event('andarma:mounted'))
  document.body.appendChild(legacyScript)
})
onBeforeUnmount(() => {
  cleanupBackTop()
  document.removeEventListener('click', trackWhatsAppClick, {capture:true})
  controller?.abort()
  delete window.__ANDARMA_HERO_SLIDES__
  legacyScript?.remove()
})
</script>
<template>
<div aria-label="Cargando ANDARMA" aria-live="polite" class="brand-loader" id="brandLoader" role="status">
<div aria-hidden="true" class="brand-loader__grid"></div>
<div class="brand-loader__center">
<div class="brand-loader__mark"><img alt="" height="80" src="/andarma-logo-original.webp" width="152"/></div>
<div class="brand-loader__name">ANDARMA</div>
<div class="brand-loader__sub">D E S A R R O L L O S</div>
<div aria-hidden="true" class="brand-loader__track"><span></span></div>
<div class="brand-loader__number">ESPACIOS PARA LO QUE VIENE <span>·</span> MÉXICO</div>
</div>
<div aria-hidden="true" class="brand-loader__corner brand-loader__corner--top">01 / 03</div>
<div aria-hidden="true" class="brand-loader__corner brand-loader__corner--bottom">ANDARMA © 2026</div>
</div>
<main>
<AndarmaPublicNavbar />
<section aria-label="ANDARMA, proyectos inmobiliarios" class="hero ad-showreel" id="inicio">
<div aria-hidden="false" class="hero-slide active"><img alt="Arquitectura residencial contemporánea" class="hero-image" decoding="async" fetchpriority="high" loading="eager" src="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=1280&amp;q=72" srcset="https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=480&amp;q=72 480w, https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=720&amp;q=72 720w, https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=960&amp;q=72 960w, https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=1280&amp;q=72 1280w, https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=1600&amp;q=72 1600w, https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&amp;fit=crop&amp;w=1920&amp;q=72 1920w" sizes="100vw" data-andarma-pagespeed-f4="hero-inicial"/><div class="hero-shade"></div></div>
<div aria-hidden="true" class="hero-slide"><img alt="Arquitectura y entorno natural" class="hero-image" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&amp;fit=crop&amp;w=2400&amp;q=85"/><div class="hero-shade"></div></div>
<div aria-hidden="true" class="hero-slide"><img alt="Diseño interior contemporáneo" class="hero-image" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&amp;fit=crop&amp;w=2400&amp;q=85"/><div class="hero-shade"></div></div>
<div class="hero-inner">
  <div class="hero-content" aria-live="polite" aria-atomic="true">
    <p class="hero-slide-label eyebrow"><span id="heroEyebrow">ANDARMA / UNA NUEVA VISIÓN</span></p>
    <div class="editorial-headline"><h1 class="hero-title" id="heroTitle"><span class="title-line">Aquí empieza</span><span class="title-line title-line--shift accent">lo que sigue.</span></h1></div>
    <div class="editorial-copy"><div class="copy-main"><p class="hero-desc" id="heroDescription">El comienzo de una gran historia merece el lugar correcto. Descubre espacios pensados para construir tu futuro.</p></div><a class="primary-cta" href="/desarrollos" id="heroCta"><span>Explorar desarrollos</span><i aria-hidden="true" class="fa-solid fa-arrow-up-right-from-square"></i></a></div>
  </div>
</div>
<nav class="ad-showreel__controls" aria-label="Controles del banner">
  <span class="ad-showreel__rail-label">PROYECTOS / ANDARMA</span>
  <span class="ad-showreel__position" aria-label="Diapositiva actual"><span class="slide-current" id="slideCurrent">01</span><span class="slide-total">/ 03</span></span>
  <div class="ad-showreel__progress" aria-hidden="true"><span id="progressBar"></span></div>
  <div class="ad-showreel__arrows"><button type="button" id="prevSlide" aria-label="Diapositiva anterior"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></button><button type="button" id="nextSlide" aria-label="Diapositiva siguiente"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></button></div>
  <button type="button" id="heroPause" class="ad-showreel__pause" aria-label="Pausar reproducción automática" aria-pressed="false"><i class="fa-solid fa-pause" aria-hidden="true"></i><span class="ad-showreel__pause-text">Pausar</span></button>
</nav>
<a class="ad-showreel__scroll" href="#nosotros">DESCUBRE ANDARMA <i class="fa-solid fa-arrow-down-long" aria-hidden="true"></i></a>
</section><span aria-hidden="true" class="ad-showcase__anchor" id="desarrollos"></span><section aria-label="Conoce ANDARMA y nuestros desarrollos" class="ad-showcase" id="nosotros">
<div class="ad-showcase__shell">
<header class="ad-showcase__top">
<div class="ad-showcase__identity"><span aria-hidden="true" class="ad-showcase__topmark"></span><span class="ad-showcase__topline">ANDARMA <span class="ad-showcase__slash">/</span> ARQUITECTURA PARA VIVIR</span></div>
<span class="ad-showcase__topline ad-showcase__topline--right">DESARROLLO INMOBILIARIO <span aria-hidden="true">·</span> NATURALEZA <span aria-hidden="true">·</span> PATRIMONIO</span>
</header>
<div class="ad-showcase__intro">
<p class="ad-showcase__intro-title">Más que espacios, <em>posibilidades para vivir.</em></p>
<p class="ad-showcase__intro-copy" data-andarma-content="nosotros.p.1">Conoce la visión que nos mueve y descubre cómo acompañamos cada proyecto: desde encontrar el lugar indicado hasta imaginar todo lo que puede llegar a ser.</p>
</div>
<div aria-label="Nuestra visión y desarrollos" aria-roledescription="carrusel" class="ad-showcase__slides" id="adShowcaseSlides">
<article aria-label="1 de 2: acerca de ANDARMA" aria-roledescription="diapositiva" class="ad-showcase__slide ad-showcase__slide--about is-active">
<div class="ad-showcase__copy">
<div class="ad-showcase__chapter"><span class="ad-showcase__chapter-rule"></span> 01 / NUESTRA ESENCIA</div>
<span class="about-kicker">ACERCA DE ANDARMA</span>
<h2 class="about-title" id="aboutTitle">Construimos comunidades <span class="accent">con propósito.</span></h2>
<div class="ad-showcase__story">
<p class="about-text">Somos una desarrolladora inmobiliaria con <strong>más de 8 años de experiencia</strong>, especializada en la venta de terrenos y la construcción de viviendas en zonas de gran riqueza natural.</p>
<p class="ad-showcase__more" data-andarma-content="nosotros.p.2">Desde 2018 impulsamos oportunidades en entornos semiboscosos, con una visión de comunidades planeadas, espacios de convivencia y respeto por la naturaleza.</p>
</div>
<div class="ad-showcase__fact"><strong>+8</strong><span><b>AÑOS DE EXPERIENCIA</b><small>Un camino dedicado al desarrollo inmobiliario y a nuevas formas de habitar.</small></span></div>
<a class="about-cta" href="#contacto"><span>Hablar con un asesor</span><span aria-hidden="true" class="ad-showcase__cta-arrow">↗</span></a>
</div>
<div class="ad-showcase__media">
<img alt="Vivienda contemporánea rodeada de vegetación; fotografía ilustrativa de la visión de ANDARMA" class="ad-showcase__image" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=1000&amp;q=70" srcset="https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=400&amp;q=70 400w, https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=640&amp;q=70 640w, https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=800&amp;q=70 800w, https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=1000&amp;q=70 1000w, https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=1280&amp;q=70 1280w, https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&amp;fit=crop&amp;w=1600&amp;q=70 1600w" sizes="(max-width: 750px) 100vw, (max-width: 1150px) 60vw, 50vw" data-andarma-pagespeed-f4="acerca-de-andarma"/>
<div class="ad-showcase__image-top"><span class="ad-showcase__image-line"></span> UNA FORMA DE VIVIR</div>
<span class="ad-showcase__image-caption"><small>01 / NUESTRO ENFOQUE</small> Espacios para lo que viene.</span>
</div>
<aside aria-label="Lo que nos define" class="ad-showcase__sidebar">
<div class="ad-showcase__sidebar-intro"><p class="ad-showcase__sidehead" data-andarma-content="nosotros.p.3">NUESTROS PILARES</p><h3>Lo importante va <em>más allá del terreno.</em></h3></div>
<div aria-label="Beneficios de ANDARMA" class="about-features">
<div class="about-feature"><span aria-hidden="true" class="about-icon"><svg viewbox="0 0 48 48"><path d="M23.4 38.5c-8.5 0-13.7-6.2-13.7-14.5 0-8.7 5.2-14.8 13.2-14.8 5 0 8.2 2.2 10.3 5.7"></path><path d="M24 19.8c-2.8 7.6-8 12.2-15.3 14.2"></path><path d="M23.7 19.8c2.5 8.2 7.1 12.8 14 14.4"></path><path d="M23.7 10.1v29.1"></path></svg></span><div><strong class="about-feature-title">Entornos naturales</strong><p class="ad-showcase__feature-desc" data-andarma-content="nosotros.p.4">Terrenos en zonas de riqueza natural para disfrutar de un entorno con identidad.</p></div></div>
<div class="about-feature"><span aria-hidden="true" class="about-icon"><svg viewbox="0 0 48 48"><path d="M15 26.5c-3.8 0-6.8-2.9-6.8-6.5s3-6.5 6.8-6.5 6.9 2.9 6.9 6.5-3.1 6.5-6.9 6.5Z"></path><path d="M33 26.5c-3.8 0-6.8-2.9-6.8-6.5s3-6.5 6.8-6.5 6.9 2.9 6.9 6.5-3.1 6.5-6.9 6.5Z"></path><path d="M24 21.5c-4.2 0-7.5-3.1-7.5-7s3.3-7 7.5-7 7.5 3.1 7.5 7-3.3 7-7.5 7Z"></path><path d="M6.8 38.5c.5-5 4.7-8.7 10-8.7 4.2 0 7.9 2.4 9.4 6"></path><path d="M21.8 38.5c.7-5.8 5.6-10.1 11.6-10.1 4.3 0 8 2.1 10 5.4"></path></svg></span><div><strong class="about-feature-title">Comunidades planeadas</strong><p class="ad-showcase__feature-desc" data-andarma-content="nosotros.p.5">Una visión de desarrollo que contempla orden, convivencia y vida en comunidad.</p></div></div>
<div class="about-feature"><span aria-hidden="true" class="about-icon"><svg viewbox="0 0 48 48"><path d="M24 7.5 36.5 11v10.4c0 8.5-4.8 14-12.5 18.9-7.7-4.9-12.5-10.4-12.5-18.9V11L24 7.5Z"></path><path d="m18.4 23.6 3.9 3.8 7.4-7.4"></path></svg></span><div><strong class="about-feature-title">Seguridad y tranquilidad</strong><p class="ad-showcase__feature-desc" data-andarma-content="nosotros.p.6">Acompañamiento para tomar decisiones informadas en cada etapa del proceso.</p></div></div>
</div>
<p class="ad-showcase__aside-footer">EL LUGAR DONDE COMIENZA TU HISTORIA <span aria-hidden="true">↗</span></p>
</aside>
</article>
<article aria-hidden="true" aria-label="2 de 2: nuestros desarrollos" aria-roledescription="diapositiva" class="ad-showcase__slide ad-showcase__slide--development" inert="">
<div class="ad-showcase__copy">
<div class="ad-showcase__chapter"><span class="ad-showcase__chapter-rule"></span> 02 / LO QUE DESARROLLAMOS</div>
<span class="developments-label">NUESTROS DESARROLLOS</span>
<h2 class="developments-title" id="developmentsTitle">Terrenos y viviendas <span class="accent">en lugares con gran potencial.</span></h2>
<div class="ad-showcase__story">
<p class="developments-description" data-andarma-content="nosotros.p.7">Conoce nuestros desarrollos en zonas de riqueza natural, con alternativas para construir tu patrimonio y elegir el espacio ideal para tu familia.</p>
<p class="ad-showcase__more" data-andarma-content="nosotros.p.8">Además de la venta de terrenos y viviendas, ofrecemos orientación durante el proceso inmobiliario, con esquemas de pago y opciones que se revisan según cada proyecto.</p>
</div>
<div class="ad-showcase__fact ad-showcase__fact--statement"><span aria-hidden="true" class="ad-showcase__fact-icon">✳</span><span><b>DE LA IDEA A TU PATRIMONIO</b><small>Te acompañamos desde la elección de tu lote hasta visualizar los siguientes pasos hacia tu vivienda.</small></span></div>
<a class="developments-link" href="/desarrollos"><span>Ver desarrollos</span><span aria-hidden="true" class="ad-showcase__cta-arrow">↗</span></a>
</div>
<div class="ad-showcase__media">
<img alt="Paisaje de terrenos y áreas verdes; fotografía ilustrativa de nuestros desarrollos" class="ad-showcase__image" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&amp;fit=crop&amp;w=1800&amp;q=85"/>
<div class="ad-showcase__image-top"><span class="ad-showcase__image-line"></span> TU PRÓXIMO CAPÍTULO</div>
<span class="ad-showcase__image-caption"><small>02 / DESARROLLOS</small> Un lugar. Nuevas posibilidades.</span>
</div>
<aside aria-label="Opciones inmobiliarias" class="ad-showcase__sidebar">
<div class="ad-showcase__sidebar-intro"><p class="ad-showcase__sidehead" data-andarma-content="nosotros.p.9">EXPLORA TUS POSIBILIDADES</p><h3>Tu siguiente paso <em>empieza aquí.</em></h3></div>
<div class="ad-showcase__side-links"><a href="#opciones"><span><small>01 / OPCIÓN</small> Viviendas</span><span aria-hidden="true">↗</span></a><a href="#opciones"><span><small>02 / OPCIÓN</small> Terrenos</span><span aria-hidden="true">↗</span></a></div>
<div class="ad-showcase__financing"><span aria-hidden="true">✳</span><p><strong>Facilidades de pago</strong>Consulta alternativas disponibles y condiciones particulares con nuestro equipo.</p></div>
<p class="ad-showcase__aside-footer">UN ESPACIO PARA TU PRÓXIMO CAPÍTULO <span aria-hidden="true">↗</span></p>
</aside>
</article>
</div>
<div class="ad-showcase__bottom"><p class="ad-showcase__bottom-label"><span class="ad-showcase__bottom-line"></span> DESCUBRE ANDARMA</p><div aria-hidden="true" class="ad-showcase__progress"><span id="adShowcaseProgress"></span></div><span aria-live="polite" class="ad-showcase__count" id="adShowcaseCount">01 / 02</span><div class="ad-showcase__controls"><button aria-label="Ver escena anterior" class="ad-showcase__arrow" id="adShowcasePrev" type="button"><svg aria-hidden="true" viewbox="0 0 24 24"><path d="m15 5-7 7 7 7M8 12h13"></path></svg></button><button aria-label="Ver siguiente escena" class="ad-showcase__arrow" id="adShowcaseNext" type="button"><svg aria-hidden="true" viewbox="0 0 24 24"><path d="m9 5 7 7-7 7M16 12H3"></path></svg></button></div></div>
</div>
</section><section aria-label="Conoce nuestras viviendas y terrenos" class="choice-duo" id="opciones">
<article aria-labelledby="choiceHomeTitle" class="choice-panel choice-panel--home">
<div class="choice-visual"><img alt="Interior de vivienda contemporánea con estancia luminosa" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&amp;fit=crop&amp;w=1600&amp;q=85"/></div>
<div class="choice-content">
<span class="choice-kicker">VIVIENDAS</span>
<h2 class="choice-title" id="choiceHomeTitle">Casas pensadas<br/>para tu bienestar</h2>
<p class="choice-description" data-andarma-content="opciones.p.1">Diseños funcionales y espacios para vivir con comodidad, tranquilidad y crear nuevas historias en familia.</p>
<a class="choice-action" href="#contacto"><span>Solicitar información</span><svg aria-hidden="true" viewbox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"></path></svg></a>
</div>
</article>
<div aria-hidden="true" class="choice-divider"></div>
<article aria-labelledby="choiceLandTitle" class="choice-panel choice-panel--land">
<div class="choice-visual"><img alt="Vista panorámica de terrenos y paisaje natural" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&amp;fit=crop&amp;w=1600&amp;q=85"/></div>
<div class="choice-content">
<span class="choice-kicker">TERRENOS</span>
<h2 class="choice-title" id="choiceLandTitle">Invierte hoy<br/>en tu futuro</h2>
<p class="choice-description" data-andarma-content="opciones.p.2">Descubre alternativas de terrenos en ubicaciones con potencial, rodeadas de naturaleza y pensadas para tu próximo proyecto.</p>
<a class="choice-action" href="#desarrollos"><span>Ver terrenos</span><svg aria-hidden="true" viewbox="0 0 24 24"><path d="M4 12h16m-7-7 7 7-7 7"></path></svg></a>
</div>
</article>
</section><section aria-labelledby="whyTitle" class="why-andarma" id="por-que-andarma">
<div class="why-image"><img alt="Paisaje natural con montañas y bosque" decoding="async" loading="lazy" src="https://images.unsplash.com/photo-1470770841072-f978cf4d019e?auto=format&amp;fit=crop&amp;w=2200&amp;q=85"/></div>
<div aria-hidden="true" class="why-overlay"></div>
<div class="why-shell">
<div class="why-heading">
<div><span class="why-kicker">¿POR QUÉ ANDARMA?</span><h2 class="why-title" id="whyTitle">Más que terrenos,<br/>construimos <strong>bienestar</strong></h2></div>
<p class="why-intro" data-andarma-content="por-que-andarma.p.1">Nos distingue la atención personalizada, la seguridad en cada paso y el compromiso con crear comunidades en armonía con su entorno.</p>
</div>
<div aria-label="Beneficios de elegir ANDARMA" class="why-benefits"><div class="why-benefit"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><p>Ubicaciones<br/>estratégicas</p></div>
<div class="why-benefit"><i class="fa-solid fa-tree" aria-hidden="true"></i><p>Entornos<br/>naturales</p></div>
<div class="why-benefit"><i class="fa-solid fa-people-roof" aria-hidden="true"></i><p>Comunidades<br/>planeadas</p></div>
<div class="why-benefit"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><p>Seguridad<br/>y tranquilidad</p></div>
<div class="why-benefit"><i class="fa-solid fa-credit-card" aria-hidden="true"></i><p>Opciones de<br/>financiamiento</p></div>
<div class="why-benefit"><i class="fa-solid fa-handshake-angle" aria-hidden="true"></i><p>Acompañamiento<br/>total</p></div></div>
</div>
</section></main>
<!-- ANDARMA_SHARED_PUBLIC_F3F -->
<AndarmaSharedPublicSections />


<!-- ANDARMA_FASE7B_WHATSAPP_FLOTANTE -->
<!-- ANDARMA_FASE7C_VOLVER_ARRIBA_LOGO -->
<a class="andarma-backtop-floating" :class="{'is-visible': showBackTop}" :aria-hidden="!showBackTop" :tabindex="showBackTop ? 0 : -1" href="#inicio" aria-label="Volver al inicio de la página" title="Volver arriba"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></a>
<a v-if="whatsAppUrl" class="andarma-wa-floating" aria-label="Contactar a ANDARMA por WhatsApp" title="Contactar por WhatsApp" :href="whatsAppUrl" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i><span>¿Hablamos?</span></a>


</template>
