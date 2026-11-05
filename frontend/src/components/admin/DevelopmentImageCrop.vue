<script setup>
import { ref, computed, nextTick, onUnmounted } from 'vue'

const props = defineProps({ currentUrl: { type: String, default: '' } })
const emit = defineEmits(['change','remove'])
const picker = ref(null)
const cropCanvas = ref(null)
const croppedUrl = ref('')
const showEditor = ref(false)
const error = ref('')
const isProcessing = ref(false)
const zoom = ref(1)
const dx = ref(0)
const dy = ref(0)
const fileSize = ref(0)
const hasPhoto = computed(() => croppedUrl.value || props.currentUrl)
const photoUrl = computed(() => croppedUrl.value || props.currentUrl)
let sourceImage = null
let sourceUrl = null
let pointerState = null
function cleanupSource() { if(sourceUrl){URL.revokeObjectURL(sourceUrl);sourceUrl=null} }
function cleanupPreview() { if(croppedUrl.value){URL.revokeObjectURL(croppedUrl.value);croppedUrl.value=''} }
onUnmounted(() => {cleanupSource(); cleanupPreview()})
function choose(){ picker.value?.click() }
function onPick(event){
  const f=event.target.files?.[0]
  event.target.value=''
  if(!f)return
  error.value=''
  if(!['image/png','image/jpeg','image/webp'].includes(f.type)){error.value='Elige una fotografía JPG, PNG o WebP.';return}
  if(f.size>10*1024*1024){error.value='La fotografía original no debe superar 10 MB.';return}
  cleanupSource()
  sourceUrl=URL.createObjectURL(f)
  const img=new Image()
  img.onload=async()=>{
    if(img.width<600||img.height<337){error.value='La imagen es demasiado pequeña. Se recomienda al menos 1200 × 675 px.';cleanupSource();return}
    sourceImage=img;zoom.value=1;dx.value=0;dy.value=0;showEditor.value=true
    await nextTick();draw()
  }
  img.onerror=()=>{error.value='No se pudo abrir la fotografía.';cleanupSource()}
  img.src=sourceUrl
}
function geometry(width=1200){
  const height=Math.round(width*9/16)
  const scale=Math.max(width/sourceImage.width,height/sourceImage.height)*zoom.value
  const w=sourceImage.width*scale, h=sourceImage.height*scale
  const x=Math.min(0,Math.max(width-w,(width-w)/2+dx.value*width/1200))
  const y=Math.min(0,Math.max(height-h,(height-h)/2+dy.value*width/1200))
  return {width,height,w,h,x,y}
}
function draw(){
  if(!cropCanvas.value||!sourceImage)return
  const g=geometry(),canvas=cropCanvas.value
  canvas.width=g.width;canvas.height=g.height
  const context=canvas.getContext('2d')
  context.imageSmoothingEnabled=true
  context.imageSmoothingQuality='high'
  context.fillStyle='#eef4f8'
  context.fillRect(0,0,g.width,g.height)
  context.drawImage(sourceImage,g.x,g.y,g.w,g.h)
}
function changeZoom(e){zoom.value=Number(e.target.value);draw()}
function pointerDown(e){pointerState={x:e.clientX,y:e.clientY,dx:dx.value,dy:dy.value};e.currentTarget.setPointerCapture(e.pointerId)}
function pointerMove(e){
  if(!pointerState)return
  const r=e.currentTarget.getBoundingClientRect()
  dx.value=pointerState.dx+(e.clientX-pointerState.x)*1200/r.width
  dy.value=pointerState.dy+(e.clientY-pointerState.y)*1200/r.width
  draw()
}
function pointerUp(){pointerState=null}
function makeBlob(canvas,quality){return new Promise(resolve=>canvas.toBlob(resolve,'image/webp',quality))}
async function applyCrop(){
  if(isProcessing.value||!sourceImage)return
  isProcessing.value=true;error.value=''
  try{
    let blob=null
    for(const size of [1200,1040,900,760]){
      const canvas=document.createElement('canvas'),g=geometry(size)
      canvas.width=g.width;canvas.height=g.height
      const ctx=canvas.getContext('2d')
      ctx.imageSmoothingQuality='high'
      ctx.drawImage(sourceImage,g.x,g.y,g.w,g.h)
      for(const quality of [.82,.72,.62,.50]){
        blob=await makeBlob(canvas,quality)
        if(blob && blob.type==='image/webp' && blob.size<=450*1024)break
      }
      if(blob && blob.type==='image/webp' && blob.size<=450*1024)break
    }
    if(!blob || blob.type!=='image/webp'||blob.size>450*1024)throw new Error('No se pudo obtener una imagen inferior a 450 KB. Prueba con otra fotografía.')
    cleanupPreview()
    croppedUrl.value=URL.createObjectURL(blob)
    fileSize.value=Math.round(blob.size/1024)
    emit('change',new File([blob],`andarma-desarrollo-${Date.now()}.webp`,{type:'image/webp'}))
    closeCrop(true)
  }catch(e){error.value=e.message||'No se pudo recortar la imagen.'}
  finally{isProcessing.value=false}
}
function closeCrop(force=false){if(isProcessing.value&&!force)return;showEditor.value=false;sourceImage=null;cleanupSource()}
function remove(){cleanupPreview();fileSize.value=0;emit('change',null);emit('remove')}
</script>

