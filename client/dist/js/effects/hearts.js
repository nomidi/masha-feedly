/** Bunte, pulsierende Herzen blühen auf und platzen nach 2,5 Sekunden. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__heart-burst';
    layer.setAttribute('aria-hidden', 'true');
    const colors = ['#ff3b91', '#ff792e', '#f5c928', '#2dcf87', '#2ebbe8', '#8957ef', '#f044bd'];
    for (let index = 0; index < 28; index += 1) {
      const heart = document.createElement('span');
      heart.className = 'kw-masha-feedly__burst-heart';
      heart.textContent = '♥';
      heart.dataset.index = String(index);
      heart.style.left = `${3 + ((index * 37) % 94)}%`;
      heart.style.top = `${14 + ((index * 53) % 70)}%`;
      heart.style.color = colors[index % colors.length];
      heart.style.fontSize = `${25 + ((index * 17) % 32)}px`;
      heart.style.animationDelay = `${(index % 5) * 12}ms`;
      layer.append(heart);
    }
    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 2850);
    return { hearts: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.hearts = { play };
})();
