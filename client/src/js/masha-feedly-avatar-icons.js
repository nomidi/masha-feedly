(() => {
  const rootSelector = '[data-masha-feedly-avatar-icons]';
  const domRoot = () => window.KWMashaFeedlyDOM?.root() || document;
  const eventTarget = (event) => window.KWMashaFeedlyDOM?.eventTarget(event) || event.target;

  function iconColorFor(value) {
    const color = /^#[\da-f]{6}$/i.test(value) ? value : '#F6B7A9';
    const channels = color.slice(1).match(/.{2}/g).map((channel) => parseInt(channel, 16) / 255);
    return (0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2]) > 0.52 ? 'black' : 'white';
  }

  function updateIconContrast(root) {
    const colorField = root.getRootNode().querySelector('[name="MashaFeedlyColor"]');
    const variant = iconColorFor(colorField?.value || '');
    root.dataset.iconColor = variant;
    root.querySelectorAll('[data-masha-feedly-avatar-icon-choice]').forEach((button) => {
      const image = button.querySelector('img');
      if (image) image.src = button.dataset[`icon${variant[0].toUpperCase()}${variant.slice(1)}`];
    });
  }

  document.addEventListener('click', (event) => {
    const target = eventTarget(event);
    const choice = target.closest('[data-masha-feedly-avatar-icon-choice]');
    const clear = target.closest('[data-masha-feedly-avatar-icon-clear]');
    const root = target.closest(rootSelector);
    if (!root || (!choice && !clear)) return;

    const hidden = root.closest('form')?.querySelector('[name="MashaFeedlyAvatarIcon"]');
    if (!hidden) return;
    hidden.value = choice?.dataset.iconId || '';
    root.querySelectorAll('[data-masha-feedly-avatar-icon-choice]').forEach((button) => {
      const selected = button === choice;
      button.classList.toggle('is-selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    root.querySelector('[data-masha-feedly-avatar-icon-clear]')?.setAttribute('aria-pressed', String(!choice));
  });

  document.addEventListener('DOMContentLoaded', () => {
    const root = domRoot();
    root.addEventListener('change', (event) => {
    if (event.target.name !== 'MashaFeedlyColor') return;
      root.querySelectorAll(rootSelector).forEach(updateIconContrast);
    });
    root.querySelectorAll(rootSelector).forEach(updateIconContrast);
  });
})();
