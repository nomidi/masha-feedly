/** Liefert einen serverseitig übersetzten Modultext samt Platzhalterwerten. */
window.KWMashaFeedlyTranslate = (key, values = {}) => {
  let message = window.KWMashaFeedlyTranslations?.[key] || key;
  Object.entries(values).forEach(([name, value]) => {
    message = message.replaceAll(`{${name}}`, String(value));
  });
  return message;
};

/** Fügt das globale Masha-Feedly-Aufklappfeld in die Seite ein. */
document.addEventListener('DOMContentLoaded', () => {
  if (!window.KWMashaFeedlyWidgetMarkup || document.querySelector('[data-kw-masha-feedly]')) {
    return;
  }

  // Verhindert das doppelte schwebende Widget innerhalb der CMS-Seitenvorschau.
  try {
    const previewFrame = window.frameElement;
    if (previewFrame && (
      previewFrame.matches?.('.cms-preview')
      || previewFrame.closest?.('.cms-preview')
      || previewFrame.closest?.('.cms-preview.fill-height.flexbox-area-grow')
    )) {
      return;
    }
  } catch (error) {
    // Ein nicht lesbarer Frame wird wie eine normale Frontend-Seite behandelt.
  }

  const container = document.createElement('div');
  container.innerHTML = window.KWMashaFeedlyWidgetMarkup;
  const widget = container.firstElementChild;
  if (!widget) return;
  document.body.append(widget);

  const toggle = widget.querySelector('.kw-masha-feedly__toggle');
  const panel = widget.querySelector('.kw-masha-feedly__panel');
  const close = widget.querySelector('.kw-masha-feedly__close');
  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', window.KWMashaFeedlyTranslate(open ? 'CLOSE_WIDGET' : 'OPEN_WIDGET'));
    panel.hidden = !open;
    widget.setAttribute('data-panel-open', String(open));
    document.dispatchEvent(new CustomEvent(open ? 'kw-masha-feedly:opened' : 'kw-masha-feedly:closed'));
  };
  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!expanded);
  });
  close.addEventListener('click', () => setOpen(false));
});
