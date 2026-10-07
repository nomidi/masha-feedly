/** Führt neue Masha-Feedly-Mitglieder abbrechbar durch die erste Fehlermeldung. */
document.addEventListener('DOMContentLoaded', () => {
  const widget = (window.KWMashaFeedlyDOM?.widget() || document.querySelector('[data-kw-masha-feedly]'));
  if (!widget) return;

  const welcome = widget.querySelector('[data-masha-feedly-onboarding-welcome]');
  const welcomeDialog = welcome?.querySelector('[role="dialog"]');
  const start = widget.querySelector('[data-masha-feedly-tour-start]');
  const skip = widget.querySelector('[data-masha-feedly-tour-skip]');
  const restart = widget.querySelector('[data-masha-feedly-restart-onboarding]');
  const restartStatus = widget.querySelector('[data-masha-feedly-restart-status]');
  const tip = widget.querySelector('[data-masha-feedly-onboarding-tip]');
  const thanks = widget.querySelector('[data-masha-feedly-onboarding-thanks]');
  const thanksDialog = thanks?.querySelector('[role="dialog"]');
  const tipText = widget.querySelector('[data-masha-feedly-onboarding-text]');
  const selectionMessage = widget.querySelector('[data-masha-feedly-selection-message]');
  const cancelSelectionButton = widget.querySelector('[data-masha-feedly-cancel-selection]');
  const t = (key) => window.KWMashaFeedlyTranslate(key);
  const shade = document.createElement('div');
  shade.className = 'kw-masha-feedly__onboarding-shade';
  shade.hidden = true;
  shade.setAttribute('aria-hidden', 'true');
  (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(shade);
  let step = 'welcome';
  // Nach abgeschlossenem oder nicht gestarteten Onboarding dürfen normale Eintragsaktionen
  // keine Tour-Schritte auslösen. Ein expliziter Neustart setzt finished wieder zurück.
  let finished = widget.dataset.onboardingEnabled !== '1';
  let currentMessage = '';
  let feedbackTimer = null;
  let spotlight = null;
  // Die ursprünglichen Zustände einmal merken. Würden wir sie bei jedem
  // Schritt neu aufnehmen, könnten bereits gesperrte Felder dauerhaft
  // deaktiviert bleiben, wenn der nächste Schritt dieselben Felder freigibt.
  const originalControlStates = new Map();

  const matches = (element, selector) => element?.closest?.(selector)
    || (element?.matches?.(selector) ? element : null);

  const updateSpotlight = () => {
    spotlight?.classList?.remove('is-onboarding-target');
    const selector = {
      icon: '.kw-masha-feedly__toggle',
      plus: '[data-masha-feedly-start-selection]',
      entries: '[data-masha-feedly-open-page-list]',
      entry: '[data-masha-feedly-entries-list]',
      comment: '[data-masha-feedly-comment-form]',
      manage: '[data-masha-feedly-edit-modal]',
    }[step];
    spotlight = selector ? widget.querySelector(selector) : null;
    spotlight?.classList?.add('is-onboarding-target');
  };

  const interactionSelector = (currentStep) => ({
    icon: '.kw-masha-feedly__toggle',
    plus: '[data-masha-feedly-start-selection]',
    target: '[data-masha-feedly-cancel-selection]',
    form: '[data-masha-feedly-entry-form]',
    entries: '[data-masha-feedly-open-page-list]',
    entry: '.kw-masha-feedly__entry-card, [data-masha-feedly-close-list]',
    comment: '[data-masha-feedly-comment-form]',
    manage: '[data-masha-feedly-edit-form]',
  }[currentStep] || '');

  const restoreControls = () => {
    originalControlStates.forEach((disabled, control) => { control.disabled = disabled; });
  };

  const trapDialogFocus = (dialog, event) => {
    if (!dialog || event.key !== 'Tab') return;
    const focusable = Array.from(dialog.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && (window.KWMashaFeedlyDOM?.activeElement() || document.activeElement) === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && (window.KWMashaFeedlyDOM?.activeElement() || document.activeElement) === last) {
      event.preventDefault();
      first.focus();
    }
  };

  // Native Select-Menüs und Buttons müssen auch bei Accessibility-Klicks gesperrt sein.
  const lockControlsToStep = () => {
    restoreControls();
    const allowed = interactionSelector(step);
    widget.querySelectorAll('button, input, select, textarea').forEach((control) => {
      if (matches(control, '[data-masha-feedly-tour-cancel]')) return;
      if (!originalControlStates.has(control)) originalControlStates.set(control, Boolean(control.disabled));
      // Formular-Buttons dürfen außerhalb des <form> stehen und über form="…"
      // zugeordnet sein (z. B. im Dialog-Footer). Sie gehören trotzdem zum Schritt.
      if (!allowed || (!matches(control, allowed) && !matches(control.form, allowed))) control.disabled = true;
    });
  };

  const beginTour = () => {
    finished = false;
    step = 'welcome';
    welcome.hidden = false;
    tip.hidden = true;
    thanks.hidden = true;
    shade.hidden = true;
    spotlight?.classList?.remove('is-onboarding-target');
    restoreControls();
    spotlight = null;
    widget.querySelector('[data-masha-feedly-tour-start]')?.focus?.();
  };

  const complete = (showThanks = false) => {
    if (finished) return;
    finished = true;
    welcome.hidden = true;
    tip.hidden = true;
    thanks.hidden = !showThanks;
    shade.hidden = true;
    spotlight?.classList?.remove('is-onboarding-target');
    restoreControls();
    const data = new FormData();
    data.set('SecurityID', widget.dataset.securityId || '');
    fetch(widget.dataset.onboardingUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
    }).catch(() => {});
    if (showThanks) widget.querySelector('[data-masha-feedly-thanks-close]')?.focus?.();
    else widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
  };

  const showTip = (message) => {
    currentMessage = message;
    tipText.textContent = t(message);
    tip.hidden = false;
    tip.classList.remove('is-feedback');
    lockControlsToStep();
    updateSpotlight();
  };

  const allowClick = (event) => {
    const target = (window.KWMashaFeedlyDOM?.eventTarget(event) || event.target);
    if (matches(target, '[data-masha-feedly-tour-cancel]')) return true;
    if (step === 'icon') return Boolean(matches(target, '.kw-masha-feedly__toggle'));
    if (step === 'plus') return Boolean(matches(target, '[data-masha-feedly-start-selection]'));
    if (step === 'target') {
      return matches(target, '[data-masha-feedly-cancel-selection]')
        ? true
        : !widget.contains(target);
    }
    if (step === 'form') {
      // Bei Klick auf den Text oder das Icon im Submit-Button ist (window.KWMashaFeedlyDOM?.eventTarget(event) || event.target)
      // ein Kind-Element. Die Formularzuordnung sitzt aber auf dem Button.
      const clickedControl = target?.closest?.('button, input, select, textarea');
      return Boolean(matches(target, '[data-masha-feedly-entry-form]')
        || matches(target.form || clickedControl?.form, '[data-masha-feedly-entry-form]')
        || matches(target, '[data-masha-feedly-close-modal]'));
    }
    if (step === 'entries') return Boolean(matches(target, '[data-masha-feedly-open-page-list]'));
    if (step === 'entry') {
      return Boolean(matches(target, '.kw-masha-feedly__entry-card')
        || matches(target, '[data-masha-feedly-close-list]'));
    }
    if (step === 'comment') return Boolean(matches(target, '[data-masha-feedly-comment-form]'));
    if (step === 'manage') {
      return Boolean(matches(target, '[data-masha-feedly-edit-form]'));
    }
    return true;
  };

  const showBlockedFeedback = () => {
    const wasHidden = tip.hidden;
    tip.hidden = false;
    tip.classList.add('is-feedback');
    tipText.textContent = t(`TOUR_BLOCKED_${step.toUpperCase()}`);
    if (feedbackTimer !== null) window.clearTimeout(feedbackTimer);
    feedbackTimer = window.setTimeout(() => {
      tip.classList.remove('is-feedback');
      tipText.textContent = t(currentMessage);
      tip.hidden = wasHidden;
      feedbackTimer = null;
    }, 2200);
  };

  document.addEventListener('click', (event) => {
    if (finished || step === 'welcome' || allowClick(event)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    showBlockedFeedback();
  }, true);

  document.addEventListener('keydown', (event) => {
    if (welcome && !welcome.hidden) {
      if (event.key === 'Escape') {
        event.preventDefault();
        skip?.click();
      } else trapDialogFocus(welcomeDialog, event);
      return;
    }
    if (thanks && !thanks.hidden) {
      if (event.key === 'Escape') {
        event.preventDefault();
        widget.querySelector('[data-masha-feedly-thanks-close]')?.click();
      } else trapDialogFocus(thanksDialog, event);
      return;
    }
    if (finished || step === 'welcome') return;
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopImmediatePropagation();
      complete();
      return;
    }
    const allowedSelector = {
      icon: '.kw-masha-feedly__toggle',
      plus: '[data-masha-feedly-start-selection]',
      form: '[data-masha-feedly-entry-form]',
      entries: '[data-masha-feedly-open-page-list]',
      entry: '.kw-masha-feedly__entry-card',
      comment: '[data-masha-feedly-comment-form]',
      manage: '[data-masha-feedly-edit-form]',
    }[step];
    if (matches((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target), '[data-masha-feedly-tour-cancel]')) return;
    if (step === 'target' && (event.key === 'Tab'
      || matches((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target), '[data-masha-feedly-cancel-selection]')
      || (!widget.contains((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target)) && ['Enter', ' '].includes(event.key)))) return;
    if (allowedSelector && matches((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target), allowedSelector)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    showBlockedFeedback();
  }, true);

  welcome.hidden = widget.dataset.onboardingEnabled !== '1';
  if (!welcome.hidden) widget.querySelector('[data-masha-feedly-tour-start]')?.focus?.();
  start?.addEventListener('click', () => {
    // Neustart aus der Hilfe kann bei geöffnetem Feedly-Fenster erfolgen.
    // Für Schritt 1 muss das Symbol wieder als klares Ziel sichtbar sein.
    if (widget.getAttribute('data-panel-open') === 'true') {
      widget.querySelector('.kw-masha-feedly__toggle')?.click();
    }
    step = 'icon';
    welcome.hidden = true;
    shade.hidden = false;
    showTip('TOUR_STEP_ICON');
    widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
  });
  restart?.addEventListener('click', async () => {
    restart.disabled = true;
    if (restartStatus) restartStatus.textContent = '';
    const data = new FormData();
    data.set('SecurityID', widget.dataset.securityId || '');
    try {
      const response = await fetch(widget.dataset.onboardingRestartUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data,
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error('Restart rejected');
      const helpModal = widget.querySelector('[data-masha-feedly-help-modal]');
      if (helpModal) helpModal.hidden = true;
      beginTour();
    } catch (_) {
      if (restartStatus) restartStatus.textContent = t('TOUR_RESTART_ERROR');
    } finally {
      restart.disabled = false;
    }
  });
  skip?.addEventListener('click', () => complete());
  widget.querySelector('[data-masha-feedly-tour-cancel]')?.addEventListener('click', () => complete());
  widget.querySelector('[data-masha-feedly-thanks-close]')?.addEventListener('click', () => {
    thanks.hidden = true;
    widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
  });
  thanks?.addEventListener('click', (event) => {
    if ((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target) === thanks) {
      thanks.hidden = true;
      widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
    }
  });

  document.addEventListener('kw-masha-feedly:opened', () => {
    if (step !== 'icon') return;
    step = 'plus';
    showTip('TOUR_STEP_PLUS');
  });
  document.addEventListener('kw-masha-feedly:onboarding-selection-started', () => {
    if (step !== 'plus') return;
    step = 'target';
    tip.hidden = true;
    if (selectionMessage) selectionMessage.textContent = t('TOUR_SELECTION_TARGET');
    if (cancelSelectionButton) cancelSelectionButton.textContent = t('TOUR_CANCEL');
    lockControlsToStep();
    cancelSelectionButton?.focus?.();
  });
  document.addEventListener('kw-masha-feedly:onboarding-selection-cancelled', () => {
    if (step !== 'target') return;
    complete();
  });
  document.addEventListener('kw-masha-feedly:onboarding-target-selected', () => {
    if (step !== 'target') return;
    step = 'form';
    showTip('TOUR_STEP_FORM');
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-saved', () => {
    if (finished || step !== 'form') return;
    step = 'entries';
    showTip('TOUR_STEP_VIEW_ENTRIES');
  });
  document.addEventListener('kw-masha-feedly:onboarding-list-opened', () => {
    if (step !== 'entries') return;
    step = 'entry';
    showTip('TOUR_STEP_OPEN_ENTRY');
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-opened', () => {
    if (step !== 'entry') return;
    step = 'comment';
    showTip('TOUR_STEP_COMMENT_ENTRY');
  });
  document.addEventListener('kw-masha-feedly:onboarding-comment-saved', () => {
    if (step !== 'comment') return;
    step = 'manage';
    showTip('TOUR_STEP_MANAGE_ENTRY');
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-updated', () => {
    if (step !== 'manage') return;
    complete(true);
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-closed', () => {
    if (step === 'manage') complete();
  });
  document.addEventListener('kw-masha-feedly:onboarding-list-closed', () => {
    if (step === 'entry') complete();
  });
  document.addEventListener('kw-masha-feedly:onboarding-form-closed', () => {
    if (step === 'form') complete();
  });
  widget.querySelector('[data-masha-feedly-cancel-selection]')?.addEventListener('click', () => {
    if (step === 'target') complete();
  });
});
