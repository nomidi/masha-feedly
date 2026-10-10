/**
 * Desktop-FAQ: durchsucht die lokal gerenderten Antworten ohne Serverabruf.
 * Native Details-Elemente bewahren Tastaturbedienung und den aufgeklappten Zustand.
 */
document.addEventListener('DOMContentLoaded', () => {
  const widget = window.KWMashaFeedlyDOM.widget();
  const modal = widget?.querySelector('[data-masha-feedly-faq-modal]');
  const openers = Array.from(widget?.querySelectorAll('[data-masha-feedly-open-faq]') || []);
  const opener = openers[0];
  if (!modal || !opener) return;

  const dialog = modal.querySelector('[role="dialog"]');
  const heading = modal.querySelector('h2');
  const search = modal.querySelector('[data-masha-feedly-faq-search]');
  const count = modal.querySelector('[data-masha-feedly-faq-count]');
  const empty = modal.querySelector('[data-masha-feedly-faq-empty]');
  const topics = Array.from(modal.querySelectorAll('[data-masha-feedly-faq-topic]'));
  const groups = Array.from(modal.querySelectorAll('[data-masha-feedly-faq-group]'));
  const items = Array.from(modal.querySelectorAll('[data-masha-feedly-faq-item]'));
  const content = modal.querySelector('[data-masha-feedly-faq-content]');
  const createEntryButton = modal.querySelector('[data-masha-feedly-faq-create-entry]');
  let selectedTopic = 'all';
  let returnFocus = opener;

  /** Ignoriert Großschreibung und Akzente bei der Suche nach Fragen und Antworten. */
  const normalise = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
  const searchableItems = items.map(element => ({ element, text: normalise(element.textContent) }));
  const isCompact = () => getComputedStyle(widget).getPropertyValue('--masha-mobile-layout').trim() === '1';

  /** Kombiniert den Themenfilter mit allen eingegebenen Suchwörtern. */
  const filterAnswers = () => {
    const words = normalise(search.value.trim()).split(/\s+/).filter(Boolean);
    let visibleCount = 0;
    searchableItems.forEach(({ element, text }) => {
      const topic = element.closest('[data-masha-feedly-faq-group]').dataset.mashaFeedlyFaqGroup;
      element.hidden = (selectedTopic !== 'all' && topic !== selectedTopic) || !words.every(word => text.includes(word));
      if (!element.hidden) visibleCount++;
    });
    groups.forEach(group => {
      group.hidden = !Array.from(group.querySelectorAll('[data-masha-feedly-faq-item]')).some(item => !item.hidden);
    });
    count.textContent = count.dataset.countLabel.replace('{count}', String(visibleCount));
    empty.hidden = visibleCount !== 0;
    topics.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mashaFeedlyFaqTopic === selectedTopic)));
    content.scrollTop = 0;
  };

  const close = (restoreFocus = true) => {
    modal.hidden = true;
    openers.forEach(button => button.setAttribute('aria-expanded', 'false'));
    if (restoreFocus) (isCompact() ? widget.querySelector('.kw-masha-feedly__toggle') : returnFocus)?.focus();
  };
  openers.forEach(button => button.addEventListener('click', () => {
    if (isCompact()) return;
    returnFocus = button;
    filterAnswers();
    modal.hidden = false;
    openers.forEach(trigger => trigger.setAttribute('aria-expanded', 'true'));
    heading.focus();
  }));
  modal.querySelector('[data-masha-feedly-close-faq]').addEventListener('click', () => close());
  modal.addEventListener('click', event => { if (event.target === modal) close(); });
  search.addEventListener('input', filterAnswers);
  topics.forEach(button => button.addEventListener('click', () => {
    selectedTopic = button.dataset.mashaFeedlyFaqTopic;
    filterAnswers();
  }));
  modal.querySelector('[data-masha-feedly-faq-reset]').addEventListener('click', () => {
    search.value = '';
    selectedTopic = 'all';
    filterAnswers();
    search.focus();
  });
  createEntryButton?.addEventListener('click', () => {
    const question = search.value.trim();
    if (!question) return;
    close(false);
    document.dispatchEvent(new CustomEvent('kw-masha-feedly:create-from-faq', { detail: { question } }));
  });

  // Die FAQ liegt vor anderen Fenstern. Deren Tastaturhandler dürfen nicht mitreagieren.
  document.addEventListener('keydown', event => {
    if (modal.hidden || !['Escape', 'Tab'].includes(event.key)) return;
    event.stopImmediatePropagation();
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
      return;
    }
    const focusable = Array.from(dialog.querySelectorAll('button,input,summary,a[href],[tabindex="0"]'))
      .filter(element => !element.disabled && element.getClientRects().length);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const active = window.KWMashaFeedlyDOM.activeElement();
    if (event.shiftKey && (active === first || !focusable.includes(active))) {
      event.preventDefault();
      last?.focus();
    } else if (!event.shiftKey && (active === last || !focusable.includes(active))) {
      event.preventDefault();
      first?.focus();
    }
  }, true);
  document.addEventListener('kw-masha-feedly:closed', () => close(false));
  window.addEventListener('resize', () => { if (!modal.hidden && isCompact()) close(); });
});
