
(() => {
  const dialog = document.getElementById('adLegalDialog');
  const title = document.getElementById('adLegalTitle');
  const body = document.getElementById('adLegalBody');
  const legal = window.ANDARMA_LEGAL;
  document.querySelectorAll('[data-legal]').forEach(button => button.addEventListener('click', () => {
    const doc = legal[button.dataset.legal];
    if (!doc) return;
    title.textContent = doc.title;
    body.innerHTML = doc.html;
    dialog.showModal();
  }));
  document.getElementById('adLegalClose').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
  document.getElementById('adFooterYear').textContent = new Date().getFullYear();
  document.getElementById('adBackTop').addEventListener('click', () => {
    document.getElementById('inicio').scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  });
})();



/* Loader with a short brand reveal; finishes on first hero image ready or timeout. */
(function(){
  const loader=document.getElementById('brandLoader');
  const heroImage=document.querySelector('.hero-slide.active .hero-image');
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const mobile=window.matchMedia('(max-width: 767px)').matches;
  const start=performance.now();
  let completed=false;
  function release(){
    if(completed)return;completed=true;
    const remaining=reduced?0:Math.max(0,(mobile?350:950)-(performance.now()-start));
    setTimeout(()=>{loader.classList.add('is-done');document.body.classList.remove('is-loading');setTimeout(()=>loader.remove(),800)},remaining);
  }
  if(!heroImage||heroImage.complete)release();
  else {heroImage.addEventListener('load',release,{once:true});heroImage.addEventListener('error',release,{once:true});}
  setTimeout(release,mobile?1600:3200);
})();



