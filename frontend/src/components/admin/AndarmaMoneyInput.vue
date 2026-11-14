<script setup>
import { computed, nextTick, ref, watch } from 'vue'
// ANDARMA_MONEY_INPUT_V3
// Captura decimal literal: no aplicar v-money3 encima de este componente.
// El modelo guarda un string decimal SIN formato; el blur solo cambia la vista.
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  currency: { type: Boolean, default: true },
  required: { type: Boolean, default: false },
  decimals: { type: Number, default: 2 },
})
const emit = defineEmits(['update:modelValue', 'blur'])
const isFocused = ref(false)
const input = ref(null)
const raw = ref(toRaw(props.modelValue))
const error = ref('')

function toRaw(value) {
  if (value === null || value === undefined || value === '') return ''
  if (typeof value === 'number') return Number.isFinite(value) && value >= 0 ? String(value) : ''
  let s = String(value).trim().replace(/(?:MXN|\$)/gi, '').replace(/\s/g, '')
  if (s.includes(',') && s.includes('.')) {
    s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '')
  } else if (s.includes(',')) {
    s = /^\d{1,3}(,\d{3})+(?:\.\d+)?$/.test(s) ? s.replace(/,/g, '') : s.replace(',', '.')
  }
  return s
}
function validPart(s) {
  if (s === '') return true
  return new RegExp(`^\\d+(?:\\.\\d{0,${props.decimals}})?$`).test(s)
}
const shown = computed(() => {
  if (isFocused.value || raw.value === '') return raw.value
  if (!validPart(raw.value) || !Number.isFinite(Number(raw.value))) return raw.value
  const parts = Number(raw.value).toFixed(props.decimals).split('.')
  const whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',')
  const formatted = parts.length === 2 ? `${whole}.${parts[1]}` : whole
  return props.currency ? `$ ${formatted}` : formatted
})
watch(() => props.modelValue, value => {
  const incoming = toRaw(value)
  if (!isFocused.value && incoming !== raw.value) raw.value = incoming
})
function onFocus() {
  isFocused.value = true
  error.value = ''
  nextTick(() => {
    if (input.value && input.value.value !== raw.value) input.value.value = raw.value
  })
}
function onInput(event) {
  const el = event.target
  const proposed = el.value
  // No transformar ni insertar ceros: si la entrada no es válida, se restaura.
  // Rechaza silenciosamente una tercera cifra decimal en vez de guardarla recortada.
  if (!validPart(proposed)) {
    const pos = el.selectionStart ?? proposed.length
    el.value = raw.value
    const adjusted = Math.min(pos, raw.value.length)
    try { el.setSelectionRange(adjusted, adjusted) } catch (_) {}
    return
  }
  raw.value = proposed
  emit('update:modelValue', proposed)
}
function onBlur(event) {
  isFocused.value = false
  // Mantener el valor numérico exacto; no modificar ni redondear el modelo.
  if (raw.value && !validPart(raw.value)) {
    error.value = 'Valor decimal inválido'
  }
  emit('blur', event)
}
</script>

<template>
  <input ref="input" type="text" inputmode="decimal" autocomplete="off"
    :value="shown" :required="required" :aria-invalid="Boolean(error)"
    @focus="onFocus" @input="onInput" @blur="onBlur" />
</template>
