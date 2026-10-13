/** Liefert einen serverseitig übersetzten Modultext samt Platzhalterwerten. */
window.KWMashaFeedlyTranslate = (key, values = {}) => {
  let message = window.KWMashaFeedlyTranslations?.[key] || key;
  Object.entries(values).forEach(([name, value]) => {
    message = message.replaceAll(`{${name}}`, String(value));
  });
  return message;
};

/** Liest ausschließlich JSON-Objektantworten und ersetzt Parserfehler durch einen verständlichen Hinweis. */
window.KWMashaFeedlyReadJSON = async (response, fallback = 'Der Server hat eine ungültige Antwort gesendet. Bitte versuche es erneut.') => {
  try {
    const data = await response.json();
    if (!data || typeof data !== 'object' || Array.isArray(data)) throw new TypeError('Ungültige JSON-Antwort');
    return data;
  } catch (_) {
    throw new Error(fallback);
  }
};

/** Gemeinsame DOM-Grenze: CMS-Vorschauen ohne Widget verwenden weiterhin das Dokument. */
window.KWMashaFeedlyDOM = {
  root: () => document.querySelector('[data-masha-feedly-host]')?.shadowRoot || document,
  widget: () => window.KWMashaFeedlyDOM.root().querySelector('[data-kw-masha-feedly]'),
  overlayRoot: () => window.KWMashaFeedlyDOM.root().querySelector('[data-masha-feedly-surface]') || document.body,
  activeElement: () => window.KWMashaFeedlyDOM.root().activeElement || document.activeElement,
  eventTarget: (event) => event.composedPath?.()[0] || event.target,
  panelPullProgress: (distance, width) => Math.max(0, Math.min(1, ((Number(distance) || 0) - 24) / (Number(width) || 368))),
  shouldOpenPulledPanel: (distance) => Number(distance) >= 72,
};

