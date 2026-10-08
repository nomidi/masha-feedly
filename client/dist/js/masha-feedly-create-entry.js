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
  /** Speichert die Klickstelle relativ zum ausgewählten Element, damit sie bei anderer Fenstergröße wiederfindbar ist. */
  const elementPosition = (element, clientX, clientY) => {
    const bounds = element?.getBoundingClientRect?.();
    if (!bounds || bounds.width <= 0 || bounds.height <= 0) return { x: '0.5', y: '0.5' };
    const ratio = (value, start, size) => {
      const coordinate = Number(value);
      return Number.isFinite(coordinate) ? Math.min(1, Math.max(0, (coordinate - start) / size)).toFixed(5) : '0.5';
    };
    return { x: ratio(clientX, bounds.left, bounds.width), y: ratio(clientY, bounds.top, bounds.height) };
  };
  /**
   * Verankert den Auswahlpfad am nächsten Vorfahren mit ID oder am Dokumentkörper.
   * Die vollständige Hierarchie unterscheidet auch wiederholte Layoutbausteine.
   * @param {Element} element Angeklicktes Seitenelement.
   * @return {string} CSS-Auswahlpfad ohne veränderliche Animations- oder Auswahlklassen.
   */
  const getElementSelector = (element) => {
    const parts = [];
    let current = element;
    while (current && current.nodeType === 1) {
      if (current.id) {
        parts.unshift(`#${CSS.escape(current.id)}`);
        break;
      }
      let part = current.tagName.toLowerCase();
      const siblings = current.parentElement ? [...current.parentElement.children].filter((sibling) => sibling.tagName === current.tagName) : [];
      if (siblings.length > 1) part += `:nth-of-type(${siblings.indexOf(current) + 1})`;
      parts.unshift(part);
      current = current.parentElement;
    }
    return parts.join(' > ');
  };
  return { collect, elementPosition, getElementSelector };
})();