window.addEventListener('andarma:mounted',()=>{
  const slides=[
    {eyebrow:'ANDARMA / UNA NUEVA VISIÓN',title:'<span class="title-line">Aquí empieza</span><span class="title-line title-line--shift accent">lo que sigue.</span>',description:'El comienzo de una gran historia merece el lugar correcto. Descubre espacios pensados para construir tu futuro.',cta:'Explorar desarrollos',href:'/desarrollos'},
    {eyebrow:'ANDARMA / DESARROLLOS',title:'<span class="title-line">Tu próximo</span><span class="title-line title-line--shift accent">gran capítulo.</span>',description:'Terrenos y proyectos inmobiliarios para visualizar el futuro que quieres construir, paso a paso.',cta:'Conocer proyectos',href:'/desarrollos'},
    {eyebrow:'ANDARMA / TU PRÓXIMA ETAPA',title:'<span class="title-line">Un lugar.</span><span class="title-line title-line--shift accent">Mil posibilidades.</span>',description:'Conoce nuestras alternativas inmobiliarias y recibe orientación para dar el siguiente paso con confianza.',cta:'Hablar con nosotros',href:'#contacto'}
  ];
  // ANDARMA_V41: los textos de cada diapositiva se leen del administrador cuando existen.
  const adminSlides=window.__ANDARMA_HERO_SLIDES__;
  if(Array.isArray(adminSlides)&&adminSlides.length){
    const escapeHtml=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    slides.splice(0,slides.length,...adminSlides.map(s=>({
      ...s,title:'<span class="title-line">'+escapeHtml(s.line1)+'</span>'+(s.line2?'<span class="title-line title-line--shift accent">'+escapeHtml(s.line2)+'</span>':''),
    })));
  }
  const panels=[...document.querySelectorAll('.hero-slide')], eyebrow=document.querySelector('#heroEyebrow'),title=document.querySelector('#heroTitle'),desc=document.querySelector('#heroDescription'),cta=document.querySelector('#heroCta'),current=document.querySelector('#slideCurrent'),progress=document.querySelector('#progressBar');
  const reduce=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let active=0,locked=false,autoTimer=null,progressTween=null;
  const duration=6.5;
  let paused=false;
  function fillContent(index){
    const d=slides[index];
    eyebrow.textContent=d.eyebrow;title.innerHTML=d.title;desc.textContent=d.description;
    cta.querySelector('span').textContent=d.cta;cta.href=d.href;
    cta.style.display=d.cta?'':'none';current.textContent=String(index+1).padStart(2,'0');
    if(Array.isArray(adminSlides)&&adminSlides.length){
      const content={'.headline-overline':d.overline,'.headline-foot':d.footnote,'.hero-tag':d.tagline,
        '.editorial-index > span:nth-child(2)':d.editorial_label,
        '.side-metadata > span':d.side_title,'.side-metadata > p':d.side_description};
      for(const [selector,value] of Object.entries(content)){
        const element=document.querySelector('#inicio '+selector);
        if(element)element.textContent=value||'';
      }
    }
  }
  fillContent(0);
  function schedule(){clearTimeout(autoTimer);if(progressTween)progressTween.kill();if(document.hidden||reduce||paused||slides.length<2)return;if(typeof gsap!=='undefined'){gsap.set(progress,{height:'0%',width:'100%'});progressTween=gsap.to(progress,{height:'100%',duration,ease:'none'});}autoTimer=setTimeout(()=>goTo((active+1)%slides.length),duration*1000);}
  function goTo(next){if(locked||next===active)return;locked=true;clearTimeout(autoTimer);if(progressTween)progressTween.kill();const prev=panels[active],incoming=panels[next];const old=active;active=next;incoming.style.visibility='visible';incoming.setAttribute('aria-hidden','false');
    if(reduce||typeof gsap==='undefined'){prev.classList.remove('active');prev.setAttribute('aria-hidden','true');incoming.classList.add('active');fillContent(next);locked=false;schedule();return;}
    gsap.killTweensOf([prev,incoming,prev.querySelector('img'),incoming.querySelector('img'),eyebrow,title,desc,cta]);
    const tl=gsap.timeline({onComplete:()=>{prev.classList.remove('active');prev.style.visibility='hidden';prev.setAttribute('aria-hidden','true');gsap.set(prev,{opacity:0,clearProps:'clipPath'});gsap.set(incoming,{clearProps:'clipPath'});incoming.classList.add('active');locked=false;schedule();}});
    gsap.set(incoming,{opacity:1,clipPath:'inset(0 0 0 100%)',zIndex:3});gsap.set(prev,{opacity:1,zIndex:2});gsap.set(incoming.querySelector('img'),{scale:1.15,xPercent:4});
    tl.to([eyebrow,title,desc,cta],{opacity:0,y:-18,duration:.23,stagger:.035,ease:'power2.in'},0)
      .to(incoming,{clipPath:'inset(0 0 0 0%)',duration:1.2,ease:'power3.inOut'},.10)
      .to(incoming.querySelector('img'),{scale:1.045,xPercent:0,duration:1.75,ease:'power2.out'},.1)
      .add(()=>fillContent(next),.56)
      .fromTo([eyebrow,title,desc,cta],{opacity:0,y:36},{opacity:1,y:0,duration:.82,stagger:.09,ease:'power3.out'},.65);
  }
  document.querySelector('#prevSlide').addEventListener('click',()=>goTo((active-1+slides.length)%slides.length));
  document.querySelector('#nextSlide').addEventListener('click',()=>goTo((active+1)%slides.length));

  // ANDARMA_SHOWREEL_EDITORIAL_V2: pausa/reanuda de verdad, no solo icono.
  const pauseControl=document.querySelector('#heroPause');
  if(pauseControl){
    pauseControl.addEventListener('click',()=>{
      paused=!paused;
      pauseControl.setAttribute('aria-pressed',String(paused));
      pauseControl.setAttribute('aria-label',paused?'Reanudar reproducción automática':'Pausar reproducción automática');
      const ico=pauseControl.querySelector('i');
      if(ico)ico.className=paused?'fa-solid fa-play':'fa-solid fa-pause';
      const lbl=pauseControl.querySelector('.ad-showreel__pause-text');
      if(lbl)lbl.textContent=paused?'Reanudar':'Pausar';
      if(paused){clearTimeout(autoTimer);if(progressTween)progressTween.pause();}
      else schedule();
    });
  }
  const toggle=document.querySelector('#menuToggle'),mobile=document.querySelector('#mobileNav');toggle.addEventListener('click',()=>{const isOpen=mobile.classList.toggle('open');toggle.setAttribute('aria-expanded',String(isOpen));toggle.setAttribute('aria-label',isOpen?'Cerrar menú':'Abrir menú');toggle.querySelector('i').className=isOpen?'fa-solid fa-xmark':'fa-solid fa-bars';});mobile.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>{mobile.classList.remove('open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Abrir menú');toggle.querySelector('i').className='fa-solid fa-bars';}));
  document.addEventListener('keydown',(event)=>{if(event.key==='Escape'&&mobile.classList.contains('open')){mobile.classList.remove('open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Abrir menú');toggle.querySelector('i').className='fa-solid fa-bars';toggle.focus();}});
  document.addEventListener('click',(event)=>{if(mobile.classList.contains('open')&&!mobile.contains(event.target)&&!toggle.contains(event.target)){mobile.classList.remove('open');toggle.setAttribute('aria-expanded','false');toggle.setAttribute('aria-label','Abrir menú');toggle.querySelector('i').className='fa-solid fa-bars';}});
  document.addEventListener('visibilitychange',()=>{if(document.hidden){clearTimeout(autoTimer);if(progressTween)progressTween.pause();}else if(!locked)schedule();});
  if(typeof gsap==='undefined'){schedule();return;}
  gsap.fromTo('.hero-content',{opacity:0,y:48},{opacity:1,y:0,duration:1.15,delay:.18,ease:'power3.out'});
  if(!reduce){
    gsap.to(panels[0].querySelector('img'),{scale:1,duration:8,ease:'none'});
    if(typeof ScrollTrigger!=='undefined'){
      gsap.registerPlugin(ScrollTrigger);
      // Un solo lenguaje de movimiento: reveal diagonal + zoom lento + textos escalonados.
      function editorialReveal(section,media,image,copy,fromRight){
        const mediaEl=document.querySelector(media);
        const imgEl=document.querySelector(image);
        const sectionEl=document.querySelector(section);
        if(!mediaEl||!imgEl||!sectionEl)return;
        const finalClip=getComputedStyle(mediaEl).clipPath;
        const startClip=fromRight
          ? 'polygon(100% 0,100% 0,100% 100%,100% 100%)'
          : 'polygon(0 0,0 0,0 100%,0 100%)';
        const timeline=gsap.timeline({scrollTrigger:{trigger:sectionEl,start:'top 82%',end:'bottom 18%',toggleActions:'play none none reverse',invalidateOnRefresh:true}});
        timeline
          .fromTo(mediaEl,{clipPath:startClip},{clipPath:finalClip,duration:1.35,ease:'power3.inOut'},0)
          .fromTo(imgEl,{scale:1.14},{scale:1,duration:1.9,ease:'power2.out'},0)
          .fromTo(copy,{opacity:1,y:16},{opacity:1,y:0,duration:.83,stagger:.10,ease:'power3.out',immediateRender:false},.22);
      }
      // V21: replaced editorial scenes have their own slider timeline.
      gsap.timeline({scrollTrigger:{trigger:'#como-comprar',start:'top 79%',once:true}})
        .from('.purchase-kicker,.purchase-heading,.purchase-intro,.purchase-step,.purchase-foot',
          {autoAlpha:0,y:28,duration:.83,stagger:.12,ease:'power3.out'})
        .to('.purchase-progress',{scaleX:1,duration:1.35,ease:'power3.inOut'},.5);
      // Misma transición cinematográfica en ambas fotos: revelado, zoom y textos.
      document.querySelectorAll('.choice-panel').forEach((panel,index)=>{
        const img=panel.querySelector('.choice-visual img');
        const visual=panel.querySelector('.choice-visual');
        const copy=panel.querySelectorAll('.choice-kicker,.choice-title,.choice-description,.choice-action');
        const starting=index===0?'inset(0 100% 0 0)':'inset(0 0 0 100%)';
        const tl=gsap.timeline({scrollTrigger:{trigger:panel,start:'top 82%',once:true}});
        tl.fromTo(visual,{clipPath:starting},{clipPath:'inset(0 0% 0 0%)',duration:1.35,ease:'power3.inOut'},0)
          .fromTo(img,{scale:1.14},{scale:1,duration:1.9,ease:'power2.out'},0)
          .from(copy,{autoAlpha:0,y:28,duration:.83,stagger:.12,ease:'power3.out'},.22);
      });
      // Misma gramática de animación que las demás secciones: imagen + contenidos.
      gsap.timeline({scrollTrigger:{trigger:'#por-que-andarma',start:'top 79%',once:true}})
        .fromTo('.why-image',{clipPath:'inset(0 0 100% 0)'},{clipPath:'inset(0 0 0% 0)',duration:1.35,ease:'power3.inOut'},0)
        .fromTo('.why-image img',{scale:1.14},{scale:1,duration:1.9,ease:'power2.out'},0)
        .from('.why-kicker,.why-title,.why-intro',{autoAlpha:0,y:28,duration:.83,stagger:.12,ease:'power3.out'},.2)
        .from('.why-benefit',{autoAlpha:0,y:24,duration:.76,stagger:.11,ease:'power3.out'},.5);
      gsap.from('.stories-heading,.stories-toolbar,.story',{autoAlpha:0,y:28,duration:.83,stagger:.10,ease:'power3.out',scrollTrigger:{trigger:'#testimonios',start:'top 79%',once:true}});
      // Contacto: animación de entrada nativa mediante IntersectionObserver (V23).

    }
  }
  schedule();
});