/** Zeigt unerwartete Serverantworten als zugängliche Meldung im Widget an. */
window.KWMashaFeedlyShowError = (message) => {
  const root = window.KWMashaFeedlyDOM.root();
  const toast = root.querySelector('[data-masha-feedly-save-toast]');
  if (!toast) return;
  toast.classList.add('is-error');
  toast.setAttribute('role', 'alert');
  toast.style.backgroundColor = '#fff1f2';
  toast.style.borderColor = '#fecdd3';
  toast.style.color = '#9f1239';
  const icon = toast.querySelector('.kw-masha-feedly__toast-icon');
  if (icon) { icon.textContent = '!'; icon.style.backgroundColor = '#be123c'; }
  const text = toast.querySelector('[data-masha-feedly-save-message]');
  if (text) text.textContent = message || 'Die Anfrage ist fehlgeschlagen. Bitte versuche es erneut.';
  toast.hidden = false;
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
    '[data-tooltip]',
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
    const text = target.getAttribute('data-tooltip')
      || target.getAttribute('aria-label')
      || target.getAttribute('title');
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

  root.addEventListener('pointerover', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target) showHoverHint(target);
  });
  root.addEventListener('pointerout', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target && target === hoverHintTarget && !target.contains(event.relatedTarget)) hideHoverHint();
  });
  root.addEventListener('focusin', (event) => showHoverHint(getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event))));
  root.addEventListener('focusout', (event) => {
    const target = getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event));
    if (target && target === hoverHintTarget && !target.contains(event.relatedTarget)) hideHoverHint();
  });
  root.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') hideHoverHint();
  });
  root.addEventListener('click', (event) => {
    if (getHoverHintTarget(window.KWMashaFeedlyDOM.eventTarget(event))) hideHoverHint();
  });

  const toggle = widget.querySelector('.kw-masha-feedly__toggle');
  const panel = widget.querySelector('.kw-masha-feedly__panel');
  const close = widget.querySelector('.kw-masha-feedly__close');
  let widgetDataPromise;
  /**
   * Lädt Auswahlwerte gemeinsam für alle Öffnungswege und bündelt gleichzeitige Aufrufe.
   * @return {Promise<boolean>} Ob Kategorien, Prioritäten und Zuständigkeiten bereitstehen.
   */
  const loadWidgetData = () => {
    if (widget.dataset.widgetDataLoaded === 'true') return Promise.resolve(true);
    if (!widgetDataPromise) {
      widgetDataPromise = fetch(widget.dataset.widgetDataUrl, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      })
        .then(async (response) => {
          const data = await window.KWMashaFeedlyReadJSON(response, 'Widgetdaten konnten nicht gelesen werden. Bitte versuche es erneut.');
          if (!response.ok || !data.success) throw new Error(data.message || 'Widgetdaten konnten nicht geladen werden.');
          const root = window.KWMashaFeedlyDOM.root();
          const categories = data.categories || [];
          const priorities = data.priorities || [];
          const option = (item, includeSystemKey = false) => {
            const element = new Option(item.title, item.id);
            if (includeSystemKey) element.dataset.systemKey = item.systemKey;
            return element;
          };
          root.querySelectorAll('[data-masha-feedly-create-category]').forEach((select) => {
            select.replaceChildren(...categories.filter((item) => item.canSelectForNewEntry).map((item) => option(item, true)));
          });
          root.querySelectorAll('[data-masha-feedly-edit-category]').forEach((select) => {
            select.replaceChildren(...categories.map((item) => option(item, true)));
          });
          root.querySelectorAll('[data-masha-feedly-category-filter]').forEach((select) => {
            const allOption = select.options[0];
            select.replaceChildren(allOption, ...categories.map((item) => option(item)));
          });
          root.querySelectorAll('select[name="PriorityID"]').forEach((select) => {
            select.replaceChildren(...priorities.map((item) => {
              const priorityOption = option(item);
              if (item.isDefault && select.closest('#kw-masha-feedly-create-form')) priorityOption.selected = true;
              return priorityOption;
            }));
          });
          root.querySelectorAll('[data-masha-feedly-priority-filter]').forEach((select) => {
            const allOption = select.options[0];
            select.replaceChildren(allOption, ...priorities.map((item) => option(item)));
          });
          root.querySelectorAll('[data-masha-feedly-assignees] > div').forEach((container) => {
            const name = container.closest('[data-masha-feedly-edit-form]') ? 'AssignedMemberIDs[]' : 'AssignedMemberIDs[]';
            container.replaceChildren(...(data.members || []).map((member) => {
              const label = document.createElement('label');
              label.className = 'kw-masha-feedly__assignee-choice';
              const checkbox = document.createElement('input');
              checkbox.type = 'checkbox'; checkbox.name = name; checkbox.value = member.id;
              const avatar = document.createElement('span');
              avatar.className = 'kw-masha-feedly__assignee-avatar';
              avatar.dataset.avatarInitials = member.initials;
              avatar.style.backgroundColor = member.color;
              avatar.setAttribute('aria-label', member.name);
              if (member.imageURL) { const image = document.createElement('img'); image.src = member.imageURL; image.alt = ''; avatar.append(image); }
              else avatar.textContent = member.initials;
              const title = document.createElement('span'); title.className = 'kw-masha-feedly__assignee-name'; title.textContent = member.name;
              label.append(checkbox, avatar, title);
              return label;
            }));
          });
          widget.dataset.widgetDataLoaded = 'true';
          document.dispatchEvent(new CustomEvent('kw-masha-feedly:widget-data-loaded'));
          return true;
        })
        .catch((error) => {
          widgetDataPromise = null;
          window.KWMashaFeedlyShowError(error.message || 'Widgetdaten konnten nicht geladen werden. Bitte versuche es erneut.');
          return false;
        });
    }
    return widgetDataPromise;
  };
  window.KWMashaFeedlyLoadWidgetData = loadWidgetData;
  document.addEventListener('kw-masha-feedly:opened', loadWidgetData);
  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', window.KWMashaFeedlyTranslate(open ? 'CLOSE_WIDGET' : 'OPEN_WIDGET'));
    panel.hidden = !open;
    widget.setAttribute('data-panel-open', String(open));
    document.dispatchEvent(new CustomEvent(open ? 'kw-masha-feedly:opened' : 'kw-masha-feedly:closed'));
  };
  toggle.addEventListener('click', () => {
    if (toggle.dataset.skipClickAfterPull === 'true') {
      delete toggle.dataset.skipClickAfterPull;
      return;
    }
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!expanded);
  });
  let pullStart = null;
  let pullDistance = 0;
  const resetPull = () => {
    widget.removeAttribute('data-panel-pulling');
    widget.style.removeProperty('--masha-panel-pull');
    widget.style.removeProperty('--masha-panel-pull-distance');
    panel.hidden = true;
    pullStart = null;
    pullDistance = 0;
  };
  toggle.addEventListener('pointerdown', (event) => {
    if (event.pointerType === 'touch' || event.button !== 0 || toggle.getAttribute('aria-expanded') === 'true') return;
    pullStart = { pointerID: event.pointerId, x: event.clientX };
    pullDistance = 0;
    toggle.setPointerCapture?.(event.pointerId);
  });
  toggle.addEventListener('pointermove', (event) => {
    if (!pullStart || event.pointerId !== pullStart.pointerID) return;
    const panelWidth = panel.getBoundingClientRect().width || 368;
    pullDistance = Math.min(panelWidth + 24, Math.max(0, pullStart.x - event.clientX));
    if (pullDistance < 5) return;

    const progress = window.KWMashaFeedlyDOM.panelPullProgress(pullDistance, panelWidth);
    panel.hidden = false;
    widget.setAttribute('data-panel-pulling', 'true');
    widget.style.setProperty('--masha-panel-pull', String(progress));
    widget.style.setProperty('--masha-panel-pull-distance', `${pullDistance}px`);
    event.preventDefault();
  });
  const finishPull = (event, cancelled = false) => {
    if (!pullStart || event.pointerId !== pullStart.pointerID) return;
    const didPull = pullDistance >= 5;
    const shouldOpen = !cancelled && didPull && window.KWMashaFeedlyDOM.shouldOpenPulledPanel(pullDistance);
    pullStart = null;
    if (!didPull) return;

    toggle.dataset.skipClickAfterPull = 'true';
    if (shouldOpen) {
      widget.removeAttribute('data-panel-pulling');
      widget.style.removeProperty('--masha-panel-pull');
      widget.style.removeProperty('--masha-panel-pull-distance');
      setOpen(true);
    } else {
      resetPull();
    }
  };
  toggle.addEventListener('pointerup', (event) => finishPull(event));
  toggle.addEventListener('pointercancel', (event) => finishPull(event, true));
  close.addEventListener('click', () => setOpen(false));
});
