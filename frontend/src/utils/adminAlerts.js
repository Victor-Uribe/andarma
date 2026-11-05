// ANDARMA_V45_DELETE_DESIGN: confirmaciones destructivas mediante variant:danger
import Swal from 'sweetalert2'

const base = {
  buttonsStyling: false,
  reverseButtons: true,
  heightAuto: false,
  background: '#ffffff',
  color: '#17324e',
  customClass: {
    popup: 'andarma-swal',
    title: 'andarma-swal__title',
    htmlContainer: 'andarma-swal__text',
    confirmButton: 'andarma-swal__confirm',
    cancelButton: 'andarma-swal__cancel',
    actions: 'andarma-swal__actions',
    closeButton: 'andarma-swal__close'
  }
}

export async function confirmAction({title, text = '', confirmText = 'Confirmar', cancelText = 'Cancelar', variant = 'primary'} = {}) {
  const result = await Swal.fire({
    ...base,
    title,
    text,
    focusCancel: true,
    icon: variant === 'danger' ? 'warning' : 'question',
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: cancelText,
    customClass: {
      ...base.customClass,
      confirmButton: variant === 'danger' ? 'andarma-swal__confirm is-danger' : 'andarma-swal__confirm',
      popup: variant === 'danger' ? 'andarma-swal andarma-delete-dialog' : 'andarma-swal',
    }
  })
  return result.isConfirmed
}

export function notifySuccess(title, text = '') {
  return Swal.fire({
    ...base,
    toast: true,
    position: 'top-end',
    timer: 2400,
    timerProgressBar: true,
    showConfirmButton: false,
    icon: 'success',
    title,
    text,
  })
}

export function notifyError(title, text = '') {
  return Swal.fire({
    ...base,
    icon: 'error',
    title,
    text,
    confirmButtonText: 'Entendido'
  })
}
