/** Wackelndes Flipper-Spielfeld mit springender Kugel und blinkendem Tilt. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__pinball-tilt';
    layer.setAttribute('aria-hidden', 'true');

    const machine = document.createElement('span');
    machine.className = 'kw-masha-feedly__pinball-machine';
    machine.innerHTML = '<span class="kw-masha-feedly__pinball-title">PINBALL</span><span class="kw-masha-feedly__pinball-bumper kw-masha-feedly__pinball-bumper--one">★</span><span class="kw-masha-feedly__pinball-bumper kw-masha-feedly__pinball-bumper--two">★</span><span class="kw-masha-feedly__pinball-bumper kw-masha-feedly__pinball-bumper--three">★</span><span class="kw-masha-feedly__pinball-ball"></span><span class="kw-masha-feedly__pinball-flipper kw-masha-feedly__pinball-flipper--left"></span><span class="kw-masha-feedly__pinball-flipper kw-masha-feedly__pinball-flipper--right"></span><strong class="kw-masha-feedly__pinball-tilt-word">TILT!</strong>';
    layer.append(machine);

    for (let index = 0; index < 12; index += 1) {
      const light = document.createElement('i');
      light.className = 'kw-masha-feedly__pinball-light';
      light.style.setProperty('--light-y', `${7 + index * 7.3}%`);
      light.style.setProperty('--light-delay', `${index * 0.07}s`);
      layer.append(light);
    }

    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 5600);
    return { pinball: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.pinballTilt = { play };
})();
