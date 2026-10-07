(() => {
  /** Zeigt die Startkategorien und das Projekt nur bei ausgewählter Mite-Aktivierung. */
  const applyMiteConfiguration = () => {
    const enabled = document.querySelector('input[name="MashaFeedlyMiteEnabled"][type="checkbox"]');
    document.querySelectorAll('[data-mite-settings]').forEach((settings) => {
      settings.hidden = !enabled?.checked;
    });
  };
  /**
   * @typedef {Object} MiteOptionsResponse
   * @property {boolean} success Ob die Projektauswahl geladen werden konnte.
   * @property {Object<string, string>} projects Zugängliche Mite-Projekte nach ID.
   * @property {Object<string, string>} services Verfügbare Mite-Leistungen nach ID.
   * @property {number} projectID Standardprojekt dieser Website.
   * @property {number} activeTimerID Laufender Timer, oder 0.
   * @property {string} activeTimerNote Beschreibung des derzeit laufenden Zeiteintrags.
   * @property {string} [message] Fehlermeldung der Serverprüfung.
   */
  const applyAnimationTheme = () => {
    const themeField = document.querySelector('select[name="MashaFeedlyTheme"]');
    const selectedTheme = themeField?.value === 'serious' ? 'serious' : 'playful';
    document.querySelectorAll('[data-masha-feedly-animation-preview-card]').forEach((card) => {
      card.hidden = card.dataset.mashaFeedlyTheme !== selectedTheme && card.dataset.mashaFeedlyTheme !== 'both';
    });
  };

  const effectText = (key, fallback) => {
    const translated = window.KWMashaFeedlyTranslate?.(key);
    return translated && translated !== key ? translated : fallback;
  };
  /** Erstellt die Vorschauen aus dem aktuellen Katalog statt aus einer festen Theme-Liste. */
  const loadEffectPreviews = async () => {
    const grids = [...document.querySelectorAll('[data-masha-feedly-effect-catalog]')].filter((grid) => !grid.dataset.effectsLoaded);
    grids.forEach((grid) => { grid.dataset.effectsLoaded = 'loading'; });
    if (!grids.length) return;
    try {
      const effects = await window.KWMashaFeedlyEffects.refresh();
      grids.forEach((grid) => {
        grid.replaceChildren();
        effects.forEach((effect) => {
          const card = document.createElement('article');
          card.className = 'masha-feedly-animation-preview';
          card.setAttribute('data-masha-feedly-animation-preview-card', '');
          card.dataset.mashaFeedlyTheme = effect.theme;
          const title = document.createElement('strong'); title.textContent = effect.name;
          const button = document.createElement('button'); button.type = 'button';
          button.dataset.mashaFeedlyAnimationPreview = effect.id;
          button.textContent = effectText('CONFIG_ANIMATION_PREVIEW', 'Vorschau ansehen');
          card.append(title, button); grid.append(card);
        });
        grid.dataset.effectsLoaded = 'true';
      });
      applyAnimationTheme();
    } catch (error) {
      grids.forEach((grid) => { grid.dataset.effectsLoaded = 'error'; grid.textContent = effectText('EFFECT_PROVIDER_UNAVAILABLE', 'Effekt-Anbieter nicht erreichbar.'); });
    }
  };
  const initialiseEffectPreviews = () => {
    loadEffectPreviews();
    if (typeof MutationObserver !== 'undefined' && document.body) {
      new MutationObserver(loadEffectPreviews).observe(document.body, { childList: true, subtree: true });
    }
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialiseEffectPreviews, { once: true });
  else initialiseEffectPreviews();
  document.addEventListener('kw-masha-feedly:opened', loadEffectPreviews);

  document.addEventListener('change', (event) => {
    if (event.target?.matches?.('select[name="MashaFeedlyTheme"]')) applyAnimationTheme();
    if (event.target?.matches?.('input[name="MashaFeedlyMiteEnabled"][type="checkbox"]')) applyMiteConfiguration();
  }, true);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyAnimationTheme, { once: true });
  } else {
    applyAnimationTheme();
  }

  // Category buttons live in SilverStripe's PJAX-managed ModelAdmin content.
  // Delegate in capture phase so the control keeps working even if the board
  // was replaced after its initialisation (or CMS code stops bubbling clicks).
  document.addEventListener('click', (event) => {
    const viewTab = event.target?.closest?.('[data-admin-view-tab]');
    if (viewTab) {
      const board = viewTab.closest('[data-masha-feedly-board]');
      const selectedView = viewTab.dataset.adminViewTab;
      board?.querySelectorAll('[data-admin-view-tab]').forEach((tab) => {
        const active = tab === viewTab;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      board?.querySelectorAll('[data-admin-view-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.adminViewPanel !== selectedView;
      });
      return;
    }
    const previewButton = event.target?.closest?.('[data-masha-feedly-animation-preview]');
    if (previewButton) {
      const preview = previewButton.closest('[data-masha-feedly-animation-previews]');
      const status = preview?.querySelector('[data-masha-feedly-animation-preview-status]');
      const animation = previewButton.dataset.mashaFeedlyAnimationPreview;
      const result = window.KWMashaFeedlyEntries?.previewCompletionAnimation(
        document,
        window,
        animation,
        preview?.dataset.unicornUrl
      );
      Promise.resolve(result).then((played) => {
        if (status) status.textContent = played
          ? effectText('CONFIG_ANIMATION_PREVIEW_STARTED', 'Vorschau gestartet.')
          : effectText('EFFECT_PREVIEW_UNAVAILABLE', 'Dieser Effekt ist momentan nicht verfügbar.');
      }).catch(() => {
        if (status) status.textContent = effectText('EFFECT_PROVIDER_UNAVAILABLE', 'Effekt-Anbieter nicht erreichbar.');
      });
      return;
    }
    const button = event.target?.closest?.('[data-open-category-form]');
    if (!button) return;
    const board = button.closest('[data-masha-feedly-board]');
    const modal = board?.querySelector('[data-category-modal]');
    if (!modal) return;
    modal.hidden = false;
    modal.querySelector('[data-category-title-input]')?.focus();
  }, true);

  document.addEventListener('input', (event) => {
    const search = event.target?.closest?.('[data-reporter-search]');
    if (!search) return;
    const query = search.value.trim().toLocaleLowerCase();
    search.closest('[data-admin-view-panel]')?.querySelectorAll('[data-reporter-row]').forEach((row) => {
      row.hidden = query !== '' && !row.dataset.search.includes(query);
    });
  }, true);

  document.addEventListener('submit', async (event) => {
    const form = event.target?.closest?.('[data-reporter-form]');
    if (!form) return;
    event.preventDefault();
    const button = form.querySelector('[type="submit"]');
    const status = form.querySelector('[data-reporter-status]');
    button.disabled = true;
    if (status) status.textContent = form.dataset.savingMessage || 'Meldeperson wird gespeichert …';
    const data = new FormData(form);
    data.set('SecurityID', form.dataset.securityId || form.closest('[data-masha-feedly-board]')?.dataset.securityId || '');
    try {
      const response = await fetch(form.dataset.saveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || form.dataset.errorMessage || 'Meldeperson konnte nicht gespeichert werden.');
      if (status) status.textContent = result.message || 'Meldeperson gespeichert.';
    } catch (error) {
      if (status) status.textContent = error.message || form.dataset.errorMessage || 'Meldeperson konnte nicht gespeichert werden.';
    } finally {
      button.disabled = false;
    }
  }, true);

  const initialiseMashaFeedlyAdmin = () => {
  applyMiteConfiguration();
  // CMS-Reiter ersetzen das Board per PJAX auch nach der ersten Initialisierung.
  if (typeof MutationObserver !== 'undefined' && document.body && !window.KWMashaFeedlyBoardObserver) {
    const observer = new MutationObserver(initialiseMashaFeedlyAdmin);
    window.KWMashaFeedlyBoardObserver = observer;
    observer.observe(document.body, { childList: true, subtree: true });
  }
  const board = document.querySelector('[data-masha-feedly-board]');
  let boardTranslations = {};
  try {
    boardTranslations = JSON.parse(board?.dataset.adminTranslations || '{}');
  } catch (error) {
    boardTranslations = {};
  }
  const t = (key, values = {}) => {
    const globalTranslation = typeof window.KWMashaFeedlyTranslate === 'function'
      ? window.KWMashaFeedlyTranslate(key, values)
      : key;
    let message = globalTranslation !== key
      ? globalTranslation
      : (boardTranslations[key] || window.KWMashaFeedlyTranslations?.[key] || key);
    Object.entries(values).forEach(([name, value]) => {
      message = message.replaceAll(`{${name}}`, String(value));
    });
    return message;
  };
  if (!board) return;
  if (board.dataset.mashaFeedlyAdminInitialized === 'true') return;
  board.dataset.mashaFeedlyAdminInitialized = 'true';

  const status = board.querySelector('.masha-feedly-board__status');
  const miteModal = board.querySelector('[data-mite-modal]');
  let miteEntryID = '';
  let miteConfirmedTimerID = 0;
  let miteBusy = false;
  let miteReturnFocus = null;
  let miteLoadVersion = 0;

  /** Schließt den Dialog und gibt den Fokus an die verschobene Karte zurück. */
  const closeMiteDialog = () => {
    if (!miteModal || miteBusy) return;
    miteLoadVersion += 1;
    miteModal.hidden = true;
    miteReturnFocus?.focus();
  };

  /** Lädt Projekte und den Timerzustand neu; ein Timerwechsel muss erneut bestätigt werden. */
  const loadMiteOptions = async () => {
    if (!miteModal || miteBusy) return;
    const loadVersion = ++miteLoadVersion;
    const project = miteModal.querySelector('[data-mite-project]');
    const service = miteModal.querySelector('[data-mite-service]');
    const start = miteModal.querySelector('[data-mite-start]');
    const stop = miteModal.querySelector('[data-mite-stop]');
    const message = miteModal.querySelector('[data-mite-status]');
    const activeTimer = miteModal.querySelector('[data-mite-active-timer]');
    project.disabled = true;
    service.disabled = true;
    start.disabled = true;
    if (stop) stop.hidden = true;
    activeTimer.textContent = '';
    message.textContent = t('MITE_LOADING');
    try {
      const response = await fetch(miteModal.dataset.optionsUrl, {
        credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      /** @type {MiteOptionsResponse} */
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('MITE_LOAD_ERROR'));
      if (loadVersion !== miteLoadVersion || miteModal.hidden) return;
      project.replaceChildren(new Option(t('MITE_CHOOSE_PROJECT'), ''));
      Object.entries(result.projects || {}).forEach(([id, label]) => project.add(new Option(label, id)));
      service.replaceChildren(new Option(t('MITE_CHOOSE_SERVICE'), ''));
      Object.entries(result.services || {}).forEach(([id, label]) => service.add(new Option(label, id)));
      project.value = String(result.projectID || '');
      project.disabled = false;
      service.disabled = false;
      start.disabled = !project.value || !service.value;
      miteConfirmedTimerID = Number(result.activeTimerID || 0);
      if (stop) stop.hidden = miteConfirmedTimerID === 0;
      activeTimer.textContent = miteConfirmedTimerID
        ? t('MITE_SWITCH_TIMER', { id: miteConfirmedTimerID, note: result.activeTimerNote || '—' })
        : t('MITE_NO_TIMER');
      message.textContent = '';
    } catch (error) {
      if (loadVersion === miteLoadVersion && !miteModal.hidden) message.textContent = error.message || t('MITE_LOAD_ERROR');
    }
  };

  /**
   * Öffnet die freiwillige Zeiterfassung nach dem Wechsel in eine konfigurierte Startkategorie.
   * @param {string} entryID ID des verschobenen Fehlers.
   * @param {HTMLElement|null} returnFocus Ziel für die Fokusrückgabe.
   */
  const openMiteDialog = (entryID, returnFocus) => {
    if (!miteModal) return;
    miteEntryID = entryID;
    miteReturnFocus = returnFocus;
    miteModal.hidden = false;
    miteModal.querySelector('h2').focus();
    loadMiteOptions();
  };

  document.addEventListener('kw-masha-feedly:mite-prompt', (event) => {
    const entryID = event.detail?.entryID;
    if (entryID && miteModal) openMiteDialog(String(entryID), document.activeElement);
  });

  miteModal?.addEventListener('change', (event) => {
    if (event.target.matches('[data-mite-project], [data-mite-service]')) {
      const project = miteModal.querySelector('[data-mite-project]');
      const service = miteModal.querySelector('[data-mite-service]');
      miteModal.querySelector('[data-mite-start]').disabled = miteBusy || !project.value || !service.value;
    }
  });
  miteModal?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      closeMiteDialog();
    }
    if (event.key !== 'Tab') return;
    const controls = [...miteModal.querySelectorAll('button:not([disabled]), select:not([disabled])')];
    if (!controls.length) { event.preventDefault(); return; }
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === miteModal.querySelector('h2'))) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); first.focus();
    }
  });
  miteModal?.addEventListener('click', async (event) => {
    if (event.target.closest('[data-mite-close]')) { closeMiteDialog(); return; }
    if (event.target.closest('[data-mite-refresh]')) { loadMiteOptions(); return; }
    const stopping = Boolean(event.target.closest('[data-mite-stop]'));
    if (!stopping && !event.target.closest('[data-mite-start]')) return;
    if (miteBusy || (stopping && !miteConfirmedTimerID)) return;
    const project = miteModal.querySelector('[data-mite-project]');
    const service = miteModal.querySelector('[data-mite-service]');
    const message = miteModal.querySelector('[data-mite-status]');
    if (!stopping && (!project.value || project.disabled || !service.value || service.disabled)) return;
    miteBusy = true;
    miteModal.querySelectorAll('button, select').forEach((control) => { control.disabled = true; });
    message.textContent = t(stopping ? 'MITE_STOPPING' : 'MITE_STARTING');
    const data = new FormData();
    data.set('SecurityID', board.dataset.securityId);
    data.set('ConfirmedTimerID', String(miteConfirmedTimerID));
    if (!stopping) {
      data.set('EntryID', miteEntryID);
      data.set('ProjectID', project.value);
      data.set('ServiceID', service.value);
    }
    try {
      const response = await fetch(stopping ? miteModal.dataset.stopUrl : miteModal.dataset.startUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('MITE_START_ERROR'));
      miteBusy = false;
      if (stopping) {
        const successMessage = result.message || t('MITE_STOPPED');
        await loadMiteOptions();
        message.textContent = successMessage;
      } else {
        status.textContent = result.message || t('MITE_STARTED');
        closeMiteDialog();
      }
    } catch (error) {
      message.textContent = error.message || t(stopping ? 'MITE_STOP_ERROR' : 'MITE_START_ERROR');
    } finally {
      miteBusy = false;
      miteModal.querySelectorAll('button, select').forEach((control) => { control.disabled = false; });
      // Nach Fehlern muss der aktuelle Timer erneut geladen werden, bevor ein weiterer Start möglich ist.
      if (!miteModal.hidden) {
        project.disabled = true;
        miteModal.querySelector('[data-mite-start]').disabled = true;
      }
    }
  });
  const assigneeFilter = board.querySelector('[data-masha-feedly-assignee-filter]');
  const columnsContainer = board.querySelector('.masha-feedly-board__columns');
  const categoryModal = board.querySelector('[data-category-modal]');
  const categoryForm = board.querySelector('[data-create-category-form]');
  const categoryNameInput = categoryForm?.querySelector('[data-category-title-input]');
  const submitCategoryButton = categoryForm?.querySelector('[data-submit-category-form]');
  const openCategoryFormButton = board.querySelector('[data-open-category-form]');
  const cancelCategoryFormButton = categoryForm?.querySelector('[data-cancel-category-form]');
  const closeCategoryModalButtons = categoryModal?.querySelectorAll('[data-close-category-modal]') || [];
  const entryModal = board.querySelector('[data-entry-modal]');
  const entryForm = board.querySelector('[data-admin-create-entry-form]');
  const entryStatus = entryForm?.querySelector('[data-entry-form-status]');
  const openEntryFormButton = board.querySelector('[data-open-entry-form]');
  const closeEntryModalButtons = entryModal?.querySelectorAll('[data-close-entry-modal]') || [];
  const applyAssigneeFilter = () => {
    if (!assigneeFilter) return;
    const selectedMemberID = assigneeFilter.value;
    board.querySelectorAll('.masha-feedly-board__card').forEach((card) => {
      const assignedMemberIDs = card.dataset.assignedMemberIds
        ? card.dataset.assignedMemberIds.split(',')
        : ['0'];
      card.hidden = selectedMemberID !== '' && !assignedMemberIDs.includes(selectedMemberID);
    });
    board.querySelectorAll('.masha-feedly-board__column').forEach((column) => {
      const columnList = column.querySelector('.masha-feedly-board__list');
      column.querySelector('.masha-feedly-board__column-header span').textContent =
        columnList.querySelectorAll('.masha-feedly-board__card:not([hidden])').length;
    });
  };
  assigneeFilter?.addEventListener('change', applyAssigneeFilter);
  applyAssigneeFilter();

  const renderCategoryColumn = (category) => {
    const column = document.createElement('section');
    column.className = 'masha-feedly-board__column';
    column.dataset.categoryId = String(category.id);
    const header = document.createElement('header');
    header.className = 'masha-feedly-board__column-header';
    const dragHandle = document.createElement('button');
    dragHandle.type = 'button';
    dragHandle.className = 'masha-feedly-board__category-drag-handle';
    dragHandle.draggable = true;
    dragHandle.textContent = '⠿';
    dragHandle.setAttribute('aria-label', t('BOARD_CATEGORY_DRAG_ARIA'));
    dragHandle.title = t('BOARD_CATEGORY_DRAG_TITLE');
    const title = document.createElement('h3');
    title.textContent = category.title;
    const count = document.createElement('span');
    count.dataset.categoryEntryCount = '';
    count.textContent = '0';
    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'masha-feedly-board__category-delete';
    deleteButton.dataset.deleteCategory = '';
    deleteButton.textContent = '×';
    deleteButton.setAttribute('aria-label', t('BOARD_CATEGORY_DELETE'));
    deleteButton.title = t('BOARD_CATEGORY_DELETE');
    header.append(dragHandle, title, count, deleteButton);
    const list = document.createElement('div');
    list.className = 'masha-feedly-board__list';
    list.dataset.categoryId = String(category.id);
    column.append(header, list);
    columnsContainer?.appendChild(column);
  };

  const closeCategoryDialog = () => {
    if (!categoryModal) return;
    categoryModal.hidden = true;
    if (categoryNameInput) categoryNameInput.value = '';
    openCategoryFormButton?.focus();
  };
  cancelCategoryFormButton?.addEventListener('click', closeCategoryDialog);
  closeCategoryModalButtons.forEach((button) => button.addEventListener('click', closeCategoryDialog));
  categoryModal?.addEventListener('click', (event) => {
    if (event.target === categoryModal) closeCategoryDialog();
  });

  openEntryFormButton?.addEventListener('click', () => {
    if (!entryModal) return;
    entryModal.hidden = false;
    entryForm?.querySelector('[name="Content"]')?.focus();
  });
  const closeEntryDialog = () => {
    if (!entryModal) return;
    entryModal.hidden = true;
    openEntryFormButton?.focus();
  };
  closeEntryModalButtons.forEach((button) => button.addEventListener('click', closeEntryDialog));
  entryModal?.addEventListener('click', (event) => {
    if (event.target === entryModal) closeEntryDialog();
  });
  board.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (categoryModal && !categoryModal.hidden) closeCategoryDialog();
    if (entryModal && !entryModal.hidden) closeEntryDialog();
  });

  const saveCategory = async () => {
    const title = String(categoryNameInput?.value || '').trim();
    if (!title) {
      status.textContent = t('BOARD_CATEGORY_NAME_REQUIRED');
      categoryNameInput?.focus();
      return;
    }

    const formData = new FormData();
    formData.append('SecurityID', board.dataset.securityId);
    formData.append('Title', title);
    status.textContent = t('BOARD_CATEGORY_ADDING');
    try {
      const response = await fetch(board.dataset.createCategoryUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success || !result.category) {
        throw new Error(result.message || t('BOARD_CATEGORY_ADD_ERROR'));
      }
      renderCategoryColumn(result.category);
      if (categoryNameInput) categoryNameInput.value = '';
      categoryModal.hidden = true;
      status.textContent = t('BOARD_CATEGORY_ADD_SUCCESS');
      openCategoryFormButton?.focus();
    } catch (error) {
      status.textContent = error.message || t('BOARD_CATEGORY_ADD_ERROR');
      categoryNameInput?.focus();
    }
  };
  submitCategoryButton?.addEventListener('click', saveCategory);
  categoryNameInput?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    saveCategory();
  });

  entryForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submitButton = entryForm.querySelector('[type="submit"]');
    submitButton.disabled = true;
    if (entryStatus) entryStatus.textContent = t('BOARD_ENTRY_SAVING');
    const formData = new FormData(entryForm);
    formData.set('SecurityID', entryForm.dataset.securityId || board.dataset.securityId);
    try {
      const response = await fetch(entryForm.dataset.createUrl || board.dataset.createEntryUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('BOARD_ENTRY_SAVE_ERROR'));
      if (entryStatus) entryStatus.textContent = result.message || t('BOARD_ENTRY_SAVE_SUCCESS');
      board.status.textContent = result.message || t('BOARD_ENTRY_SAVE_SUCCESS');
      entryModal.hidden = true;
      window.location.reload();
    } catch (error) {
      if (entryStatus) entryStatus.textContent = error.message || t('BOARD_ENTRY_SAVE_ERROR');
      entryForm.querySelector('[name="Content"]')?.focus();
    } finally {
      submitButton.disabled = false;
    }
  });

  let draggedCard = null;
  let originalList = null;
  let originalNextCard = null;
  let draggedCategory = null;
  let originalColumns = null;
  let originalNextCategory = null;

  board.addEventListener('dragstart', (event) => {
    const categoryHandle = event.target.closest('.masha-feedly-board__category-drag-handle');
    const category = categoryHandle?.closest('.masha-feedly-board__column');
    if (category) {
      draggedCategory = category;
      originalColumns = category.parentElement;
      originalNextCategory = category.nextElementSibling;
      category.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', category.dataset.categoryId);
      return;
    }
    const handle = event.target.closest('.masha-feedly-board__drag-handle');
    const card = handle?.closest('.masha-feedly-board__card');
    if (!card) return;
    draggedCard = card;
    originalList = card.parentElement;
    originalNextCard = card.nextElementSibling;
    card.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', card.dataset.entryId);
  });

  board.addEventListener('dragend', () => {
    if (draggedCategory) draggedCategory.classList.remove('is-dragging');
    const card = draggedCard || board.querySelector('.masha-feedly-board__card.is-dragging');
    if (card) card.classList.remove('is-dragging');
    board.querySelectorAll('.is-drop-target').forEach((list) => list.classList.remove('is-drop-target'));
    draggedCard = null;
    originalList = null;
    originalNextCard = null;
    draggedCategory = null;
    originalColumns = null;
    originalNextCategory = null;
  });

  board.addEventListener('dragover', (event) => {
    if (draggedCategory) {
      const columns = event.target.closest('.masha-feedly-board__columns');
      const column = event.target.closest('.masha-feedly-board__column');
      if (!columns) return;
      event.preventDefault();
      columns.classList.add('is-drop-target');
      if (!column || column === draggedCategory) return;
      const bounds = column.getBoundingClientRect();
      const insertAfter = event.clientX > bounds.left + bounds.width / 2;
      columns.insertBefore(draggedCategory, insertAfter ? column.nextElementSibling : column);
      return;
    }
    const list = event.target.closest('.masha-feedly-board__list');
    if (!list || !draggedCard) return;
    event.preventDefault();
    list.classList.add('is-drop-target');

    const card = event.target.closest('.masha-feedly-board__card');
    if (!card || card === draggedCard) return;
    const bounds = card.getBoundingClientRect();
    const insertAfter = event.clientY > bounds.top + bounds.height / 2;
    list.insertBefore(draggedCard, insertAfter ? card.nextElementSibling : card);
  });

  board.addEventListener('dragleave', (event) => {
    const columns = event.target.closest('.masha-feedly-board__columns');
    if (columns && !columns.contains(event.relatedTarget)) columns.classList.remove('is-drop-target');
    const list = event.target.closest('.masha-feedly-board__list');
    if (list && !list.contains(event.relatedTarget)) list.classList.remove('is-drop-target');
  });

  board.addEventListener('drop', async (event) => {
    if (draggedCategory) {
      const columns = event.target.closest('.masha-feedly-board__columns');
      if (!columns) return;
      event.preventDefault();
      const category = draggedCategory;
      category.classList.remove('is-dragging');
      columns.classList.remove('is-drop-target');
      const formData = new FormData();
      formData.append('SecurityID', board.dataset.securityId);
      columns.querySelectorAll('.masha-feedly-board__column').forEach((item) => {
        formData.append('CategoryIDs[]', item.dataset.categoryId);
      });
      draggedCategory = null;
      const previousColumns = originalColumns;
      const previousNext = originalNextCategory;
      originalColumns = null;
      originalNextCategory = null;
      status.textContent = t('BOARD_CATEGORY_SAVING');

      try {
        const response = await fetch(board.dataset.moveCategoryUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData,
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || t('BOARD_CATEGORY_SAVE_ERROR'));
        status.textContent = t('BOARD_CATEGORY_SAVE_SUCCESS');
      } catch (error) {
        if (previousColumns) previousColumns.insertBefore(
          category,
          previousNext && previousNext.parentElement === previousColumns ? previousNext : null,
        );
        status.textContent = error.message || t('BOARD_CATEGORY_SAVE_FAILURE');
      }
      return;
    }
    const list = event.target.closest('.masha-feedly-board__list');
    if (!list || !draggedCard) return;
    event.preventDefault();
    list.classList.remove('is-drop-target');

    const card = draggedCard;
    card.classList.remove('is-dragging');
    const previousList = originalList;
    const previousNextCard = originalNextCard;
    if (!list.contains(card)) list.appendChild(card);
    const formData = new FormData();
    formData.append('SecurityID', board.dataset.securityId);
    formData.append('EntryID', card.dataset.entryId);
    formData.append('CategoryID', list.dataset.categoryId);
    list.querySelectorAll('.masha-feedly-board__card').forEach((item) => {
      formData.append('EntryIDs[]', item.dataset.entryId);
    });

    draggedCard = null;
    originalList = null;
    originalNextCard = null;
    status.textContent = t('BOARD_SAVING');

    try {
      const response = await fetch(board.dataset.moveUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('BOARD_SAVE_ERROR'));
      board.querySelectorAll('.masha-feedly-board__column').forEach((column) => {
        const columnList = column.querySelector('.masha-feedly-board__list');
        column.querySelector('.masha-feedly-board__column-header span').textContent =
          columnList.querySelectorAll('.masha-feedly-board__card:not([hidden])').length;
      });
      status.textContent = t('BOARD_SAVE_SUCCESS');
      if (result.mitePrompt) openMiteDialog(card.dataset.entryId, card.querySelector('a'));
    } catch (error) {
      if (previousList) previousList.insertBefore(card, previousNextCard && previousNextCard.parentElement === previousList ? previousNextCard : null);
      status.textContent = error.message || t('BOARD_SAVE_FAILURE');
    }
  });

  board.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-delete-category]');
    const column = button?.closest('.masha-feedly-board__column');
    if (!button || !column) return;
    if (typeof window.confirm === 'function' && !window.confirm(t('BOARD_CATEGORY_DELETE_CONFIRM'))) return;

    const formData = new FormData();
    formData.append('SecurityID', board.dataset.securityId);
    formData.append('CategoryID', column.dataset.categoryId);
    status.textContent = t('BOARD_CATEGORY_DELETING');
    try {
      const response = await fetch(board.dataset.deleteCategoryUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('BOARD_CATEGORY_DELETE_ERROR'));
      column.remove();
      status.textContent = t('BOARD_CATEGORY_DELETE_SUCCESS');
    } catch (error) {
      status.textContent = error.message || t('BOARD_CATEGORY_DELETE_FAILURE');
    }
  });
  };

  // Requirements scripts may be emitted before the ModelAdmin form markup.
  // Wait for the board DOM so its controls always receive their handlers.
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseMashaFeedlyAdmin, { once: true });
  } else {
    initialiseMashaFeedlyAdmin();
  }
})();
