/** Ermöglicht freigegebenen Benutzern, einen Seitenbereich auszuwählen und als Eintrag zu speichern. */
window.KWMashaFeedlyEnvironment = (() => {
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  /** Erkennt die für eine Fehlermeldung hilfreiche System- und Fensterumgebung. */
  const collect = (userAgent = '', platform = '', screen = {}, viewport = {}) => {
    const agent = String(userAgent);
    const osVersion = agent.match(/Mac OS X ([\d_]+)/)?.[1]?.replaceAll('_', '.');
    let operatingSystem = osVersion ? `Mac OS ${osVersion}` : '';
    if (!operatingSystem && /Mac|iPhone|iPad/i.test(platform)) operatingSystem = /iPhone|iPad/i.test(platform) ? 'iOS' : 'Mac OS';
    if (!operatingSystem && /Windows NT ([\d.]+)/i.test(agent)) operatingSystem = `Windows NT ${agent.match(/Windows NT ([\d.]+)/i)[1]}`;
    if (!operatingSystem && /Android ([\d.]+)/i.test(agent)) operatingSystem = `Android ${agent.match(/Android ([\d.]+)/i)[1]}`;
    if (!operatingSystem && /Linux/i.test(agent)) operatingSystem = 'Linux';

    const browserMatch = agent.match(/Edg(?:e|A|iOS)?\/([\d.]+)/)
      || agent.match(/OPR\/([\d.]+)/)
      || agent.match(/Chrome\/([\d.]+)/)
      || agent.match(/Firefox\/([\d.]+)/)
      || agent.match(/Version\/([\d.]+).*Safari/);
    const browserName = browserMatch
      ? (browserMatch[0].startsWith('Edg') ? 'Microsoft Edge' : (browserMatch[0].startsWith('OPR') ? 'Opera' : (browserMatch[0].startsWith('Chrome') ? 'Chrome' : (browserMatch[0].startsWith('Firefox') ? 'Firefox' : 'Safari'))))
      : t('UNKNOWN_BROWSER');
    const dimension = (width, height) => Number(width) > 0 && Number(height) > 0 ? `${Number(width)} × ${Number(height)} px` : '';
    return {
      operatingSystem,
      browser: browserMatch ? `${browserName} ${browserMatch[1]}` : browserName,
      userAgent: agent.slice(0, 512),
      resolution: dimension(screen.width, screen.height),
      browserWindow: dimension(viewport.innerWidth, viewport.innerHeight),
      colorDepth: Number(screen.colorDepth) > 0 ? String(screen.colorDepth) : '',
    };
  };
  return { collect };
})();

