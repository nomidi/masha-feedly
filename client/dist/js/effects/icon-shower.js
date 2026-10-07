/** Lässt fünfzig kleine, bunte Figuren als lockeren Schauer durchs Bild tanzen. */
(() => {
  const icons = [
    '<svg viewBox="0 0 64 64"><path d="M17 24V14a15 15 0 0 1 30 0v10h5v20h-5v8H17v-8h-5V24z" fill="#303b55"/><path d="M21 24V15a11 11 0 0 1 22 0v9z" fill="#91e8f4"/><circle cx="26" cy="17" r="3" fill="#fff"/><circle cx="38" cy="17" r="3" fill="#fff"/><path d="M27 38q5 5 10 0" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M8 29h4m40 0h4M24 54v5m16-5v5" stroke="#ffca57" stroke-width="4" stroke-linecap="round"/></svg>',
    '<svg viewBox="0 0 64 64"><path d="M32 58V30" stroke="#638d38" stroke-width="5" stroke-linecap="round"/><path d="M31 44C12 43 9 31 12 23c12-1 21 7 20 21m1 6c18-2 21-14 18-22-12-1-21 7-20 22" fill="#8dcc54" stroke="#638d38" stroke-width="2"/><path d="M23 30q9-9 18 0v12H23z" fill="#ffdb54"/><circle cx="28" cy="35" r="2" fill="#354b2a"/><circle cx="36" cy="35" r="2" fill="#354b2a"/></svg>',
    '<svg viewBox="0 0 64 64"><path d="M14 37c-8-12 2-23 14-19 5-11 21-7 21 5 12 4 10 19 0 21-5 10-19 9-23 3-10 5-19 0-12-10" fill="#ffdc58" stroke="#e99743" stroke-width="2"/><path d="M20 34c4-8 18-9 26-1 2 8-4 14-13 14-8 0-14-5-13-13" fill="#70c9f2"/><circle cx="29" cy="37" r="3" fill="#fff"/><circle cx="40" cy="37" r="3" fill="#fff"/><circle cx="30" cy="38" r="1.5" fill="#26344f"/><circle cx="41" cy="38" r="1.5" fill="#26344f"/><path d="M31 44q4 3 8 0" fill="none" stroke="#26344f" stroke-width="2" stroke-linecap="round"/></svg>',
    '<svg viewBox="0 0 64 64"><path d="M32 7c5 8 11 8 18 6-3 8 0 13 8 18-8 5-9 11-6 19-9-2-14 1-20 9-5-8-11-10-19-8 2-9 0-14-8-20 8-5 10-11 7-19 9 3 14 1 20-5" fill="#fb7f96" stroke="#d95078" stroke-width="2"/><circle cx="32" cy="34" r="13" fill="#ffe58b"/><circle cx="28" cy="32" r="2" fill="#55405b"/><circle cx="37" cy="32" r="2" fill="#55405b"/><path d="M29 39q3 3 7 0" fill="none" stroke="#55405b" stroke-width="2" stroke-linecap="round"/></svg>',
  ];

  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__icon-shower';
    layer.setAttribute('aria-hidden', 'true');

    for (let index = 0; index < 50; index += 1) {
      const icon = document.createElement('span');
      const start = Math.random() * 100;
      icon.className = 'kw-masha-feedly__shower-icon';
      icon.style.setProperty('--icon-start', `${start}vw`);
      icon.style.setProperty('--icon-end', `${start + (Math.random() * 36 - 18)}vw`);
      icon.style.setProperty('--icon-size', `${1.4 + Math.random() * 2.2}rem`);
      icon.style.setProperty('--icon-delay', `${Math.random() * 1.8}s`);
      icon.style.setProperty('--icon-duration', `${4.8 + Math.random() * 2.8}s`);
      const rotation = Math.random() * 540 - 270;
      icon.style.setProperty('--icon-middle-rotation', `${rotation * 0.55}deg`);
      icon.style.setProperty('--icon-rotation', `${rotation}deg`);
      icon.innerHTML = icons[index % icons.length];
      layer.append(icon);
    }

    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 10500);
    return { iconShower: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.iconShower = { play };
})();
