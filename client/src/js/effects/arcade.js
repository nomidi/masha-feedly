/** Kurzer 8-Bit-Moment mit Pixelblitzen, Invadern und Level-Erfolg. */
(() => {
  const invaderPath = 'M31 16h-2v-2c0-.6-.4-1-1-1h-2v-2c0-.6-.4-1-1-1h-2v-2h2c.6 0 1-.4 1-1v-3c0-.6-.4-1-1-1h-3c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v3h-4v-3c0-.6-.4-1-1-1h-2v-2c0-.6-.4-1-1-1h-3c-.6 0-1 .4-1 1v3c0 .6.4 1 1 1h2v2h-2c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v9c0 .6.4 1 1 1h3c.6 0 1-.4 1-1v-5h1v5c0 .6.4 1 1 1h2v2c0 .6.4 1 1 1h4c.6 0 1-.4 1-1v-3c0-.6-.4-1-1-1h-3v-1h10v1h-3c-.6 0-1 .4-1 1v3c0 .6.4 1 1 1h4c.6 0 1-.4 1-1v-2h2c.6 0 1-.4 1-1v-5h1v5c0 .6.4 1 1 1h3c.6 0 1-.4 1-1v-9c0-.6-.4-1-1-1zm-17 3c0 .6-.4 1-1 1h-3c-.6 0-1-.4-1-1v-3c0-.6.4-1 1-1h3c.6 0 1 .4 1 1zm9 0c0 .6-.4 1-1 1h-3c-.6 0-1-.4-1-1v-3c0-.6.4-1 1-1h3c.6 0 1 .4 1 1z';
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__arcade-effect';
    layer.setAttribute('aria-hidden', 'true');
    const screen = document.createElement('div');
    screen.className = 'kw-masha-feedly__arcade-screen';
    for (let index = 0; index < 56; index += 1) {
      const pixel = document.createElement('i');
      pixel.className = 'kw-masha-feedly__arcade-pixel';
      pixel.dataset.tone = String(index % 4);
      pixel.style.left = `${(index * 43) % 100}%`;
      pixel.style.top = `${(index * 61) % 100}%`;
      pixel.style.width = `${10 + ((index * 7) % 24)}px`;
      pixel.style.height = `${10 + ((index * 11) % 24)}px`;
      pixel.style.animationDelay = `${(index % 8) * 24}ms`;
      screen.append(pixel);
    }
    for (let index = 0; index < 4; index += 1) {
      const invader = document.createElement('span');
      invader.className = 'kw-masha-feedly__arcade-invader';
      invader.dataset.player = String(index + 1);
      invader.style.left = `${12 + index * 24}%`;
      invader.style.top = `${22 + ((index % 2) * 48)}%`;
      invader.innerHTML = `<svg viewBox="0 0 32 32" focusable="false" aria-hidden="true"><path fill="currentColor" d="${invaderPath}"/></svg>`;
      screen.append(invader);
    }
    const message = document.createElement('strong');
    message.className = 'kw-masha-feedly__arcade-message';
    message.textContent = 'MISSION COMPLETE!';
    screen.append(message);
    layer.append(screen);
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 2900);
    return { arcade: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.arcade = { play };
})();
