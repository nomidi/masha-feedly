/** Drei feine, transparente Ringe breiten sich ruhig aus. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__serious-rings';
    layer.setAttribute('aria-hidden', 'true');
    for (let index = 0; index < 3; index += 1) {
      const ring = document.createElement('i');
      ring.className = 'kw-masha-feedly__serious-ring';
      ring.dataset.ring = String(index + 1);
      layer.append(ring);
    }
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 2200);
    return { rings: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.rings = { play };
})();
