/** Tapsende Katzenpfoten ziehen quer über den Bildschirm und verblassen. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__cat-paw-trail';
    layer.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < 18; index += 1) {
      const paw = document.createElement('span');
      const curve = Math.sin(index * 0.72) * 12;
      const side = index % 2 === 0 ? -1 : 1;
      paw.className = 'kw-masha-feedly__cat-paw';
      paw.style.setProperty('--paw-x', `${index * 6 - 3}vw`);
      paw.style.setProperty('--paw-y', `${43 + curve}vh`);
      paw.style.setProperty('--paw-delay', `${index * 0.22}s`);
      paw.style.setProperty('--paw-tilt', `${side * (12 + Math.abs(curve) * 0.35)}deg`);
      paw.innerHTML = '<svg viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path d="M17 31c-5 2-8 8-6 14 2 5 7 7 12 4l8-4c2-1 4-1 6 0l8 4c5 3 10 1 12-4 2-6-1-12-6-14-4-2-8 0-12 2-3 2-7 2-10 0-4-2-8-4-12-2Z"/><ellipse cx="14" cy="23" rx="5.2" ry="7" transform="rotate(-22 14 23)"/><ellipse cx="25" cy="16" rx="5.2" ry="7" transform="rotate(-8 25 16)"/><ellipse cx="39" cy="16" rx="5.2" ry="7" transform="rotate(8 39 16)"/><ellipse cx="50" cy="23" rx="5.2" ry="7" transform="rotate(22 50 23)"/></svg>';
      layer.append(paw);
    }

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 6500);
    return { catPaws: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.catPaws = { play };
})();
