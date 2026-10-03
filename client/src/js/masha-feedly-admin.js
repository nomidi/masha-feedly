(() => {
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  /** Aktualisiert den Zähler des Masha-Feedly-Menüpunkts für das aktuelle Mitglied. */
  const updateUnreadMenuCount = (generalCount, personalCount) => {
    const menuLink = [...document.querySelectorAll('#cms-menu a[href*="masha-feedly"]')][0];
    const title = menuLink?.querySelector('.text');
    if (!title) return;
    const baseTitle = title.textContent.replace(/\s+\(\d+\/\d+\)$/, '');
    if (title.textContent !== baseTitle) title.textContent = baseTitle;
    const updateBadge = (type, count, label) => {
      const className = `masha-feedly-menu__badge--${type}`;
      let badge = menuLink.querySelector(`.${className}`);
      if (count <= 0) {
        badge?.remove();
        return;
      }
      if (!badge) {
        badge = document.createElement('span');
        badge.classList.add('masha-feedly-menu__badge', className);
        menuLink.appendChild(badge);
      }
      if (badge.textContent !== String(count)) badge.textContent = String(count);
      if (badge.getAttribute('aria-label') !== label) badge.setAttribute('aria-label', label);
    };
    updateBadge('general', generalCount, t('MENU_GENERAL_UNREAD', { count: generalCount }));
    const personalKey = window.KWMashaFeedlyTranslations?.FORMAL_ADDRESS === 'sie'
      ? 'MENU_PERSONAL_UNREAD_SIE'
      : 'MENU_PERSONAL_UNREAD_DU';
    updateBadge('personal', personalCount, t(personalKey, { count: personalCount }));
  };

  /** Übernimmt den vom Server gelieferten individuellen Zähler aus dem Eintragsformular. */
  const refreshUnreadMenuCount = () => {
    const marker = document.querySelector('[data-masha-feedly-unread-general-count]');
    if (marker) {
      updateUnreadMenuCount(
        Number(marker.dataset.mashaFeedlyUnreadGeneralCount || 0),
        Number(marker.dataset.mashaFeedlyUnreadPersonalCount || 0)
      );
      return;
    }
    const title = [...document.querySelectorAll('#cms-menu a[href*="masha-feedly"]')][0]?.querySelector('.text');
    const serverCounts = title?.textContent.match(/\((\d+)\/(\d+)\)$/);
    if (serverCounts) updateUnreadMenuCount(Number(serverCounts[1]), Number(serverCounts[2]));
  };

  refreshUnreadMenuCount();
  if (typeof MutationObserver !== 'undefined' && document.body) {
    new MutationObserver(refreshUnreadMenuCount).observe(document.body, { childList: true, subtree: true });
  }

  const board = document.querySelector('[data-masha-feedly-board]');
  if (!board) return;

  const status = board.querySelector('.masha-feedly-board__status');
  const assigneeFilter = board.querySelector('[data-masha-feedly-assignee-filter]');
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
  let draggedCard = null;
  let originalList = null;
  let originalNextCard = null;

  board.addEventListener('dragstart', (event) => {
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
    const card = draggedCard || board.querySelector('.masha-feedly-board__card.is-dragging');
    if (card) card.classList.remove('is-dragging');
    board.querySelectorAll('.is-drop-target').forEach((list) => list.classList.remove('is-drop-target'));
    draggedCard = null;
    originalList = null;
    originalNextCard = null;
  });

  board.addEventListener('dragover', (event) => {
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
    const list = event.target.closest('.masha-feedly-board__list');
    if (list && !list.contains(event.relatedTarget)) list.classList.remove('is-drop-target');
  });

  board.addEventListener('drop', async (event) => {
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
      if (Number.isFinite(Number(result.unreadGeneralCount))) {
        updateUnreadMenuCount(Number(result.unreadGeneralCount), Number(result.unreadPersonalCount || 0));
      }
      board.querySelectorAll('.masha-feedly-board__column').forEach((column) => {
        const columnList = column.querySelector('.masha-feedly-board__list');
        column.querySelector('.masha-feedly-board__column-header span').textContent =
          columnList.querySelectorAll('.masha-feedly-board__card:not([hidden])').length;
      });
      status.textContent = t('BOARD_SAVE_SUCCESS');
    } catch (error) {
      if (previousList) previousList.insertBefore(card, previousNextCard && previousNextCard.parentElement === previousList ? previousNextCard : null);
      status.textContent = error.message || t('BOARD_SAVE_FAILURE');
    }
  });
})();
