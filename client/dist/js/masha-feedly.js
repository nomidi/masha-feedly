/** Liefert einen serverseitig übersetzten Modultext samt Platzhalterwerten. */
window.KWMashaFeedlyTranslate = (key, values = {}) => {
  let message = window.KWMashaFeedlyTranslations?.[key] || key;
  Object.entries(values).forEach(([name, value]) => {
    message = message.replaceAll(`{${name}}`, String(value));
  });
  return message;
};

/** Gemeinsame DOM-Grenze: CMS-Vorschauen ohne Widget verwenden weiterhin das Dokument. */
window.KWMashaFeedlyDOM = {
  root: () => document.querySelector('[data-masha-feedly-host]')?.shadowRoot || document,
  widget: () => window.KWMashaFeedlyDOM.root().querySelector('[data-kw-masha-feedly]'),
  overlayRoot: () => window.KWMashaFeedlyDOM.root().querySelector('[data-masha-feedly-surface]') || document.body,
  activeElement: () => window.KWMashaFeedlyDOM.root().activeElement || document.activeElement,
  eventTarget: (event) => event.composedPath?.()[0] || event.target,
};

/** Fügt das globale Masha-Feedly-Aufklappfeld in die Seite ein. */
document.addEventListener('DOMContentLoaded', () => {
  if (!window.KWMashaFeedlyWidgetMarkup || window.KWMashaFeedlyDOM.widget()) {
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
  const host = document.createElement('div');
  host.setAttribute('data-masha-feedly-host', '');
  const root = host.attachShadow({ mode: 'open' });
  const stylesheet = document.createElement('link');
  stylesheet.rel = 'stylesheet';
  stylesheet.href = window.KWMashaFeedlyWidgetStylesheet;
  const surface = document.createElement('div');
  surface.setAttribute('data-masha-feedly-surface', '');
  // Verhindert ungestaltete Dialoge, während der Browser das Stylesheet lädt.
  surface.hidden = true;
  stylesheet.addEventListener('load', () => { surface.hidden = false; });
  stylesheet.addEventListener('error', () => {
    console.error('[Masha:Feedly] Widget-Stylesheet konnte nicht geladen werden.');
  });
  surface.append(widget);
  root.append(stylesheet, surface);
  root.addEventListener('error', (event) => {
    const image = event.target;
    if (!(image instanceof HTMLImageElement)) return;
    const avatar = image.closest('.kw-masha-feedly__assignee-avatar');
    if (!avatar) return;
    avatar.replaceChildren();
    avatar.textContent = avatar.dataset.avatarInitials || '?';
  }, true);
  document.body.append(host);

  const hoverHintSelector = [
    '.kw-masha-feedly__add',
    '.kw-masha-feedly__count-button',
    '[data-masha-feedly-open-news]',
    '[data-masha-feedly-open-feedback]',
    '[data-masha-feedly-open-closed]',
    '[data-masha-feedly-open-help]',
    '[data-masha-feedly-rainbow]',
  ].join(', ');
  let hoverHint;
  let hoverHintTarget;
  let hideHoverHintTimer;
  let previousDescribedBy = '';

  const getHoverHintTarget = (target) => target?.closest?.(hoverHintSelector) || null;
  const hideHoverHint = () => {
    if (!hoverHint || !hoverHintTarget) return;
    window.clearTimeout(hideHoverHintTimer);
    hoverHint.classList.remove('is-visible');
    hoverHint.setAttribute('aria-hidden', 'true');
    if (previousDescribedBy) {
      hoverHintTarget.setAttribute('aria-describedby', previousDescribedBy);
    } else {
      hoverHintTarget.removeAttribute('aria-describedby');
    }
    hoverHintTarget = null;
    hideHoverHintTimer = window.setTimeout(() => { hoverHint.hidden = true; }, 180);
  };
  const showHoverHint = (target) => {
    if (!target || !widget.contains(target)) return;
    const text = target.getAttribute('aria-label') || target.getAttribute('title');
    if (!text) return;
    window.clearTimeout(hideHoverHintTimer);
    if (!hoverHint) {
      hoverHint = document.createElement('div');
      hoverHint.className = 'kw-masha-feedly__hover-tooltip';
      hoverHint.id = 'kw-masha-feedly-hover-tooltip';
      hoverHint.setAttribute('role', 'tooltip');
      hoverHint.setAttribute('aria-hidden', 'true');
      surface.append(hoverHint);
    }
    if (hoverHintTarget !== target) {
      if (hoverHintTarget) {
        if (previousDescribedBy) hoverHintTarget.setAttribute('aria-describedby', previousDescribedBy);
        else hoverHintTarget.removeAttribute('aria-describedby');
      }
      hoverHintTarget = target;
      previousDescribedBy = target.getAttribute('aria-describedby') || '';
    }
    hoverHint.textContent = text;
    hoverHint.hidden = false;
    hoverHint.setAttribute('aria-hidden', 'false');
    target.setAttribute('aria-describedby', [previousDescribedBy, hoverHint.id].filter(Boolean).join(' '));

    const rect = target.getBoundingClientRect();
    const gap = 12;
    const edge = 8;
    const width = hoverHint.offsetWidth;
    const height = hoverHint.offsetHeight;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const leftSpace = rect.left - width - gap;
    const rightSpace = viewportWidth - rect.right - width - gap;
    let placement;
    let left;
    let top;

    if (leftSpace >= edge) {
      placement = 'is-left';
      left = leftSpace;
      top = Math.min(Math.max(rect.top + rect.height / 2 - height / 2, edge), viewportHeight - height - edge);
    } else if (rightSpace >= edge) {
      placement = 'is-right';
      left = rect.right + gap;
      top = Math.min(Math.max(rect.top + rect.height / 2 - height / 2, edge), viewportHeight - height - edge);
    } else {
      left = Math.min(Math.max(rect.left + rect.width / 2 - width / 2, edge), viewportWidth - width - edge);
      if (rect.top >= height + gap + edge) {
        placement = 'is-above';
        top = rect.top - height - gap;
      } else {
        placement = 'is-below';
        top = Math.min(rect.bottom + gap, viewportHeight - height - edge);
      }
    }
    hoverHint.classList.remove('is-left', 'is-right', 'is-above', 'is-below', 'is-visible');
    hoverHint.classList.add(placement);
    hoverHint.style.left = `${Math.max(edge, left)}px`;
    hoverHint.style.top = `${Math.max(edge, top)}px`;
    hoverHint.setAttribute('aria-hidden', 'false');
    if (window.requestAnimationFrame) window.requestAnimationFrame(() => hoverHint.classList.add('is-visible'));
    else hoverHint.classList.add('is-visible');
  };

  document.addEventListener('pointerover', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target) showHoverHint(target);
  });
  document.addEventListener('pointerout', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target && target === hoverHintTarget && !target.contains(event.relatedTarget)) hideHoverHint();
  });
  document.addEventListener('focusin', (event) => showHoverHint(getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event))));
  document.addEventListener('focusout', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target && target === hoverHintTarget && !target.contains(event.relatedTarget)) hideHoverHint();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') hideHoverHint();
  });
  document.addEventListener('click', (event) => {
    if (getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event))) hideHoverHint();
  });

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
