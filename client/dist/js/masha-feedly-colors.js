/** Übernimmt eine Avatarfarbe, wenn im Profil oder in der Konfiguration ein Farbfeld angeklickt wird. */
document.addEventListener('click', (event) => {
  const option = event.target.closest('[data-masha-feedly-color-option]');
  if (!option) return;

  const fieldName = option.dataset.fieldName;
  const colorField = [...document.querySelectorAll('input[name], select[name]')]
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
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-masha-feedly-color-option]').forEach((option) => {
    const fieldName = option.dataset.fieldName;
    const colorField = [...document.querySelectorAll('input[name], select[name]')]
      .find((field) => field.name === fieldName);
    const selected = colorField && colorField.value === option.dataset.color;
    option.classList.toggle('is-selected', Boolean(selected));
    option.setAttribute('aria-pressed', String(Boolean(selected)));
  });
});