(function(){
 const nav=document.querySelector('.topbar');
 if(!nav)return;
 const paint=()=>nav.classList.toggle('is-scrolled',window.scrollY>Math.max(75,window.innerHeight*.4));
 window.addEventListener('scroll',paint,{passive:true});paint();

})();



(function(){
  const track=document.getElementById('storiesTrack'),viewport=document.getElementById('storiesViewport');
  const prev=document.getElementById('storiesPrev'),next=document.getElementById('storiesNext');
  if(!track||!viewport||!prev||!next)return;
  let index=0;
  const cards=Array.from(track.children);
  const perPage=()=>matchMedia('(max-width:680px)').matches?1:matchMedia('(max-width:1180px)').matches?2:3;
  function update(){
    const max=Math.max(0,cards.length-perPage());index=Math.min(index,max);
    const offset=cards[index].offsetLeft-cards[0].offsetLeft;
    track.style.transform='translate3d('+(-offset)+'px,0,0)';
    prev.disabled=index===0;next.disabled=index===max;
  }
  prev.addEventListener('click',()=>{index=Math.max(0,index-1);update()});
  next.addEventListener('click',()=>{index=Math.min(cards.length-perPage(),index+1);update()});
  viewport.addEventListener('keydown',e=>{if(e.key==='ArrowRight'){e.preventDefault();next.click()}if(e.key==='ArrowLeft'){e.preventDefault();prev.click()}});
  window.addEventListener('resize',update,{passive:true});update();
})();