document.addEventListener('DOMContentLoaded', () => {
  const widget = (window.KWMashaFeedlyDOM?.widget() || document.querySelector('[data-kw-masha-feedly]'));
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
  const createCategory = form?.querySelector('[data-masha-feedly-create-category]');
  const diagnosticsSection = form?.querySelector('[data-masha-feedly-diagnostics]');
  const diagnosticsFields = ['StepsToReproduce', 'ExpectedResult', 'ActualResult'].map((name) => form?.elements[name]).filter(Boolean);
  const estimateSection = form?.querySelector('[data-masha-feedly-create-estimate]');
  const estimateDuration = form?.elements.EstimatedCostDuration;
  const estimateNote = form?.elements.EstimatedCostNote;
  const estimatePrice = estimateSection?.querySelector('[data-masha-feedly-estimate-price]');
  const screenshotButton = form?.querySelector('[data-masha-feedly-screenshot-capture]');
  const screenshotStatus = form?.querySelector('[data-masha-feedly-screenshot-status]');
  const screenshotPreview = form?.querySelector('[data-masha-feedly-screenshot-preview]');
  const screenshotImage = form?.querySelector('[data-masha-feedly-screenshot-image]');
  const screenshotCropper = form?.querySelector('[data-masha-feedly-screenshot-cropper]');
  const screenshotCrop = form?.querySelector('[data-masha-feedly-screenshot-crop]');
  const screenshotApplyCrop = form?.querySelector('[data-masha-feedly-screenshot-apply]');
  const screenshotRemove = form?.querySelector('[data-masha-feedly-screenshot-remove]');
  let screenshotFile = null;
  let screenshotPreviewURL = '';
  let screenshotSourceURL = '';
  let screenshotSelection = null;
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  const clearScreenshot = () => {
    screenshotFile = null;
    if (screenshotPreviewURL) URL.revokeObjectURL(screenshotPreviewURL);
    if (screenshotSourceURL) URL.revokeObjectURL(screenshotSourceURL);
    screenshotPreviewURL = '';
    screenshotSourceURL = '';
    screenshotSelection = null;
    if (screenshotImage) screenshotImage.src = '';
    if (screenshotCrop) screenshotCrop.hidden = true;
    if (screenshotApplyCrop) screenshotApplyCrop.disabled = true;
    if (screenshotPreview) screenshotPreview.hidden = true;
    if (screenshotStatus) screenshotStatus.textContent = '';
  };
  screenshotButton?.addEventListener('click', async () => {
    if (!window.navigator.mediaDevices?.getDisplayMedia) {
      screenshotStatus.textContent = t('SCREENSHOT_UNSUPPORTED');
      return;
    }
    screenshotButton.disabled = true;
    screenshotStatus.textContent = t('SCREENSHOT_PERMISSION');
    const screenshotHost = document.querySelector('[data-masha-feedly-host]');
    const previousHostHidden = screenshotHost?.hidden;
    const screenshotSurface = window.KWMashaFeedlyDOM?.root?.().querySelector('[data-masha-feedly-surface]');
    const previousSurfaceHidden = screenshotSurface?.hidden;
    const previousWidgetHidden = widget.hidden;
    if (screenshotHost) screenshotHost.hidden = true;
    if (screenshotSurface) screenshotSurface.hidden = true;
    else if (!screenshotHost) widget.hidden = true;
    let stream;
    try {
      // Vor dem nativen Fensterauswahldialog muss der Browser die ausgeblendete Oberfläche rendern.
      await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
      stream = await window.navigator.mediaDevices.getDisplayMedia({ video: true, audio: false });
      const video = document.createElement('video');
      video.srcObject = stream;
      await video.play();
      await new Promise((resolve) => window.requestAnimationFrame(() => window.requestAnimationFrame(resolve)));
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      if (!canvas.width || !canvas.height) throw new Error('Für den Screenshot wurde kein Bild geliefert.');
      canvas.getContext('2d').drawImage(video, 0, 0);
      const blob = await new Promise((resolve, reject) => canvas.toBlob((image) => image ? resolve(image) : reject(new Error('Der Screenshot konnte nicht erstellt werden.')), 'image/png'));
      if (screenshotSourceURL) URL.revokeObjectURL(screenshotSourceURL);
      screenshotSourceURL = URL.createObjectURL(blob);
      screenshotImage.src = screenshotSourceURL;
      screenshotFile = null;
      screenshotSelection = null;
      screenshotCrop.hidden = true;
      screenshotApplyCrop.disabled = true;
      screenshotPreview.hidden = false;
      screenshotStatus.textContent = '';
    } catch (error) {
      screenshotStatus.textContent = error.name === 'NotAllowedError' ? t('SCREENSHOT_CANCELLED') : (error.message || t('SCREENSHOT_FAILED'));
    } finally {
      stream?.getTracks().forEach((track) => track.stop());
      if (screenshotSurface) screenshotSurface.hidden = previousSurfaceHidden;
      if (screenshotHost) screenshotHost.hidden = previousHostHidden;
      else if (!screenshotSurface) widget.hidden = previousWidgetHidden;
      screenshotButton.disabled = false;
    }
  });
  screenshotCropper?.addEventListener('pointerdown', (event) => {
    if (screenshotFile || !screenshotImage.naturalWidth || (event.pointerType === 'mouse' && event.button !== 0)) return;
    event.preventDefault();
    const bounds = screenshotImage.getBoundingClientRect();
    if (!bounds.width || !bounds.height) return;
    const x = Math.max(0, Math.min(bounds.width, event.clientX - bounds.left));
    const y = Math.max(0, Math.min(bounds.height, event.clientY - bounds.top));
    if (!screenshotSelection || screenshotSelection.complete) {
      screenshotSelection = { startX: x, startY: y, endX: x, endY: y, bounds, complete: false };
      screenshotCrop.hidden = false;
      screenshotApplyCrop.disabled = true;
      return;
    }
    screenshotSelection.endX = x;
    screenshotSelection.endY = y;
    screenshotSelection.complete = true;
    screenshotSelection.bounds = bounds;
    screenshotApplyCrop.disabled = Math.abs(x - screenshotSelection.startX) < 12 || Math.abs(y - screenshotSelection.startY) < 12;
    updateScreenshotCropFrame();
  });
  const updateScreenshotCropFrame = () => {
    if (!screenshotSelection) return;
    const { bounds, startX, startY, endX, endY } = screenshotSelection;
    screenshotCrop.hidden = false;
    screenshotCrop.style.left = `${Math.min(startX, endX) / bounds.width * 100}%`;
    screenshotCrop.style.top = `${Math.min(startY, endY) / bounds.height * 100}%`;
    screenshotCrop.style.width = `${Math.abs(endX - startX) / bounds.width * 100}%`;
    screenshotCrop.style.height = `${Math.abs(endY - startY) / bounds.height * 100}%`;
  };
  screenshotCropper?.addEventListener('pointermove', (event) => {
    if (!screenshotSelection || screenshotSelection.complete) return;
    const bounds = screenshotImage.getBoundingClientRect();
    const endX = Math.max(0, Math.min(bounds.width, event.clientX - bounds.left));
    const endY = Math.max(0, Math.min(bounds.height, event.clientY - bounds.top));
    screenshotSelection.endX = endX;
    screenshotSelection.endY = endY;
    screenshotSelection.bounds = bounds;
    updateScreenshotCropFrame();
  });
  screenshotApplyCrop?.addEventListener('click', async () => {
    if (!screenshotSelection || !screenshotImage.naturalWidth) return;
    const { bounds, startX, startY, endX, endY } = screenshotSelection;
    const scaleX = screenshotImage.naturalWidth / bounds.width;
    const scaleY = screenshotImage.naturalHeight / bounds.height;
    const left = Math.round(Math.min(startX, endX) * scaleX);
    const top = Math.round(Math.min(startY, endY) * scaleY);
    const width = Math.round(Math.abs(endX - startX) * scaleX);
    const height = Math.round(Math.abs(endY - startY) * scaleY);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(screenshotImage, left, top, width, height, 0, 0, width, height);
    const blob = await new Promise((resolve, reject) => canvas.toBlob((image) => image ? resolve(image) : reject(new Error('Der Ausschnitt konnte nicht erstellt werden.')), 'image/png'));
    screenshotFile = new File([blob], 'seiten-ausschnitt.png', { type: 'image/png' });
    if (screenshotPreviewURL) URL.revokeObjectURL(screenshotPreviewURL);
    screenshotPreviewURL = URL.createObjectURL(blob);
    screenshotImage.src = screenshotPreviewURL;
    if (screenshotSourceURL) URL.revokeObjectURL(screenshotSourceURL);
    screenshotSourceURL = '';
    screenshotCrop.hidden = true;
    screenshotApplyCrop.disabled = true;
    screenshotSelection = null;
    screenshotStatus.textContent = t('SCREENSHOT_READY');
  });
  screenshotRemove?.addEventListener('click', clearScreenshot);
  const calculatePreview = (text) => {
    const match = String(text).trim().match(/^([0-9]+(?:[.,][0-9]+)?)(?:\s*(?:-|–|bis)\s*([0-9]+(?:[.,][0-9]+)?))?\s*(stunden?|std\.?|h|minuten?|min)$/i);
    if (!match) return '';
    const first = Number(match[1].replace(',', '.'));
    const last = Number((match[2] || match[1]).replace(',', '.'));
    const unit = match[3].toLowerCase();
    const factor = ['minute', 'minuten', 'min'].includes(unit) ? 1 / 60 : 1;
    const rate = Math.max(0, Number(widget.dataset.estimateHourlyRate) || 0);
    if (rate <= 0) return t('ESTIMATE_RATE_REQUIRED');
    if (first <= 0 || last < first) return '';
    const money = (hours) => new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' }).format(Math.round(hours * factor * rate * 100) / 100);
    return first === last ? money(first) : `${money(first)} – ${money(last)}`;
  };
  const updateCreateEstimate = () => {
    if (!createCategory || !estimateSection) return;
    const show = widget.dataset.canManageEstimate === '1'
      && createCategory.selectedOptions[0]?.dataset.systemKey === 'estimate_pending';
    estimateSection.hidden = !show;
    [estimateDuration, estimateNote].forEach((field) => { if (field) field.disabled = !show; });
    if (estimatePrice) estimatePrice.textContent = show ? (calculatePreview(estimateDuration?.value || '') || (Number(widget.dataset.estimateHourlyRate) > 0 ? t('ESTIMATE_PRICE_HINT') : t('ESTIMATE_RATE_REQUIRED'))) : '';
  };
  const updateDiagnostics = () => {
    if (!diagnosticsSection) return;
    const text = String(contentField?.value || '').toLocaleLowerCase('de');
    const hasErrorSignal = /\b(fehler|fehlermeldung|bug|defekt|problem|funktioniert nicht|geht nicht|klappt nicht|störung|error|issue|broken|doesn.t work|not working)\b/i.test(text);
    if (hasErrorSignal) diagnosticsSection.open = true;
    diagnosticsFields.forEach((field) => { field.disabled = false; });
  };
  createCategory?.addEventListener('change', updateCreateEstimate);
  contentField?.addEventListener('input', updateDiagnostics);
  estimateDuration?.addEventListener('input', updateCreateEstimate);
  form?.addEventListener('reset', () => setTimeout(() => { updateCreateEstimate(); updateDiagnostics(); }, 0));
  updateCreateEstimate();
  updateDiagnostics();
  let selecting = false;
  let highlighted = null;
  const getElementSelector = window.KWMashaFeedlyEnvironment.getElementSelector;

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
    ['PageURL', 'ElementSelector', 'ElementText', 'ElementPositionX', 'ElementPositionY', 'OperatingSystem', 'Browser', 'UserAgent', 'Resolution', 'BrowserWindow', 'ColorDepth']
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
      ['ElementPositionX', options.elementPosition?.x ?? ''],
      ['ElementPositionY', options.elementPosition?.y ?? ''],
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
        if ((event.shiftKey && (window.KWMashaFeedlyDOM?.activeElement() || document.activeElement) === first) || (!event.shiftKey && (window.KWMashaFeedlyDOM?.activeElement() || document.activeElement) === last)) {
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
    if (!selecting || widget.contains((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target))) return;
    if (highlighted) highlighted.classList.remove('kw-masha-feedly-selected-target');
    highlighted = (window.KWMashaFeedlyDOM?.eventTarget(event) || event.target).closest('body *');
    if (highlighted && highlighted !== document.body) highlighted.classList.add('kw-masha-feedly-selected-target');
  });

  document.addEventListener('click', (event) => {
    if (!selecting || widget.contains((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target))) return;
    event.preventDefault();
    event.stopPropagation();
    const element = (window.KWMashaFeedlyDOM?.eventTarget(event) || event.target).closest('body *');
    const elementPosition = window.KWMashaFeedlyEnvironment.elementPosition(element, event.clientX, event.clientY);
    stopSelection();
    if (element && element !== document.body) {
      // Kennzeichnet den einen Seitenklick, der gerade den Tour-Schritt wechselt.
      event.mashaFeedlyTargetSelectionHandled = true;
      showEntryDialog(element, {
        elementPosition,
      });
    }
  }, true);

  widget.querySelector('[data-masha-feedly-cancel-selection]')?.addEventListener('click', cancelSelection);
  widget.querySelector('[data-masha-feedly-dismiss-toast]')?.addEventListener('click', () => { toast.hidden = true; });
  widget.querySelectorAll('[data-masha-feedly-close-modal]').forEach((button) => button.addEventListener('click', () => {
    modal.hidden = true;
    toggleButton?.focus?.();
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-form-closed'));
  }));
  modal?.addEventListener('click', (event) => {
    if ((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target) === modal) { modal.hidden = true; toggleButton?.focus?.(); }
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    // Der Speichern-Button liegt im Dialog-Footer außerhalb des <form> und ist
    // über das HTML-Attribut form="…" zugeordnet.
    const submit = form.querySelector('[type="submit"]')
      || (form.id ? widget.querySelector(`button[type="submit"][form="${CSS.escape(form.id)}"]`) : null);
    if (!submit) {
      status.textContent = t('CREATE_SAVE_ERROR');
      return;
    }
    submit.disabled = true;
    status.textContent = t('CREATE_SAVING');
    const data = new FormData(form);
    if (screenshotFile) data.append('Attachments[]', screenshotFile);
    data.set('SecurityID', form.dataset.securityId);
    let saveConfirmed = false;
    try {
      const response = await fetch(form.dataset.createUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('CREATE_SAVE_ERROR'));
      saveConfirmed = true;
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
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-saved', {
        detail: { entryID: Number(result.entryID) },
      }));
      form.reset();
      clearScreenshot();
      setTimeout(() => { modal.hidden = true; toggleButton?.focus?.(); }, 500);
      setTimeout(() => { toast.hidden = true; }, 6000);
    } catch (error) {
      status.textContent = saveConfirmed
        ? t('SAVE_CONFIRMED_DISPLAY_ERROR')
        : (error.message || t('CREATE_SAVE_ERROR'));
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
