/** Hilfsfunktionen für Sortierung und Filterung der Masha-Feedly-Seitenübersicht. */
window.KWMashaFeedlyEntries = (() => {
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  const commentReactionOptions = [
    { emoji: '👍', label: 'COMMENT_REACTION_LIKE' },
    { emoji: '❤️', label: 'COMMENT_REACTION_LOVE' },
    { emoji: '😂', label: 'COMMENT_REACTION_LAUGH' },
    { emoji: '😢', label: 'COMMENT_REACTION_CRY' },
    { emoji: '😮', label: 'COMMENT_REACTION_SURPRISED' },
    { emoji: '🙏', label: 'COMMENT_REACTION_THANKS' },
  ];
  /** Zeichnet die sechs erlaubten Reaktionen mit Zähler und eigenem Auswahlzustand. */
  const renderCommentReactions = (container, reactions, documentRef, onReact) => {
    if (!container) return;
    container.replaceChildren();
    container.className = 'kw-masha-feedly__comment-reactions';
    container.setAttribute('role', 'group');
    container.setAttribute('aria-label', t('COMMENT_REACTIONS'));
    const byEmoji = new Map((Array.isArray(reactions) ? reactions : []).map((reaction) => [reaction.emoji, reaction]));
    const currentSelection = Array.from(byEmoji.values()).find((reaction) => reaction.selected)?.emoji || '';
    const summary = documentRef.createElement('div');
    summary.className = 'kw-masha-feedly__comment-reaction-summary';
    commentReactionOptions.forEach(({ emoji, label }) => {
      const reaction = byEmoji.get(emoji) || { count: 0, selected: false };
      if (!Number(reaction.count)) return;
      const pill = documentRef.createElement('button');
      pill.type = 'button';
      pill.className = 'kw-masha-feedly__comment-reaction';
      pill.dataset.reactionEmoji = emoji;
      pill.setAttribute('aria-pressed', reaction.selected ? 'true' : 'false');
      pill.setAttribute('aria-label', t(reaction.selected ? 'COMMENT_REACTION_REMOVE' : 'COMMENT_REACTION_ADD', {
        reaction: t(label), count: Number(reaction.count) || 0,
      }));
      const icon = documentRef.createElement('span');
      icon.className = 'kw-masha-feedly__comment-reaction-emoji';
      icon.setAttribute('aria-hidden', 'true');
      icon.textContent = emoji;
      const count = documentRef.createElement('span');
      count.className = 'kw-masha-feedly__comment-reaction-count';
      count.textContent = String(Number(reaction.count) || 0);
      pill.append(icon, count);
      pill.addEventListener('click', () => onReact?.(emoji, pill));
      summary.append(pill);
    });
    const pickerButton = documentRef.createElement('button');
    pickerButton.type = 'button';
    pickerButton.className = 'kw-masha-feedly__comment-reaction-picker-toggle';
    const pickerFace = documentRef.createElement('span');
    pickerFace.className = 'kw-masha-feedly__comment-reaction-picker-face';
    pickerFace.setAttribute('aria-hidden', 'true');
    pickerFace.textContent = '☺';
    pickerButton.append(pickerFace);
    pickerButton.setAttribute('aria-expanded', 'false');
    pickerButton.setAttribute('aria-label', t('COMMENT_REACTION_PICKER_OPEN'));

    const picker = documentRef.createElement('div');
    picker.className = 'kw-masha-feedly__comment-reaction-picker';
    picker.hidden = true;
    picker.setAttribute('role', 'group');
    picker.setAttribute('aria-label', t('COMMENT_REACTION_PICKER_TITLE'));
    commentReactionOptions.forEach(({ emoji, label }) => {
      const reaction = byEmoji.get(emoji) || { count: 0, selected: false };
      const choice = documentRef.createElement('button');
      choice.type = 'button';
      choice.className = 'kw-masha-feedly__comment-reaction-choice';
      choice.dataset.reactionEmoji = emoji;
      choice.setAttribute('aria-pressed', reaction.selected ? 'true' : 'false');
      choice.setAttribute('aria-label', t(reaction.selected ? 'COMMENT_REACTION_REMOVE' : 'COMMENT_REACTION_ADD', {
        reaction: t(label), count: Number(reaction.count) || 0,
      }));
      choice.textContent = emoji;
      choice.addEventListener('click', () => onReact?.(emoji, choice));
      picker.append(choice);
    });
    pickerButton.addEventListener('click', () => {
      picker.hidden = !picker.hidden;
      pickerButton.setAttribute('aria-expanded', picker.hidden ? 'false' : 'true');
      pickerButton.setAttribute('aria-label', t(picker.hidden ? 'COMMENT_REACTION_PICKER_OPEN' : 'COMMENT_REACTION_PICKER_CLOSE'));
    });
    summary.append(pickerButton);
    container.append(summary, picker);
    if (currentSelection) container.dataset.selectedReaction = currentSelection;
    else delete container.dataset.selectedReaction;
  };
  /** Hält die Tab-Tastaturbedienung innerhalb eines geöffneten Dialogs. */
  const trapFocus = (container, event) => {
    if (!container || !event || event.key !== 'Tab') return false;
    const candidates = Array.from(container.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'))
      .filter((element) => !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.getAttribute('tabindex') !== '-1');
    const focusable = candidates.length ? candidates : [container];
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !container.contains(document.activeElement))) {
      event.preventDefault();
      last.focus();
      return true;
    }
    if (!event.shiftKey && (document.activeElement === last || !container.contains(document.activeElement))) {
      event.preventDefault();
      first.focus();
      return true;
    }
    return false;
  };
  /** Gibt ausschließlich die statischen, unterstützten Prioritätssymbole zurück. */
  const priorityIconSVG = (iconType) => iconType === 'info'
    ? '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M256 0C114.509 0 0 114.496 0 256s114.496 256 256 256 256-114.496 256-256S397.504 0 256 0zm0 476.279c-121.462 0-220.279-98.816-220.279-220.279S134.538 35.721 256 35.721 476.279 134.537 476.279 256 377.462 476.279 256 476.279z"/><path d="M256.006 213.397c-15.164 0-25.947 6.404-25.947 15.839v128.386c0 8.088 10.783 16.174 25.947 16.174 14.49 0 26.283-8.086 26.283-16.174V229.234c0-9.434-11.793-15.837-26.283-15.837z"/><path d="M256.006 134.208c-15.501 0-27.631 11.12-27.631 23.925s12.131 24.263 27.631 24.263c15.164 0 27.296-11.457 27.296-24.263s-12.132-23.925-27.296-23.925z"/></svg>'
    : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path class="kw-masha-feedly__priority-shape" d="m14.45 4a2.86 2.86 0 0 0-4.9 0l-7.88 12.87a2.87 2.87 0 0 0 2.45 4.36h15.76a2.87 2.87 0 0 0 2.45-4.36z"/><path class="kw-masha-feedly__priority-mark" d="M12 14.75a.76.76 0 0 1-.75-.75v-4.5a.75.75 0 0 1 1.5 0V14a.76.76 0 0 1-.75.75z"/><circle class="kw-masha-feedly__priority-mark" cx="12" cy="16.5" r="1"/></svg>';
  /** Sortiert nach dem gewählten Kriterium; ohne Auswahl bleibt die bisherige Standardsortierung erhalten. */
  const sortEntries = (entries, categories, priorities = [], sorting = null) => {
    const order = new Map(categories.map((category, index) => [Number(category.id), index]));
    const priorityOrder = new Map(priorities.map((priority, index) => [Number(priority.id), index]));
    if (sorting?.key && sorting.key !== 'default') {
      const direction = sorting.direction === 'desc' ? -1 : 1;
      const compareDate = (a, b) => String(a || '').localeCompare(String(b || ''));
      return [...entries].sort((left, right) => {
        let comparison = 0;
        if (sorting.key === 'due') {
          const leftHasDueDate = Boolean(left.dueDate);
          const rightHasDueDate = Boolean(right.dueDate);
          if (leftHasDueDate !== rightHasDueDate) return leftHasDueDate ? -1 : 1;
          comparison = compareDate(left.dueDate, right.dueDate);
        }
        if (sorting.key === 'created') comparison = compareDate(left.createdAt || left.loggedAt || left.entryDate, right.createdAt || right.loggedAt || right.entryDate);
        if (sorting.key === 'priority') comparison = (priorityOrder.get(Number(left.priorityID)) ?? Number.MAX_SAFE_INTEGER) - (priorityOrder.get(Number(right.priorityID)) ?? Number.MAX_SAFE_INTEGER);
        if (sorting.key === 'assignee') {
          const assigneeName = (entry) => (entry.assignees || []).map((member) => String(member.name || '').trim()).filter(Boolean).sort((a, b) => a.localeCompare(b, 'de', { sensitivity: 'base' }))[0] || '';
          const leftName = assigneeName(left);
          const rightName = assigneeName(right);
          if (Boolean(leftName) !== Boolean(rightName)) return leftName ? -1 : 1;
          comparison = leftName.localeCompare(rightName, 'de', { sensitivity: 'base' });
        }
        if (sorting.key === 'activity') comparison = compareDate(left.history?.[0]?.created || left.createdAt || left.loggedAt, right.history?.[0]?.created || right.createdAt || right.loggedAt);
        return comparison * direction
          || (order.get(Number(left.categoryID)) ?? Number.MAX_SAFE_INTEGER) - (order.get(Number(right.categoryID)) ?? Number.MAX_SAFE_INTEGER)
          || (priorityOrder.get(Number(left.priorityID)) ?? Number.MAX_SAFE_INTEGER) - (priorityOrder.get(Number(right.priorityID)) ?? Number.MAX_SAFE_INTEGER)
          || String(right.createdAt || right.loggedAt || right.entryDate || '').localeCompare(String(left.createdAt || left.loggedAt || left.entryDate || ''))
          || Number(right.id) - Number(left.id);
      });
    }
    return [...entries].sort((left, right) =>
      (order.get(Number(left.categoryID)) ?? Number.MAX_SAFE_INTEGER)
        - (order.get(Number(right.categoryID)) ?? Number.MAX_SAFE_INTEGER)
      || (priorityOrder.get(Number(left.priorityID)) ?? Number.MAX_SAFE_INTEGER)
        - (priorityOrder.get(Number(right.priorityID)) ?? Number.MAX_SAFE_INTEGER)
      || String(right.entryDate).localeCompare(String(left.entryDate))
      || Number(right.id) - Number(left.id));
  };

  /** Aktiviert ein Sortierkriterium oder kehrt die Richtung des aktiven Kriteriums um. */
  const toggleSorting = (current, key) => current?.key === key
    ? { key, direction: current.direction === 'asc' ? 'desc' : 'asc' }
    : { key, direction: ['created', 'activity'].includes(key) ? 'desc' : 'asc' };

  /** Filtert eine Eintragsliste nach Kategorie oder lässt alle Kategorien sichtbar. */
  const filterByCategory = (entries, categoryID) => !categoryID
    ? [...entries]
    : entries.filter((entry) => String(entry.categoryID) === String(categoryID));

  /** Filtert Einträge nach Priorität oder lässt alle Prioritäten sichtbar. */
  const filterByPriority = (entries, priorityID) => !priorityID
    ? [...entries]
    : entries.filter((entry) => String(entry.priorityID) === String(priorityID));

  /** Begrenzte Auswahl passender Verknüpfungen: nur andere Einträge derselben Seite. */
  const relatedEntryOptions = (entries, entry) => entries.filter((candidate) =>
    Number(candidate.id) !== Number(entry.id));

  /** Gemeinsame Darstellung pro Beziehung: Gegenrichtungen behalten Symbol und Farbfamilie. */
  const relationPresentation = (type) => ({
    duplicate_of: { kind: 'duplicate', label: 'RELATION_DUPLICATE_OF', icon: 'duplicate' },
    has_duplicate: { kind: 'duplicate', label: 'RELATION_HAS_DUPLICATE', icon: 'duplicate' },
    blocked_by: { kind: 'blocked', label: 'RELATION_BLOCKED_BY', icon: 'blocked' },
    blocks: { kind: 'blocked', label: 'RELATION_BLOCKS', icon: 'blocked' },
    related: { kind: 'related', label: 'RELATION_RELATED', icon: 'related' },
    related_to: { kind: 'related', label: 'RELATION_RELATED', icon: 'related' },
  })[type] || { kind: 'related', label: 'RELATION_RELATED', icon: 'related' };

  /** Gibt das zum Beziehungstyp gehörende, feste und rein dekorative Symbol zurück. */
  const relationIconSVG = (type) => {
    const icon = relationPresentation(type).icon;
    if (icon === 'duplicate') {
      return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3.625 23h9.75a2.629 2.629 0 0 0 2.625-2.625v-12.75a2.629 2.629 0 0 0 -2.625-2.625h-9.75a2.629 2.629 0 0 0 -2.625 2.625v12.75a2.629 2.629 0 0 0 2.625 2.625zm-.625-15.375a.625.625 0 0 1 .625-.625h9.75a.625.625 0 0 1 .625.625v12.75a.625.625 0 0 1 -.625.625h-9.75a.625.625 0 0 1 -.625-.625z"></path><path d="m20.37 1h-9.74a2.629 2.629 0 0 0 -2.421 1.61 1 1 0 1 0 1.842.78.63.63 0 0 1 .579-.39h9.74a.631.631 0 0 1 .63.63v12.74a.631.631 0 0 1 -.63.63h-2.37a1 1 0 0 0 0 2h2.37a2.633 2.633 0 0 0 2.63-2.63v-12.74a2.633 2.633 0 0 0 -2.63-2.63z"></path></svg>';
    }
    if (icon === 'blocked') {
      return '<svg viewBox="0 0 468.293 468.293" aria-hidden="true" focusable="false"><path d="M234.146 0C104.898 0 0 104.898 0 234.146s104.898 234.146 234.146 234.146 234.146-104.898 234.146-234.146S363.395 0 234.146 0zM66.185 234.146c0-93.034 75.551-168.585 167.961-168.585 34.966 0 68.059 10.615 94.907 29.346L95.532 329.054c-18.732-26.849-29.347-59.942-29.347-94.908zm167.961 167.961c-34.966 0-68.059-10.615-94.907-29.346l233.522-233.522c18.732 26.849 29.346 59.941 29.346 94.907 0 92.41-75.551 167.961-167.961 167.961z"></path></svg>';
    }
    return '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false"><path d="m18.6 32.3c-.7 0-1.4-.3-2-.8-1.1-1.1-1.1-2.9 0-4l10.9-10.9c1.1-1.1 2.9-1.1 4 0s1.1 2.9 0 4l-10.9 10.8c-.6.6-1.3.9-2 .9z"></path><path d="m21.9 32.8c-.5.5-1 .8-1.6 1-.1.2-.3.4-.5.6l-6.2 6.2c-1.7 1.7-4.5 1.7-6.2 0s-1.7-4.5 0-6.2l6.2-6.2c.2-.2.4-.3.6-.5.2-.6.6-1.1 1-1.6l4.5-4.5c-.9-.3-1.9-.4-2.9-.4-2.7 0-5.2 1-7.1 2.9l-6.2 6.2c-1.9 1.9-2.9 4.4-2.9 7.1s1 5.2 2.9 7.1 4.4 2.9 7.1 2.9 5.2-1 7.1-2.9l6.2-6.2c1.9-1.9 2.9-4.4 2.9-7.1 0-1-.2-2-.4-2.9z"></path><path d="m44.6 3.4c-3.9-3.9-10.3-3.9-14.2 0l-6.2 6.2c-1.9 1.9-2.9 4.4-2.9 7.1 0 1 .2 2 .4 2.9l4.5-4.5c.5-.5 1-.8 1.6-1 .1-.2.3-.4.5-.6l6.2-6.2c1.7-1.7 4.5-1.7 6.2 0s1.7 4.5 0 6.2l-6.2 6.2c-.2.2-.4.3-.6.5-.2.6-.6 1.1-1 1.6l-4.5 4.5c.9.3 1.9.4 2.9.4 2.7 0 5.2-1 7.1-2.9l6.2-6.2c3.9-3.9 3.9-10.3 0-14.2z"></path></svg>';
  };

  /** Zeigt Verknüpfungen direkt an der Karte mit Typ und Ziel, statt sie im Editor zu verstecken. */
  const renderRelationBadges = (container, relations, document) => {
    if (!container) return;
    container.replaceChildren();
    const validRelations = (relations || []).filter((relation) => relation && Number(relation.id) > 0);
    if (!validRelations.length) return;
    container.className = 'kw-masha-feedly__entry-relations';
    container.setAttribute('aria-label', t('ENTRY_RELATIONS_ARIA'));
    validRelations.forEach((relation) => {
      const badge = document.createElement('span');
      badge.className = 'kw-masha-feedly__entry-relation';
      const presentation = relationPresentation(relation.type);
      badge.dataset.relationKind = presentation.kind;
      badge.dataset.relationDirection = relation.direction || 'outgoing';
      const icon = document.createElement('span');
      icon.className = 'kw-masha-feedly__entry-relation-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = relationIconSVG(relation.type);
      const text = document.createElement('span');
      const relationType = t(presentation.label);
      text.textContent = `${relationType} · #${relation.id} ${relation.title || t('ENTRY_WITHOUT_TITLE')}`;
      badge.title = text.textContent;
      badge.append(icon, text);
      container.append(badge);
    });
  };

  /** Baut das Ziel einer Eintragskarte samt Kennung für das automatische Öffnen am Zielort. */
  const entryTargetURL = (entry, currentURL) => {
    const targetURL = new URL(entry.pageURL || currentURL, currentURL);
    targetURL.searchParams.set('masha-feedly-entry', String(entry.id));
    return targetURL.toString();
  };

  /** Liefert die Bugbeschreibung nur zum Lesen und die separat veränderbaren Eintragsmetadaten. */
  const editableEntryData = (entry) => ({
    id: Number(entry.id),
    categoryID: Number(entry.categoryID),
    priorityID: Number(entry.priorityID),
    dueDate: entry.dueDate || '',
    assignedMemberIDs: (entry.assignedMemberIDs || []).map(Number),
    context: t('ENTRY_CONTEXT_STATUS', { status: entry.categoryTitle || t('ENTRY_WITHOUT_CATEGORY') }),
    description: entry.content || t('ENTRY_NO_DESCRIPTION'),
    assignees: entry.assignees || [],
  });

  /** Formatiert Urheber und Erstellungszeit aus dem unveränderlichen Erstellungspunkt. */
  const entryCreationMeta = (entry) => {
    const creation = (entry?.history || []).find((item) => item?.type === 'created');
    const author = String(entry?.reportedByName || creation?.actor || entry?.createdBy || '').trim() || t('ENTRY_CREATED_UNKNOWN');
    const rawDate = String(creation?.created || entry?.createdAt || entry?.loggedAt || '').trim();
    const normalizedDate = rawDate.replace(' ', 'T');
    const dateWithZone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalizedDate) ? normalizedDate : `${normalizedDate}Z`;
    const date = rawDate ? new Date(dateWithZone) : null;
    const when = date && !Number.isNaN(date.getTime())
      ? date.toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' })
      : rawDate;
    const initials = author.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => Array.from(part)[0]).join('').toLocaleUpperCase('de');
    return {
      author,
      initials: String(entry?.createdByInitials || initials),
      color: String(entry?.createdByColor || ''),
      imageURL: String(entry?.createdByImageURL || ''),
      when,
      dateTime: date && !Number.isNaN(date.getTime()) ? date.toISOString() : rawDate,
    };
  };

  /** Zeichnet das Ersteller-Profilbild wie bei den Zuständigkeiten, mit Initialen als Fallback. */
  const renderEntryCreatorAvatar = (container, creator, document) => {
    if (!container) return;
    container.replaceChildren();
    container.style.backgroundColor = creator.color || '#d9b6cd';
    container.title = creator.author || '';
    if (creator.imageURL) {
      const image = document.createElement('img');
      image.src = creator.imageURL;
      image.alt = '';
      image.loading = 'lazy';
      container.append(image);
    } else {
      container.textContent = creator.initials || '?';
    }
  };

  /** Rendert gespeicherte Browser-, Seiten- und Elementdaten als sichere Textwerte. */
  const renderEnvironment = (container, entry, document) => {
    if (!container) return;
    const rows = [
      [t('ENV_LOGGED_AT'), entry.loggedAt],
      [t('ENV_PAGE'), entry.pageURL],
      [t('ENV_OPERATING_SYSTEM'), entry.operatingSystem],
      [t('ENV_BROWSER'), entry.browser],
      [t('ENV_SELECTED_AREA'), entry.selector],
      [t('ENV_ELEMENT_TEXT'), entry.elementText],
      [t('ENV_RESOLUTION'), entry.resolution],
      [t('ENV_BROWSER_WINDOW'), entry.browserWindow],
      [t('ENV_COLOR_DEPTH'), entry.colorDepth ? `${entry.colorDepth} Bit` : ''],
      [t('ENV_USER_AGENT'), entry.userAgent],
    ].filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');
    container.replaceChildren();
    if (!rows.length) {
      container.hidden = true;
      return;
    }
    container.hidden = false;
    rows.forEach(([label, value]) => {
      const term = document.createElement('dt');
      term.textContent = label;
      const description = document.createElement('dd');
      description.textContent = String(value);
      container.append(term, description);
    });
  };

  /** Zeichnet verantwortliche Mitglieder mit Profilbild oder farbigen Initialen. */
  const renderAssignees = (container, members, document) => {
    container.replaceChildren();
    if (!members.length) return;
    container.className = 'kw-masha-feedly__entry-assignees';
    container.setAttribute('aria-label', t('ASSIGNEES_ARIA'));
    members.forEach((member) => {
      const avatar = document.createElement('span');
      avatar.className = 'kw-masha-feedly__assignee-avatar';
      avatar.style.backgroundColor = member.color || '#d9b6cd';
      avatar.title = member.name || member.initials || '';
      avatar.setAttribute('aria-label', member.name || member.initials || t('MEMBER_FALLBACK'));
      if (member.imageURL) {
        const image = document.createElement('img');
        image.src = member.imageURL;
        image.alt = '';
        image.loading = 'lazy';
        avatar.append(image);
      } else {
        avatar.textContent = member.initials || '?';
      }
      container.append(avatar);
    });
  };

  /** Rendert Status- und Zuständigkeitsänderungen als sichere Verlaufsliste. */
  const renderHistory = (container, history, document) => {
    if (!container) return;
    container.replaceChildren();
    (history || []).forEach((item) => {
      const row = document.createElement('li');
      row.className = `kw-masha-feedly__history-item is-${['status', 'priority', 'relations', 'reported_by', 'comment_reaction'].includes(item.type) ? (item.type === 'reported_by' ? 'reported-by' : item.type) : item.type === 'due_date' ? 'due-date' : 'assignees'}`;
      const change = document.createElement('p');
      const historyText = {
        status: () => t('HISTORY_STATUS_CHANGE', { oldValue: item.oldValue, newValue: item.newValue }),
        priority: () => t('HISTORY_PRIORITY_CHANGE', { oldValue: item.oldValue, newValue: item.newValue }),
        due_date: () => t('HISTORY_DUE_DATE_CHANGE', { oldValue: item.oldValue || t('NO_DUE_DATE'), newValue: item.newValue || t('NO_DUE_DATE') }),
        created: () => t('HISTORY_CREATED', { title: item.newValue }),
        comment: () => t('HISTORY_COMMENT', { text: item.newValue }),
        attachment: () => t('HISTORY_ATTACHMENT', { text: item.newValue }),
        comment_edited: () => t('HISTORY_COMMENT_EDITED', { oldValue: item.oldValue, newValue: item.newValue }),
        comment_deleted: () => t('HISTORY_COMMENT_DELETED', { text: item.oldValue }),
        comment_reaction: () => t('HISTORY_COMMENT_REACTION', { oldValue: item.oldValue || t('HISTORY_NO_REACTION'), newValue: item.newValue || t('HISTORY_NO_REACTION') }),
        assignees: () => t('HISTORY_ASSIGNEES_CHANGE', { oldValue: item.oldValue || t('HISTORY_NOBODY'), newValue: item.newValue || t('HISTORY_NOBODY') }),
        relations: () => t('HISTORY_RELATIONS_CHANGE', { oldValue: item.oldValue || t('HISTORY_NO_RELATIONS'), newValue: item.newValue || t('HISTORY_NO_RELATIONS') }),
        reported_by: () => t('HISTORY_REPORTED_BY', { oldValue: item.oldValue, newValue: item.newValue }),
        estimate: () => t('HISTORY_ESTIMATE_CHANGE', { oldValue: item.oldValue, newValue: item.newValue }),
      }[item.type] || (() => t('HISTORY_ASSIGNEES_CHANGE', { oldValue: item.oldValue || t('HISTORY_NOBODY'), newValue: item.newValue || t('HISTORY_NOBODY') }));
      change.textContent = historyText();
      const meta = document.createElement('small');
      const rawDate = String(item.created || '').trim();
      const normalizedDate = rawDate.replace(' ', 'T');
      const dateWithZone = /(?:Z|[+-]\d{2}:?\d{2})$/i.test(normalizedDate) ? normalizedDate : `${normalizedDate}Z`;
      const date = new Date(dateWithZone);
      const when = Number.isNaN(date.getTime()) ? String(item.created || '') : date.toLocaleString('de-DE');
      meta.textContent = t('HISTORY_META', { actor: item.actor || t('MEMBER_FALLBACK'), when });
      row.append(change, meta);
      container.append(row);
    });
  };

  const effects = window.KWMashaFeedlyEffects || {};
  const celebrateDone = (document, window) => effects.confetti?.(document, window) || null;
  const celebrateClosedCategory = (document, window, imageURL) => effects.unicorn?.(document, window, imageURL) || null;
  const celebrateRocketLaunch = (document, window) => effects.rocket?.(document, window) || null;
  const celebrateCompletion = (document, window, imageURL, theme = "playful", random = Math.random) =>
    effects.playOnDone?.(document, window, imageURL, theme, random) || null;
  const previewCompletionAnimation = (document, window, animation, imageURL) =>
    effects.preview?.(animation, document, window, imageURL) || null;

  /**
   * Unterscheidet bei älteren, verkürzten Auswahlpfaden wiederholte Seitenbereiche anhand ihres Textes.
   * Mehrdeutige Treffer bleiben ohne Marker, damit kein fremder Bereich als Fehlerstelle erscheint.
   * @param {{selector: string, elementText?: string}} entry Gespeicherter Bereich des Eintrags.
   * @param {Document} document Seitendokument zur Auflösung des Auswahlpfads.
   * @return {Element|null} Eindeutig zugeordneter Bereich oder kein Treffer.
   */
  const resolveTarget = (entry, document) => {
    if (!entry.selector) return null;
    try {
      const first = document.querySelector(entry.selector);
      if (!first) return null;
      const candidates = [...(document.querySelectorAll?.(entry.selector) || [first])];
      if (candidates.length <= 1) return first;
      const normalize = (text) => String(text || '').trim().replace(/\s+/g, ' ').slice(0, 220).toLowerCase();
      const savedText = normalize(entry.elementText);
      if (!savedText) return null;
      const matches = candidates.filter((element) => normalize(element.innerText || element.textContent) === savedText);
      return matches.length === 1 ? matches[0] : null;
    } catch (_) {
      return null;
    }
  };

  /** Erstellt eine anklickbare, am passenden Seitenelement verankerte Markierung. */
  const createMarker = (entry, index, target, document, window, onClick) => {
    const marker = document.createElement('button');
    marker.type = 'button';
    marker.className = 'kw-masha-feedly__page-marker';
    marker.setAttribute('aria-label', t('ENTRY_MARKER_ARIA', { number: index + 1, title: entry.title }));
    marker.setAttribute('data-marker-number', String(index + 1));
    marker.setAttribute('aria-pressed', 'false');
    marker.title = t('ENTRY_MARKER_TITLE', { category: entry.categoryTitle, title: entry.title });
    marker.dataset.entryId = String(entry.id);
    const priorityColor = /^#[\da-f]{6}$/i.test(String(entry.priorityColor || '')) ? entry.priorityColor : '#64748b';
    marker.style.setProperty('--masha-feedly-priority-color', priorityColor);
    marker.innerHTML = '<svg class="kw-masha-feedly__page-marker-icon" viewBox="0 0 612.001 612.001" aria-hidden="true" focusable="false"><path d="M64.601 236.822c0 157.434 128.185 375.178 241.4 375.178 106.581 0 241.399-217.744 241.399-375.178S439.322 0 306 0 64.601 79.388 64.601 236.822zm304.12 116.415c29.475-29.475 70.598-40.195 108.552-32.173 8.021 37.954-2.698 79.077-32.173 108.552-29.475 29.475-70.598 40.195-108.552 32.173 1.978-37.955 12.698-79.078 42.173-108.552zm-233.994-32.174c37.954-8.021 79.077 2.698 108.552 32.173 29.475 29.475 40.195 70.598 32.173 108.552-37.954-8.021-79.077-2.698-108.552-32.173-29.475-29.476-40.194-70.598-32.173-108.552z"/></svg>';
    const reposition = () => {
      const bounds = target.getBoundingClientRect();
      const hasPosition = entry.elementPositionX !== '' && entry.elementPositionX !== null && entry.elementPositionX !== undefined
        && entry.elementPositionY !== '' && entry.elementPositionY !== null && entry.elementPositionY !== undefined
        && Number.isFinite(Number(entry.elementPositionX)) && Number.isFinite(Number(entry.elementPositionY));
      const xRatio = hasPosition ? Math.max(0, Math.min(1, Number(entry.elementPositionX))) : 0.5;
      const yRatio = hasPosition ? Math.max(0, Math.min(1, Number(entry.elementPositionY))) : 0.5;
      const left = `${bounds.left + bounds.width * xRatio}px`;
      const top = `${bounds.top + bounds.height * yRatio}px`;
      if (marker.style.left !== left) marker.style.left = left;
      if (marker.style.top !== top) marker.style.top = top;
    };
    marker.reposition = reposition;
    marker.addEventListener('click', onClick);
    reposition();
    return marker;
  };

  /** Markiert genau den aktuell geöffneten Eintrag auf der Seite. */
  const setActiveMarker = (markers, entryID = null) => markers.forEach((marker) => {
    const active = entryID !== null && String(marker.dataset.entryId) === String(entryID);
    marker.classList?.toggle?.('is-active', active);
    marker.setAttribute?.('aria-pressed', String(active));
  });

  /**
   * Führt sichtbare Marker auch bei Animationen und nachträglichen Layoutänderungen nach.
   * Ein gemeinsamer Frame verhindert einen eigenen Animationszyklus pro Eintrag.
   * @param {Array<HTMLButtonElement & {reposition: function(): void}>} markers Aktuelle Seitenmarker.
   * @param {Window} window Browserfenster für die Frame-Verwaltung.
   * @return {function(): void} Stoppt die Nachführung beim Schließen oder erneuten Rendern.
   */
  const trackMarkers = (markers, window) => {
    let frame = null;
    let stopped = false;
    const update = () => {
      if (stopped || !markers.length) return;
      markers.forEach((marker) => marker.reposition());
      frame = window.requestAnimationFrame(update);
    };
    if (markers.length && typeof window.requestAnimationFrame === 'function') {
      frame = window.requestAnimationFrame(update);
    }
    return () => {
      stopped = true;
      if (frame !== null) window.cancelAnimationFrame?.(frame);
    };
  };

  /** Rendert HTTP(S)-Links als Links und jeden übrigen Text weiterhin als reinen Text. */
  const renderLinks = (container, value, documentRef = document) => {
    const text = String(value || '');
    const pattern = /https?:\/\/[^\s<>]+/gi;
    let offset = 0;
    let match;
    let found = false;
    while ((match = pattern.exec(text))) {
      let url = match[0];
      const trailing = url.match(/[.,!?;:)}\]]+$/)?.[0] || '';
      if (trailing) url = url.slice(0, -trailing.length);
      let parsed;
      try { parsed = new URL(url); } catch (_) { continue; }
      if (!['http:', 'https:'].includes(parsed.protocol) || !url) continue;
      found = true;
      if (match.index > offset) container.append(documentRef.createTextNode(text.slice(offset, match.index)));
      const anchor = documentRef.createElement('a');
      anchor.href = parsed.href;
      anchor.textContent = url;
      anchor.target = '_blank';
      anchor.rel = 'noopener noreferrer';
      container.append(anchor);
      offset = match.index + url.length;
      if (trailing) {
        container.append(documentRef.createTextNode(trailing));
        offset += trailing.length;
      }
    }
    if (!found) container.textContent = text;
    else if (offset < text.length) container.append(documentRef.createTextNode(text.slice(offset)));
  };

  return { sortEntries, toggleSorting, filterByCategory, filterByPriority, relatedEntryOptions, renderRelationBadges, entryTargetURL, editableEntryData, entryCreationMeta, renderEntryCreatorAvatar, renderEnvironment, renderAssignees, renderHistory, renderLinks, renderCommentReactions, priorityIconSVG, resolveTarget, createMarker, setActiveMarker, trackMarkers, celebrateDone, celebrateClosedCategory, celebrateRocketLaunch, celebrateCompletion, previewCompletionAnimation, trapFocus };
})();