/* V17: navegación activa coherente con el orden de lectura. */
(()=>{
 const nav=document.querySelector('.topbar');
 const links=[...document.querySelectorAll('.desktop-nav a[href^="#"]')];
 const sections=[...document.querySelectorAll('main > section[id]')];
 if(!nav||!sections.length||!('IntersectionObserver' in window))return;
 const io=new IntersectionObserver(entries=>{
  const relevant=entries.filter(e=>e.isIntersecting).sort((a,b)=>b.intersectionRatio-a.intersectionRatio)[0];
  if(!relevant)return;
  links.forEach(link=>{
   const current=link.getAttribute('href')==='#'+relevant.target.id;
   if(current)link.setAttribute('aria-current','location');else link.removeAttribute('aria-current');
  });
 },{rootMargin:'-25% 0px -55% 0px',threshold:[0,.1,.25,.4]});
 sections.forEach(sec=>io.observe(sec));
})();



/* V21 showcase — manual slide navigation; no scroll hijacking; reversible on reentry. */
(()=>{
 const root=document.querySelector('.ad-showcase');if(!root)return;
 const slides=[...root.querySelectorAll('.ad-showcase__slide')],prev=root.querySelector('#adShowcasePrev'),next=root.querySelector('#adShowcaseNext'),count=root.querySelector('#adShowcaseCount'),progress=root.querySelector('#adShowcaseProgress');
 let current=0;
 function show(index,focus=false){
   current=(index+slides.length)%slides.length;
   slides.forEach((slide,i)=>{
     const active=i===current;
     slide.classList.toggle('is-active',active);
     slide.toggleAttribute('inert',!active);
     if(active)slide.removeAttribute('aria-hidden');else slide.setAttribute('aria-hidden','true');
   });
   count.textContent=String(current+1).padStart(2,'0')+' / '+String(slides.length).padStart(2,'0');
   progress.style.transform='scaleX('+(current+1)+')';
   if(focus)root.querySelector('.ad-showcase__slide.is-active h2')?.focus?.({preventScroll:true});
 }
 prev.addEventListener('click',()=>show(current-1));next.addEventListener('click',()=>show(current+1));
 root.addEventListener('keydown',e=>{if(e.target.closest('button,a'))return;if(e.key==='ArrowRight')show(current+1);if(e.key==='ArrowLeft')show(current-1)});
 document.querySelectorAll('a[href="#desarrollos"]').forEach(a=>a.addEventListener('click',()=>show(1)));
 document.querySelectorAll('a[href="#nosotros"]').forEach(a=>a.addEventListener('click',()=>show(0)));
 if(location.hash==='#desarrollos')show(1);
 window.addEventListener('hashchange',()=>{if(location.hash==='#desarrollos')show(1);else if(location.hash==='#nosotros')show(0)});
 show(current);
})();



/* V23: se activa una sola vez cuando contacto entra en pantalla; sin animaciones de scroll costosas. */
(()=>{
  const contact=document.getElementById('contacto');
  if(!contact)return;
  const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if(reduced || !('IntersectionObserver' in window))return;
  document.documentElement.classList.add('ad-contact-motion');
  const observer=new IntersectionObserver((entries)=>{
    if(entries.some(entry=>entry.isIntersecting)){
      contact.classList.add('ad-contact-visible');
      observer.disconnect();
    }
  },{threshold:.12,rootMargin:'0px 0px -8% 0px'});
  observer.observe(contact);
})();
