/** Tapsende Katzenpfoten ziehen quer über den Bildschirm und verblassen. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__cat-paw-trail';
    layer.setAttribute('aria-hidden', 'true');
    const colors = ['#ff1744', '#ff9100', '#ffea00', '#00e5ff', '#d500f9', '#76ff03', '#ff4081', '#651fff'];

    for (let index = 0; index < 18; index += 1) {
      const paw = document.createElement('span');
      const curve = Math.sin(index * 0.72) * 12;
      const side = index % 2 === 0 ? -1 : 1;
      paw.className = 'kw-masha-feedly__cat-paw';
      paw.style.setProperty('--paw-x', `${index * 6 - 3}vw`);
      paw.style.setProperty('--paw-y', `${43 + curve}vh`);
      paw.style.setProperty('--paw-delay', `${index * 0.22}s`);
      paw.style.setProperty('--paw-tilt', `${side * (12 + Math.abs(curve) * 0.35)}deg`);
      paw.style.setProperty('--paw-color', colors[index % colors.length]);
      colors.slice(0, 4).forEach((color, toeIndex) => paw.style.setProperty(`--paw-toe-${toeIndex + 1}`, colors[(index + toeIndex + 2) % colors.length]));
      paw.innerHTML = '<svg viewBox="0 0 64 64" aria-hidden="true" focusable="false"><g><path class="kw-masha-feedly__cat-paw-pad" d="m40.1 33.51a11.78 11.78 0 0 0 -16.2 0l-9.77 9.24c-5.6 5.09-1.56 14.82 6 14.44 4.1-.11 7.43-2.21 11.86-2.13 4.59-.08 8 1.77 12.12 2.13 7.43.17 11.27-9.45 5.75-14.44z"/><path class="kw-masha-feedly__cat-paw-toe-1" d="m56.92 19.93c-3.82-.75-6.74 3.5-7.5 7.16-2.28 11.33 8.7 13.49 11.27 2.5 1.05-4.71-.61-8.96-3.77-9.66z"/><path class="kw-masha-feedly__cat-paw-toe-2" d="m40.73 26.05c3.8 0 6.89-4.32 6.89-9.62-.38-12.76-13.4-12.76-13.77 0 0 5.3 3.09 9.62 6.88 9.62z"/><path class="kw-masha-feedly__cat-paw-toe-3" d="m14.58 27.09c-.75-3.66-3.68-7.91-7.5-7.16-7.08 1.79-4.08 16.88 2.84 16.92 3.92-.13 5.73-4.68 4.66-9.76z"/><path class="kw-masha-feedly__cat-paw-toe-4" d="m23.27 26.05c3.79 0 6.88-4.32 6.88-9.62-.37-12.76-13.39-12.76-13.77 0 0 5.3 3.09 9.62 6.89 9.62z"/></g></svg>';
      layer.append(paw);
    }

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 6500);
    return { catPaws: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.catPaws = { play };
})();
