#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ANDARMA / Fase 4A v2. Hero editorial minimalista compatible con 3U-3Z.

Uso:
 python3 ANDARMA_Fase4A_v2_Hero_Editorial_Compatible.py . --check
 python3 ANDARMA_Fase4A_v2_Hero_Editorial_Compatible.py .

Solo reescribe el template del hero público, conserva el script del carrusel.
No modifica Home, mapa, API, imágenes, pagos ni repositorio remoto.
"""
from pathlib import Path
from datetime import datetime
import argparse
import shutil
import sys

VUE = Path('frontend/src/components/public/AndarmaDevelopmentHeroSlider.vue')
CSS = Path('frontend/src/assets/andarma-fase4a-v2-hero-editorial.css')
MARK = 'ANDARMA_FASE4A_V2_EDITORIAL'
IMPORT = "import '../../assets/andarma-fase4a-v2-hero-editorial.css'"
OLD_IMPORTS = (
    'andarma-fase3u-hero-contacto.css',  # 3U también incluye CSS para el hero anterior
    'andarma-fase3v-hero-home.css',
    'andarma-fase3w-alineacion-hero.css',
    'andarma-fase3x-hero-centrado.css',
    'andarma-fase3y-boton-fondo-editorial.css',
    'andarma-fase3z-hero-refinado.css',
    'andarma-fase4a-hero-editorial-minimal.css',
)

TEMPLATE = '''<template>
  <!-- ANDARMA_FASE4A_V2_EDITORIAL: fotografía + título + descripción + CTA -->
  <section class="ad3a-hero ad4a-hero" aria-label="Presentación del desarrollo"
    @mouseenter="motion=false;stop()" @mouseleave="motion=true;start()"
    @focusin="motion=false;stop()" @focusout="motion=true;start()"
    @touchstart.passive="touchBegin" @touchend.passive="touchEnd">

    <div class="ad3a-hero__slides ad4a-hero__slides" aria-hidden="true">
      <Transition name="ad3a-photo">
        <div v-if="photos.length" :key="`${development?.id}-${index}`"
          class="ad3a-hero__photo ad4a-hero__photo"
          :style="{backgroundImage:`url(${JSON.stringify(photos[index])})`}"></div>
      </Transition>
      <div v-if="!photos.length" class="ad3a-hero__fallback"></div>
      <div class="ad4a-hero__shade"></div>
    </div>

    <div class="ad4a-hero__layout">
      <div class="ad4a-hero__content" :key="development?.id">
        <span class="ad4a-hero__kicker">
          <span aria-hidden="true"></span>{{ development?.location || 'ANDARMA / DESARROLLOS' }}
        </span>
        <h1 class="ad4a-hero__title">{{ development?.name || 'Nuestros desarrollos' }}</h1>
        <span class="ad4a-hero__rule" aria-hidden="true"></span>
        <p class="ad4a-hero__description">{{ development?.description || 'Conoce nuestro desarrollo y explora los terrenos disponibles.' }}</p>
        <a class="ad4a-hero__cta" href="#mapa-de-terrenos">
          <span>Explorar terrenos</span>
          <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
      </div>

      <div class="ad4a-hero__footer">
        <span class="ad4a-hero__signature">ANDARMA <i>/</i> ARQUITECTURA PARA VIVIR</span>
        <div v-if="photos.length>1" class="ad4a-hero__controls" aria-label="Controles de la galería">
          <span class="ad4a-hero__counter">{{String(index+1).padStart(2,'0')}} <small>/ {{String(photos.length).padStart(2,'0')}}</small></span>
          <div class="ad3a-hero__progress" aria-hidden="true"><span :key="index" :style="{'animationPlayState':paused?'paused':'running'}"></span></div>
          <button type="button" aria-label="Fotografía anterior" @click="prev"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
          <button type="button" aria-label="Fotografía siguiente" @click="go(index+1)"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
          <button type="button" :aria-label="paused?'Reanudar galería':'Pausar galería'" @click="togglePause"><i :class="paused?'fa-solid fa-play':'fa-solid fa-pause'" aria-hidden="true"></i></button>
        </div>
      </div>
    </div>
  </section>
</template>
'''

STYLES = r'''/* ANDARMA_FASE4A_V2_EDITORIAL — exclusivo de /desarrollos */
.adp-map-page .ad4a-hero{
  position:relative;isolation:isolate;display:block;overflow:hidden;
  min-height:clamp(680px,92svh,980px);color:#fff;background:#071d39;
}
.adp-map-page .ad4a-hero__slides{position:absolute;inset:0;z-index:-1;overflow:hidden}
.adp-map-page .ad4a-hero__slides .ad4a-hero__photo{
  position:absolute;inset:0;background-position:center 52%;background-size:cover;
  animation:ad3a-kenburns 7.2s linear both;
}
.adp-map-page .ad4a-hero__shade{
  position:absolute;inset:0;
  background:linear-gradient(180deg,rgba(2,17,35,.54) 0%,rgba(2,17,35,.20) 32%,rgba(2,17,35,.34) 64%,rgba(2,17,35,.85) 100%),
    linear-gradient(90deg,rgba(3,21,44,.20),rgba(3,21,44,.09) 50%,rgba(3,21,44,.20));
}
.adp-map-page .ad4a-hero__layout{
  box-sizing:border-box;position:relative;z-index:1;
  width:100%;max-width:1600px;min-height:inherit;
  display:grid;grid-template-rows:minmax(0,1fr) auto;align-items:center;
  margin-inline:auto;padding:clamp(135px,16vh,205px) clamp(24px,5vw,86px) clamp(20px,4vh,42px);
}
.adp-map-page .ad4a-hero__content{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  width:min(100%,900px);margin:auto;padding:24px 0 45px;
  background:none;border:0;border-radius:0;box-shadow:none;backdrop-filter:none;
  text-align:center;animation:ad4a-enter .8s cubic-bezier(.22,1,.36,1) both;
}
@keyframes ad4a-enter{from{opacity:0;transform:translateY(23px)}to{opacity:1;transform:translateY(0)}}
.adp-map-page .ad4a-hero__kicker{
  display:flex;align-items:center;justify-content:center;gap:13px;max-width:100%;
  color:#d7ebff;font:800 clamp(10px,.82vw,12px)/1.6 'DM Sans',sans-serif;
  text-transform:uppercase;letter-spacing:.21em;overflow-wrap:anywhere;text-shadow:0 2px 10px #061b37;
}
.adp-map-page .ad4a-hero__kicker>span{width:26px;height:2px;background:#38b6ff;flex:0 0 26px}
.adp-map-page .ad4a-hero__title{
  max-width:900px;margin:clamp(19px,3vh,30px) auto 0;color:#fff;
  font:800 clamp(54px,7vw,108px)/1.02 'Manrope','DM Sans',sans-serif;
  letter-spacing:-.073em;text-wrap:balance;overflow-wrap:break-word;
  text-align:center;text-shadow:0 6px 28px rgba(0,12,30,.55);
}
.adp-map-page .ad4a-hero__rule{width:50px;height:2px;background:#38b6ff;margin:clamp(21px,3.1vh,31px) auto 0}
.adp-map-page .ad4a-hero__description{
  max-width:630px;margin:clamp(16px,2.4vh,24px) auto 0;color:#edf5ff;
  font:500 clamp(15px,1.15vw,19px)/1.67 'DM Sans',sans-serif;
  text-align:center;text-wrap:pretty;text-shadow:0 3px 14px rgba(0,12,30,.65);
  display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;
}
.adp-map-page .ad4a-hero__cta{
  display:inline-flex;align-items:center;justify-content:center;gap:26px;
  min-height:57px;margin:clamp(24px,4vh,38px) auto 0;padding:15px 28px;
  background:#0066e0;border:1px solid #0891ff;border-radius:2px;
  box-shadow:0 12px 30px rgba(0,21,55,.26);color:white;text-decoration:none;
  font:800 11px/1.5 'DM Sans',sans-serif;letter-spacing:.11em;text-transform:uppercase;
  transition:background .25s,transform .25s,box-shadow .25s;
}
.adp-map-page .ad4a-hero__cta:hover{background:#0891ff;transform:translateY(-2px);box-shadow:0 17px 35px rgba(0,21,55,.33)}
.adp-map-page .ad4a-hero__cta:focus-visible,.adp-map-page .ad4a-hero__controls button:focus-visible{outline:3px solid #a8e6ff;outline-offset:4px}
.adp-map-page .ad4a-hero__footer{display:flex;align-items:center;justify-content:space-between;gap:22px;padding-top:15px;border-top:1px solid rgba(255,255,255,.20)}
.adp-map-page .ad4a-hero__signature{font:800 10px/1.4 'DM Sans',sans-serif;letter-spacing:.17em;color:#d3e6f8}
.adp-map-page .ad4a-hero__signature i{font-style:normal;color:#38b6ff;padding:0 7px}
.adp-map-page .ad4a-hero__controls{display:flex;align-items:center;gap:12px;min-width:0}
.adp-map-page .ad4a-hero__counter{font:800 19px 'Manrope',sans-serif;white-space:nowrap}
.adp-map-page .ad4a-hero__counter small{font-size:11px;font-weight:650;color:#c4d5e8}
.adp-map-page .ad4a-hero__controls .ad3a-hero__progress{width:100px;margin:0 8px}
.adp-map-page .ad4a-hero__controls button{display:grid;place-items:center;width:40px;height:40px;background:rgba(0,30,61,.22);border:1px solid rgba(240,248,255,.54);color:#fff;cursor:pointer}
.adp-map-page .ad4a-hero__controls button:hover{background:#0066e0}
@media(max-width:700px){
  .adp-map-page .ad4a-hero{min-height:690px;min-height:92svh}
  .adp-map-page .ad4a-hero__layout{padding:118px 22px 22px}
  .adp-map-page .ad4a-hero__content{padding:15px 0 26px}
  .adp-map-page .ad4a-hero__kicker{font-size:10px;letter-spacing:.12em;gap:8px}
  .adp-map-page .ad4a-hero__kicker>span{width:15px;flex-basis:15px}
  .adp-map-page .ad4a-hero__title{font-size:clamp(40px,10vw,68px)}
  .adp-map-page .ad4a-hero__description{font-size:14px;-webkit-line-clamp:4}
  .adp-map-page .ad4a-hero__cta{min-height:53px;padding-inline:22px}
  .adp-map-page .ad4a-hero__footer{flex-direction:column;align-items:center;gap:14px}
  .adp-map-page .ad4a-hero__signature{font-size:9px;text-align:center}
  .adp-map-page .ad4a-hero__controls{gap:8px}
  .adp-map-page .ad4a-hero__controls .ad3a-hero__progress{width:60px}
  .adp-map-page .ad4a-hero__controls button{width:37px;height:37px}
}
@media(prefers-reduced-motion:reduce){
  .adp-map-page .ad4a-hero__content,.adp-map-page .ad4a-hero__photo{animation:none!important}
  .adp-map-page .ad4a-hero__cta{transition:none!important}
}
'''


def make_changes(old):
    if MARK in old or IMPORT in old:
        raise ValueError('La Fase 4A v2 ya parece aplicada. No se cambia nada.')
    if old.count('<template>') != 1 or old.count('</template>') != 1:
        raise ValueError('El archivo debe tener un unico template raíz. Se detiene para protegerlo.')
    needed=('const photos=', 'function prev()', 'function go(', 'function togglePause()',
            'function touchBegin(', 'function touchEnd(', 'function stop()', 'function start()')
    missing=[x for x in needed if x not in old]
    if missing:raise ValueError('El carrusel local difiere del revisado: '+', '.join(missing))
    head=old[:old.index('<template>')]
    if '</script>' not in head:raise ValueError('No se encontró el script del carrusel.')
    # Eliminar únicamente importaciones CSS de versiones del HERO: se preservan
    # andarma-development-gallery.css y la lógica del slideshow.
    filtered=[]
    for line in head.splitlines(keepends=True):
        if line.lstrip().startswith('import ') and any(name in line for name in OLD_IMPORTS):
            continue
        filtered.append(line)
    head=''.join(filtered)
    head=head.replace('</script>',IMPORT+'\n</script>',1)
    return head+TEMPLATE


def main():
    ap=argparse.ArgumentParser()
    ap.add_argument('root',nargs='?',default='.')
    ap.add_argument('--check',action='store_true')
    a=ap.parse_args()
    root=Path(a.root).resolve();vp=root/VUE;cp=root/CSS
    print('ANDARMA · Fase 4A v2 · Hero editorial compatible 3U-3Z')
    print('Proyecto:',root)
    if not vp.is_file():raise ValueError('No se encontró '+str(VUE))
    old=vp.read_text('utf-8')
    if cp.exists() or MARK in old or IMPORT in old:
        print('YA APLICADO. No se modificó nada.')
        return
    new=make_changes(old)
    print('EDITAR',VUE)
    print('CREAR',CSS)
    print('Conservar: script del carrusel, galería del administrador, mapa, Home')
    if a.check:
        print('VALIDACION OK. --check: ningún archivo modificado.')
        return
    backup=root/('.andarma_respaldo_fase4a_v2_'+datetime.now().strftime('%Y%m%d_%H%M%S_%f'))
    dest=backup/VUE;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(vp,dest)
    cp.parent.mkdir(parents=True,exist_ok=True)
    tmp_vue=vp.with_name(vp.name+'.fase4av2tmp')
    tmp_css=cp.with_name(cp.name+'.fase4av2tmp')
    try:
        tmp_vue.write_text(new,'utf-8');tmp_css.write_text(STYLES,'utf-8')
        tmp_css.replace(cp);tmp_vue.replace(vp)
    except Exception:
        for f in [tmp_vue,tmp_css]:
            if f.exists():f.unlink()
        shutil.copy2(dest,vp)
        if cp.exists():cp.unlink()
        raise
    print('CAMBIOS APLICADOS. Respaldo:',backup)
    print('Validar: cd frontend && npm run build')
    print('Sin commits, pushes ni despliegues.')

if __name__=='__main__':
    try:main()
    except (ValueError,OSError) as exc:
        print('VALIDACION DETENIDA:',exc)
        sys.exit(1)