<template>
 <div class="ad-dev37-image">
   <div v-if="hasPhoto" class="ad-dev37-photo-preview">
     <img :src="photoUrl" alt="Fotografía comercial del desarrollo" />
     <div class="ad-dev37-photo-buttons">
       <span v-if="croppedUrl" class="ad-dev37-photo-ready"><i class="fa-solid fa-check-circle" aria-hidden="true"></i> Recorte listo · {{fileSize}} KB</span>
       <span v-else class="ad-dev37-photo-ready"><i class="fa-regular fa-image" aria-hidden="true"></i> Fotografía actual</span>
       <button type="button" class="ad-btn" @click="choose"><i class="fa-solid fa-crop-simple" aria-hidden="true"></i> Reemplazar y recortar</button>
       <button type="button" class="ad-btn" @click="remove"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Quitar</button>
     </div>
   </div>
   <button v-else type="button" class="ad-dev37-upload-zone" @click="choose">
     <span class="ad-dev37-upload-icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>
     <strong>Seleccionar y recortar fotografía</strong>
     <span>Arrastra el encuadre y ajusta el zoom antes de guardar</span>
     <small>JPG, PNG o WebP · original hasta 10 MB · salida 16:9 WebP de máximo 450 KB</small>
   </button>
   <input ref="picker" type="file" accept="image/png,image/jpeg,image/webp" class="ad-dev37-hidden-input" @change="onPick"/>
   <p v-if="error" class="ad-dev37-error" role="alert">{{error}}</p>
   <Teleport to="body">
     <div v-if="showEditor" class="ad-dev37-crop-backdrop" @click.self="closeCrop">
       <div class="ad-dev37-crop-dialog" role="dialog" aria-modal="true" aria-labelledby="ad-dev37-crop-title">
         <header><div><span class="ad-dev37-overline">FOTOGRAFÍA DEL DESARROLLO</span><h3 id="ad-dev37-crop-title">Ajustar imagen</h3><p>Arrastra la fotografía para elegir el encuadre. La vista previa respeta el formato 16:9.</p></div><button type="button" class="ad-icon-btn" aria-label="Cerrar" @click="closeCrop"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header>
         <div class="ad-dev37-crop-area"><canvas ref="cropCanvas" aria-label="Área de recorte de imagen" @pointerdown="pointerDown" @pointermove="pointerMove" @pointerup="pointerUp" @pointercancel="pointerUp" /></div>
         <label class="ad-dev37-zoom"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i><input type="range" min="1" max="3" step="0.02" :value="zoom" @input="changeZoom" aria-label="Zoom de fotografía"/><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></label>
         <p v-if="error" class="ad-dev37-error" role="alert">{{error}}</p>
         <footer><button type="button" class="admin-outline" :disabled="isProcessing" @click="closeCrop">Cancelar</button><button type="button" class="ad-btn ad-btn--primary" :disabled="isProcessing" @click="applyCrop"><i class="fa-solid fa-check" aria-hidden="true"></i> {{isProcessing?'Preparando...':'Usar este recorte'}}</button></footer>
       </div>
     </div>
   </Teleport>
 </div>
</template>
