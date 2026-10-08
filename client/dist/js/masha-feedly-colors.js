/** Übernimmt eine Avatarfarbe in CMS-Formularen und im isolierten Widget. */
const mashaFeedlyColorRoot = () => window.KWMashaFeedlyDOM?.root() || document;
const mashaFeedlyColorTarget = (event) => window.KWMashaFeedlyDOM?.eventTarget(event) || event.target;

/** CMS-Profil und Widget besitzen gleichnamige Felder; jede Palette gehört ausschließlich zu ihrem Formular. */
const mashaFeedlyColorFieldFor = (option) => {
  const scope = option.closest('form') || option.getRootNode();
  return [...scope.querySelectorAll('input[name], select[name]')]
    .find((field) => field.name === option.dataset.fieldName);
};

document.addEventListener('click', (event) => {
  const option = mashaFeedlyColorTarget(event).closest('[data-masha-feedly-color-option]');
  if (!option) return;

  const colorField = mashaFeedlyColorFieldFor(option);
  if (!colorField) return;

  colorField.value = option.dataset.color;
  const preview = option.closest('form')?.querySelector('[data-masha-feedly-color-preview]');
  if (preview) preview.style.backgroundColor = option.dataset.color || '#F4D06F';
  colorField.dispatchEvent(new Event('change', { bubbles: true, composed: true }));
  option.closest('.masha-feedly-color-palette')
    ?.querySelectorAll('[data-masha-feedly-color-option]')
    .forEach((item) => {
      const selected = item === option;
      item.classList.toggle('is-selected', selected);
      item.setAttribute('aria-pressed', String(selected));
  });
});

/** Markiert beim Laden den Farbton, der aktuell im verborgenen Formularfeld gespeichert ist. */
document.addEventListener('DOMContentLoaded', () => {
  const scopes = new Set([document, mashaFeedlyColorRoot()]);
  scopes.forEach((scope) => scope.querySelectorAll('[data-masha-feedly-color-option]').forEach((option) => {
    const colorField = mashaFeedlyColorFieldFor(option);
    const selected = colorField && colorField.value === option.dataset.color;
    option.classList.toggle('is-selected', Boolean(selected));
    option.setAttribute('aria-pressed', String(Boolean(selected)));
  }));
});
