/** Dezente Bestätigungskarte mit Häkchen und einer ruhigen Statuslinie. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__serious-confirmation';
    layer.setAttribute('aria-hidden', 'true');
    const card = document.createElement('span');
    card.className = 'kw-masha-feedly__serious-confirmation-card';
    const icon = document.createElement('span');
    icon.className = 'kw-masha-feedly__serious-confirmation-icon';
    icon.textContent = '✓';
    const lines = document.createElement('span');
    lines.className = 'kw-masha-feedly__serious-confirmation-lines';
    lines.innerHTML = '<i></i><i></i>';
    card.append(icon, lines);
    layer.append(card);
    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(layer);
    window.setTimeout(() => layer.remove(), 2100);
    return { confirmation: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.confirmation = { play };
})();
