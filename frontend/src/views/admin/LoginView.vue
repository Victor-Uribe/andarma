<script setup>
import '../../assets/andarma-brand-admin.css'
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { post, setToken } from '../../services/api'
import { ArrowLeft, ArrowRight, Eye, EyeOff, LockKeyhole, ShieldCheck, CheckCircle2, AlertCircle } from '../../components/admin/AndarmaFaIcons.js'
// ANDARMA_FASE17_TOKEN_SEGURO: notificacion accesible de sesion vencida.
const email = ref(''), password = ref(''), error = ref(''), busy = ref(false), showPassword = ref(false)
const router = useRouter(), route = useRoute()
async function login() { if (busy.value) return; busy.value = true; error.value = ''; try { const result = await post('/admin/login', { email: email.value, password: password.value }); if (!result.token) throw new Error('La respuesta no contiene un token de acceso.'); setToken(result.token); const redirect = typeof route.query.redirect === 'string' && route.query.redirect.startsWith('/admin/') ? route.query.redirect : '/admin/dashboard'; await router.replace(redirect) } catch (e) { error.value = e.status === 429 ? 'Demasiados intentos. Espera un minuto antes de intentar nuevamente.' : e.status === 401 || e.status === 422 ? 'Revisa tu correo y contraseña.' : e.message } finally { busy.value = false } }
</script>
<template>
    <div class="admin-login admin-login-v26">
        <section class="login-visual">
            <div class="login-art-overlay"></div>
            <div class="login-logo">
                <div class="login-logo-mark andarma-real-logo"><img src="/andarma-logo-blanco.png" alt="" width="46"
                        height="46" /></div>
                <div><strong>ANDARMA</strong><small>DESARROLLOS</small></div>
            </div>
            <div class="login-visual-copy"><span class="login-eyebrow"><span class="login-eyebrow-line"></span>
                    PLATAFORMA PRIVADA</span>
                <h1>Un espacio para<br><em>gestionar el futuro.</em></h1>
                <p>Control preciso de tu operación inmobiliaria, en una experiencia diseñada con claridad.</p>
                <div class="login-visual-features"><span>
                        <CheckCircle2 :size="16" /> Gestión de proyectos
                    </span><span>
                        <CheckCircle2 :size="16" /> Información centralizada
                    </span></div>
            </div>
            <div class="login-visual-bottom"><span>ANDARMA · PANEL DE CONTROL</span><span>01 / 01</span></div>
        </section>
        <section class="login-panel">
            <RouterLink to="/" class="login-back">
                <ArrowLeft :size="16" /> Volver a la página pública
            </RouterLink>
            <div class="login-form-wrap"><!-- ANDARMA_FASE7D_LOGO_BACKTOP -->
                <div class="login-form-icon andarma-login-mark"><img src="/andarma-logo-original.png" alt="ANDARMA"
                        width="48" height="48" /></div><span class="admin-kicker">ACCESO AL SISTEMA</span>
                <h2>Bienvenido de nuevo.</h2>
                <p>Ingresa tus credenciales para continuar al administrador de ANDARMA.</p>
                <p v-if="route.query.expired === '1'" class="admin-alert" role="status">Tu sesión terminó o fue revocada.
                    Inicia sesión nuevamente para continuar.</p>
                <form @submit.prevent="login"><label class="login-field"><span>Correo electrónico</span><input
                            v-model.trim="email" type="email" autocomplete="username" inputmode="email"
                            placeholder="correo@andarma.mx" required :disabled="busy" /></label><label
                        class="login-field"><span>Contraseña</span>
                        <div class="login-password"><input v-model="password" :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password" placeholder="Ingresa tu contraseña" required
                                :disabled="busy" /><button type="button"
                                :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                @click="showPassword = !showPassword">
                                <EyeOff v-if="showPassword" :size="19" />
                                <Eye v-else :size="19" />
                            </button></div>
                    </label>
                    <p v-if="error" class="admin-alert error" role="alert">
                        <AlertCircle :size="17" /> {{ error }}
                    </p><button class="admin-primary full login-submit" type="submit"
                        :disabled="busy"><span>{{ busy ? 'Validando acceso…' : 'Entrar al administrador' }}</span>
                        <ArrowRight :size="18" />
                    </button>
                </form>
                <div class="login-trust">
                    <ShieldCheck :size="19" /><span>Conexión protegida. Acceso exclusivo para personal
                        autorizado.</span>
                </div>
            </div><small class="login-copyright">© 2026 ANDARMA · Gestión inmobiliaria</small>
        </section>
    </div>
</template>
