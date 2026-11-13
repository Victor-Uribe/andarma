/* ANDARMA | Preferencias de privacidad - menú público | 2026-11-12.
 * GTM se mantiene desactivado hasta aprobar el inventario y GTMS_AUDITADO=true.
 */
(() => {
  'use strict';
  const KEY = 'andarma_cookie_preferences_v1';
  const MAX_AGE = 180 * 24 * 60 * 60 * 1000;
  const GTM_ID = 'GTM-522W3R2F';
  const GTM_AUDITADO = false;
  const isPublic = !location.pathname.startsWith('/admin');
  const gtag = (...args) => { if (typeof window.gtag === 'function') window.gtag(...args); };
  const getStored = () => {
    try {
      const value = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (!value || value.version !== 1 || !Number.isFinite(value.updated_at) || Date.now() - value.updated_at > MAX_AGE) return null;
      if (typeof value.analytics !== 'boolean' || typeof value.marketing !== 'boolean') return null;
      return value;
    } catch (_) { return null; }
  };
  let current = getStored();
  let gtmLoaded = false;
  const applyGoogleState = (value) => gtag('consent', 'update', {
    analytics_storage: value?.analytics ? 'granted' : 'denied',
    ad_storage: value?.marketing ? 'granted' : 'denied',
    ad_user_data: value?.marketing ? 'granted' : 'denied',
    ad_personalization: value?.marketing ? 'granted' : 'denied'
  });
  const maybeStartGtm = (value) => {
    if (!isPublic || !GTM_AUDITADO || gtmLoaded || !(value?.analytics || value?.marketing)) return;
    gtmLoaded = true;
    (window.dataLayer = window.dataLayer || []).push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    const script = document.createElement('script');
    script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(GTM_ID)}`;
    script.async = true;
    script.onerror = () => { gtmLoaded = false; };
    document.head.appendChild(script);
  };
  if (current) { applyGoogleState(current); maybeStartGtm(current); }
  if (!isPublic) return;

  const banner = document.createElement('section');
  banner.className = 'ad-cookie-bar';
  banner.setAttribute('aria-label', 'Preferencias de cookies de ANDARMA');
  banner.innerHTML = `<div class="ad-cookie-bar__text"><span class="ad-cookie-bar__eyebrow">TU PRIVACIDAD IMPORTA</span><h2>Una experiencia transparente</h2><p>Usamos almacenamiento necesario para el funcionamiento del sitio. Tú decides si autorizas medición adicional y publicidad. Puedes cambiarlo cuando quieras.</p><button type="button" class="ad-cookie-link" data-ad-cookie-action="policy">Conoce nuestra política de cookies</button></div><div class="ad-cookie-bar__actions"><button type="button" class="ad-cookie-btn ad-cookie-btn--primary" data-ad-cookie-action="accept">Aceptar todas</button><button type="button" class="ad-cookie-btn ad-cookie-btn--secondary" data-ad-cookie-action="reject">Rechazar opcionales</button><button type="button" class="ad-cookie-btn ad-cookie-btn--quiet" data-ad-cookie-action="config">Personalizar</button></div>`;
  const modal = document.createElement('dialog');
  modal.className = 'ad-cookie-prefs';
  modal.setAttribute('aria-labelledby', 'adCookiePrefsTitle');
  modal.innerHTML = `<div class="ad-cookie-prefs__top"><span class="ad-cookie-bar__eyebrow">ANDARMA / PRIVACIDAD</span><button class="ad-cookie-prefs__close" aria-label="Cerrar configuración" type="button" data-ad-cookie-action="close">×</button><h2 id="adCookiePrefsTitle">Preferencias de cookies</h2><p>Elige qué tecnologías opcionales autorizas. Las necesarias siempre permanecen activas.</p></div><div class="ad-cookie-option"><div><strong>Necesarias</strong><p>Seguridad y preferencias básicas del sitio.</p></div><span class="ad-cookie-needed">Siempre activas</span></div><label class="ad-cookie-option" for="adCookieAnalytics"><span><strong>Analíticas</strong><span class="ad-cookie-option__desc">Medición externa, solo si está verificada.</span></span><input type="checkbox" id="adCookieAnalytics"></label><label class="ad-cookie-option" for="adCookieMarketing"><span><strong>Marketing</strong><span class="ad-cookie-option__desc">Publicidad y conversiones, solo si están verificadas.</span></span><input type="checkbox" id="adCookieMarketing"></label><div class="ad-cookie-prefs__actions"><button class="ad-cookie-btn ad-cookie-btn--secondary" type="button" data-ad-cookie-action="reject">Rechazar opcionales</button><button class="ad-cookie-btn ad-cookie-btn--primary" type="button" data-ad-cookie-action="save">Guardar preferencias</button></div>`;
  document.body.append(banner, modal);
  const analytics = modal.querySelector('#adCookieAnalytics');
  const marketing = modal.querySelector('#adCookieMarketing');
  const showBanner = () => { banner.hidden = Boolean(current); };
  const configure = () => {
    analytics.checked = Boolean(current?.analytics);
    marketing.checked = Boolean(current?.marketing);
    if (!modal.open) modal.showModal();
  };
  const update = (selected) => {
    const previous = current;
    current = { version: 1, updated_at: Date.now(), analytics: Boolean(selected.analytics), marketing: Boolean(selected.marketing) };
    try { localStorage.setItem(KEY, JSON.stringify(current)); } catch (_) { /* La preferencia dura únicamente durante esta página. */ }
    applyGoogleState(current);
    if (modal.open) modal.close();
    showBanner();
    // Una etiqueta de terceros ya cargada puede no ser plenamente reversible sin recargar.
    if (GTM_AUDITADO && gtmLoaded && previous && ((previous.analytics && !current.analytics) || (previous.marketing && !current.marketing))) {
      location.reload(); return;
    }
    maybeStartGtm(current);
  };
  showBanner();
  document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-ad-cookie-open]');
    if (opener) { event.preventDefault(); configure(); return; }
    const button = event.target.closest('[data-ad-cookie-action]');
    if (!button) return;
    const action = button.getAttribute('data-ad-cookie-action');
    if (action === 'accept') update({ analytics: true, marketing: true });
    if (action === 'reject') update({ analytics: false, marketing: false });
    if (action === 'save') update({ analytics: analytics.checked, marketing: marketing.checked });
    if (action === 'config') configure();
    if (action === 'close' && modal.open) modal.close();
    if (action === 'policy') {
      const link = document.querySelector('[data-legal="cookies"]');
      if (link) link.click();
    }
  });
  modal.addEventListener('click', (event) => { if (event.target === modal) modal.close(); });
})();
