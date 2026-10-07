/** Ein Schwarm kleiner Geister schwebt durch Nebel und flackert kurz auf. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__ghost-swarm';
    layer.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < 9; index += 1) {
      const ghost = document.createElement('span');
      ghost.className = 'kw-masha-feedly__swarm-ghost';
      ghost.style.setProperty('--ghost-x', `${8 + index * 10.5 + (Math.random() * 8 - 4)}vw`);
      ghost.style.setProperty('--ghost-size', `${2.6 + Math.random() * 2.5}rem`);
      ghost.style.setProperty('--ghost-delay', `${Math.random() * 1.5}s`);
      ghost.style.setProperty('--ghost-drift', `${Math.random() * 7 - 3.5}vw`);
      ghost.innerHTML = '<svg viewBox="0 0 100 110" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__ghost-shape" d="M15 93V45C15 23 30 9 50 9s35 14 35 36v48l-12-9-11 9-12-9-12 9-12-9z"/><ellipse class="kw-masha-feedly__ghost-eye" cx="38" cy="48" rx="4" ry="7"/><ellipse class="kw-masha-feedly__ghost-eye" cx="62" cy="48" rx="4" ry="7"/><ellipse class="kw-masha-feedly__ghost-mouth" cx="50" cy="67" rx="5" ry="7"/></svg>';
      layer.append(ghost);
    }

    for (let index = 0; index < 12; index += 1) {
      const mist = document.createElement('i');
      mist.className = 'kw-masha-feedly__ghost-mist';
      mist.style.setProperty('--mist-x', `${index * 10 - 14}vw`);
      mist.style.setProperty('--mist-delay', `${index * 0.12}s`);
      layer.append(mist);
    }

    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 8500);
    return { ghostSwarm: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.ghostSwarm = { play };
})();
