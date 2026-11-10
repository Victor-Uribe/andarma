<script setup>
import {ref,computed,onUnmounted} from 'vue'
import {post} from '../services/api'
import {UploadCloud,ZoomIn,ZoomOut,Check,X,Image as ImageIcon} from './admin/AndarmaFaIcons.js'
const props=defineProps({modelValue:{type:String,default:''},ratio:{type:Number,default:16/9},label:{type:String,default:'Imagen'},maxKb:{type:Number,default:450}})
const emit=defineEmits(['update:modelValue'])
const input=ref(null),canvas=ref(null),open=ref(false),busy=ref(false),error=ref(''),zoom=ref(1),image=ref(null),offsetX=ref(0),offsetY=ref(0)
let sourceUrl=null,drag=null
const ratioText=computed(()=>props.ratio===1?'1:1':props.ratio===4/3?'4:3':'16:9')
function cleanup(){if(sourceUrl){URL.revokeObjectURL(sourceUrl);sourceUrl=null}}
onUnmounted(cleanup)
function begin(event){const file=event.target.files?.[0];if(event.target)event.target.value='';if(!file)return;error.value='';if(!['image/jpeg','image/png','image/webp'].includes(file.type))return error.value='Selecciona JPG, PNG o WebP.';if(file.size>12*1024*1024)return error.value='La fotografía original no debe superar 12 MB.';cleanup();sourceUrl=URL.createObjectURL(file);const img=new Image();img.onload=()=>{image.value=img;zoom.value=1;offsetX.value=0;offsetY.value=0;open.value=true;requestAnimationFrame(draw)};img.onerror=()=>{error.value='No se pudo leer la imagen.';cleanup()};img.src=sourceUrl}
function geometry(){const w=720,h=Math.round(720/props.ratio),im=image.value;const base=Math.max(w/im.width,h/im.height)*zoom.value;const dw=im.width*base,dh=im.height*base;const tx=Math.max(w-dw,Math.min(0,(w-dw)/2+offsetX.value)),ty=Math.max(h-dh,Math.min(0,(h-dh)/2+offsetY.value));return {w,h,dw,dh,tx,ty}}
function draw(){if(!image.value||!canvas.value)return;const g=geometry(),c=canvas.value;c.width=g.w;c.height=g.h;const ctx=c.getContext('2d');ctx.fillStyle='#f4f7fb';ctx.fillRect(0,0,g.w,g.h);ctx.drawImage(image.value,g.tx,g.ty,g.dw,g.dh)}
function zoomChange(v){zoom.value=Number(v);draw()}
function pointerDown(e){drag={x:e.clientX,y:e.clientY,ox:offsetX.value,oy:offsetY.value};e.currentTarget.setPointerCapture(e.pointerId)}
function pointerMove(e){if(!drag)return;const rect=e.currentTarget.getBoundingClientRect();offsetX.value=drag.ox+(e.clientX-drag.x)*720/rect.width;offsetY.value=drag.oy+(e.clientY-drag.y)*720/rect.width;draw()}
function pointerUp(){drag=null}
function close(){if(busy.value)return;open.value=false;image.value=null;cleanup()}
// ANDARMA_HD_EXPORT_V1: la vista previa conserva 720 px; el archivo final se dibuja en HD.
// Se limita la exportación al detalle útil de la fuente para evitar ampliación artificial.
// ANDARMA_HD_EXPORT_V2: exportar en resolución de destino, nunca el canvas de previsualización.
// Si el original es pequeño, el archivo tendrá dimensiones HD pero no recuperará detalle perdido;
// por eso el editor muestra la resolución útil del recorte antes de aplicar.
const exportInfo=computed(()=>{
  const im=image.value
  if(!im)return null
  const g=geometry()
  const nativeCropW=Math.round(g.w*im.naturalWidth/g.dw)
  const nativeCropH=Math.round(g.h*im.naturalHeight/g.dh)
  const targetW=props.ratio>=1.65?1920:props.ratio>=1.2?1600:1200
  const targetH=Math.round(targetW/props.ratio)
  return {sourceW:im.naturalWidth,sourceH:im.naturalHeight,
    nativeCropW,nativeCropH,targetW,targetH,lowResolution:nativeCropW<targetW||nativeCropH<targetH}
})
function exportCanvas(){
  const im=image.value
  if(!im)throw new Error('No hay una imagen cargada.')
  const g=geometry()
  const info=exportInfo.value
  if(!info)throw new Error('No se pudo calcular la resolución.')
  // 1920px para hero 16:9. El encuadre se escala desde las coordenadas de la vista previa.
  const out=document.createElement('canvas')
  out.width=info.targetW
  out.height=info.targetH
  const ctx=out.getContext('2d')
  if(!ctx)throw new Error('No se pudo crear el lienzo HD.')
  const scaleX=out.width/g.w,scaleY=out.height/g.h
  ctx.imageSmoothingEnabled=true
  ctx.imageSmoothingQuality='high'
  ctx.fillStyle='#f4f7fb'
  ctx.fillRect(0,0,out.width,out.height)
  ctx.drawImage(im,g.tx*scaleX,g.ty*scaleY,g.dw*scaleX,g.dh*scaleY)
  return out
}
function blobQuality(c,q){return new Promise(resolve=>c.toBlob(resolve,'image/webp',q))}
async function publish(){
  if(busy.value||!image.value)return
  busy.value=true
  error.value=''
  try{
    const output=exportCanvas()
    // Evitar comprimir grandes banners a los 450 KB destinados a miniaturas.
    const budgetKb=Math.max(props.maxKb,props.ratio>=1.65?2500:1400)
    let blob=null
    for(const q of [.92,.89,.85,.80]){
      blob=await blobQuality(output,q)
      if(blob&&blob.size<=budgetKb*1024)break
    }
    if(!blob)throw new Error('No se pudo generar la imagen WebP.')
    if(blob.size>budgetKb*1024){
      throw new Error('El archivo supera '+budgetKb+' KB; selecciona una imagen original menos pesada.')
    }
    const data=new FormData()
    data.append('file',new File([blob],`andarma-${Date.now()}.webp`,{type:'image/webp'}))
    const result=await post('/admin/media',data)
    if(!result.url)throw new Error('La API no devolvió una URL válida.')
    emit('update:modelValue',result.url)
    open.value=false
    image.value=null
    cleanup()
  }catch(e){error.value=e.message||'No se pudo subir la imagen.'}
  finally{busy.value=false}
}
</script>
<template>
 <div class="ad-image-control"><div class="ad-image-control-top"><span>{{label}} · {{ratioText}}</span><small>WebP en alta resolución · optimización inteligente</small></div><div v-if="modelValue" class="ad-image-preview"><img :src="modelValue" :alt="label"/><button type="button" class="admin-outline" @click="input?.click()">Cambiar imagen</button></div><button v-else type="button" class="ad-image-select" @click="input?.click()"><UploadCloud :size="24"/><strong>Seleccionar y recortar</strong><small>JPG, PNG o WebP · original hasta 12 MB</small></button><input ref="input" class="ad-visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" @change="begin"/><p v-if="error" class="ad-inline-error" role="alert">{{error}}</p></div>
 <div v-if="open" class="ad-crop-overlay" role="dialog" aria-modal="true" :aria-label="'Recortar '+label" @click.self="close"><div class="ad-crop-dialog"><header><div><small>EDITOR DE IMAGEN</small><h3>Encuadra tu fotografía</h3><p>Arrastra para ajustar el encuadre. Solo se enviará la versión recortada y comprimida.</p></div><button type="button" aria-label="Cerrar" @click="close"><X :size="20"/></button></header><div class="ad-crop-surface"><canvas ref="canvas" :style="{aspectRatio:String(ratio)}" @pointerdown="pointerDown" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp"/></div><p v-if="exportInfo" role="status" style="font-size:12px;line-height:1.5;margin:8px 0;color:inherit">Original: {{ exportInfo.sourceW }} × {{ exportInfo.sourceH }} px · Detalle útil del recorte: {{ exportInfo.nativeCropW }} × {{ exportInfo.nativeCropH }} px · Exportación: {{ exportInfo.targetW }} × {{ exportInfo.targetH }} px. <strong v-if="exportInfo.lowResolution" style="color:#b45309">Atención: el original o el zoom no aportan suficientes píxeles; subir la resolución no recupera nitidez. Usa una imagen original más grande o reduce el zoom.</strong></p><label class="ad-crop-zoom"><ZoomOut :size="18"/><input :value="zoom" type="range" min="1" max="3" step="0.02" @input="zoomChange($event.target.value)"/><ZoomIn :size="18"/></label><p v-if="error" class="ad-inline-error" role="alert">{{error}}</p><footer><button type="button" class="admin-outline" :disabled="busy" @click="close">Cancelar</button><button type="button" class="admin-primary" :disabled="busy" @click="publish"><Check :size="17"/>{{busy?'Optimizando y subiendo…':'Aplicar imagen'}}</button></footer></div></div>
</template>
