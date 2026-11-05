// ANDARMA: máscaras de captura para Vue 3 (v-money3).
// masked:false devuelve el número SIN símbolos ni separadores en el modelo.
// modelModifiers.number:true mantiene valores numéricos en los formularios.
const base = {
  masked: false,
  thousands: ',',
  decimal: '.',
  disableNegative: true,
  allowBlank: true,
  modelModifiers: { number: true }
}
export const mxnMask = { ...base, prefix: '$ ', precision: 2, min: 0 }
export const decimalMask = { ...base, prefix: '', precision: 2, min: 0 }
