/** Der Raketen-Effekt ist eigenständig: Sternenspur und Rakete, ohne Konfetti. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__rocket-runner';
    layer.setAttribute('aria-hidden', 'true');
    const trail = document.createElement('span');
    trail.className = 'kw-masha-feedly__rocket-trail';
    for (let index = 0; index < 14; index += 1) {
      const star = document.createElement('i');
      star.className = 'kw-masha-feedly__rocket-star';
      star.dataset.index = String(index);
      star.style.left = `${5 + ((index * 29) % 87)}%`;
      star.style.top = `${8 + ((index * 43) % 84)}%`;
      star.style.animationDelay = `${((index * 137) % 650) - 500}ms`;
      layer.append(star);
    }
    const smoke = document.createElement('span');
    smoke.className = 'kw-masha-feedly__rocket-smoke';
    smoke.setAttribute('aria-hidden', 'true');
    for (let attempt = 0; attempt < 3; attempt += 1) {
      const puff = document.createElement('i');
      puff.className = 'kw-masha-feedly__rocket-smoke-puff';
      puff.dataset.attempt = String(attempt + 1);
      smoke.append(puff);
    }
    layer.append(smoke);
    const rocket = document.createElement('span');
    rocket.className = 'kw-masha-feedly__rocket';
    rocket.innerHTML = '<svg viewBox="0 0 512 512" focusable="false" aria-hidden="true"><defs><linearGradient id="kw-masha-feedly-rocket-rainbow" x1="0" y1="1" x2="1" y2="0"><stop stop-color="#ff168c"/><stop offset=".28" stop-color="#ff8b22"/><stop offset=".52" stop-color="#fff02e"/><stop offset=".74" stop-color="#26e88a"/><stop offset="1" stop-color="#35cfff"/></linearGradient></defs>'
      + '<path class="kw-masha-feedly__rocket-nose" d="M501.752 10.001s-1.93 53.83-24.3 117.12l-92.82-92.82c63.29-22.37 117.12-24.3 117.12-24.3z"/>'
      + '<path class="kw-masha-feedly__rocket-body" d="m384.632 34.301 92.82 92.82c-14.54 41.18-37.75 86.36-74.69 123.3-34.041 34.041-74.993 61.29-117.81 80.18l-103.8-103.8c12.64-28.65 30.28-59.22 53.59-88.17 8.16-10.13 17.01-20.06 26.59-29.64 36.94-36.94 82.12-60.15 123.3-74.69z"/>'
      + '<path class="kw-masha-feedly__rocket-window" d="M388.612 123.141c15.62 15.62 15.62 40.95 0 56.57s-40.94 15.62-56.57 0c-15.62-15.63-15.62-40.95 0-56.57s40.95-15.62 56.57 0z"/>'
      + '<path class="kw-masha-feedly__rocket-fin" d="M374.472 278.701c51.54 77.31-44.94 127.28-98.99 127.28 28.28-28.28 20.02-64.83 20.02-64.83l-10.55-10.55c28.65-12.64 59.22-30.28 88.17-53.59l1.35 1.69z"/>'
      + '<path class="kw-masha-feedly__rocket-fin" d="m169.532 300.041-1.62-1.62c-4.78-4.79-7.13-11.73-5.45-18.29 4.17-16.24 10.33-34.36 18.69-53.33l103.8 103.8c-18.97 8.36-37.09 14.52-53.33 18.69-6.56 1.68-13.5-.67-18.29-5.45l-1.62-1.62-42.18-42.18zM233.052 137.281l1.69 1.35c-23.31 28.95-40.95 59.52-53.59 88.17l-10.55-10.55s-36.55-8.26-64.83 20.02c0-53.05 49.97-149.53 127.28-98.99z"/>'
      + '<path class="kw-masha-feedly__rocket-flame" d="m169.532 300.041 42.18 42.18c-3.03 11.99-10.22 24.61-21.09 35.48-23.43 23.43-84.85 28.28-84.85 28.28s4.85-61.42 28.28-84.85c10.87-10.87 23.49-18.06 35.48-21.09z"/>'
      + '<path class="kw-masha-feedly__rocket-detail" d="M268.662 257.491c3.9-3.91 3.9-10.24 0-14.14-3.91-3.91-10.24-3.91-14.15 0-3.9 3.9-3.9 10.23 0 14.14 3.91 3.9 10.24 3.9 14.15 0zM395.933 186.782c19.538-19.538 19.542-51.171 0-70.712-19.54-19.539-51.172-19.54-70.713 0-19.489 19.489-19.49 51.209.003 70.714 19.496 19.484 51.216 19.492 70.71-.002zm-56.57-56.57c11.723-11.723 30.703-11.725 42.428 0 11.723 11.722 11.725 30.703 0 42.427-11.693 11.694-30.727 11.694-42.426.002-11.695-11.702-11.696-30.736-.002-42.429z"/>'
      + '<path class="kw-masha-feedly__rocket-speedline kw-masha-feedly__rocket-speedline--one" d="m212.093 455.481 28.28-28.29c3.904-3.906 3.903-10.238-.002-14.142-3.907-3.905-10.239-3.903-14.143.002l-28.28 28.29c-3.904 3.906-3.903 10.238.002 14.142 3.907 3.904 10.239 3.904 14.143-.002z"/>'
      + '<path class="kw-masha-feedly__rocket-speedline kw-masha-feedly__rocket-speedline--two" d="m70.661 314.053 28.29-28.28c3.906-3.904 3.907-10.236.003-14.142s-10.235-3.906-14.142-.002l-28.29 28.28c-3.906 3.904-3.907 10.236-.003 14.142 3.904 3.904 10.235 3.908 14.142.002z"/>'
      + '<path class="kw-masha-feedly__rocket-speedline kw-masha-feedly__rocket-speedline--three" d="m155.521 427.199-67.74 67.73c-3.906 3.905-3.906 10.237-.001 14.142 3.903 3.905 10.236 3.907 14.142.001l67.74-67.73c3.906-3.905 3.906-10.237.001-14.142-3.903-3.905-10.236-3.905-14.142-.001z"/>'
      + '<path class="kw-masha-feedly__rocket-speedline kw-masha-feedly__rocket-speedline--four" d="m75.521 427.199-67.74 67.73c-3.906 3.905-3.906 10.237-.001 14.142 3.903 3.905 10.236 3.907 14.142.001l67.74-67.73c3.906-3.905 3.906-10.237.001-14.142-3.904-3.904-10.237-3.904-14.142-.001z"/>'
      + '<path class="kw-masha-feedly__rocket-speedline kw-masha-feedly__rocket-speedline--five" d="m17.073 424.221 67.73-67.74c3.905-3.906 3.905-10.237-.001-14.143-3.904-3.904-10.237-3.904-14.142.001l-67.73 67.74c-3.905 3.906-3.905 10.237.001 14.143 3.905 3.905 10.237 3.905 14.142-.001z"/>'
      + '</svg>';
    layer.append(trail, rocket);
    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 5200);
    return { rocket: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.rocket = { play };
})();
