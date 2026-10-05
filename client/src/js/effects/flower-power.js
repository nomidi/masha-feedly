/** Siebziger-Sonnenkreis, wellige Farben und tanzende Gänseblümchen. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__flower-power';
    layer.setAttribute('aria-hidden', 'true');

    const sun = document.createElement('span');
    sun.className = 'kw-masha-feedly__flower-power-sun';
    layer.append(sun);

    const title = document.createElement('strong');
    title.className = 'kw-masha-feedly__flower-power-title';
    title.textContent = 'GROOVY!';
    layer.append(title);

    const colors = ['#f47735', '#f2c94c', '#ef6a9d', '#65b89a', '#a66bb5', '#f7e7bf'];
    const petals = Array.from({ length: 10 }, (_, index) => {
      const rotation = index * 36;
      const color = colors[(index + 1) % colors.length];
      return `<ellipse cx="50" cy="27" rx="10" ry="22" transform="rotate(${rotation} 50 50)" fill="${color}"/>`;
    }).join('');

    for (let index = 0; index < 8; index += 1) {
      const flower = document.createElement('span');
      flower.className = 'kw-masha-feedly__flower-power-flower';
      flower.style.setProperty('--flower-x', `${8 + (index % 4) * 24 + (index > 3 ? 5 : 0)}vw`);
      flower.style.setProperty('--flower-y', `${19 + Math.floor(index / 4) * 48 + (index % 2) * 9}vh`);
      flower.style.setProperty('--flower-delay', `${index * 0.12}s`);
      flower.style.setProperty('--flower-size', `${3.2 + (index % 3) * 0.8}rem`);
      flower.innerHTML = `<svg viewBox="0 0 100 100" aria-hidden="true" focusable="false">${petals}<circle cx="50" cy="50" r="13" fill="#f2c94c"/><circle cx="46" cy="46" r="3" fill="#6c352d"/><circle cx="55" cy="46" r="3" fill="#6c352d"/><path d="M46 55q4 4 8 0" fill="none" stroke="#6c352d" stroke-width="2.5" stroke-linecap="round"/></svg>`;
      layer.append(flower);
    }

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 6200);
    return { flowerPower: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.flowerPower = { play };
})();
