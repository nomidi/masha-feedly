(() => {
  const rootSelector = '[data-masha-feedly-avatar-icons]';
  const domRoot = () => window.KWMashaFeedlyDOM?.root() || document;
  const eventTarget = (event) => window.KWMashaFeedlyDOM?.eventTarget(event) || event.target;

  function iconColorFor(value) {
    const color = /^#[\da-f]{6}$/i.test(value) ? value : '#F6B7A9';
    // Smaragd und Waldgrün verwenden wie die serverseitigen Avatare bewusst Weiß.
    if (['#35A98F', '#69B85A'].includes(color.toUpperCase())) return 'white';
    const channels = color.slice(1).match(/.{2}/g).map((channel) => parseInt(channel, 16) / 255);
    return (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) > 0.52 ? 'black' : 'white';
  }

  /** Hält die lokale Profilvorschau mit Farb- und Icon-Feld desselben Formulars synchron. */
  function updateAvatarPreview(scope) {
    const preview = scope.querySelector('[data-masha-feedly-avatar-preview]');
    if (!preview) return;
    const color = scope.querySelector('[name="MashaFeedlyColor"]')?.value || '#F4D06F';
    const iconID = scope.querySelector('[name="MashaFeedlyAvatarIcon"]')?.value ?? preview.dataset.iconId;
    const avatarBase = preview.dataset.avatarBase?.replace(/\/$/, '');
    const variant = iconColorFor(color);
    const choice = [...scope.querySelectorAll('[data-masha-feedly-avatar-icon-choice]')]
      .find((button) => button.dataset.iconId === iconID);
    // Ungespeicherte Symbole sind noch nicht im lokalen Avatar-Speicher vorhanden.
    const catalogueURL = choice?.dataset[`icon${variant[0].toUpperCase()}${variant.slice(1)}`];
    const url = iconID && avatarBase
      ? catalogueURL || `${avatarBase}/${encodeURIComponent(iconID)}/${variant}`
      : preview.dataset.uploadUrl;
    preview.dataset.iconId = iconID || '';
    preview.style.backgroundColor = color;
    preview.replaceChildren();
    if (url) {
      const image = document.createElement('img');
      image.src = url;
      image.alt = '';
      image.addEventListener('error', () => {
        if (image.parentNode === preview) preview.textContent = preview.dataset.initials;
      }, { once: true });
      preview.append(image);
    } else preview.textContent = preview.dataset.initials;
  }

  function updateIconContrast(root) {
    const colorField = (root.closest('form') || root.getRootNode()).querySelector('[name="MashaFeedlyColor"]');
    const variant = iconColorFor(colorField?.value || '');
    root.style.setProperty('--masha-avatar-color', colorField?.value || '#F4D06F');
    root.dataset.iconColor = variant;
    root.querySelectorAll('[data-masha-feedly-avatar-icon-choice]').forEach((button) => {
      const image = button.querySelector('img');
      if (image?.dataset.loaded === 'true') image.src = button.dataset[`icon${variant[0].toUpperCase()}${variant.slice(1)}`];
    });
    const selectedName = root.querySelector('[data-masha-feedly-avatar-icon-choice][aria-pressed="true"] span')?.textContent?.trim();
    const trigger = root.querySelector('[data-masha-feedly-avatar-icon-open]');
    if (trigger) trigger.textContent = selectedName ? `Symbol: ${selectedName} ändern` : 'Symbol auswählen';
  }

  /** Aktiviert ein Register und fordert erst dann die Bilder dieser Kategorie an. */
  function activateCategory(root, tab) {
    if (!tab) return;
    const categoryID = tab.dataset.mashaFeedlyAvatarIconTab;
    root.querySelectorAll('[data-masha-feedly-avatar-icon-tab]').forEach((candidate) => {
      const active = candidate === tab;
      candidate.setAttribute('aria-selected', String(active));
      candidate.setAttribute('tabindex', active ? '0' : '-1');
    });
    root.querySelectorAll('[role="tabpanel"]').forEach((panel) => {
      panel.hidden = panel.id !== `masha-feedly-icons-${categoryID}`;
    });
    const variant = root.dataset.iconColor || 'black';
    root.querySelectorAll(`#masha-feedly-icons-${categoryID} [data-masha-feedly-avatar-icon-choice] img`).forEach((image) => {
      const choice = image.closest('[data-masha-feedly-avatar-icon-choice]');
      image.src = choice.dataset[`icon${variant[0].toUpperCase()}${variant.slice(1)}`];
      image.dataset.loaded = 'true';
    });
  }

  document.addEventListener('click', (event) => {
    const target = eventTarget(event);
    const choice = target.closest('[data-masha-feedly-avatar-icon-choice]');
    const tab = target.closest('[data-masha-feedly-avatar-icon-tab]');
    const clear = target.closest('[data-masha-feedly-avatar-icon-clear]');
    const open = target.closest('[data-masha-feedly-avatar-icon-open]');
    const close = target.closest('[data-masha-feedly-avatar-icon-close]');
    const root = target.closest(rootSelector);
    if (!root || root.inert) return;
    const dialog = root.querySelector('[data-masha-feedly-avatar-icon-dialog]');
    if (open && dialog) {
      // Das Widget kann erst nach DOMContentLoaded eingebaut werden; beim Öffnen wird die aktive Kategorie sicher geladen.
      updateIconContrast(root);
      activateCategory(root, root.querySelector('[data-masha-feedly-avatar-icon-tab][aria-selected="true"]'));
      dialog.hidden = false;
      dialog.querySelector('[data-masha-feedly-avatar-icon-close]')?.focus();
      return;
    }
    if (close && dialog) {
      dialog.hidden = true;
      root.querySelector('[data-masha-feedly-avatar-icon-open]')?.focus();
      return;
    }
    if (tab) {
      activateCategory(root, tab);
      return;
    }
    if (!choice && !clear) return;

    const hidden = root.closest('form')?.querySelector('[name="MashaFeedlyAvatarIcon"]');
    if (!hidden) return;
    hidden.value = choice?.dataset.iconId || '';
    hidden.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
    updateAvatarPreview(root.closest('form') || root.getRootNode());
    root.querySelectorAll('[data-masha-feedly-avatar-icon-choice]').forEach((button) => {
      const selected = button === choice;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    root.querySelector('[data-masha-feedly-avatar-icon-clear]')?.setAttribute('aria-pressed', String(!choice));
    const selectedName = choice?.querySelector('span')?.textContent?.trim();
    const trigger = root.querySelector('[data-masha-feedly-avatar-icon-open]');
    if (trigger) trigger.textContent = selectedName ? `Symbol: ${selectedName} ändern` : 'Symbol auswählen';
    if (dialog) dialog.hidden = true;
    trigger?.focus();
  });

  document.addEventListener('keydown', (event) => {
    const tab = eventTarget(event).closest?.('[data-masha-feedly-avatar-icon-tab]');
    if (tab && ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
      const root = tab.closest(rootSelector);
      const tabs = [...root.querySelectorAll('[data-masha-feedly-avatar-icon-tab]')];
      const index = tabs.indexOf(tab);
      const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
        : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
      event.preventDefault();
      tabs[nextIndex]?.focus();
      activateCategory(root, tabs[nextIndex]);
      return;
    }
    if (event.key !== 'Escape') return;
    const activeRoot = eventTarget(event).closest?.(rootSelector);
    const dialog = activeRoot?.querySelector('[data-masha-feedly-avatar-icon-dialog]:not([hidden])');
    if (!dialog) return;
    dialog.hidden = true;
    activeRoot.querySelector('[data-masha-feedly-avatar-icon-open]')?.focus();
  });

  document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('change', (event) => {
      const field = eventTarget(event);
      if (field.name !== 'MashaFeedlyColor') return;
      const scope = field.closest('form') || field.getRootNode();
      scope.querySelectorAll(rootSelector).forEach(updateIconContrast);
      updateAvatarPreview(scope);
    });
    new Set([document, domRoot()]).forEach((scope) => scope.querySelectorAll(rootSelector).forEach((picker) => {
      updateIconContrast(picker);
      activateCategory(picker, picker.querySelector('[data-masha-feedly-avatar-icon-tab][aria-selected="true"]'));
    }));
  });
})();
