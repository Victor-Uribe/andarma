#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ANDARMA Fase 4A: hero editorial minimalista sobre Fase 3X/3Y.
Uso:
  python3 ANDARMA_Fase4A_Hero_Editorial_Minimalista.py . --check
  python3 ANDARMA_Fase4A_Hero_Editorial_Minimalista.py .
No cambia API, datos, galeria, mapa, ni Home. Sin git ni despliegue.
"""
from pathlib import Path
from datetime import datetime
import argparse, shutil, sys

VUE = Path('frontend/src/components/public/AndarmaDevelopmentHeroSlider.vue')
CSS = Path('frontend/src/assets/andarma-fase4a-hero-editorial-minimal.css')
IMPORT = "import '../../assets/andarma-fase4a-hero-editorial-minimal.css'"
MARK = 'ANDARMA_FASE4A_HERO_EDITORIAL'

STYLES = r'''/* ANDARMA_FASE4A_HERO_EDITORIAL
   Fotografia protagonista + composicion minimalista editorial.
   Modifica exclusivamente /desarrollos. No modifica el DOM ni el carrusel. */
.adp-map-page .ad3v-hero {
  min-height: clamp(620px, 88svh, 940px) !important;
  background: #09243d;
  isolation: isolate;
}
/* La imagen permanece visible; el degradado sustituye al cristal opaco. */
.adp-map-page .ad3v-hero__shade {
  background:
    linear-gradient(180deg, rgba(4,17,35,.35) 0%, rgba(5,22,42,.19) 23%, rgba(2,19,38,.38) 73%, rgba(3,18,37,.82) 100%),
    linear-gradient(90deg, rgba(6,28,53,.24), rgba(6,28,53,.12) 47%, rgba(6,28,53,.24)) !important;
}
.adp-map-page .ad3v-hero__shell {
  box-sizing: border-box;
  width: 100%;
  max-width: 1600px;
  min-height: inherit;
  margin-inline: auto;
  padding: clamp(120px,15vh,170px) clamp(24px,5vw,86px) clamp(24px,4vh,46px) !important;
  grid-template-columns: minmax(0,1fr) !important;
  align-items: stretch;
}
/* SIN tarjeta, borde, blur ni falsa sombra tipo dashboard. */
.adp-map-page .ad3v-hero__headline {
  box-sizing: border-box;
  position: relative;
  isolation: auto;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: center !important;
  justify-self: center !important;
  align-self: center !important;
  width: min(100%, 980px) !important;
  max-width: 980px !important;
  min-height: 0 !important;
  padding: 15px 0 30px !important;
  margin: 0 auto !important;
  gap: 0 !important;
  border: 0 !important;
  border-radius: 0 !important;
  background: transparent !important;
  box-shadow: none !important;
  -webkit-backdrop-filter: none !important;
  backdrop-filter: none !important;
  text-align: center !important;
}
.adp-map-page .ad3v-hero__headline::before,
.adp-map-page .ad3v-hero__headline::after {
  display: none !important;
  content: none !important;
}
/* Kicker editorial, sin recuadro. */
.adp-map-page .ad3v-hero__eyebrow {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  align-self: center !important;
  max-width: 90%;
  margin: 0 auto clamp(17px,2.7vh,27px) !important;
  padding: 0 !important;
  background: transparent !important;
  border: none !important;
  border-radius: 0 !important;
  color: #d6eaff !important;
  font: 800 11px/1.5 'DM Sans',sans-serif !important;
  letter-spacing: .21em !important;
  text-transform: uppercase;
  text-shadow: 0 2px 12px rgba(0,12,30,.55);
}
.adp-map-page .ad3v-hero__title {
  display: block;
  width: 100% !important;
  max-width: 960px !important;
  margin: 0 auto !important;
  color: #fff !important;
  font: 750 clamp(56px,7.1vw,112px)/1.03 'Manrope','DM Sans',sans-serif !important;
  letter-spacing: -.075em !important;
  overflow-wrap: break-word !important;
  word-break: normal !important;
  text-wrap: balance;
  text-align: center !important;
  text-shadow: 0 8px 34px rgba(0,12,27,.57), 0 1px 3px rgba(0,12,27,.38) !important;
}
/* Separador fino arquitectonico, en lugar de caja oscura. */
.adp-map-page .ad3v-hero__title::after {
  content: '';
  display: block;
  width: 54px;
  height: 2px;
  margin: clamp(20px,3vh,30px) auto 0;
  background: #38b6ff;
  box-shadow: 0 2px 13px rgba(0,102,224,.42);
}
.adp-map-page .ad3x-hero__description,
.adp-map-page .ad3v-hero__description {
  display: -webkit-box !important;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  width: min(100%,620px) !important;
  max-width: 620px !important;
  margin: clamp(15px,2.5vh,24px) auto 0 !important;
  padding: 0 !important;
  border: 0 !important;
  color: #f1f7ff !important;
  font: 500 clamp(15px,1.2vw,19px)/1.7 'DM Sans',sans-serif !important;
  letter-spacing: 0;
  text-align: center !important;
  text-wrap: pretty;
  overflow: hidden;
  text-shadow: 0 3px 14px rgba(0,10,26,.75);
}
/* CTA rectangular, sin circulo, contraste alto y foco accesible. */
.adp-map-page .ad3x-hero__cta,
.adp-map-page .ad3v-hero__cta {
  box-sizing: border-box;
  position: relative;
  display: inline-flex !important;
  flex-direction: row !important;
  align-items: center !important;
  justify-content: center !important;
  align-self: center !important;
  width: auto !important;
  min-width: 210px !important;
  max-width: 100% !important;
  min-height: 57px !important;
  height: auto !important;
  padding: 15px 26px !important;
  margin: clamp(26px,4vh,38px) auto 0 !important;
  gap: 20px !important;
  border: 1px solid rgba(255,255,255,.45) !important;
  border-radius: 2px !important;
  background: #0066e0 !important;
  color: #fff !important;
  box-shadow: 0 9px 24px rgba(0,18,47,.28) !important;
  font: 800 11px/1.5 'DM Sans',sans-serif !important;
  letter-spacing: .11em !important;
  text-transform: uppercase;
  text-decoration: none;
  text-align: center !important;
  white-space: normal !important;
  visibility: visible !important;
  opacity: 1 !important;
  transition: background-color .25s ease, transform .25s ease, box-shadow .25s ease;
}
.adp-map-page .ad3x-hero__cta:hover,
.adp-map-page .ad3v-hero__cta:hover {
  background: #0891ff !important;
  transform: translateY(-2px);
  box-shadow: 0 14px 29px rgba(0,15,48,.35) !important;
}
.adp-map-page .ad3x-hero__cta:focus-visible,
.adp-map-page .ad3v-hero__cta:focus-visible {
  outline: 3px solid #b4e9ff;
  outline-offset: 4px;
}
/* Controles visualmente separados de la composicion de texto. */
.adp-map-page .ad3v-hero__bottom {
  width: 100%;
  padding-top: clamp(26px,4vh,45px) !important;
  margin: 0 !important;
  justify-content: center !important;
}
.adp-map-page .ad3v-hero__controls {
  gap: 12px !important;
  justify-content: center !important;
}
.adp-map-page .ad3v-hero__feature {display: none !important;}
@media (max-width: 760px) {
  .adp-map-page .ad3v-hero {min-height: 670px !important;min-height: 92svh !important;}
  .adp-map-page .ad3v-hero__shell {padding: 120px 22px 26px !important;}
  .adp-map-page .ad3v-hero__headline {width: 100% !important;padding: 5px 0 25px !important;}
  .adp-map-page .ad3v-hero__eyebrow {font-size: 10px !important;letter-spacing: .12em !important;}
  .adp-map-page .ad3v-hero__title {font-size: clamp(41px,10vw,70px) !important;line-height: 1.06 !important;}
  .adp-map-page .ad3x-hero__description,
  .adp-map-page .ad3v-hero__description {font-size: 14px !important;line-height: 1.65 !important;-webkit-line-clamp: 4;}
  .adp-map-page .ad3x-hero__cta,
  .adp-map-page .ad3v-hero__cta {min-height: 53px !important;min-width: 190px !important;}
}
@media(prefers-reduced-motion:reduce){
  .adp-map-page .ad3x-hero__cta,
  .adp-map-page .ad3v-hero__cta {transition:none !important;}
}
'''

def main():
    parser = argparse.ArgumentParser(description='Hero editorial minimalista ANDARMA')
    parser.add_argument('root', nargs='?', default='.')
    parser.add_argument('--check', action='store_true')
    args=parser.parse_args()
    root=Path(args.root).resolve()
    vue=root/VUE; css=root/CSS
    print('ANDARMA · Fase 4A · Hero editorial minimalista')
    print('Proyecto:',root)
    if not vue.is_file():raise ValueError('No se encuentra '+str(VUE))
    old=vue.read_text('utf-8')
    if IMPORT in old or css.exists():
        print('YA APLICADO. No se requiere modificar archivos.')
        return
    if 'ad3v-hero' not in old or ('ad3x-hero__cta' not in old and 'ad3v-hero__cta' not in old):
        raise ValueError('No se reconocio el hero de las fases 3V/3X. No se modifica nada.')
    if '</script>' not in old:raise ValueError('Componente sin cierre de script.')
    # Evita que la Fase 3Z vuelva a imponerse visualmente si aun esta importada.
    lines=old.splitlines(keepends=True)
    removed=[line for line in lines if "import '../../assets/andarma-fase3z-hero-refinado.css'" in line]
    cleaned=''.join(line for line in lines if line not in removed)
    new=cleaned.replace('</script>',IMPORT+'\n</script>',1)
    if new.count(IMPORT)!=1:raise ValueError('No se pudo insertar el CSS de forma segura.')
    print('EDITAR',VUE)
    print('CREAR',CSS)
    if removed:print('DESACTIVAR importación de fase 3Z (sin borrar archivos)')
    if args.check:
        print('VALIDACION OK. No se modificó ningún archivo.')
        return
    backup=root/('.andarma_respaldo_fase4a_'+datetime.now().strftime('%Y%m%d_%H%M%S_%f'))
    dst=backup/VUE;dst.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(vue,dst)
    css.parent.mkdir(parents=True,exist_ok=True)
    try:
        css.write_text(STYLES,'utf-8')
        vue.write_text(new,'utf-8')
    except Exception:
        shutil.copy2(dst,vue)
        if css.exists():css.unlink()
        raise
    print('CAMBIOS APLICADOS. Respaldo:',backup)
    print('Compilar: cd frontend && npm run build')
    print('Sin commits, pushes ni despliegues.')

if __name__=='__main__':
    try:main()
    except (OSError,ValueError) as exc:
        print('VALIDACION DETENIDA:',exc)
        sys.exit(1)