/** Lädt Einträge, zeichnet Seitenmarkierungen und zeigt die filterbare Übersicht. */
document.addEventListener('DOMContentLoaded', () => {
  const widget = document.querySelector('[data-kw-masha-feedly]');
  if (!widget) return;

  // Der Betreiber kann Mite unabhängig von einem Eintragswechsel starten oder stoppen.
  const miteModal = widget.querySelector('[data-widget-mite-modal]');
  if (miteModal) {
    let miteEntryID = '';
    let miteTimerID = 0;
    let miteBusy = false;
    const miteProject = miteModal.querySelector('[data-widget-mite-project]');
    const miteService = miteModal.querySelector('[data-widget-mite-service]');
    const miteStart = miteModal.querySelector('[data-widget-mite-start]');
    const miteStop = miteModal.querySelector('[data-widget-mite-stop]');
    const miteStatus = miteModal.querySelector('[data-widget-mite-status]');
    const miteButton = widget.querySelector('[data-masha-feedly-open-mite]');
    const loadMiteOptions = async () => {
      miteStatus.textContent = t('MITE_LOADING');
      miteProject.disabled = miteService.disabled = miteStart.disabled = true;
      miteStop.hidden = true;
      try {
        const response = await fetch(miteModal.dataset.optionsUrl, { credentials: 'same-origin' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || t('MITE_LOAD_ERROR'));
        miteProject.replaceChildren(new Option(t('MITE_CHOOSE_PROJECT'), ''));
        Object.entries(result.projects || {}).forEach(([id, label]) => miteProject.add(new Option(label, id)));
        miteService.replaceChildren(new Option(t('MITE_CHOOSE_SERVICE'), ''));
        Object.entries(result.services || {}).forEach(([id, label]) => miteService.add(new Option(label, id)));
        miteProject.value = String(result.projectID || '');
        miteProject.disabled = miteService.disabled = false;
        miteTimerID = Number(result.activeTimerID || 0);
        miteStop.hidden = miteTimerID === 0;
        miteModal.querySelector('[data-widget-mite-active]').textContent = miteTimerID
          ? t('MITE_SWITCH_TIMER', { id: miteTimerID, note: result.activeTimerNote || '—' })
          : t('MITE_NO_TIMER');
        miteStart.hidden = false;
        miteStart.disabled = !miteProject.value || !miteService.value;
        miteStatus.textContent = '';
      } catch (error) {
        miteStatus.textContent = error.message || t('MITE_LOAD_ERROR');
      }
    };
    const openMite = (entryID = '') => {
      miteEntryID = String(entryID || '');
      miteStart.hidden = false;
      miteStart.disabled = true;
      miteModal.hidden = false;
      miteModal.querySelector('h2').focus();
      loadMiteOptions();
    };
    const closeMite = () => { if (!miteBusy) miteModal.hidden = true; };
    miteButton?.addEventListener('click', () => openMite());
    document.addEventListener('kw-masha-feedly:mite-prompt', (event) => {
      if (event.detail?.entryID) openMite(event.detail.entryID);
    });
    miteModal.addEventListener('change', () => {
      miteStart.disabled = miteBusy || !miteProject.value || !miteService.value;
    });
    miteModal.addEventListener('click', async (event) => {
      if (event.target.closest('[data-widget-mite-close]')) { closeMite(); return; }
      if (event.target.closest('[data-widget-mite-refresh]')) { loadMiteOptions(); return; }
      const stopping = Boolean(event.target.closest('[data-widget-mite-stop]'));
      if (!stopping && !event.target.closest('[data-widget-mite-start]')) return;
      if (miteBusy || (stopping ? !miteTimerID : (!miteProject.value || !miteService.value))) return;
      miteBusy = true;
      miteModal.querySelectorAll('button,select').forEach((control) => { control.disabled = true; });
      miteStatus.textContent = stopping ? t('MITE_STOPPING') : t('MITE_STARTING');
      const data = new FormData();
      data.set('SecurityID', widget.dataset.securityId || '');
      data.set('ConfirmedTimerID', String(miteTimerID));
      if (!stopping && miteEntryID) {
        data.set('EntryID', miteEntryID);
      }
      if (!stopping) {
        data.set('ProjectID', miteProject.value);
        data.set('ServiceID', miteService.value);
      }
      try {
        const response = await fetch(stopping ? miteModal.dataset.stopUrl : miteModal.dataset.startUrl, {
          method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data,
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || (stopping ? t('MITE_STOP_ERROR') : t('MITE_START_ERROR')));
        const successMessage = result.message || (stopping ? t('MITE_STOPPED') : t('MITE_STARTED'));
        await loadMiteOptions();
        miteStatus.textContent = successMessage;
      } catch (error) {
        miteStatus.textContent = error.message || t(stopping ? 'MITE_STOP_ERROR' : 'MITE_START_ERROR');
      } finally {
        miteBusy = false;
        miteProject.disabled = miteService.disabled = false;
        miteStop.disabled = false;
        miteStart.disabled = !miteProject.value || !miteService.value;
      }
    });
  }

  const form = widget.querySelector('[data-masha-feedly-entry-form]');
  const pageCount = widget.querySelector('[data-masha-feedly-page-count]');
  const totalCount = widget.querySelector('[data-masha-feedly-total-count]');
  const closedCountDisplay = widget.querySelector('[data-masha-feedly-closed-count]');
  const feedbackButton = widget.querySelector('[data-masha-feedly-open-feedback]');
  const feedbackCountDisplay = widget.querySelector('[data-masha-feedly-feedback-count]');
  const estimatePendingButton = widget.querySelector('[data-masha-feedly-open-estimate-pending]');
  const estimatePendingCountDisplay = widget.querySelector('[data-masha-feedly-estimate-pending-count]');
  const estimateApprovedButton = widget.querySelector('[data-masha-feedly-open-estimate-approved]');
  const estimateApprovedCountDisplay = widget.querySelector('[data-masha-feedly-estimate-approved-count]');
  const unreadCountDisplay = widget.querySelector('[data-masha-feedly-unread-count]');
  const newsSummary = widget.querySelector('[data-masha-feedly-news-summary]');
  const openListButton = widget.querySelector('[data-masha-feedly-open-list]');
  const openClosedButton = widget.querySelector('[data-masha-feedly-open-closed]');
  const openPageListButton = widget.querySelector('[data-masha-feedly-open-page-list]');
  const openNewsButton = widget.querySelector('[data-masha-feedly-open-news]');
  const rainbow = widget.querySelector('[data-masha-feedly-rainbow]');
  const rainbowCopy = widget.querySelector('[data-masha-feedly-rainbow-copy]');
  const rainbowTitle = widget.querySelector('[data-masha-feedly-rainbow-title]');
  const rainbowMessage = widget.querySelector('[data-masha-feedly-rainbow-message]');
  const helpModal = widget.querySelector('[data-masha-feedly-help-modal]');
  const openHelpButton = widget.querySelector('[data-masha-feedly-open-help]');
  const closeHelpButton = widget.querySelector('[data-masha-feedly-close-help]');
  const listModal = widget.querySelector('[data-masha-feedly-entries-modal]');
  const closeListButton = widget.querySelector('[data-masha-feedly-close-list]');
  const modeField = widget.querySelector('[data-masha-feedly-list-mode]');
  const categoryFilter = widget.querySelector('[data-masha-feedly-category-filter]');
  const priorityFilter = widget.querySelector('[data-masha-feedly-priority-filter]');
  const filtersDetails = widget.querySelector('[data-masha-feedly-filters-details]');
  const sortDetails = widget.querySelector('[data-masha-feedly-sort-details]');
  const sortCurrent = widget.querySelector('[data-masha-feedly-sort-current]');
  const sortLabel = widget.querySelector('[data-masha-feedly-sort-label]');
  const sortOptions = Array.from(widget.querySelectorAll('[data-masha-feedly-sort-option]'));
  const filterCount = widget.querySelector('[data-masha-feedly-filter-count]');
  const filterState = widget.querySelector('[data-masha-feedly-filter-state]');
  const activeFilters = widget.querySelector('[data-masha-feedly-active-filters]');
  const priorityFilterIcon = widget.querySelector('[data-masha-feedly-priority-filter-icon]');
  const savedViewSelect = widget.querySelector('[data-masha-feedly-saved-view]');
  const savedViewName = widget.querySelector('[data-masha-feedly-saved-view-name]');
  const saveViewButton = widget.querySelector('[data-masha-feedly-save-view]');
  const deleteViewButton = widget.querySelector('[data-masha-feedly-delete-view]');
  const savedViewStatus = widget.querySelector('[data-masha-feedly-saved-view-status]');
  const listCount = widget.querySelector('[data-masha-feedly-list-count]');
  const listContainer = widget.querySelector('[data-masha-feedly-entries-list]');
  const editModal = widget.querySelector('[data-masha-feedly-edit-modal]');
  const editForm = widget.querySelector('[data-masha-feedly-edit-form]');
  const editStatus = widget.querySelector('[data-masha-feedly-edit-status]');
  const editHeading = widget.querySelector('[data-masha-feedly-edit-heading]');
  const createdMeta = widget.querySelector('[data-masha-feedly-entry-created]');
  const createdAvatar = widget.querySelector('[data-masha-feedly-entry-created-avatar]');
  const createdBy = widget.querySelector('[data-masha-feedly-entry-created-by]');
  const createdAt = widget.querySelector('[data-masha-feedly-entry-created-at]');
  const listHeading = widget.querySelector('[data-masha-feedly-list-heading]');
  const helpHeading = widget.querySelector('[data-masha-feedly-help-heading]');
  const editPriorityIcon = widget.querySelector('[data-masha-feedly-edit-priority]');
  const shareActiveEntryButton = widget.querySelector('[data-masha-feedly-share-active-entry]');
  const editContext = widget.querySelector('[data-masha-feedly-edit-context]');
  const editRelations = widget.querySelector('[data-masha-feedly-edit-relations]');
  const editDescription = widget.querySelector('[data-masha-feedly-edit-description]');
  const editAttachments = widget.querySelector('[data-masha-feedly-edit-attachments]');
  const editEnvironment = widget.querySelector('[data-masha-feedly-edit-environment]');
  const editEnvironmentDetails = widget.querySelector('[data-masha-feedly-edit-environment-details]');
  const estimateSection = widget.querySelector('[data-masha-feedly-estimate]');
  const estimateFields = widget.querySelector('[data-masha-feedly-estimate-fields]');
  const estimateReadonly = widget.querySelector('[data-masha-feedly-estimate-readonly]');
  const estimateReadonlyDuration = widget.querySelector('[data-masha-feedly-estimate-duration]');
  const estimateReadonlyAmount = widget.querySelector('[data-masha-feedly-estimate-amount]');
  const estimateReadonlyNote = widget.querySelector('[data-masha-feedly-estimate-note]');
  const estimateReadonlyNoteRow = widget.querySelector('[data-masha-feedly-estimate-note-row]');
  const estimateReadonlyHelp = widget.querySelector('[data-masha-feedly-estimate-help]');
  const estimateState = widget.querySelector('[data-masha-feedly-estimate-state]');
  const estimateLock = widget.querySelector('[data-masha-feedly-estimate-lock]');
  const estimatePrice = estimateSection?.querySelector('[data-masha-feedly-estimate-price]');
  const relationType = widget.querySelector('[data-masha-feedly-relation-type]');
  const relatedEntries = widget.querySelector('[data-masha-feedly-related-entries]');
  const relationSearch = widget.querySelector('[data-masha-feedly-relation-search]');
  let relationCandidates = [];
  let relationCandidatesLoaded = false;
  const editHistory = widget.querySelector('[data-masha-feedly-history]');
  const commentForm = widget.querySelector('[data-masha-feedly-comment-form]');
  const commentList = widget.querySelector('[data-masha-feedly-comments]');
  const commentCount = widget.querySelector('[data-masha-feedly-comment-count]');
  const commentStatus = widget.querySelector('[data-masha-feedly-comment-status]');
  const markers = [];
  let categories = [];
  let priorities = [];
  let entries = [];
  let savedViews = [];
  let loadedMode = '';
  let sorting = { key: 'due', direction: 'asc' };
  let panelOpen = false;
  let listRequest = 0;
  let activeEntry = null;
  const requestedEntryID = new URL(window.location.href).searchParams.get('masha-feedly-entry');
  const formalAddress = widget.dataset.address === 'sie';
  const t = (key, values = {}) => window.KWMashaFeedlyTranslate(key, values);
  const renderActiveFilters = () => {
    if (!activeFilters) return;
    const modeLabels = {
      unread: 'FILTER_UNREAD', open: 'FILTER_OPEN', 'page-open': 'FILTER_PAGE_OPEN',
      feedback: 'FILTER_FEEDBACK', closed: 'FILTER_CLOSED', all: 'FILTER_ALL', page: 'FILTER_PAGE', mine: 'FILTER_MINE',
      'estimate-pending': 'ESTIMATE_PENDING_BUTTON', 'estimate-approved': 'ESTIMATE_APPROVED_BUTTON',
    };
    const active = [];
    if (modeField?.value && modeField.value !== 'page') {
      active.push({ kind: 'mode', label: t(modeLabels[modeField.value] || 'FILTER_MODE') });
    }
    const category = categories.find((item) => String(item.id) === String(categoryFilter?.value));
    if (category) active.push({ kind: 'category', label: category.title });
    const priority = priorities.find((item) => String(item.id) === String(priorityFilter?.value));
    if (priority) active.push({ kind: 'priority', label: priority.title });

    filterCount.textContent = String(active.length);
    filterState.textContent = active.length ? t('FILTERS_ACTIVE', { count: active.length }) : t('FILTERS_NONE');
    filtersDetails?.setAttribute('data-has-active-filters', String(active.length > 0));
    activeFilters.replaceChildren();
    activeFilters.hidden = active.length === 0;
    active.forEach((filter) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'kw-masha-feedly__active-filter';
      chip.dataset.removeFilter = filter.kind;
      chip.textContent = `${filter.label} ×`;
      chip.setAttribute('aria-label', t('FILTER_REMOVE', { label: filter.label }));
      chip.addEventListener('click', () => {
        if (filter.kind === 'mode') modeField.value = 'page';
        if (filter.kind === 'category') categoryFilter.value = '';
        if (filter.kind === 'priority') priorityFilter.value = '';
        savedViewSelect.value = '';
        deleteViewButton.disabled = true;
        if (filter.kind === 'mode') {
          return loadEntries('page');
        } else {
          renderList({ mode: loadedMode, entries, categories, priorities });
        }
      });
      activeFilters.append(chip);
    });
    if (active.length) {
      const clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'kw-masha-feedly__clear-filters';
      clear.dataset.clearFilters = 'true';
      clear.textContent = t('FILTERS_CLEAR');
      clear.addEventListener('click', () => {
        modeField.value = 'page';
        categoryFilter.value = '';
        priorityFilter.value = '';
        savedViewSelect.value = '';
        deleteViewButton.disabled = true;
        return loadEntries('page');
      });
      activeFilters.append(clear);
    }
  };
  const updateUnreadCount = (count, commentCount = 0) => {
    if (unreadCountDisplay && Number.isFinite(Number(count))) {
      const unread = Math.max(0, Number(count));
      unreadCountDisplay.textContent = String(unread);
      openNewsButton?.setAttribute('data-has-news', String(unread > 0));
      const comments = Math.max(0, Number(commentCount) || 0);
      if (newsSummary) newsSummary.textContent = t('NEWS_SUMMARY', { entries: unread, comments });
      const description = `${t('NEWS_TITLE')} · ${t('NEWS_SUMMARY', { entries: unread, comments })}`;
      openNewsButton?.setAttribute('aria-label', description);
      openNewsButton?.setAttribute('data-tooltip', description);
    }
  };
  const renderSavedViews = (views) => {
    const selected = savedViewSelect.value;
    savedViews = Array.isArray(views) ? views : [];
    savedViewSelect.replaceChildren(new Option(t('SAVED_VIEW_NONE'), ''));
    savedViews.forEach((view) => savedViewSelect.append(new Option(view.title, view.id)));
    savedViewSelect.value = savedViews.some((view) => String(view.id) === selected) ? selected : '';
    deleteViewButton.disabled = !savedViewSelect.value;
  };
  const loadSavedViews = async () => {
    const response = await fetch(widget.dataset.savedViewsUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const data = await response.json();
    if (!response.ok || !data.success) throw new Error(data.message || t('SAVED_VIEW_ERROR'));
    renderSavedViews(data.views);
  };
  const postSavedView = async (url, values) => {
    const data = new FormData();
    data.set('SecurityID', widget.dataset.securityId || '');
    Object.entries(values).forEach(([key, value]) => data.set(key, String(value)));
    const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || t('SAVED_VIEW_ERROR'));
    return result;
  };
  if (openListButton) openListButton.dataset.label = t('OPEN_ALL_LABEL');
  if (openPageListButton) openPageListButton.dataset.label = t('OPEN_PAGE_LABEL');
  const copyEntryURL = async (url) => {
    if (window.navigator?.clipboard?.writeText) {
      try {
        await window.navigator.clipboard.writeText(url);
        return;
      } catch (_) {
        // Fallback für Browser, die den Clipboard-Zugriff im aktuellen Kontext blockieren.
      }
    }
    const textarea = document.createElement('textarea');
    textarea.value = url;
    textarea.setAttribute('readonly', '');
    textarea.setAttribute('aria-hidden', 'true');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.append(textarea);
    textarea.select();
    const copied = document.execCommand?.('copy') === true;
    textarea.remove();
    if (!copied) throw new Error('Clipboard copy failed');
  };
  const shareEntryLink = async (entry, button) => {
    if (!entry || !button) return;
    const url = window.KWMashaFeedlyEntries.entryTargetURL(entry, window.location.href);
    try {
      await copyEntryURL(url);
      button.dataset.shared = 'true';
      button.setAttribute('aria-label', t('ENTRY_SHARE_DONE'));
      button.title = t('ENTRY_SHARE_DONE');
    } catch (_) {
      button.setAttribute('aria-label', t('ENTRY_SHARE_ERROR'));
      button.title = t('ENTRY_SHARE_ERROR');
    }
  };

  const renderAttachments = (attachments) => {
    if (!editAttachments) return;
    editAttachments.replaceChildren();
    (attachments || []).forEach((attachment) => {
      if (!attachment.url) return;
      const link = document.createElement('a');
      link.href = attachment.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = attachment.name;
      if (String(attachment.mimeType || '').startsWith('image/')) {
        const image = document.createElement('img');
        image.src = attachment.url;
        image.alt = attachment.name;
        image.loading = 'lazy';
        link.replaceChildren(image, document.createTextNode(attachment.name));
      }
      editAttachments.append(link);
    });
    editAttachments.hidden = editAttachments.children.length === 0;
  };

  const renderEditPriorityIcon = (entry) => {
    if (!editPriorityIcon || !entry) return;
    const iconType = entry.priorityIconType === 'info' ? 'info' : 'warning';
    editPriorityIcon.dataset.iconType = iconType;
    editPriorityIcon.innerHTML = window.KWMashaFeedlyEntries.priorityIconSVG(iconType);
    editPriorityIcon.setAttribute('aria-label', entry.priorityTitle || 'Priorität');
    editPriorityIcon.title = entry.priorityTitle || 'Priorität';
    editPriorityIcon.setAttribute('aria-label', entry.priorityTitle || 'Priorität');
    editPriorityIcon.style.setProperty('--masha-feedly-priority-color', entry.priorityColor || '#64748b');
    editPriorityIcon.hidden = false;
  };

  const renderEstimate = (entry = activeEntry) => {
    if (!estimateSection || !entry || !editForm) return;
    const categoryID = editForm.elements.CategoryID?.value || entry.categoryID;
    const category = categories.find((item) => String(item.id) === String(categoryID));
    const role = category?.systemKey || entry.categoryRole || '';
    const relevant = ['estimate_pending', 'estimate_approved'].includes(role);
    estimateSection.hidden = !relevant;
    if (!relevant) return;
    const transitionLocked = entry.categoryRole === 'estimate_pending';
    [...editForm.elements.CategoryID.options].forEach((option) => {
      const optionRole = categories.find((item) => String(item.id) === option.value)?.systemKey || '';
      option.disabled = (transitionLocked && !['estimate_pending', 'estimate_approved'].includes(optionRole))
        || (optionRole === 'estimate_pending' && !entry.canManageEstimate);
    });
    estimateLock.hidden = !transitionLocked;
    const canEdit = Boolean(entry.canManageEstimate) && role === 'estimate_pending';
    const hasEstimate = Boolean(entry.estimateDuration);
    const amount = entry.estimateAmount
      ? (entry.estimateAmountMax && entry.estimateAmountMax !== entry.estimateAmount
        ? `${entry.estimateAmount}–${entry.estimateAmountMax} €`
        : `${entry.estimateAmount} €`)
      : t('ESTIMATE_NOT_ENTERED');
    estimateState.textContent = t(role === 'estimate_approved'
      ? 'ESTIMATE_APPROVED'
      : (role === 'estimate_pending' ? 'ESTIMATE_PENDING' : 'ESTIMATE_TITLE'));
    if (estimateFields) estimateFields.hidden = !canEdit;
    ['EstimatedCostDuration', 'EstimatedCostNote'].forEach((fieldName) => {
      if (editForm.elements[fieldName]) editForm.elements[fieldName].disabled = !canEdit;
    });
    if (estimateReadonly) {
      estimateReadonly.hidden = canEdit || !hasEstimate;
      estimateReadonly.open = role === 'estimate_pending';
    }
    if (estimateReadonlyDuration) estimateReadonlyDuration.textContent = hasEstimate ? entry.estimateDuration : '';
    if (estimateReadonlyAmount) estimateReadonlyAmount.textContent = hasEstimate ? amount : '';
    if (estimateReadonlyNote) {
      estimateReadonlyNote.textContent = entry.estimateNote || '';
      if (estimateReadonlyNoteRow) estimateReadonlyNoteRow.hidden = !entry.estimateNote;
    }
    if (estimateReadonlyHelp) {
      estimateReadonlyHelp.textContent = role === 'estimate_pending' ? t('ESTIMATE_REVIEW_HELP') : '';
      estimateReadonlyHelp.hidden = role !== 'estimate_pending';
    }
    if (canEdit) {
      editForm.elements.EstimatedCostDuration.value = entry.estimateDuration || '';
      editForm.elements.EstimatedCostNote.value = entry.estimateNote || '';
    }
    if (estimatePrice) {
      estimatePrice.textContent = canEdit
        ? (estimatePricePreview(editForm.elements.EstimatedCostDuration?.value || '') || t('ESTIMATE_PRICE_HINT'))
        : (hasEstimate ? amount : '');
    }
  };

  const estimatePricePreview = (text) => {
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

  let editReturnFocus = null;
  let listReturnFocus = null;
  let helpReturnFocus = null;
  const openEntry = (entry, trigger = document.activeElement) => {
    if (!entry || !editForm || !editModal) return;
    if (entry.isUnread) {
      const readData = new FormData();
      readData.set('SecurityID', widget.dataset.securityId);
      readData.set('EntryID', String(entry.id));
      fetch(widget.dataset.markEntryReadUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body: readData,
      }).then((response) => response.json()).then((result) => {
        if (!result.success) return;
        updateUnreadCount(result.unreadCount, result.unreadCommentCount);
        entry.isUnread = false;
        const card = listContainer.querySelector(`[data-entry-id="${entry.id}"]`);
        card?.querySelector('[data-entry-unread]')?.remove();
        if (card) delete card.dataset.entryUnread;
      }).catch(() => {});
    }
    activeEntry = entry;
    window.KWMashaFeedlyEntries.setActiveMarker(markers, entry.id);
    const editable = window.KWMashaFeedlyEntries.editableEntryData(entry);
    editForm.elements.EntryID.value = String(editable.id);
    if (commentForm?.elements.EntryID) commentForm.elements.EntryID.value = String(editable.id);
    editForm.elements.CategoryID.value = String(editable.categoryID);
    editForm.elements.PriorityID.value = String(editable.priorityID);
    if (editForm.elements.DueDate) editForm.elements.DueDate.value = editable.dueDate;
    renderEstimate(entry);
    editHeading.textContent = t('ENTRY_NUMBER', { id: editable.id });
    if (createdMeta && createdAvatar && createdBy && createdAt) {
      const creation = window.KWMashaFeedlyEntries.entryCreationMeta(entry);
      window.KWMashaFeedlyEntries.renderEntryCreatorAvatar(createdAvatar, creation, document);
      createdBy.textContent = t('ENTRY_REPORTED_BY', { author: creation.author });
      createdAt.textContent = creation.when;
      createdAt.dateTime = creation.dateTime;
      createdMeta.setAttribute('aria-label', `${createdBy.textContent} · ${creation.when}`.trim());
    }
    renderEditPriorityIcon(entry);
    window.KWMashaFeedlyEntries.renderRelationBadges(editRelations, entry.relations, document);
    window.KWMashaFeedlyEntries.renderLinks(editDescription, editable.description, document);
    renderAttachments(entry.attachments);
    window.KWMashaFeedlyEntries.renderEnvironment(editEnvironment, entry, document);
    if (editEnvironmentDetails) editEnvironmentDetails.hidden = editEnvironment.children.length === 0;
    if (relatedEntries) {
      const linkedIDs = new Set((entry.relations || []).filter((relation) => relation.direction !== 'incoming').map((relation) => Number(relation.id)));
      const fillRelationChoices = (choices) => relatedEntries.replaceChildren(...window.KWMashaFeedlyEntries.relatedEntryOptions(choices, entry)
        .map((candidate) => {
          const option = document.createElement('option');
          option.value = String(candidate.id);
          option.textContent = `#${candidate.id} · ${candidate.title || candidate.content || 'Eintrag'}`;
          option.dataset.search = `${candidate.title || ''} ${candidate.content || ''} ${candidate.categoryTitle || ''}`.toLocaleLowerCase('de');
          option.selected = linkedIDs.has(Number(candidate.id));
          return option;
        }));
      const localChoices = [...entries, ...(entry.relations || []).map((relation) => ({
        id: relation.id,
        title: relation.title,
        pageURL: relation.pageURL || entry.pageURL,
        categoryTitle: relation.categoryTitle,
      }))];
      fillRelationChoices(relationCandidatesLoaded ? relationCandidates : localChoices);
      if (relationType) relationType.value = entry.relations?.find((relation) => relation.direction !== 'incoming')?.type || 'related';
      if (relationSearch) relationSearch.value = '';
    }
    window.KWMashaFeedlyEntries.renderHistory(editHistory, entry.history || [], document);
    renderComments(entry.comments || []);
    editForm.querySelectorAll('[name="AssignedMemberIDs[]"]').forEach((field) => {
      field.checked = editable.assignedMemberIDs.map(String).includes(field.value);
    });
    editContext.textContent = editable.context;
    editStatus.textContent = '';
    editModal.hidden = false;
    editReturnFocus = trigger;
    editHeading?.focus?.();
    widget.setAttribute('data-edit-open', 'true');
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-opened'));
    const target = window.KWMashaFeedlyEntries.resolveTarget(entry, document);
    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  editForm?.elements.CategoryID?.addEventListener('change', () => renderEstimate(activeEntry));
  editForm?.elements.EstimatedCostDuration?.addEventListener('input', () => {
    if (!estimatePrice) return;
    estimatePrice.textContent = estimatePricePreview(editForm.elements.EstimatedCostDuration.value)
      || (Number(widget.dataset.estimateHourlyRate) > 0 ? t('ESTIMATE_PRICE_HINT') : t('ESTIMATE_RATE_REQUIRED'));
  });

  relatedEntries?.addEventListener('focus', async () => {
    if (relationCandidatesLoaded) return;
    const requestURL = new URL(widget.dataset.listUrl, window.location.href);
    requestURL.searchParams.set('mode', 'all');
    try {
      const response = await fetch(requestURL, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const result = await response.json();
      if (!response.ok || !result.success || !Array.isArray(result.entries)) return;
      relationCandidates = result.entries;
      relationCandidatesLoaded = true;
      if (activeEntry && relatedEntries) {
        const selectedIDs = new Set(Array.from(relatedEntries.options || []).filter((option) => option.selected).map((option) => Number(option.value)));
        relatedEntries.replaceChildren(...window.KWMashaFeedlyEntries.relatedEntryOptions(relationCandidates, activeEntry).map((candidate) => {
          const option = document.createElement('option');
          option.value = String(candidate.id);
          option.textContent = `#${candidate.id} · ${candidate.title || candidate.content || 'Eintrag'}`;
          option.dataset.search = `${candidate.title || ''} ${candidate.content || ''} ${candidate.categoryTitle || ''}`.toLocaleLowerCase('de');
          option.selected = selectedIDs.has(Number(candidate.id)) || (activeEntry.relations || []).some((relation) => relation.direction !== 'incoming' && Number(relation.id) === Number(candidate.id));
          return option;
        }));
      }
    } catch (_) { /* Die seitenlokale Auswahl bleibt bei einem Ladefehler benutzbar. */ }
  });
  relationSearch?.addEventListener('input', () => {
    const query = relationSearch.value.trim().toLocaleLowerCase('de');
    Array.from(relatedEntries?.options || []).forEach((option) => {
      option.hidden = query !== '' && !option.dataset.search.includes(query);
    });
  });

  const renderComments = (comments) => {
    if (!commentList) return;
    commentList.replaceChildren();
    comments.forEach((comment, index) => {
      const bubble = document.createElement('article');
      bubble.className = `kw-masha-feedly__comment${index % 2 ? ' is-right' : ' is-left'}`;
      const author = document.createElement('strong');
      author.textContent = comment.author || t('MEMBER_FALLBACK');
      const body = document.createElement('p');
      window.KWMashaFeedlyEntries.renderLinks(body, comment.text || '', document);
      const date = document.createElement('time');
      if (comment.created) date.dateTime = comment.created.replace(' ', 'T');
      date.textContent = comment.created || '';
      if (comment.edited) {
        const editedLabel = document.createElement('span');
        editedLabel.className = 'kw-masha-feedly__comment-edited';
        editedLabel.textContent = 'bearbeitet';
        editedLabel.setAttribute('aria-label', 'Kommentar wurde bearbeitet');
        date.append(editedLabel);
      }
      const reactions = document.createElement('div');
      window.KWMashaFeedlyEntries.renderCommentReactions(reactions, comment.reactions, document, async (emoji, button) => {
        button.disabled = true;
        try {
          const data = new FormData(commentForm);
          data.set('SecurityID', commentForm.dataset.securityId);
          data.set('EntryID', String(activeEntry.id));
          data.set('CommentID', String(comment.id));
          data.set('CommentAction', 'react');
          data.set('ReactionEmoji', emoji);
          const result = await postCommentData(data);
          comment.reactions = result.reactions;
          if (Array.isArray(result.history)) {
            activeEntry.history = result.history;
            window.KWMashaFeedlyEntries.renderHistory(editHistory, activeEntry.history, document);
          }
          renderComments(activeEntry.comments || []);
          commentStatus.textContent = t('COMMENT_REACTION_SAVED');
        } catch (error) {
          commentStatus.textContent = error.message || t('COMMENT_REACTION_ERROR');
          button.disabled = false;
        }
      });
      bubble.append(author, body, date, reactions);
      if (comment.canManage) {
        const actions = document.createElement('div');
        actions.className = 'kw-masha-feedly__comment-actions';
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = 'Bearbeiten';
        edit.setAttribute('aria-label', `Kommentar von ${comment.author} bearbeiten`);
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Löschen';
        remove.setAttribute('aria-label', `Kommentar von ${comment.author} löschen`);
        edit.addEventListener('click', () => {
          const editor = document.createElement('form');
          editor.className = 'kw-masha-feedly__comment-editor';
          editor.dataset.commentEditForm = 'true';
          const entryID = document.createElement('input');
          entryID.type = 'hidden';
          entryID.name = 'EntryID';
          entryID.value = String(activeEntry.id);
          const commentID = document.createElement('input');
          commentID.type = 'hidden';
          commentID.name = 'CommentID';
          commentID.value = String(comment.id);
          const textarea = document.createElement('textarea');
          textarea.name = 'CommentText';
          textarea.value = comment.text || '';
          textarea.rows = 3;
          textarea.maxLength = 5000;
          textarea.required = true;
          const save = document.createElement('button');
          save.type = 'submit';
          save.textContent = 'Änderungen speichern';
          const cancel = document.createElement('button');
          cancel.type = 'button';
          cancel.textContent = 'Abbrechen';
          cancel.addEventListener('click', () => renderComments(activeEntry.comments || []));
          const editorActions = document.createElement('div');
          editorActions.append(cancel, save);
          editor.append(entryID, commentID, textarea, editorActions);
          editor.addEventListener('submit', async (event) => {
            event.preventDefault();
            save.disabled = true;
            try {
              const data = new FormData(editor);
              data.set('SecurityID', commentForm.dataset.securityId);
              data.set('CommentAction', 'edit');
              const result = await postCommentData(data);
              activeEntry.comments = activeEntry.comments.map((item) => item.id === result.comment.id ? result.comment : item);
              renderComments(activeEntry.comments);
              if (Array.isArray(result.history)) {
                activeEntry.history = result.history;
                window.KWMashaFeedlyEntries.renderHistory(editHistory, activeEntry.history, document);
              }
            } catch (error) {
              commentStatus.textContent = error.message || t('COMMENT_UPDATE_ERROR');
              save.disabled = false;
            }
          });
          bubble.replaceChildren(author, editor, date);
          textarea.focus?.();
        });
        remove.addEventListener('click', async () => {
          if (typeof window.confirm === 'function' && !window.confirm('Diesen Kommentar wirklich löschen?')) return;
          remove.disabled = true;
          try {
            const data = new FormData(commentForm);
            data.set('SecurityID', commentForm.dataset.securityId);
            data.set('CommentAction', 'delete');
            data.set('CommentID', String(comment.id));
            const result = await postCommentData(data);
            activeEntry.comments = activeEntry.comments.filter((item) => item.id !== result.commentID);
            renderComments(activeEntry.comments);
            if (Array.isArray(result.history)) {
              activeEntry.history = result.history;
              window.KWMashaFeedlyEntries.renderHistory(editHistory, activeEntry.history, document);
            }
          } catch (error) {
            commentStatus.textContent = error.message || t('COMMENT_DELETE_ERROR');
            remove.disabled = false;
          }
        });
        actions.append(edit, remove);
        bubble.append(actions);
      }
      commentList.append(bubble);
    });
    commentCount.textContent = String(comments.length);
    commentList.scrollTop = commentList.scrollHeight;
  };

  const postCommentData = async (data) => {
    const response = await fetch(commentForm.dataset.commentUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, body: data });
    let result;
    try {
      result = await response.json();
    } catch (_) {
      throw new Error('Der Kommentar-Endpunkt hat keine gültige JSON-Antwort geliefert.');
    }
    if (!response.ok || !result.success) throw new Error(result.message || t('COMMENT_SAVE_ERROR'));
    return result;
  };

  let stopMarkerTracking = null;
  const clearMarkers = () => {
    stopMarkerTracking?.();
    stopMarkerTracking = null;
    window.KWMashaFeedlyEntries.setActiveMarker(markers);
    markers.forEach((marker) => marker.remove());
    markers.length = 0;
  };

  const repositionMarkers = () => markers.forEach((marker) => marker.reposition?.());

  const openList = (mode = 'page', trigger = document.activeElement) => {
    modeField.value = mode;
    savedViewSelect.value = '';
    deleteViewButton.disabled = true;
    listModal.hidden = false;
    listReturnFocus = trigger;
    listHeading?.focus?.();
    widget.setAttribute('data-list-open', 'true');
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-list-opened'));
    return Promise.all([loadEntries(mode), loadSavedViews()]).catch(() => {
      savedViewStatus.textContent = t('SAVED_VIEW_ERROR');
    });
  };

  const renderMarkers = (pageEntries) => {
    clearMarkers();
    if (!panelOpen) return;
    pageEntries.forEach((entry) => {
      if (entry.isClosed) return;
      if (!entry.selector) return;
      const target = window.KWMashaFeedlyEntries.resolveTarget(entry, document);
      if (!target || widget.contains(target)) return;
      const marker = window.KWMashaFeedlyEntries.createMarker(entry, markers.length, target, document, window, (event) => {
        event.preventDefault();
        event.stopPropagation();
        openEntry(entry, event.currentTarget);
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      });
      document.body.append(marker);
      markers.push(marker);
    });
    if (activeEntry) window.KWMashaFeedlyEntries.setActiveMarker(markers, activeEntry.id);
    stopMarkerTracking = window.KWMashaFeedlyEntries.trackMarkers(markers, window);
  };

  const activityBadge = () => {
    const badge = document.createElement('span');
    badge.className = 'kw-masha-feedly__entry-activity';
    badge.dataset.entryUnread = 'true';
    badge.setAttribute('aria-label', t('UNREAD_ACTIVITY'));
    const icon = widget.querySelector('[data-masha-feedly-open-news] svg');
    if (icon?.cloneNode) badge.append(icon.cloneNode(true));
    const label = document.createElement('span');
    label.className = 'kw-masha-feedly__sr-only';
    label.textContent = t('UNREAD_ACTIVITY');
    badge.append(label);
    return badge;
  };

  /** Aktualisiert Aktivitätsmarkierungen im geöffneten Listenpanel ohne die Liste neu aufzubauen. */
  const refreshListActivity = async () => {
    if (!panelOpen) return;
    const url = new URL(widget.dataset.listUrl, window.location.href);
    url.searchParams.set('mode', 'all');
    url.searchParams.set('PageURL', window.location.href);
    try {
      const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      if (!response.ok || !data.success) return;
      updateUnreadCount(data.unreadCount, data.unreadCommentCount);
      const latest = new Map((data.entries || []).map((entry) => [Number(entry.id), entry]));
      entries = entries.map((entry) => latest.get(Number(entry.id)) || entry);
      if (listModal.hidden) return;
      entries.forEach((entry) => {
        const card = listContainer.querySelector(`[data-entry-id="${entry.id}"]`);
        if (!card) return;
        const badge = card.querySelector('[data-entry-unread]');
        if (entry.isUnread && !badge) {
          card.dataset.entryUnread = 'true';
          card.prepend(activityBadge());
        } else if (!entry.isUnread && badge) {
          badge.remove();
          delete card.dataset.entryUnread;
        }
      });
    } catch (_) { /* Der Listeninhalt bleibt bei einem vorübergehenden Netzwerkfehler unverändert. */ }
  };

  const renderList = (data) => {
    categories = data.categories || categories;
    priorities = data.priorities || priorities;
    const currentCategory = categoryFilter.value;
    const currentPriority = priorityFilter.value;
    categoryFilter.replaceChildren(new Option(t('CATEGORY_ALL'), ''));
    categories.filter((category) => category.systemKey !== 'restricted_estimate')
      .forEach((category) => categoryFilter.append(new Option(category.title, String(category.id))));
    if ([...categoryFilter.options].some((option) => option.value === currentCategory)) categoryFilter.value = currentCategory;
    priorityFilter.replaceChildren(new Option(t('FILTER_ALL_PRIORITIES'), ''));
    priorities.forEach((priority) => priorityFilter.append(new Option(priority.title, String(priority.id))));
    if ([...priorityFilter.options].some((option) => option.value === currentPriority)) priorityFilter.value = currentPriority;
    modeField.value = data.mode;
    renderActiveFilters();
    const selectedPriority = priorities.find((priority) => String(priority.id) === String(priorityFilter.value));
    if (priorityFilterIcon) {
      priorityFilterIcon.innerHTML = window.KWMashaFeedlyEntries.priorityIconSVG(selectedPriority?.iconType === 'info' ? 'info' : 'warning');
      priorityFilterIcon.style.setProperty('--masha-feedly-priority-color', selectedPriority?.color || '#64748b');
    }
    sortOptions.forEach((item) => {
      const active = item.dataset.mashaFeedlySortOption === sorting.key;
      item.setAttribute('aria-pressed', String(active));
      const direction = item.querySelector('[data-sort-direction]');
      if (direction) direction.textContent = active ? (sorting.direction === 'asc' ? '↑' : '↓') : '';
    });
    const sortName = t(`SORT_${sorting.key.toUpperCase()}`);
    const directionName = t(sorting.direction === 'asc' ? 'SORT_ASCENDING' : 'SORT_DESCENDING');
    if (sortCurrent) sortCurrent.textContent = sorting.direction === 'asc' ? '↑' : '↓';
    if (sortLabel) sortLabel.textContent = `${t('SORTING_TITLE')}: ${sortName}, ${directionName}`;
    const sortSummary = sortDetails?.querySelector('summary');
    if (sortSummary && sortLabel) {
      sortSummary.setAttribute('aria-label', sortLabel.textContent);
      sortSummary.title = sortLabel.textContent;
    }

    const sorted = window.KWMashaFeedlyEntries.sortEntries(data.entries || [], categories, priorities, sorting);
    const modeEntries = ['open', 'page-open'].includes(data.mode)
      ? sorted.filter((entry) => !entry.isClosed)
      : (data.mode === 'closed' ? sorted.filter((entry) => entry.isClosed) : sorted);
    const visible = window.KWMashaFeedlyEntries.filterByPriority(
      window.KWMashaFeedlyEntries.filterByCategory(modeEntries, categoryFilter.value), priorityFilter.value,
    );
    entries = data.entries || [];
    loadedMode = data.mode;
    const modeLabel = ['estimate-pending', 'estimate-approved'].includes(data.mode)
      ? t(data.mode === 'estimate-pending' ? 'ESTIMATE_PENDING_BUTTON' : 'ESTIMATE_APPROVED_BUTTON')
      : (data.mode === 'unread' ? t('LIST_COUNT_UNREAD') : (data.mode === 'mine' ? t(formalAddress ? 'LIST_COUNT_MINE_SIE' : 'LIST_COUNT_MINE_DU') : t(data.mode === 'all' ? 'LIST_COUNT_ALL' : (data.mode === 'feedback' ? 'LIST_COUNT_FEEDBACK' : (data.mode === 'page-open' ? 'LIST_COUNT_PAGE_OPEN' : 'LIST_COUNT_PAGE')))));
    const closedCount = visible.filter((entry) => entry.isClosed).length;
    const openCount = visible.length - closedCount;
    listCount.textContent = data.mode === 'unread'
      ? `${visible.length} ${t(visible.length === 1 ? 'ENTRY_SINGULAR' : 'ENTRY_PLURAL')} ${modeLabel}`
      : (['estimate-pending', 'estimate-approved'].includes(data.mode)
      ? `${visible.length} ${t(visible.length === 1 ? 'ENTRY_SINGULAR' : 'ENTRY_PLURAL')} ${modeLabel}`
      : (data.mode === 'feedback'
      ? `${visible.length} ${t(visible.length === 1 ? 'ENTRY_SINGULAR' : 'ENTRY_PLURAL')} ${t('LIST_COUNT_FEEDBACK')}`
      : (['open', 'page-open'].includes(data.mode)
      ? `${visible.length} ${t(visible.length === 1 ? 'ENTRY_SINGULAR' : 'ENTRY_PLURAL')} ${t('LIST_COUNT_OPEN')}`
      : (data.mode === 'closed'
        ? `${visible.length} ${t(visible.length === 1 ? 'ENTRY_SINGULAR' : 'ENTRY_PLURAL')} ${t('LIST_COUNT_CLOSED')}`
        : `${openCount} ${t('LIST_COUNT_OPEN')} · ${closedCount} ${t('LIST_COUNT_CLOSED')} · ${visible.length} ${modeLabel}`))));
    listContainer.replaceChildren();
    if (!visible.length) {
      const empty = document.createElement('p');
      empty.className = 'kw-masha-feedly__entries-empty';
      empty.textContent = data.mode === 'mine'
        ? t(formalAddress ? 'EMPTY_MINE_SIE' : 'EMPTY_MINE_DU')
        : (['estimate-pending', 'estimate-approved'].includes(data.mode)
          ? t('EMPTY_ESTIMATE_QUEUE')
          : (data.mode === 'unread'
          ? t('NEWS_EMPTY')
          : (data.mode === 'open'
          ? t('EMPTY_OPEN')
          : (data.mode === 'closed' ? t('EMPTY_CLOSED') : t(data.mode === 'all' ? 'EMPTY_ALL' : 'EMPTY_PAGE')))));
      listContainer.append(empty);
      return;
    }

    let currentCategoryID = null;
    visible.forEach((entry) => {
      if (entry.categoryID !== currentCategoryID) {
        currentCategoryID = entry.categoryID;
        const heading = document.createElement('h3');
        heading.className = 'kw-masha-feedly__entries-category';
        heading.textContent = entry.categoryTitle || t('ENTRY_WITHOUT_CATEGORY');
        listContainer.append(heading);
      }
      const card = document.createElement('article');
      card.className = 'kw-masha-feedly__entry-card';
      card.dataset.entryId = String(entry.id);
      card.tabIndex = 0;
      card.setAttribute('role', 'button');
      card.setAttribute('aria-label', t('ENTRY_OPEN_ARIA', { title: entry.title || t('ENTRY_NUMBER', { id: entry.id }) }));
      if (entry.isUnread) {
        card.dataset.entryUnread = 'true';
      }
      card.dataset.hasAssignees = entry.assignees?.length ? 'true' : 'false';
      const title = document.createElement('h4');
      title.className = 'kw-masha-feedly__entry-title';
      const number = document.createElement('span');
      number.className = 'kw-masha-feedly__entry-number';
      number.textContent = `#${entry.id}`;
      const titleText = document.createElement('span');
      titleText.textContent = entry.title || t('ENTRY_WITHOUT_TITLE');
      title.append(number, titleText);
      const priority = document.createElement('span');
      priority.className = 'kw-masha-feedly__priority';
      priority.dataset.priorityId = String(entry.priorityID || '');
      const priorityTitle = entry.priorityTitle || '';
      priority.setAttribute('aria-label', priorityTitle);
      priority.title = priorityTitle;
      priority.dataset.iconType = entry.priorityIconType === 'info' ? 'info' : 'warning';
      priority.innerHTML = window.KWMashaFeedlyEntries.priorityIconSVG(priority.dataset.iconType);
      if (entry.priorityColor) priority.style.setProperty('--masha-feedly-priority-color', entry.priorityColor);
      const priorityLabel = document.createElement('span');
      priorityLabel.className = 'kw-masha-feedly__entry-priority-label';
      priorityLabel.textContent = priorityTitle || t('PRIORITY_FALLBACK');
      const priorityItem = document.createElement('span');
      priorityItem.className = 'kw-masha-feedly__entry-priority';
      priorityItem.title = priorityTitle;
      priorityItem.setAttribute('aria-label', t('ENTRY_PRIORITY_ARIA', { priority: priorityTitle || t('PRIORITY_FALLBACK') }));
      if (entry.priorityColor) priorityItem.style.setProperty('--masha-feedly-priority-color', entry.priorityColor);
      priorityItem.append(priority, priorityLabel);
      const metadata = document.createElement('div');
      metadata.className = 'kw-masha-feedly__entry-metadata';
      const status = document.createElement('span');
      status.className = 'kw-masha-feedly__entry-status';
      status.dataset.closed = String(Boolean(entry.isClosed));
      status.textContent = entry.categoryTitle || t('ENTRY_WITHOUT_CATEGORY');
      status.title = status.textContent;
      const estimateStatus = ['estimate_pending', 'estimate_approved'].includes(entry.categoryRole)
        ? document.createElement('span') : null;
      if (estimateStatus) {
        const isApproved = entry.categoryRole === 'estimate_approved';
        estimateStatus.className = `kw-masha-feedly__entry-estimate-status${isApproved ? ' is-approved' : ' is-pending'}`;
        estimateStatus.setAttribute('role', 'img');
        estimateStatus.setAttribute('aria-label', t(isApproved ? 'ESTIMATE_APPROVED' : 'ESTIMATE_PENDING'));
        estimateStatus.title = estimateStatus.getAttribute('aria-label');
        estimateStatus.innerHTML = isApproved
          ? '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.5 14.4 9.6 21.5 12l-7.1 2.4L12 21.5l-2.4-7.1L2.5 12l7.1-2.4z"/><path d="m8.2 12.2 2.5 2.5 5.2-5.2" class="check"/></svg>'
          : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
      }
      const actions = document.createElement('div');
      actions.className = 'kw-masha-feedly__entry-card-actions';
      const shareButton = document.createElement('button');
      shareButton.type = 'button';
      shareButton.className = 'kw-masha-feedly__entry-share';
      shareButton.dataset.entryShare = String(entry.id);
      shareButton.setAttribute('aria-label', t('ENTRY_SHARE_ARIA', { title: entry.title || t('ENTRY_WITHOUT_TITLE') }));
      shareButton.title = t('ENTRY_SHARE_ARIA', { title: entry.title || t('ENTRY_WITHOUT_TITLE') });
      shareButton.innerHTML = '<svg viewBox="-33 0 512 512" aria-hidden="true" focusable="false"><path d="m361.824219 344.394531c-24.53125 0-46.632813 10.59375-61.972657 27.445313l-137.972656-85.453125c3.683594-9.429688 5.726563-19.671875 5.726563-30.386719 0-10.71875-2.042969-20.960938-5.726563-30.386719l137.972656-85.457031c15.339844 16.851562 37.441407 27.449219 61.972657 27.449219 46.210937 0 83.804687-37.59375 83.804687-83.804688 0-46.210937-37.59375-83.800781-83.804687-83.800781-46.210938 0-83.804688 37.59375-83.804688 83.804688 0 10.714843 2.046875 20.957031 5.726563 30.386718l-137.96875 85.453125c-15.339844-16.851562-37.441406-27.449219-61.972656-27.449219-46.210938 0-83.804688 37.597657-83.804688 83.804688 0 46.210938 37.59375 83.804688 83.804688 83.804688 24.53125 0 46.632812-10.59375 61.972656-27.449219l137.96875 85.453125c-3.679688 9.429687-5.726563 19.671875-5.726563 30.390625 0 46.207031 37.59375 83.800781 83.804688 83.800781 46.210937 0 83.804687-37.59375 83.804687-83.800781 0-46.210938-37.59375-83.804688-83.804687-83.804688zm-53.246094-260.589843c0-29.359376 23.886719-53.246094 53.246094-53.246094s53.246093 23.886718 53.246093 53.246094-23.886718 53.246093-53.246093 53.246093-53.246094-23.886719-53.246094-53.246093zm-224.773437 225.441406c-29.363282 0-53.25-23.886719-53.25-53.246094s23.886718-53.246094 53.25-53.246094c29.359374 0 53.242187 23.886719 53.242187 53.246094s-23.882813 53.246094-53.242187 53.246094zm224.773437 118.949218c0-29.359374 23.886719-53.246093 53.246094-53.246093s53.246093 23.886719 53.246093 53.246093-23.886718 53.246094-53.246093 53.246094-53.246094-23.886718-53.246094-53.246094z"/></svg>';
      if (entry.isUnread) actions.append(activityBadge());
      actions.append(shareButton);
      metadata.append(status);
      if (estimateStatus) metadata.append(estimateStatus);
      metadata.append(priorityItem);
      if (entry.dueDate) {
        const dueDate = document.createElement('time');
        dueDate.className = 'kw-masha-feedly__entry-due-date';
        dueDate.dateTime = entry.dueDate;
        const [year, month, day] = entry.dueDate.split('-').map(Number);
        dueDate.textContent = t('ENTRY_DUE_DATE', { date: new Date(year, month - 1, day, 12).toLocaleDateString('de-DE') });
        metadata.append(dueDate);
      }
      card.append(title);
      if (entry.assignees?.length) {
        const assigned = document.createElement('div');
        window.KWMashaFeedlyEntries.renderAssignees(assigned, entry.assignees, document);
        metadata.append(assigned);
      }
      card.append(metadata, actions);
      const relations = document.createElement('div');
      window.KWMashaFeedlyEntries.renderRelationBadges(relations, entry.relations, document);
      if (relations.childNodes?.length || relations.children?.length) card.append(relations);
      listContainer.append(card);
    });
  };

  const loadEntries = async (mode = 'page') => {
    const requestID = ++listRequest;
    const url = new URL(widget.dataset.listUrl, window.location.href);
    url.searchParams.set('mode', mode);
    url.searchParams.set('PageURL', window.location.href);
    listCount.textContent = t('LIST_LOADING');
    try {
      const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || t('LIST_LOAD_ERROR'));
      if (requestID !== listRequest) return;
      updateUnreadCount(data.unreadCount, data.unreadCommentCount);
      const pageOpenCount = Number(data.pageOpenCount ?? data.pageCount ?? 0);
      const openCount = Number(data.openCount ?? data.totalCount ?? 0);
      const feedbackCount = Number(data.feedbackCount || 0);
      const closedCount = Math.max(0, Number(data.totalCount || 0) - openCount);
      pageCount.textContent = String(pageOpenCount);
      totalCount.textContent = String(openCount);
      if (closedCountDisplay) closedCountDisplay.textContent = String(closedCount);
      if (feedbackCountDisplay) feedbackCountDisplay.textContent = String(feedbackCount);
      if (feedbackButton) {
        feedbackButton.hidden = feedbackCount === 0;
        feedbackButton.setAttribute('data-tooltip', `${t('OPEN_FEEDBACK_ENTRIES')} · ${feedbackCount}`);
        feedbackButton.setAttribute('aria-label', `${t('OPEN_FEEDBACK_ENTRIES')} · ${feedbackCount}`);
      }
      const estimatePendingCount = Number(data.estimatePendingCount || 0);
      const estimateApprovedCount = Number(data.estimateApprovedCount || 0);
      if (estimatePendingCountDisplay) estimatePendingCountDisplay.textContent = String(estimatePendingCount);
      if (estimateApprovedCountDisplay) estimateApprovedCountDisplay.textContent = String(estimateApprovedCount);
      if (estimatePendingButton) {
        estimatePendingButton.hidden = !data.canApproveEstimate || estimatePendingCount === 0;
        estimatePendingButton.setAttribute('data-tooltip', `${t('OPEN_ESTIMATE_PENDING')} · ${estimatePendingCount}`);
        estimatePendingButton.setAttribute('aria-label', `${t('OPEN_ESTIMATE_PENDING')} · ${estimatePendingCount}`);
      }
      if (estimateApprovedButton) {
        estimateApprovedButton.hidden = !data.canApproveEstimate || estimateApprovedCount === 0;
        estimateApprovedButton.setAttribute('data-tooltip', `${t('OPEN_ESTIMATE_APPROVED')} · ${estimateApprovedCount}`);
        estimateApprovedButton.setAttribute('aria-label', `${t('OPEN_ESTIMATE_APPROVED')} · ${estimateApprovedCount}`);
      }
      openClosedButton?.setAttribute('data-tooltip', `${t('OPEN_CLOSED_ENTRIES')} · ${closedCount}`);
      openClosedButton?.setAttribute('aria-label', `${t('OPEN_CLOSED_ENTRIES')} · ${closedCount}`);
      if (rainbow) rainbow.hidden = pageOpenCount !== 0;
      widget.setAttribute('data-success-visible', String(pageOpenCount === 0));
      if (rainbow && pageOpenCount === 0) {
        const totalCountValue = Number(data.totalCount || 0);
        const seriousTheme = widget.dataset.theme === 'serious';
        if (openCount === 0 && totalCountValue === 0) {
          if (rainbowTitle) rainbowTitle.textContent = t(seriousTheme ? 'SUCCESS_EMPTY_BOARD_TITLE' : 'RAINBOW_EMPTY_BOARD_TITLE');
          if (rainbowMessage) rainbowMessage.textContent = t(seriousTheme ? 'SUCCESS_EMPTY_BOARD_MESSAGE' : 'RAINBOW_EMPTY_BOARD_MESSAGE');
        } else if (openCount === 0) {
          if (rainbowTitle) rainbowTitle.textContent = t(seriousTheme ? 'SUCCESS_BOARD_TITLE' : 'RAINBOW_BOARD_TITLE');
          if (rainbowMessage) rainbowMessage.textContent = t(seriousTheme ? 'SUCCESS_BOARD_MESSAGE' : 'RAINBOW_BOARD_MESSAGE');
        } else {
          if (rainbowTitle) rainbowTitle.textContent = t(seriousTheme ? 'SUCCESS_PAGE_TITLE' : 'RAINBOW_PAGE_TITLE');
          if (rainbowMessage) rainbowMessage.textContent = totalCountValue === 0
            ? t(seriousTheme ? 'SUCCESS_EMPTY_PAGE_MESSAGE' : 'RAINBOW_EMPTY_PAGE_MESSAGE')
            : t(seriousTheme ? 'SUCCESS_PAGE_MESSAGE' : 'RAINBOW_PAGE_MESSAGE');
        }
        const rainbowLabel = [rainbowTitle?.textContent, rainbowMessage?.textContent].filter(Boolean).join(' ');
        if (rainbowLabel) rainbow.setAttribute('aria-label', rainbowLabel);
      }
      if (mode === 'page') {
        renderMarkers(data.entries || []);
      }
      renderList(data);
      if (requestedEntryID && mode === 'page') {
        const requestedEntry = (data.entries || []).find((entry) => Number(entry.id) === Number(requestedEntryID));
        if (requestedEntry) {
          const panel = widget.querySelector('.kw-masha-feedly__panel');
          const toggle = widget.querySelector('.kw-masha-feedly__toggle');
          panel.hidden = false;
          toggle.setAttribute('aria-expanded', 'true');
          widget.setAttribute('data-panel-open', 'true');
          panelOpen = true;
          renderMarkers(data.entries || []);
          listModal.hidden = false;
          widget.setAttribute('data-list-open', 'true');
          openEntry(requestedEntry);
          const cleanURL = new URL(window.location.href);
          cleanURL.searchParams.delete('masha-feedly-entry');
          window.history.replaceState({}, '', `${cleanURL.pathname}${cleanURL.search}${cleanURL.hash}`);
        }
      }
    } catch (error) {
      if (requestID !== listRequest) return;
      listCount.textContent = error.message || t('LIST_LOAD_ERROR');
      listContainer.replaceChildren();
    }
  };

  document.addEventListener('kw-masha-feedly:opened', () => {
    panelOpen = true;
    return loadEntries('page');
  });
  document.addEventListener('kw-masha-feedly:closed', () => {
    panelOpen = false;
    clearMarkers();
    listModal.hidden = true;
    editModal.hidden = true;
    widget.setAttribute('data-list-open', 'false');
    widget.setAttribute('data-edit-open', 'false');
  });
  document.addEventListener('kw-masha-feedly:refresh', () => loadEntries('page'));
  openListButton?.addEventListener('click', (event) => openList('open', event?.currentTarget || openListButton));
  openNewsButton?.addEventListener('click', (event) => openList('unread', event?.currentTarget || openNewsButton));
  openClosedButton?.addEventListener('click', (event) => openList('closed', event?.currentTarget || openClosedButton));
  openPageListButton?.addEventListener('click', (event) => openList('page-open', event?.currentTarget || openPageListButton));
  feedbackButton?.addEventListener('click', (event) => openList('feedback', event?.currentTarget || feedbackButton));
  estimatePendingButton?.addEventListener('click', (event) => openList('estimate-pending', event?.currentTarget || estimatePendingButton));
  estimateApprovedButton?.addEventListener('click', (event) => openList('estimate-approved', event?.currentTarget || estimateApprovedButton));
  openHelpButton?.addEventListener('click', (event) => {
    helpReturnFocus = event?.currentTarget || openHelpButton;
    helpModal.hidden = false;
    helpHeading?.focus?.();
  });
  shareActiveEntryButton?.addEventListener('click', async (event) => {
    event.preventDefault();
    event.stopPropagation();
    await shareEntryLink(activeEntry, shareActiveEntryButton);
  });
  closeHelpButton?.addEventListener('click', () => { helpModal.hidden = true; helpReturnFocus?.focus?.(); });
  helpModal?.addEventListener('click', (event) => {
    if (event.target === helpModal) { helpModal.hidden = true; helpReturnFocus?.focus(); }
  });
  closeListButton?.addEventListener('click', () => {
    listModal.hidden = true;
    listReturnFocus?.focus?.();
    widget.setAttribute('data-list-open', 'false');
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-list-closed'));
  });
  listModal?.addEventListener('click', (event) => {
    if (event.target === listModal) {
      listModal.hidden = true;
      listReturnFocus?.focus?.();
      widget.setAttribute('data-list-open', 'false');
    }
  });
  modeField?.addEventListener('change', () => {
    savedViewSelect.value = '';
    deleteViewButton.disabled = true;
    return loadEntries(modeField.value);
  });
  categoryFilter?.addEventListener('change', () => {
    savedViewSelect.value = '';
    deleteViewButton.disabled = true;
    renderList({ mode: loadedMode, entries, categories });
  });
  priorityFilter?.addEventListener('change', () => {
    savedViewSelect.value = '';
    deleteViewButton.disabled = true;
    renderList({ mode: loadedMode, entries, categories, priorities });
  });
  sortOptions.forEach((option) => option.addEventListener('click', () => {
    const key = option.dataset.mashaFeedlySortOption;
    sorting = window.KWMashaFeedlyEntries.toggleSorting(sorting, key);
    renderList({ mode: loadedMode, entries, categories, priorities });
  }));
  sortDetails?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && sortDetails.open) {
      sortDetails.open = false;
      sortDetails.querySelector('summary')?.focus();
    }
  });
  savedViewSelect?.addEventListener('change', async () => {
    const view = savedViews.find((item) => String(item.id) === savedViewSelect.value);
    deleteViewButton.disabled = !view;
    if (!view) return;
    modeField.value = view.mode;
    categoryFilter.value = String(view.categoryID || '');
    priorityFilter.value = String(view.priorityID || '');
    await loadEntries(view.mode);
  });
  saveViewButton?.addEventListener('click', async () => {
    savedViewStatus.textContent = '';
    try {
      const result = await postSavedView(widget.dataset.saveViewUrl, {
        ViewAction: 'save',
        Title: savedViewName.value,
        Mode: modeField.value,
        CategoryID: categoryFilter.value || 0,
        PriorityID: priorityFilter.value || 0,
      });
      renderSavedViews(result.views);
      savedViewSelect.value = result.view.id;
      deleteViewButton.disabled = false;
      savedViewName.value = '';
      savedViewStatus.textContent = t('SAVED_VIEW_SAVED');
    } catch (_) {
      savedViewStatus.textContent = t('SAVED_VIEW_ERROR');
    }
  });
  deleteViewButton?.addEventListener('click', async () => {
    if (!savedViewSelect.value) return;
    savedViewStatus.textContent = '';
    try {
      const result = await postSavedView(widget.dataset.deleteViewUrl, { ViewAction: 'delete', ViewID: savedViewSelect.value });
      renderSavedViews(result.views);
      savedViewStatus.textContent = t('SAVED_VIEW_DELETED');
    } catch (_) {
      savedViewStatus.textContent = t('SAVED_VIEW_DELETE_ERROR');
    }
  });
  listContainer?.addEventListener('click', async (event) => {
    const card = event.target.closest('[data-entry-id]');
    const entry = entries.find((item) => Number(item.id) === Number(card?.dataset.entryId));
    if (!entry) return;
    const shareButton = event.target.closest('[data-entry-share]');
    if (shareButton) {
      event.preventDefault();
      event.stopPropagation();
      await shareEntryLink(entry, shareButton);
      return;
    }
    if (event.target.closest('a')) return;
    event.preventDefault();
    if (entry.pageURL && new URL(entry.pageURL, window.location.href).href.split(/[?#]/)[0] !== window.location.href.split(/[?#]/)[0]) {
      window.location.assign(window.KWMashaFeedlyEntries.entryTargetURL(entry, window.location.href));
      return;
    }
    openEntry(entry, card);
    const target = window.KWMashaFeedlyEntries.resolveTarget(entry, document);
    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  listContainer?.addEventListener('keydown', (event) => {
    if (!['Enter', ' '].includes(event.key)) return;
    const card = event.target.closest('.kw-masha-feedly__entry-card');
    if (!card || event.target !== card) return;
    event.preventDefault();
    card.click();
  });
  widget.querySelectorAll('[data-masha-feedly-close-edit]').forEach((button) => button.addEventListener('click', () => {
    editModal.hidden = true;
    window.KWMashaFeedlyEntries.setActiveMarker(markers);
    widget.setAttribute('data-edit-open', 'false');
    editReturnFocus?.focus?.();
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-closed'));
  }));
  editModal?.addEventListener('click', (event) => {
    if (event.target === editModal) {
      editModal.hidden = true;
      window.KWMashaFeedlyEntries.setActiveMarker(markers);
      widget.setAttribute('data-edit-open', 'false');
      editReturnFocus?.focus?.();
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-closed'));
    }
  });
  document.addEventListener('keydown', (event) => {
    const activeModal = !editModal?.hidden ? editModal.querySelector('.kw-masha-feedly__dialog')
      : (!helpModal?.hidden ? helpModal.querySelector('.kw-masha-feedly__dialog')
        : (!listModal?.hidden ? listModal.querySelector('.kw-masha-feedly__dialog') : null));
    if (!activeModal) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      if (!editModal.hidden) widget.querySelector('[data-masha-feedly-close-edit]')?.click();
      else if (!helpModal.hidden) closeHelpButton?.click();
      else closeListButton?.click();
      return;
    }
    window.KWMashaFeedlyEntries.trapFocus(activeModal, event);
  });
  editForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = editForm.querySelector('[type="submit"]');
    submit.disabled = true;
    editStatus.textContent = t('EDIT_SAVING');
    const data = new FormData(editForm);
    data.set('SecurityID', editForm.dataset.securityId);
    try {
      const response = await fetch(editForm.dataset.updateUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: data });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || t('EDIT_SAVE_ERROR'));
      editStatus.textContent = result.message;
      if (activeEntry) activeEntry.attachments = result.attachments || activeEntry.attachments || [];
      if (activeEntry && Object.hasOwn(result, 'dueDate')) {
        activeEntry.dueDate = result.dueDate || '';
        if (editForm.elements.DueDate) editForm.elements.DueDate.value = activeEntry.dueDate;
      }
      if (activeEntry && Object.hasOwn(result, 'estimateAmount')) {
        activeEntry = {
          ...activeEntry,
          estimateAmount: result.estimateAmount || '',
          estimateAmountMax: result.estimateAmountMax || result.estimateAmount || '',
          estimateDuration: result.estimateDuration || '',
          estimateCurrency: result.estimateCurrency || 'EUR',
          estimateNote: result.estimateNote || '',
          categoryRole: result.categoryRole || activeEntry.categoryRole,
        };
      }
      if (activeEntry && Array.isArray(result.relations)) activeEntry.relations = result.relations;
      window.KWMashaFeedlyEntries.renderRelationBadges(editRelations, activeEntry?.relations, document);
      if (activeEntry && result.priorityID) {
        activeEntry = {
          ...activeEntry,
          priorityID: Number(result.priorityID),
          priorityTitle: result.priorityTitle || '',
          priorityColor: result.priorityColor || '',
          priorityIconType: result.priorityIconType === 'info' ? 'info' : 'warning',
        };
        renderEditPriorityIcon(activeEntry);
      }
      if (activeEntry && Array.isArray(result.history)) {
        activeEntry.history = result.history;
        window.KWMashaFeedlyEntries.renderHistory(editHistory, activeEntry.history, document);
      }
      renderAttachments(activeEntry?.attachments);
      editForm.querySelectorAll('[name="Attachments[]"]').forEach((field) => { field.value = ''; });
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-entry-updated'));
      const requestedCategoryID = Number(editForm.elements.CategoryID.value);
      const nextCategoryID = Number(result.categoryID ?? requestedCategoryID);
      const listedCategory = categories.find((category) => Number(category.id) === nextCategoryID);
      const nextCategory = listedCategory
        ? {
          ...listedCategory,
          title: result.categoryTitle ?? listedCategory.title,
          isClosed: result.categoryIsClosed ?? listedCategory.isClosed,
        }
        : (result.categoryID ? {
          id: nextCategoryID,
          title: result.categoryTitle || '',
          isClosed: Boolean(result.categoryIsClosed),
        } : null);
      if (result.categoryID && nextCategory) editForm.elements.CategoryID.value = String(nextCategoryID);
      if (result.mitePrompt === true) {
        document.dispatchEvent(new CustomEvent('kw-masha-feedly:mite-prompt', {
          detail: { entryID: String(activeEntry?.id || editForm.elements.EntryID.value) },
        }));
      }
      if (result.celebrateCompletion === true && nextCategory?.isClosed && !activeEntry?.isClosed) {
        window.KWMashaFeedlyEntries.celebrateCompletion(document, window, widget.dataset.unicornUrl, widget.dataset.theme);
      }
      if (activeEntry && nextCategory) {
        activeEntry = { ...activeEntry, categoryID: nextCategoryID, categoryTitle: nextCategory.title, categoryRole: nextCategory.systemKey || result.categoryRole || '', isClosed: Boolean(nextCategory.isClosed) };
        editContext.textContent = t('ENTRY_CONTEXT_STATUS', { status: nextCategory.title });
      }
      renderEstimate(activeEntry);
      await loadEntries('page');
    } catch (error) {
      editStatus.textContent = error.message || t('EDIT_SAVE_ERROR');
    } finally {
      submit.disabled = false;
    }
  });
  commentForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const submit = commentForm.querySelector('[type="submit"]');
    submit.disabled = true;
    commentStatus.textContent = '';
    const data = new FormData(commentForm);
    data.set('SecurityID', commentForm.dataset.securityId);
    try {
      const result = await postCommentData(data);
      activeEntry.comments = [...(activeEntry.comments || []), result.comment];
      renderComments(activeEntry.comments);
      if (Array.isArray(result.history)) {
        activeEntry.history = result.history;
        window.KWMashaFeedlyEntries.renderHistory(editHistory, activeEntry.history, document);
      }
      commentForm.elements.CommentText.value = '';
      commentStatus.textContent = t('COMMENT_SAVED');
      document.dispatchEvent(new CustomEvent('kw-masha-feedly:onboarding-comment-saved'));
    } catch (error) {
      commentStatus.textContent = error.message || t('COMMENT_SAVE_ERROR');
    } finally {
      submit.disabled = false;
    }
  });
  if (requestedEntryID) {
    panelOpen = true;
    loadEntries('page');
  }
  window.addEventListener('scroll', () => {
    if (panelOpen) repositionMarkers();
  }, { passive: true });
  window.addEventListener('resize', () => {
    if (panelOpen) repositionMarkers();
  });
  window.setInterval(() => {
    if (panelOpen) refreshListActivity();
  }, 30000);
});
