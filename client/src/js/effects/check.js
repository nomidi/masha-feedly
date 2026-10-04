/** Schlichtes, gezeichnetes Häkchen als ruhige Abschlussbestätigung. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__serious-check';
    layer.setAttribute('aria-hidden', 'true');
    layer.innerHTML = '<svg viewBox="0 0 72 72" focusable="false" aria-hidden="true"><circle class="kw-masha-feedly__serious-check-ring" cx="36" cy="36" r="30"/><path class="kw-masha-feedly__serious-check-mark" d="m20 37 10 10 22-23"/></svg>';
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 1900);
    return { check: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.check = { play };
})();
