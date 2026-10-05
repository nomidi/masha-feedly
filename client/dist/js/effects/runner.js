/** Gemeinsamer Startpunkt für Done und Admin-Vorschauen samt Abbruch per Klick. */
(() => {
  const modules = window.KWMashaFeedlyEffectModules || {};
  const themeEffects = {
    playful: ['unicorn', 'rocket', 'hearts', 'arcade', 'retro', 'dino', 'ducks', 'frogs', 'iconShower'],
    serious: ['check', 'glow', 'rings', 'confirmation'],
  };
  const activeByDocument = new WeakMap();
  const reducedMotion = (window) => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

  const collectLayers = (value, layers = new Set(), visited = new Set()) => {
    if (!value || (typeof value !== 'object' && typeof value !== 'function') || visited.has(value)) return layers;
    visited.add(value);
    if (typeof value.remove === 'function') {
      layers.add(value);
      return layers;
    }
    if (Array.isArray(value)) value.forEach((item) => collectLayers(item, layers, visited));
    else Object.values(value).forEach((item) => collectLayers(item, layers, visited));
    return layers;
  };

  const cancelActive = (document) => {
    const active = activeByDocument.get(document);
    if (!active?.size) return 0;
    const records = [...active];
    active.clear();
    records.forEach((record) => {
      if (record.timer !== null) record.window.clearTimeout?.(record.timer);
      record.layers.forEach((layer) => layer.remove());
    });
    return records.length;
  };

  const track = (result, document, window) => {
    const layers = collectLayers(result);
    if (!layers.size || !document?.addEventListener) return result;
    let active = activeByDocument.get(document);
    if (!active) {
      active = new Set();
      activeByDocument.set(document, active);
      // pointerdown happens before the CMS click handler can start a new preview.
      document.addEventListener('pointerdown', () => cancelActive(document), true);
    }
    const record = { layers, window, timer: null };
    active.add(record);
    record.timer = window.setTimeout?.(() => {
      active.delete(record);
      record.layers.clear();
    }, 15000) ?? null;
    return result;
  };

  const play = (name, document, window, imageURL) => track(modules[name]?.play(document, window, imageURL) || null, document, window);
  const playOnDone = (document, window, imageURL, theme = 'playful', random = Math.random) => {
    if (reducedMotion(window)) return null;
    const candidates = themeEffects[theme] || [];
    if (!candidates.length) return null;
    const index = Math.min(candidates.length - 1, Math.floor(random() * candidates.length));
    return play(candidates[index], document, window, imageURL);
  };

  const preview = (name, document, window, imageURL) => {
    if (reducedMotion(window)) return null;
    return play(name === 'playful' ? 'unicorn' : name, document, window, imageURL);
  };

  window.KWMashaFeedlyEffects = {
    play,
    playOnDone,
    preview,
    cancelActive,
    confetti: (document, window) => track(modules.unicorn?.confetti(document, window) || null, document, window),
    unicorn: (document, window, imageURL) => track(modules.unicorn?.unicorn(document, window, imageURL) || null, document, window),
    rocket: (document, window) => track(modules.rocket?.play(document, window)?.rocket || null, document, window),
    hearts: (document, window) => track(modules.hearts?.play(document, window)?.hearts || null, document, window),
    arcade: (document, window) => track(modules.arcade?.play(document, window)?.arcade || null, document, window),
    retro: (document, window) => track(modules.retro?.play(document, window)?.retro || null, document, window),
    dino: (document, window) => track(modules.dino?.play(document, window)?.dino || null, document, window),
    ducks: (document, window) => track(modules.ducks?.play(document, window)?.ducks || null, document, window),
    frogs: (document, window) => track(modules.frogs?.play(document, window)?.frogs || null, document, window),
    iconShower: (document, window) => track(modules.iconShower?.play(document, window)?.iconShower || null, document, window),
    check: (document, window) => track(modules.check?.play(document, window)?.check || null, document, window),
    glow: (document, window) => track(modules.glow?.play(document, window)?.glow || null, document, window),
    rings: (document, window) => track(modules.rings?.play(document, window)?.rings || null, document, window),
    confirmation: (document, window) => track(modules.confirmation?.play(document, window)?.confirmation || null, document, window),
  };
})();
