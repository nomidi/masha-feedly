/** Kurzer 8-Bit-Moment mit Pixelblitzen, Invadern und Level-Erfolg. */
(() => {
  const invaderPath = 'M31 16h-2v-2c0-.6-.4-1-1-1h-2v-2c0-.6-.4-1-1-1h-2v-2h2c.6 0 1-.4 1-1v-3c0-.6-.4-1-1-1h-3c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v3h-4v-3c0-.6-.4-1-1-1h-2v-2c0-.6-.4-1-1-1h-3c-.6 0-1 .4-1 1v3c0 .6.4 1 1 1h2v2h-2c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v2h-2c-.6 0-1 .4-1 1v9c0 .6.4 1 1 1h3c.6 0 1-.4 1-1v-5h1v5c0 .6.4 1 1 1h2v2c0 .6.4 1 1 1h4c.6 0 1-.4 1-1v-3c0-.6-.4-1-1-1h-3v-1h10v1h-3c-.6 0-1 .4-1 1v3c0 .6.4 1 1 1h4c.6 0 1-.4 1-1v-2h2c.6 0 1-.4 1-1v-5h1v5c0 .6.4 1 1 1h3c.6 0 1-.4 1-1v-9c0-.6-.4-1-1-1zm-17 3c0 .6-.4 1-1 1h-3c-.6 0-1-.4-1-1v-3c0-.6.4-1 1-1h3c.6 0 1 .4 1 1zm9 0c0 .6-.4 1-1 1h-3c-.6 0-1-.4-1-1v-3c0-.6.4-1 1-1h3c.6 0 1 .4 1 1z';
  const victoryNotes = [659.25, 783.99, 987.77, 1318.51, 987.77, 1318.51];
  let audioContext = null;
  let audioUnlocked = false;

  const makeAudioContext = (window) => {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!audioContext && AudioContextClass) audioContext = new AudioContextClass();
    return audioContext;
  };
  const unlockAudio = (window) => {
    const context = makeAudioContext(window);
    if (!context || audioUnlocked) return;
    try {
      const resumed = context.resume?.();
      if (resumed?.then) resumed.then(() => { audioUnlocked = context.state === 'running'; }).catch(() => {});
      else audioUnlocked = context.state === 'running';
    } catch (_) { /* Audio bleibt optional, falls der Browser es blockiert. */ }
  };
  const playVictorySound = (window) => {
    const context = makeAudioContext(window);
    if (!context) return false;
    const schedule = () => {
      if (context.state !== 'running') return;
      const start = context.currentTime + 0.025;
      victoryNotes.forEach((frequency, index) => {
        const oscillator = context.createOscillator();
        const volume = context.createGain();
        const at = start + index * 0.13;
        oscillator.type = 'square';
        oscillator.frequency.setValueAtTime(frequency, at);
        volume.gain.setValueAtTime(0.0001, at);
        volume.gain.exponentialRampToValueAtTime(0.075, at + 0.012);
        volume.gain.exponentialRampToValueAtTime(0.0001, at + 0.115);
        oscillator.connect(volume);
        volume.connect(context.destination);
        oscillator.start(at);
        oscillator.stop(at + 0.12);
      });
    };
    if (context.state === 'running' || audioUnlocked) schedule();
    else {
      try {
        const resumed = context.resume?.();
        if (resumed?.then) resumed.then(schedule).catch(() => {});
      } catch (_) { /* kein Ton, wenn Audio im Browser nicht verfügbar ist */ }
    }
    return true;
  };

  // Wecken des Audio-Kontexts während einer echten Nutzeraktion, damit auch ein später
  // eintreffendes erfolgreiches Speichern den kurzen Arcade-Ton abspielen darf.
  if (window.addEventListener) {
    const unlock = () => unlockAudio(window);
    window.addEventListener('pointerdown', unlock, { once: true, capture: true });
    window.addEventListener('keydown', unlock, { once: true, capture: true });
  }

  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    playVictorySound(window);
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
  window.KWMashaFeedlyEffectModules.arcade = { play, playVictorySound, victoryNotes };
})();
