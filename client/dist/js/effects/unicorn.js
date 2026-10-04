/** Der Einhorn-Effekt ist immer eine Einheit aus Chaos-Konfetti und Einhorn. */
(() => {
  const isReducedMotion = (window) => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const playConfetti = (document, window) => {
    if (isReducedMotion(window)) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__confetti';
    layer.setAttribute('aria-hidden', 'true');
    const colors = ['#ff2d95', '#ffd400', '#00d4ff', '#7b2cff', '#39e75f', '#ff7a00', '#ff4fdb', '#00f0b5'];
    const directions = ['oben', 'rechts', 'unten', 'links'];
    for (let index = 0; index < 128; index += 1) {
      const piece = document.createElement('i');
      piece.className = 'kw-masha-feedly__confetti-piece';
      piece.dataset.origin = directions[index % directions.length];
      piece.dataset.form = index % 3 === 0 ? 'band' : 'square';
      piece.style.left = `${(index * 47) % 100}%`;
      piece.style.top = `${(index * 31) % 100}%`;
      piece.style.animationDelay = `${(index % 32) * 18}ms`;
      piece.style.animationDuration = `${(8.8 + (index % 12) * 0.24).toFixed(2)}s`;
      piece.style.backgroundColor = colors[index % colors.length];
      piece.style.transform = `rotate(${(index * 41) % 360}deg)`;
      layer.append(piece);
    }
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 13000);
    return layer;
  };

  const playUnicorn = (document, window, imageURL) => {
    if (!imageURL || isReducedMotion(window)) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__unicorn-runner';
    layer.setAttribute('aria-hidden', 'true');
    const unicorn = document.createElement('img');
    unicorn.className = 'kw-masha-feedly__unicorn';
    unicorn.src = imageURL;
    unicorn.alt = '';
    layer.append(unicorn);
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 13000);
    return layer;
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.unicorn = {
    play(document, window, imageURL) {
      if (isReducedMotion(window)) return null;
      return {
        confetti: playConfetti(document, window),
        unicorn: playUnicorn(document, window, imageURL),
      };
    },
    confetti: playConfetti,
    unicorn: playUnicorn,
  };
})();
