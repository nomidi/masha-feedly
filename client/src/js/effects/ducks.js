/** Sieben quietschgelbe Badeenten watscheln in einer kleinen Parade durchs Bild. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__duck-parade';
    layer.setAttribute('aria-hidden', 'true');
    const ducks = ['#ffd928', '#ffe45c', '#ffc928', '#ffdf45', '#ffd12f', '#ffe45c', '#ffd928'];

    ducks.forEach((color, index) => {
      const duck = document.createElement('span');
      duck.className = 'kw-masha-feedly__parade-duck';
      duck.style.animationDelay = `${index * 0.48}s`;
      duck.style.setProperty('--duck-color', color);
      duck.style.setProperty('--duck-lane', `${(index % 3) * 3.2}vh`);
      duck.innerHTML = '<svg viewBox="0 0 120 100" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__duck-body" d="M20 69c-2-18 10-32 29-36 2-16 14-25 29-21 13 4 18 16 15 28 12 6 19 16 20 29H20Z"/><path class="kw-masha-feedly__duck-beak" d="M91 45c15-5 25-3 27 2-2 6-13 10-27 7Z"/><ellipse class="kw-masha-feedly__duck-wing" cx="54" cy="62" rx="18" ry="11"/><circle class="kw-masha-feedly__duck-eye" cx="82" cy="37" r="3.5"/><path class="kw-masha-feedly__duck-shine" d="M28 70c12 8 46 10 68 3"/></svg>';
      layer.append(duck);
    });

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 12000);
    return { ducks: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.ducks = { play };
})();
