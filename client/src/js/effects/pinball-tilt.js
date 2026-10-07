/** Rüttelt kurz die Browseransicht und blendet die Tilt-Warnung ein. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__pinball-tilt';
    layer.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.add('kw-masha-feedly__pinball-viewport-shake');

    const warning = document.createElement('strong');
    warning.className = 'kw-masha-feedly__pinball-tilt-word';
    warning.textContent = 'TILT!';
    layer.append(warning);

    for (let index = 0; index < 12; index += 1) {
      const light = document.createElement('i');
      light.className = 'kw-masha-feedly__pinball-light';
      light.style.setProperty('--light-x', `${5 + index * 8.2}%`);
      light.style.setProperty('--light-y', `${12 + (index % 4) * 22}%`);
      light.style.setProperty('--light-delay', `${index * 0.07}s`);
      layer.append(light);
    }

    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => {
      layer.remove();
      document.documentElement.classList.remove('kw-masha-feedly__pinball-viewport-shake');
    }, 2100);
    return { pinball: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.pinballTilt = { play };
})();
