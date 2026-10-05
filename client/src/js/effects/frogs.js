/** Fünf kleine Frösche hüpfen versetzt als bunte Parade durchs Bild. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__frog-parade';
    layer.setAttribute('aria-hidden', 'true');
    const colors = ['#42b85a', '#ff8f3d', '#55b9e9', '#bc71d8', '#f4c542'];

    colors.forEach((color, index) => {
      const frog = document.createElement('span');
      frog.className = 'kw-masha-feedly__parade-frog';
      frog.style.animationDelay = `${index * 0.52}s`;
      frog.style.setProperty('--frog-color', color);
      frog.style.setProperty('--frog-lane', `${(index % 3) * 3.4}vh`);
      frog.innerHTML = '<svg viewBox="0 0 120 100" aria-hidden="true" focusable="false"><ellipse class="kw-masha-feedly__frog-shadow" cx="59" cy="88" rx="36" ry="5"/><path class="kw-masha-feedly__frog-leg" d="M36 67 19 78l-8-2-5 7 11 5 21-8m46-13 17 11 8-2 5 7-11 5-21-8"/><ellipse class="kw-masha-feedly__frog-body" cx="60" cy="62" rx="37" ry="27"/><path class="kw-masha-feedly__frog-belly" d="M39 68c3 14 39 18 43 0-7-9-35-10-43 0Z"/><circle class="kw-masha-feedly__frog-eye" cx="42" cy="35" r="15"/><circle class="kw-masha-feedly__frog-eye" cx="77" cy="35" r="15"/><circle class="kw-masha-feedly__frog-pupil" cx="45" cy="35" r="5"/><circle class="kw-masha-feedly__frog-pupil" cx="74" cy="35" r="5"/><path class="kw-masha-feedly__frog-smile" d="M44 57q16 12 32 0"/><circle class="kw-masha-feedly__frog-cheek" cx="30" cy="53" r="5"/><circle class="kw-masha-feedly__frog-cheek" cx="89" cy="53" r="5"/></svg>';
      layer.append(frog);
    });

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 10000);
    return { frogs: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.frogs = { play };
})();
