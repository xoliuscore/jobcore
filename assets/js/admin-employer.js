/**
 * JobCore — employer logo picker (Media Library).
 */
(() => {
  'use strict';

  const L10N = window.wpjcEmployerAdmin || {};

  const paint = (box, id, src) => {
    const img = box.querySelector('img');
    box.querySelector('[data-wpjc-logo-id]').value = id ? String(id) : '';
    box.querySelector('[data-wpjc-logo-url]').value = '';
    img.src = src || '';
    img.hidden = !src;
    box.classList.toggle('has-logo', Boolean(src));
  };

  const init = (box) => {
    let frame = null;
    box.querySelectorAll('[data-wpjc-logo-select]').forEach((btn) => {
      btn.addEventListener('click', (ev) => {
        ev.preventDefault();
        if (!window.wp || !window.wp.media) {
          return;
        }
        if (!frame) {
          frame = window.wp.media({
            title: L10N.title,
            button: { text: L10N.button },
            library: { type: 'image' },
            multiple: false,
          });
          frame.on('open', () => {
            const id = parseInt(box.querySelector('[data-wpjc-logo-id]').value, 10);
            const selection = frame.state().get('selection');
            selection.reset(id ? [window.wp.media.attachment(id)] : []);
          });
          frame.on('select', () => {
            const file = frame.state().get('selection').first().toJSON();
            const sizes = file.sizes || {};
            const src = (sizes.medium || sizes.thumbnail || sizes.full || file).url;
            paint(box, file.id, src);
          });
        }
        frame.open();
      });
    });
    box.querySelector('[data-wpjc-logo-remove]').addEventListener('click', (ev) => {
      ev.preventDefault();
      paint(box, 0, '');
    });
  };

  document.querySelectorAll('[data-wpjc-logo-pick]').forEach(init);

  // The add-employer form is saved over AJAX and core only clears visible text fields.
  const list = document.getElementById('the-list');
  const addForm = document.getElementById('addtag');
  if (list && addForm) {
    new MutationObserver((changes) => {
      if (changes.some((c) => c.addedNodes.length)) {
        addForm.querySelectorAll('[data-wpjc-logo-pick]').forEach((box) => paint(box, 0, ''));
      }
    }).observe(list, { childList: true });
  }
})();
