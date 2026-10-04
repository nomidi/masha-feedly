/** Windows-3.1-inspiriertes Erfolgsfenster für den verspielten Abschluss. */
(() => {
  const play = (document, window) => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return null;

    const translations = window.KWMashaFeedlyTranslations || {};
    const layer = document.createElement('div');
    layer.className = 'kw-masha-feedly__retro-effect';
    layer.setAttribute('aria-hidden', 'true');

    const dialog = document.createElement('div');
    dialog.className = 'kw-masha-feedly__retro-dialog';
    const titlebar = document.createElement('div');
    titlebar.className = 'kw-masha-feedly__retro-titlebar';
    titlebar.textContent = 'Masha:Feedly';

    const content = document.createElement('div');
    content.className = 'kw-masha-feedly__retro-content';
    const icon = document.createElement('span');
    icon.className = 'kw-masha-feedly__retro-check';
    icon.textContent = '✓';
    const message = document.createElement('strong');
    message.textContent = translations.RETRO_SUCCESS_TITLE || 'Erfolgreich erledigt!';
    const detail = document.createElement('span');
    detail.textContent = translations.RETRO_SUCCESS_MESSAGE || 'Der Eintrag wurde abgeschlossen.';
    content.append(icon, message, detail);

    const footer = document.createElement('div');
    footer.className = 'kw-masha-feedly__retro-footer';
    const button = document.createElement('span');
    button.className = 'kw-masha-feedly__retro-button';
    button.textContent = translations.RETRO_SUCCESS_BUTTON || 'OK';
    footer.append(button);

    dialog.append(titlebar, content, footer);
    layer.append(dialog);
    document.body.append(layer);
    window.setTimeout(() => layer.remove(), 3600);
    return { retro: layer };
  };

  window.KWMashaFeedlyEffectModules ||= {};
  window.KWMashaFeedlyEffectModules.retro = { play };
})();
