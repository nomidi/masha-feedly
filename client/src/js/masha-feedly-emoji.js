(() => {
  let pickerNumber = 0;
  const categories = [
    ['SMILEYS', [['😀', 'lachen'], ['😃', 'freude'], ['😄', 'grinsen'], ['😁', 'lächeln'], ['😅', 'schwitzen'], ['😂', 'tränen lachen'], ['🤣', 'rofl'], ['🥲', 'gerührt'], ['😊', 'freundlich'], ['😇', 'engel'], ['🙂', 'lächeln'], ['🙃', 'umgedreht'], ['😉', 'zwinkern'], ['😍', 'liebe'], ['🥰', 'herz'], ['😘', 'kuss'], ['😋', 'lecker'], ['😎', 'cool'], ['🤓', 'nerd'], ['🧐', 'prüfen'], ['🤔', 'denken'], ['🤨', 'skeptisch'], ['😐', 'neutral'], ['😑', 'genervt'], ['🙄', 'augen rollen'], ['😏', 'frech'], ['😟', 'sorge'], ['🙁', 'traurig'], ['😮', 'überrascht'], ['😱', 'schock'], ['😴', 'schlafen'], ['🤒', 'krank'], ['🤕', 'verletzt'], ['🤢', 'übel'], ['🥳', 'party'], ['😭', 'weinen'], ['😡', 'wütend'], ['🤬', 'fluchen'], ['😈', 'teufel'], ['💀', 'tot'], ['🤡', 'clown'], ['👻', 'geist'], ['🤖', 'roboter'], ['💩', 'mist']]],
    ['PEOPLE', [['👋', 'winken'], ['🤚', 'hand'], ['✋', 'stopp'], ['🖐️', 'handfläche'], ['👌', 'ok'], ['🤌', 'gesten'], ['✌️', 'frieden'], ['🤞', 'daumen drücken'], ['🤟', 'liebe'], ['🤘', 'rock'], ['👉', 'rechts'], ['👆', 'oben'], ['👇', 'unten'], ['👍', 'daumen hoch'], ['👎', 'daumen runter'], ['👏', 'applaus'], ['🙌', 'jubel'], ['🫶', 'herz hände'], ['🙏', 'danke'], ['💪', 'stark'], ['🧠', 'gehirn'], ['👀', 'sehen'], ['👨‍💻', 'programmierer'], ['👩‍💻', 'programmiererin'], ['🧑‍🔧', 'reparatur'], ['🧑‍🚀', 'astronaut']]],
    ['NATURE', [['🐶', 'hund'], ['🐱', 'katze'], ['🐭', 'maus'], ['🐰', 'hase'], ['🦊', 'fuchs'], ['🐻', 'bär'], ['🐼', 'panda'], ['🐨', 'koala'], ['🐸', 'frosch'], ['🐵', 'affe'], ['🦄', 'einhorn'], ['🐝', 'biene'], ['🦋', 'schmetterling'], ['🐞', 'marienkäfer'], ['🌷', 'blume'], ['🌹', 'rose'], ['🌻', 'sonnenblume'], ['🌱', 'pflanze'], ['🌳', 'baum'], ['🍀', 'kleeblatt'], ['🍁', 'blatt'], ['🌈', 'regenbogen'], ['☀️', 'sonne'], ['🌤️', 'wetter'], ['🌧️', 'regen'], ['⚡', 'blitz'], ['❄️', 'schnee'], ['🔥', 'feuer'], ['💧', 'wasser'], ['🌊', 'welle']]],
    ['FOOD', [['🍎', 'apfel'], ['🍐', 'birne'], ['🍊', 'orange'], ['🍋', 'zitrone'], ['🍌', 'banane'], ['🍉', 'melone'], ['🍇', 'trauben'], ['🍓', 'erdbeere'], ['🫐', 'blaubeere'], ['🍒', 'kirsche'], ['🍑', 'pfirsich'], ['🥑', 'avocado'], ['🍕', 'pizza'], ['🍔', 'burger'], ['🍟', 'pommes'], ['🌮', 'taco'], ['🍰', 'kuchen'], ['🍪', 'keks'], ['🍩', 'donut'], ['☕', 'kaffee'], ['🍵', 'tee'], ['🥂', 'prost'], ['🍻', 'bier']]],
    ['ACTIVITY', [['🎉', 'party'], ['🎊', 'konfetti'], ['🎈', 'luftballon'], ['🎁', 'geschenk'], ['🏆', 'pokal'], ['🥇', 'medaille'], ['⚽', 'fußball'], ['🏀', 'basketball'], ['🎮', 'spiel'], ['🎲', 'würfel'], ['🎨', 'kunst'], ['🎤', 'mikrofon'], ['🎧', 'kopfhörer'], ['🎸', 'gitarre'], ['🚴', 'fahrrad'], ['🏃', 'laufen'], ['🧘', 'yoga'], ['🛠️', 'werkzeug'], ['🧩', 'puzzle'], ['🎯', 'ziel']]],
    ['PLACES', [['🚗', 'auto'], ['🚕', 'taxi'], ['🚌', 'bus'], ['🚲', 'fahrrad'], ['✈️', 'flugzeug'], ['🚀', 'rakete'], ['🚁', 'hubschrauber'], ['🚂', 'zug'], ['🚦', 'ampel'], ['🏠', 'haus'], ['🏢', 'gebäude'], ['🏖️', 'strand'], ['🏕️', 'camping'], ['⛰️', 'berg'], ['🌍', 'erde'], ['🌙', 'mond'], ['⭐', 'stern'], ['🌟', 'funkeln'], ['🪐', 'planet']]],
    ['OBJECTS', [['⌚', 'uhr'], ['📱', 'telefon'], ['💻', 'computer'], ['⌨️', 'tastatur'], ['🖱️', 'maus'], ['📷', 'kamera'], ['💡', 'idee'], ['🔦', 'taschenlampe'], ['📚', 'bücher'], ['📝', 'notiz'], ['📌', 'nadel'], ['📎', 'klammer'], ['🔒', 'schloss'], ['🔑', 'schlüssel'], ['🔧', 'schraubenschlüssel'], ['⚙️', 'zahnrad'], ['🧪', 'experiment'], ['🔬', 'mikroskop'], ['💊', 'tablette'], ['🛒', 'einkaufswagen']]],
    ['SYMBOLS', [['❤️', 'rotes herz'], ['🩷', 'rosa herz'], ['🧡', 'oranges herz'], ['💛', 'gelbes herz'], ['💚', 'grünes herz'], ['💙', 'blaues herz'], ['💜', 'lila herz'], ['🖤', 'schwarzes herz'], ['💔', 'gebrochenes herz'], ['💯', 'hundert'], ['💬', 'kommentar'], ['💭', 'gedanke'], ['✅', 'erledigt'], ['❌', 'kreuz'], ['⚠️', 'warnung'], ['❓', 'frage'], ['❗', 'ausrufezeichen'], ['➕', 'plus'], ['➖', 'minus'], ['🔄', 'aktualisieren'], ['🔔', 'glocke'], ['🔗', 'link'], ['💤', 'müde']]],
  ];

  const translate = (key, fallback) => {
    const translated = window.KWMashaFeedlyTranslate?.(key);
    return translated && translated !== key ? translated : fallback;
  };

  const decorate = (textarea) => {
    if (textarea.dataset.emojiReady || textarea.disabled || textarea.readOnly) return;
    const host = textarea.closest('[data-kw-masha-feedly]');
    if (!host || !['Content', 'CommentText'].includes(textarea.name)) return;
    textarea.dataset.emojiReady = 'true';
    // SilverStripe injects its translation map after external scripts have loaded.
    // Resolve labels here, when the widget textarea is decorated, not at file evaluation.
    const labels = {
      SMILEYS: translate('EMOJI_CATEGORY_SMILEYS', 'Smileys'), PEOPLE: translate('EMOJI_CATEGORY_PEOPLE', 'Menschen'),
      NATURE: translate('EMOJI_CATEGORY_NATURE', 'Natur'), FOOD: translate('EMOJI_CATEGORY_FOOD', 'Essen'),
      ACTIVITY: translate('EMOJI_CATEGORY_ACTIVITY', 'Aktivität'), PLACES: translate('EMOJI_CATEGORY_PLACES', 'Orte'),
      OBJECTS: translate('EMOJI_CATEGORY_OBJECTS', 'Objekte'), SYMBOLS: translate('EMOJI_CATEGORY_SYMBOLS', 'Symbole'),
    };
    const field = document.createElement('div');
    field.className = 'kw-masha-feedly__emoji-field';
    textarea.parentNode.insertBefore(field, textarea);
    field.append(textarea);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'kw-masha-feedly__emoji-toggle';
    toggle.setAttribute('data-masha-feedly-emoji-toggle', '');
    toggle.setAttribute('aria-label', translate('EMOJI_PICKER_OPEN', 'Emoji auswählen'));
    toggle.setAttribute('title', translate('EMOJI_PICKER_OPEN', 'Emoji auswählen'));
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-haspopup', 'dialog');
    toggle.innerHTML = '<svg viewBox="0 0 512 512" aria-hidden="true" focusable="false"><path d="M256 0C114.84 0 0 114.84 0 256s114.84 256 256 256 256-114.84 256-256S397.16 0 256 0zm0 480c-123.51 0-224-100.49-224-224S132.49 32 256 32s224 100.49 224 224-100.49 224-224 224zm134.86-146c-27.97 48.45-78.39 77.37-134.86 77.37S148.12 382.45 120.14 334c-4.42-7.65-1.8-17.44 5.85-21.86 7.65-4.42 17.44-1.8 21.86 5.85 22.19 38.43 62.25 61.37 107.15 61.37s84.95-22.94 107.14-61.37c4.42-7.65 14.2-10.27 21.86-5.86 7.65 4.42 10.27 14.2 5.85 21.86zM118.68 192.94c0-17.65 14.36-32.01 32.01-32.01s32.01 14.36 32.01 32.01-14.36 32.01-32.01 32.01-32.01-14.36-32.01-32.01zm274.64 0c0 17.65-14.36 32.01-32.01 32.01s-32.01-14.36-32.01-32.01 14.36-32.01 32.01-32.01 32.01 14.36 32.01 32.01z"/></svg>';
    const picker = document.createElement('div');
    picker.className = 'kw-masha-feedly__emoji-picker';
    picker.hidden = true;
    picker.id = `kw-masha-feedly-emoji-picker-${++pickerNumber}`;
    toggle.setAttribute('aria-controls', picker.id);
    picker.setAttribute('role', 'dialog');
    picker.setAttribute('aria-label', translate('EMOJI_PICKER_TITLE', 'Emoji auswählen'));
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'kw-masha-feedly__emoji-search';
    search.placeholder = translate('EMOJI_PICKER_SEARCH', 'Emoji suchen …');
    search.setAttribute('aria-label', search.placeholder);
    const tabs = document.createElement('div');
    tabs.className = 'kw-masha-feedly__emoji-categories';
    tabs.setAttribute('role', 'tablist');
    const grid = document.createElement('div');
    grid.className = 'kw-masha-feedly__emoji-grid';
    grid.setAttribute('role', 'listbox');
    picker.append(search, tabs, grid);
    field.append(toggle);
    (window.KWMashaFeedlyDOM?.overlayRoot() || document.body).append(picker);

    const positionPicker = () => {
      if (picker.hidden) return;
      const rect = toggle.getBoundingClientRect();
      const edge = 12;
      const gap = 10;
      const width = picker.offsetWidth;
      const height = picker.offsetHeight;
      const left = Math.min(Math.max(edge, rect.right - width), window.innerWidth - width - edge);
      const above = rect.top - height - gap;
      const top = above >= edge ? above : Math.min(rect.bottom + gap, window.innerHeight - height - edge);
      picker.style.left = `${left}px`;
      picker.style.top = `${Math.max(edge, top)}px`;
    };
    window.addEventListener('resize', positionPicker);
    document.addEventListener('scroll', positionPicker, true);
    window.KWMashaFeedlyDOM?.root().addEventListener('scroll', positionPicker, true);

    let selectedCategory = 'SMILEYS';
    const render = () => {
      const query = search.value.trim().toLocaleLowerCase();
      const filteredCategories = query ? categories : categories.filter(([name]) => name === selectedCategory);
      grid.replaceChildren();
      filteredCategories.forEach(([name, items]) => items.forEach(([emoji, description]) => {
        if (query && !`${emoji} ${description} ${labels[name]}`.toLocaleLowerCase().includes(query)) return;
        const choice = document.createElement('button');
        choice.type = 'button';
        choice.className = 'kw-masha-feedly__emoji-choice';
        choice.textContent = emoji;
        choice.title = `${emoji} · ${description}`;
        choice.setAttribute('role', 'option');
        choice.setAttribute('aria-label', description);
        choice.dataset.emoji = emoji;
        grid.append(choice);
      }));
      tabs.querySelectorAll('button').forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.category === selectedCategory)));
    };
    categories.forEach(([name]) => {
      const tab = document.createElement('button');
      tab.type = 'button';
      tab.textContent = labels[name];
      tab.dataset.category = name;
      tab.setAttribute('role', 'tab');
      tab.addEventListener('click', () => { selectedCategory = name; search.value = ''; render(); });
      tabs.append(tab);
    });
    search.addEventListener('input', render);
    toggle.addEventListener('click', () => {
      const isOpen = !picker.hidden;
      (window.KWMashaFeedlyDOM?.root() || document).querySelectorAll('.kw-masha-feedly__emoji-picker:not([hidden])').forEach((openPicker) => {
        openPicker.hidden = true;
        (window.KWMashaFeedlyDOM?.root() || document).querySelector(`[aria-controls="${openPicker.id}"]`)?.setAttribute('aria-expanded', 'false');
      });
      picker.hidden = isOpen;
      toggle.setAttribute('aria-expanded', String(!isOpen));
      if (!isOpen) { render(); positionPicker(); search.focus(); }
    });
    grid.addEventListener('click', (event) => {
      const choice = (window.KWMashaFeedlyDOM?.eventTarget(event) || event.target).closest('[data-emoji]');
      if (!choice) return;
      const start = textarea.selectionStart ?? textarea.value.length;
      const end = textarea.selectionEnd ?? start;
      textarea.setRangeText(choice.dataset.emoji, start, end, 'end');
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      picker.hidden = true;
      toggle.setAttribute('aria-expanded', 'false');
      textarea.focus();
    });
    picker.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        picker.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
    document.addEventListener('pointerdown', (event) => {
      if (!field.contains((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target)) && !picker.contains((window.KWMashaFeedlyDOM?.eventTarget(event) || event.target))) { picker.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
    });
  };

  const init = () => {
    const scan = (root) => {
      if (root.matches?.('textarea')) decorate(root);
      root.querySelectorAll?.('textarea[name="Content"],textarea[name="CommentText"]').forEach(decorate);
    };
    (window.KWMashaFeedlyDOM?.root() || document).querySelectorAll('[data-kw-masha-feedly]').forEach(scan);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
      if (node.nodeType === 1) scan(node);
    }))).observe(window.KWMashaFeedlyDOM?.overlayRoot() || document.body, { childList: true, subtree: true });
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
  else init();
})();
