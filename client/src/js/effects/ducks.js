/** Sieben Badeenten watscheln in einer gelben Parade durchs Bild. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__duck-parade';
    layer.setAttribute('aria-hidden', 'true');
    const yellow = { body: '#fec80e', wing: '#f4ae3d' };
    const black = { body: '#242424', wing: '#505050' };
    const accents = [
      { body: '#ff70a6', wing: '#ffb7cd' },
      { body: '#3fc8ba', wing: '#8ee5d9' },
      { body: '#9170e8', wing: '#c2aff5' },
    ];
    const accent = accents[Math.floor(Math.random() * accents.length)];
    const ducks = [yellow, yellow, yellow, black, yellow, accent, yellow];

    ducks.forEach((colors, index) => {
      const duck = document.createElement('span');
      duck.className = 'kw-masha-feedly__parade-duck';
      duck.style.animationDelay = `${index * 0.48}s`;
      duck.style.setProperty('--duck-body-color', colors.body);
      duck.style.setProperty('--duck-wing-color', colors.wing);
      duck.style.setProperty('--duck-lane', `${(index % 3) * 3.2}vh`);
      duck.innerHTML = '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__duck-body" d="m252.59 211.91c-19.96-14.34-49.9-75.02-44.52-119.3 4.45-36.58 41.45-92.05 114.91-82.53s155.23 61.66 92.19 187.81c0 0 54.71 24.11 55.27 137.37s-100.92 149.71-150.26 160.37-211.95 24.67-267.46-76.82-45.98-215.87-6.73-225.4 63.56 24.49 105.41 40.37c43.85 16.64 101.45-5 101.19-21.87z"/><path class="kw-masha-feedly__duck-wing" d="m322.42 297.14c39.97 21.21 53.27 117.75-10.65 122.23s-160.92-13.46-177.74-84.67c-10.47-44.27 133.44-66.72 188.39-37.56z"/><path class="kw-masha-feedly__duck-beak" d="m436.2 98.4s33.68 8.1 46.02 5.3 16.49-5.05 15.91 16.82-27.69 61.68-69.74 56.63"/><path class="kw-masha-feedly__duck-eye" d="m385.77 119.93c1.57-3.15 2.49-6.77 2.49-10.61 0-11.84-8.4-21.67-20.24-21.43-10.97.22-20.8 9.93-21.43 21.43-.18 3.28.6 7.76 2.11 10.57 2.41 4.51 7.58 11.85 16.52 11.85s16.98-4.62 20.55-11.81z"/></svg>';
      layer.append(duck);
    });

    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 12000);
    return { ducks: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.ducks = { play };
})();
