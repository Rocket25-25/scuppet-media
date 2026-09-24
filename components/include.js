/* =========================================================
   SCUPPET MEDIA — components/include.js
   Loads the shared header and footer partials into every
   page, then tells main.js it's safe to run header-dependent
   logic (sticky nav, progress bar, active link, offcanvas).

   NOTE: this uses fetch() to pull in components/header.html
   and components/footer.html, so pages must be served over
   http:// (Live Server, `python3 -m http.server`, etc.).
   Opening a file directly via file:// will block the fetch
   in most browsers.
   ========================================================= */
(function () {
  "use strict";

  function loadComponent(mountSelector, url) {
    var mount = document.querySelector(mountSelector);
    if (!mount) return Promise.resolve();

    return fetch(url)
      .then(function (res) {
        if (!res.ok) throw new Error("Could not load " + url + " (" + res.status + ")");
        return res.text();
      })
      .then(function (html) {
        mount.innerHTML = html;
      })
      .catch(function (err) {
        console.error("[include.js]", err);
      });
  }

  document.addEventListener("DOMContentLoaded", function () {
    Promise.all([
      loadComponent("#site-header", "components/header.html"),
      loadComponent("#site-footer", "components/footer.html")
    ]).then(function () {
      document.dispatchEvent(new Event("components:loaded"));
    });
  });
})();
