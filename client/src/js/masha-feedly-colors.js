/** Übernimmt eine Avatarfarbe in CMS-Formularen und im isolierten Widget. */
const mashaFeedlyColorRoot = () => window.KWMashaFeedlyDOM?.root() || document;
const mashaFeedlyColorTarget = (event) => window.KWMashaFeedlyDOM?.eventTarget(event) || event.target;

mashaFeedlyColorRoot().addEventListener('click', (event) => {
  const option = mashaFeedlyColorTarget(event).closest('[data-masha-feedly-color-option]');
  if (!option) return;

  const fieldName = option.dataset.fieldName;
  const colorField = [...mashaFeedlyColorRoot().querySelectorAll('input[name], select[name]')]
    .find((field) => field.name === fieldName);
  if (!colorField) return;

  colorField.value = option.dataset.color;
  colorField.dispatchEvent(new Event('change', { bubbles: true }));
  option.closest('.masha-feedly-color-palette')
    ?.querySelectorAll('[data-masha-feedly-color-option]')
    .forEach((item) => {
      const selected = item === option;
      item.classList.toggle('is-selected', selected);
      item.setAttribute('aria-pressed', String(selected));
  });
});

/** Markiert beim Laden den Farbton, der aktuell im verborgenen Formularfeld gespeichert ist. */
document.addEventListener('DOMContentLoaded', () => mashaFeedlyColorRoot().querySelectorAll('[data-masha-feedly-color-option]').forEach((option) => {
    const fieldName = option.dataset.fieldName;
    const colorField = [...mashaFeedlyColorRoot().querySelectorAll('input[name], select[name]')]
      .find((field) => field.name === fieldName);
    const selected = colorField && colorField.value === option.dataset.color;
    option.classList.toggle('is-selected', Boolean(selected));
    option.setAttribute('aria-pressed', String(Boolean(selected)));
}));
