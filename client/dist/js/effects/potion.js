/** Leuchtender Zauberkessel, aus dem bunte Blasen aufsteigen. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__potion-effect';
    layer.setAttribute('aria-hidden', 'true');

    const glow = document.createElement('span');
    glow.className = 'kw-masha-feedly__potion-glow';
    layer.append(glow);

    const cauldron = document.createElement('span');
    cauldron.className = 'kw-masha-feedly__potion-cauldron';
    cauldron.innerHTML = '<svg viewBox="0 0 180 150" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__cauldron-handle" d="M35 66H19c-18 0-18 34 0 34h16m110-34h16c18 0 18 34 0 34h-16"/><path class="kw-masha-feedly__cauldron-body" d="M37 55h106l-9 57c-2 15-13 25-29 25H75c-16 0-27-10-29-25z"/><path class="kw-masha-feedly__cauldron-rim" d="M31 49h118v16H31z"/><path class="kw-masha-feedly__cauldron-brew" d="M44 77h92l-4 34c-1 11-9 18-21 18H69c-12 0-20-7-21-18z"/><path class="kw-masha-feedly__cauldron-highlight" d="m57 87-3 24c0 6 3 10 8 12"/><circle class="kw-masha-feedly__cauldron-star" cx="90" cy="101" r="5"/><path class="kw-masha-feedly__cauldron-star" d="m112 90 2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/></svg>';
    layer.append(cauldron);

    const colors = ['#75f0ff', '#ff79d1', '#a8ff70', '#ffe56b', '#b69aff'];
    for (let index = 0; index < 18; index += 1) {
      const bubble = document.createElement('i');
      bubble.className = 'kw-masha-feedly__potion-bubble';
      bubble.style.setProperty('--bubble-x', `${27 + Math.random() * 46}%`);
      bubble.style.setProperty('--bubble-size', `${0.55 + Math.random() * 1.1}rem`);
      bubble.style.setProperty('--bubble-color', colors[index % colors.length]);
      bubble.style.setProperty('--bubble-delay', `${Math.random() * 2}s`);
      bubble.style.setProperty('--bubble-duration', `${2.4 + Math.random() * 1.8}s`);
      layer.append(bubble);
    }

    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 7600);
    return { potion: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.potion = { play };
})();
