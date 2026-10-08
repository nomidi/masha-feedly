/** Führt neue Masha-Feedly-Mitglieder abbrechbar durch die erste Fehlermeldung. */
document.addEventListener('DOMContentLoaded', () => {
  const widget = (window.KWMashaFeedlyDOM?.widget() || document.querySelector('[data-kw-masha-feedly]'));
  if (!widget) return;

  const welcome = widget.querySelector('[data-masha-feedly-onboarding-welcome]');
  const welcomeDialog = welcome?.querySelector('[role="dialog"]');
  const start = widget.querySelector('[data-masha-feedly-tour-start]');
  const skip = widget.querySelector('[data-masha-feedly-tour-skip]');
  const mobileClose = widget.querySelector('[data-masha-feedly-tour-mobile-close]');
  const restart = widget.querySelector('[data-masha-feedly-restart-onboarding]');
  const restartStatus = widget.querySelector('[data-masha-feedly-restart-status]');
  const tip = widget.querySelector('[data-masha-feedly-onboarding-tip]');
  const thanks = widget.querySelector('[data-masha-feedly-onboarding-thanks]');
  const thanksDialog = thanks?.querySelector('[role="dialog"]');
  const tipText = widget.querySelector('[data-masha-feedly-onboarding-text]');
  const selectionMessage = widget.querySelector('[data-masha-feedly-selection-message]');
  const cancelSelectionButton = widget.querySelector('[data-masha-feedly-cancel-selection]');
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(
    widget.dataset.address === 'sie' && window.KWMashaFeedlyTranslations?.[`${key}_SIE`] ? `${key}_SIE` : key,
    values,
  );
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
  let spotlights = [];
  let createdEntryID = null;
  // Die ursprünglichen Zustände einmal merken. Würden wir sie bei jedem
  // Schritt neu aufnehmen, könnten bereits gesperrte Felder dauerhaft
  // deaktiviert bleiben, wenn der nächste Schritt dieselben Felder freigibt.
  const originalControlStates = new Map();

  widget.querySelectorAll('[data-masha-feedly-address-copy]').forEach((element) => {
    element.textContent = t(element.dataset.mashaFeedlyAddressCopy);
  });

  const matches = (element, selector) => element?.closest?.(selector)
    || (element?.matches?.(selector) ? element : null);

  const updateSpotlight = () => {
    spotlights.forEach((element) => element.classList?.remove('is-onboarding-target'));
    const selectors = {
      icon: '.kw-masha-feedly__toggle',
      plus: '[data-masha-feedly-start-selection]',
      form: widget.querySelector('[data-masha-feedly-entry-form] [name="Content"]')?.value?.trim()
        ? '[type="submit"][form="kw-masha-feedly-create-form"]'
        : '[data-masha-feedly-entry-form] [name="Content"]',
      entries: '[data-masha-feedly-open-page-list]',
      entry: createdEntryID ? `[data-entry-id="${createdEntryID}"]` : null,
      comment: '[data-masha-feedly-comment-form]',
      manage: [
        '[data-masha-feedly-edit-form] [data-masha-feedly-edit-category]',
        '[data-masha-feedly-edit-form] [name="PriorityID"]',
        '[data-masha-feedly-edit-form] .kw-masha-feedly__assignees',
        '[data-masha-feedly-edit-form] [type="submit"]',
      ],
    }[step];
    spotlights = (Array.isArray(selectors) ? selectors : selectors ? [selectors] : [])
      .map((selector) => widget.querySelector(selector))
      .filter(Boolean);
    spotlights.forEach((element) => element.classList?.add('is-onboarding-target'));
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

  /** Liest den SCSS-Breakpoint am Widget, auch wenn Dialoge gerade ausgeblendet sind. */
  const isCompactViewport = () => {
    return window.getComputedStyle?.(widget)?.getPropertyValue('--masha-mobile-layout').trim() === '1';
  };
  let compactViewport = isCompactViewport();

  /** Schließt Fenster nur beim Wechsel der Variante; normale Größenänderungen behalten die Ansicht. */
  const adaptTourToViewport = () => {
    const compact = isCompactViewport();
    if (compact === compactViewport) return;
    compactViewport = compact;
    const restartTour = !finished;
    // Bereichsauswahl und Tour-Sperren lösen, ohne einen Abschluss an den Server zu melden.
    step = 'welcome';
    window.clearTimeout(feedbackTimer);
    restoreControls();
    cancelSelectionButton?.click();
    widget.querySelector('.kw-masha-feedly__panel .kw-masha-feedly__close')?.click();
    ['[data-masha-feedly-modal]', '[data-masha-feedly-entries-modal]', '[data-masha-feedly-edit-modal]', '[data-masha-feedly-help-modal]', '[data-masha-feedly-avatar-icon-dialog]']
      .forEach((selector) => { const modal = widget.querySelector(selector); if (modal) modal.hidden = true; });
    tip.hidden = true;
    thanks.hidden = true;
    shade.hidden = true;
    spotlights.forEach((element) => element.classList?.remove('is-onboarding-target'));
    spotlights = [];
    window.KWMashaFeedlyEffects?.cancelActive();
    if (restartTour) beginTour();
    else {
      welcome.hidden = true;
      widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
    }
  };
  window.addEventListener?.('resize', adaptTourToViewport);

  const beginTour = () => {
    finished = false;
    step = 'welcome';
    createdEntryID = null;
    welcome.hidden = false;
    tip.hidden = true;
    thanks.hidden = true;
    shade.hidden = true;
    spotlights.forEach((element) => element.classList?.remove('is-onboarding-target'));
    restoreControls();
    spotlights = [];
    (mobileClose && mobileClose.getClientRects?.().length ? mobileClose : start)?.focus?.();
  };

  const complete = (showThanks = false, deferred = false) => {
    if (finished) return;
    finished = true;
    welcome.hidden = true;
    tip.hidden = true;
    thanks.hidden = !showThanks;
    shade.hidden = true;
    spotlights.forEach((element) => element.classList?.remove('is-onboarding-target'));
    restoreControls();
    const data = new FormData();
    data.set('SecurityID', widget.dataset.securityId || '');
    if (deferred) data.set('Deferred', '1');
    fetch(widget.dataset.onboardingUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
    }).catch(() => {});
    if (showThanks) {
      thanksDialog?.querySelector('[data-masha-feedly-avatar-icon-dialog]')?.setAttribute?.('hidden', '');
      thanksDialog?.querySelector('#kw-masha-feedly-thanks-title')?.focus?.();
      if (preferencesForm) preferencesForm.scrollTop = 0;
    } else widget.querySelector('.kw-masha-feedly__toggle')?.focus?.();
  };

  const preferencesForm = widget.querySelector('[data-masha-feedly-profile-preferences]');
  preferencesForm?.querySelector('input[name="MashaFeedlyDisableSoundEffects"]')?.addEventListener('change', () => window.KWMashaFeedlyEffects?.cancelActive());
  const emailMaster = preferencesForm?.querySelector('[data-masha-feedly-email-master]');
  const updateEmailOptionsState = () => preferencesForm?.querySelectorAll('[data-masha-feedly-email-option]')
    .forEach((option) => { option.disabled = !emailMaster?.checked; });
  emailMaster?.addEventListener('change', updateEmailOptionsState);
  updateEmailOptionsState();

  thanks?.addEventListener('click', async (event) => {
    const button = event.target?.closest?.('[data-masha-feedly-theme-preview]');
    if (!button || !thanks.contains(button) || button.disabled) return;
    const status = thanks.querySelector('[data-masha-feedly-theme-preview-status]');
    const category = button.dataset.mashaFeedlyThemePreview;
    const effects = window.KWMashaFeedlyEffects;
    if (!category || !status || !effects) return;
    button.disabled = true;
    status.textContent = '';
    try {
      const catalogue = await effects.refreshCatalogue();
      const candidates = catalogue.effects.filter((effect) => effect.categories.includes(category));
      if (!candidates.length) {
        const fallback = await effects.previewFallback(document);
        status.textContent = fallback
          ? t('TOUR_THANKS_EXAMPLE_FALLBACK')
          : t('TOUR_THANKS_EXAMPLE_UNAVAILABLE');
        return;
      }
      const chosen = effects.choose(candidates);
      const preview = window.KWMashaFeedlyEntries?.previewCompletionAnimation
        ? window.KWMashaFeedlyEntries.previewCompletionAnimation(document, window, chosen.id)
        : effects.preview(chosen.id, document);
      const result = await preview;
      status.textContent = result
        ? t('TOUR_THANKS_EXAMPLE_STARTED')
        : t('TOUR_THANKS_EXAMPLE_UNAVAILABLE');
    } catch (_) {
      status.textContent = t('TOUR_THANKS_EXAMPLE_UNAVAILABLE');
    } finally {
      button.disabled = false;
    }
  });

  preferencesForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const status = form.querySelector('[data-masha-feedly-profile-preferences-status]');
    const submit = form.querySelector('[type="submit"]')
      || widget.querySelector('[data-masha-feedly-onboarding-save]');
    if (!form.dataset.saveUrl || !status || !submit) return;
    submit.disabled = true;
    status.textContent = t('TOUR_PREFERENCES_SAVING');
    try {
      const data = new FormData(form);
      data.set('SecurityID', form.dataset.securityId || widget.dataset.securityId || '');
      form.querySelectorAll('[data-masha-feedly-email-master], [data-masha-feedly-email-option]').forEach((checkbox) => {
        data.set(checkbox.name, checkbox.checked ? '1' : '0');
      });
      const response = await fetch(form.dataset.saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data,
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) {
        throw new Error(result.message || t('TOUR_PREFERENCES_ERROR'));
      }
      if (result.theme) widget.dataset.theme = result.theme;
      if (typeof result.disableSoundEffects === 'boolean') widget.dataset.disableSoundEffects = result.disableSoundEffects ? '1' : '0';
      status.textContent = result.message || t('TOUR_PREFERENCES_SAVED');
    } catch (error) {
      status.textContent = error instanceof Error && error.message !== 'Failed to fetch'
        ? error.message
        : t('TOUR_PREFERENCES_ERROR');
    } finally {
      submit.disabled = false;
    }
  });

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
      const card = matches(target, '.kw-masha-feedly__entry-card');
      return Boolean((card && Number(card.dataset.entryId) === createdEntryID)
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
    // Die Bereichsauswahl verarbeitet denselben Klick ebenfalls im Capture-Modus.
    // Nur diesen bestätigten Auswahlklick geben wir frei, falls das Formular den
    // Tour-Schritt schon synchron umgestellt hat.
    if (finished || step === 'welcome' || event.mashaFeedlyTargetSelectionHandled === true || allowClick(event)) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    showBlockedFeedback();
  }, true);

  document.addEventListener('keydown', (event) => {
    if (welcome && !welcome.hidden) {
      if (event.key === 'Escape') {
        event.preventDefault();
        complete();
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
      entry: createdEntryID ? `[data-entry-id="${createdEntryID}"]` : '',
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
  if (!welcome.hidden) (mobileClose && mobileClose.getClientRects?.().length ? mobileClose : start)?.focus?.();
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
  skip?.addEventListener('click', () => complete(false, true));
  widget.querySelector('[data-masha-feedly-tour-end]')?.addEventListener('click', () => complete());
  // OK verschiebt die Einführung; Abbrechen beendet sie dauerhaft.
  mobileClose?.addEventListener('click', () => complete(false, true));
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
  widget.querySelector('[data-masha-feedly-entry-form] [name="Content"]')?.addEventListener('input', () => {
    if (step === 'form') updateSpotlight();
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-saved', (event) => {
    if (finished || step !== 'form') return;
    createdEntryID = Number(event.detail?.entryID) || null;
    step = 'entries';
    showTip('TOUR_STEP_VIEW_ENTRIES');
  });
  document.addEventListener('kw-masha-feedly:onboarding-list-opened', () => {
    if (step !== 'entries') return;
    step = 'entry';
    showTip('TOUR_STEP_OPEN_ENTRY');
  });
  document.addEventListener('kw-masha-feedly:onboarding-list-rendered', () => {
    if (step === 'entry') updateSpotlight();
  });
  document.addEventListener('kw-masha-feedly:onboarding-entry-opened', (event) => {
    if (step !== 'entry' || Number(event.detail?.entryID) !== createdEntryID) return;
    step = 'comment';
    showTip('TOUR_STEP_COMMENT_ENTRY');
    widget.querySelector('[data-masha-feedly-comment-form] textarea')?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
  });
  document.addEventListener('kw-masha-feedly:onboarding-comment-saved', () => {
    if (step !== 'comment') return;
    step = 'manage';
    showTip('TOUR_STEP_MANAGE_ENTRY');
    widget.querySelector('[data-masha-feedly-edit-category]')?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
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