document.addEventListener('DOMContentLoaded', () => {
  const widget = document.querySelector('[data-kw-masha-feedly]');
  if (!widget) return;

  const startButton = widget.querySelector('[data-masha-feedly-start-selection]');
  const toggleButton = widget.querySelector('.kw-masha-feedly__toggle');
  const banner = widget.querySelector('[data-masha-feedly-selection-banner]');
  const modal = widget.querySelector('[data-masha-feedly-modal]');
  const form = widget.querySelector('[data-masha-feedly-entry-form]');
  const context = widget.querySelector('[data-masha-feedly-selected-context]');
  const status = widget.querySelector('[data-masha-feedly-form-status]');
  const dialog = modal?.querySelector('[role="dialog"]');
  const toast = widget.querySelector('[data-masha-feedly-save-toast]');
  const toastMessage = widget.querySelector('[data-masha-feedly-save-message]');
  const contentField = form?.querySelector('[name="Content"]');
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  let selecting = false;
  let highlighted = null;
  const getElementSelector = (element) => {
    if (element.id) return `#${CSS.escape(element.id)}`;
    const parts = [];
    let current = element;
    while (current && current.nodeType === 1 && current !== document.body && parts.length < 5) {
      let part = current.tagName.toLowerCase();
      if (current.classList.length) part += `.${[...current.classList].slice(0, 2).map((name) => CSS.escape(name)).join('.')}`;
      const siblings = current.parentElement ? [...current.parentElement.children].filter((sibling) => sibling.tagName === current.tagName) : [];
      if (siblings.length > 1) part += `:nth-of-type(${siblings.indexOf(current) + 1})`;
      parts.unshift(part);
      current = current.parentElement;
    }
    return parts.join(' > ');
  };

  const stopSelection = () => {
    selecting = false;
    document.body.classList.remove('kw-masha-feedly-is-selecting');
    banner.hidden = true;
    if (highlighted) highlighted.classList.remove('kw-masha-feedly-selected-target');
    highlighted = null;
  };

  const cancelSelection = () => {
    if (!selecting) return;
    stopSelection();
    const panel = widget.querySelector('.kw-masha-feedly__panel');
    const toggle = widget.querySelector('.kw-masha-feedly__toggle');
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', t('CLOSE_WIDGET'));
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-selection-cancelled'));
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:opened'));
    startButton?.focus?.();
  };

  const showEntryDialog = (element, options = {}) => {
    const selector = options.elementSelector || getElementSelector(element);
    const selectedText = options.selectedText ?? (element.innerText || element.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 220);
    context.textContent = selectedText ? t('ENTRY_SELECTED_CONTEXT', { text: selectedText }) : t('ENTRY_CONTEXT_EMPTY');
    ['PageURL', 'ElementSelector', 'ElementText', 'OperatingSystem', 'Browser', 'UserAgent', 'Resolution', 'BrowserWindow', 'ColorDepth']
      .forEach((name) => form.querySelector(`[name="${name}"]`)?.remove());
    const browserInfo = window.KWMashaFeedlyEnvironment.collect(
      window.navigator?.userAgent || '',
      window.navigator?.platform || '',
      window.screen || {},
      window
    );
    [
      ['PageURL', window.location.href],
      ['ElementSelector', selector],
      ['ElementText', selectedText],
      ['OperatingSystem', browserInfo.operatingSystem],
      ['Browser', browserInfo.browser],
      ['UserAgent', browserInfo.userAgent],
      ['Resolution', browserInfo.resolution],
      ['BrowserWindow', browserInfo.browserWindow],
      ['ColorDepth', browserInfo.colorDepth],
    ].forEach(([name, value]) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.append(input);
    });
    modal.hidden = false;
    form.querySelector('[name="Content"]').focus();
    if (options.dispatchOnboarding !== false) {
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-target-selected'));
    }
  };

  startButton?.addEventListener('click', () => {
    stopSelection();
    selecting = true;
    banner.hidden = false;
    document.body.classList.add('kw-masha-feedly-is-selecting');
    widget.querySelector('.kw-masha-feedly__panel').hidden = true;
    widget.querySelector('.kw-masha-feedly__toggle').setAttribute('aria-expanded', 'false');
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-selection-started'));
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:closed'));
  });

  document.addEventListener('keydown', (event) => {
    if (modal && !modal.hidden) {
      if (event.key === 'Escape') {
        event.preventDefault();
        modal.hidden = true;
        toggleButton?.focus?.();
        document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-form-closed'));
        return;
      }
      if (event.key === 'Tab' && dialog) {
        const candidates = Array.from(dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
          .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
        const first = candidates[0] || dialog;
        const last = candidates[candidates.length - 1] || dialog;
        if ((event.shiftKey && document.activeElement === first) || (!event.shiftKey && document.activeElement === last)) {
          event.preventDefault();
          (event.shiftKey ? last : first).focus();
        }
      }
      return;
    }
    if (event.key === 'Escape' && selecting) {
      event.preventDefault();
      cancelSelection();
    }
  });

  document.addEventListener('pointerover', (event) => {
    if (!selecting || widget.contains(event.target)) return;
    if (highlighted) highlighted.classList.remove('kw-masha-feedly-selected-target');
    highlighted = event.target.closest('body *');
    if (highlighted && highlighted !== document.body) highlighted.classList.add('kw-masha-feedly-selected-target');
  });

  document.addEventListener('click', (event) => {
    if (!selecting || widget.contains(event.target)) return;
    event.preventDefault();
    event.stopPropagation();
    const element = event.target.closest('body *');
    stopSelection();
    if (element && element !== document.body) showEntryDialog(element);
  }, true);

  widget.querySelector('[data-masha-feedly-cancel-selection]')?.addEventListener('click', cancelSelection);
  widget.querySelector('[data-masha-feedly-dismiss-toast]')?.addEventListener('click', () => { toast.hidden = true; });
  widget.querySelectorAll('[data-masha-feedly-close-modal]').forEach((button) => button.addEventListener('click', () => {
    modal.hidden = true;
    toggleButton?.focus?.();
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-form-closed'));
  }));
  modal?.addEventListener('click', (event) => {
    if (event.target === modal) { modal.hidden = true; toggleButton?.focus?.(); }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    // Der Speichern-Button liegt im Dialog-Footer außerhalb des <form> und ist
    // über das HTML-Attribut form="…" zugeordnet.
    const submit = form.querySelector('[type="submit"]')
      || (form.id ? document.querySelector(`button[type="submit"][form="${CSS.escape(form.id)}"]`) : null);
    if (!submit) {
      status.textContent = t('CREATE_SAVE_ERROR');
      return;
    }
    submit.disabled = true;
    status.textContent = t('CREATE_SAVING');
    const data = new FormData(form);
    data.set('SecurityID', form.dataset.securityId);
    try {
      const response = await fetch(form.dataset.createUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('CREATE_SAVE_ERROR'));
      status.textContent = result.message;
      toastMessage.textContent = result.message;
      toast.hidden = false;
      const confirmation = document.createElement('article');
      confirmation.className = 'kw-masha-feedly__entry-confirmation';
      confirmation.textContent = result.title || t('CREATE_ENTRY_FALLBACK');
      widget.querySelector('.kw-masha-feedly__column')?.append(confirmation);
      const panel = widget.querySelector('.kw-masha-feedly__panel');
      const toggle = widget.querySelector('.kw-masha-feedly__toggle');
      panel.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', t('CLOSE_WIDGET'));
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:opened'));
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:refresh'));
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-saved'));
      form.reset();
      setTimeout(() => { modal.hidden = true; toggleButton?.focus?.(); }, 500);
      setTimeout(() => { toast.hidden = true; }, 6000);
    } catch (error) {
      status.textContent = error.message || t('CREATE_SAVE_ERROR');
    } finally {
      submit.disabled = false;
    }
  });

  const currentURL = new URL(window.location.href);
  if (currentURL.searchParams.get('masha-feedly-create') === '1') {
    currentURL.searchParams.delete('masha-feedly-create');
    window.history.replaceState({}, '', `${currentURL.pathname}${currentURL.search}${currentURL.hash}`);
    const panel = widget.querySelector('.kw-masha-feedly__panel');
    panel.hidden = false;
    toggleButton?.setAttribute('aria-expanded', 'true');
    toggleButton?.setAttribute('aria-label', t('CLOSE_WIDGET'));
    showEntryDialog(document.body, { elementSelector: 'body', selectedText: '', dispatchOnboarding: false });
  }
});
