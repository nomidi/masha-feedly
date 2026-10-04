/** Ein unaufdringlicher, mintfarbener Lichtimpuls über dem Bildschirm. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__serious-glow';
    layer.setAttribute('aria-hidden', 'true');
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 1800);
    return { glow: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.glow = { play };
})();
