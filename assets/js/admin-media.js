/**
 * JobCore — image URL settings with a Media Library picker.
 */
(() => {
  'use strict';

  const L10N = window.wpjcAdminMedia || {};

  document.querySelectorAll('[data-wpjc-media]').forEach((box) => {
    const input = box.querySelector('[data-wpjc-media-url]');
    const img = box.querySelector('img');
    let frame = null;

    const paint = () => {
      const src = input.value.trim();
      img.src = src;
      img.hidden = !src;
      box.classList.toggle('has-image', Boolean(src));
    };

    box.querySelectorAll('[data-wpjc-media-select]').forEach((btn) => {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (!window.wp || !window.wp.media) {
          input.focus();
          return;
        }
        if (!frame) {
          frame = window.wp.media({
            title: L10N.title,
            button: { text: L10N.button },
            library: { type: 'image' },
            multiple: false,
          });
          frame.on('select', () => {
            const file = frame.state().get('selection').first().toJSON();
            input.value = file.url;
            paint();
          });
        }
        frame.open();
      });
    });

    box.querySelector('[data-wpjc-media-remove]').addEventListener('click', (ev) => {
      ev.preventDefault();
      input.value = '';
      paint();
    });

    input.addEventListener('change', paint);
    img.addEventListener('error', () => {
      img.hidden = true;
      box.classList.remove('has-image');
    });
  });
})();
