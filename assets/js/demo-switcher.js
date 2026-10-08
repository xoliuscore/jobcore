/* Demo look switcher (includes/demo-switcher.php): opens and closes the panel. */
(() => {
  "use strict";
  const tab = document.getElementById("wpjcDemoTab");
  const panel = document.getElementById("wpjcDemoPanel");
  if (!tab || !panel) {
    return;
  }

  const open = () => {
    panel.hidden = false;
    requestAnimationFrame(() => {
      panel.classList.add("is-open");
    });
    tab.setAttribute("aria-expanded", "true");
    const current = panel.querySelector(".wpjc-demo-card.is-current") || panel.querySelector(".wpjc-demo-card");
    if (current) {
      current.focus({ preventScroll: true });
    }
  };

  const close = () => {
    panel.classList.remove("is-open");
    tab.setAttribute("aria-expanded", "false");
    setTimeout(() => {
      if (!panel.classList.contains("is-open")) {
        panel.hidden = true;
      }
    }, 300);
    tab.focus({ preventScroll: true });
  };

  tab.addEventListener("click", () => {
    panel.classList.contains("is-open") ? close() : open();
  });
  panel.addEventListener("click", (e) => {
    if (e.target.closest("[data-wpjc-demo-close]")) {
      close();
    }
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && panel.classList.contains("is-open")) {
      close();
    }
  });
  document.addEventListener("click", (e) => {
    if (panel.classList.contains("is-open") && !panel.contains(e.target) && !tab.contains(e.target)) {
      close();
    }
  });
})();
