/* =========================================================
   SCUPPET MEDIA — main.js
   Vanilla JS. No dependencies beyond Bootstrap's bundle.
   ========================================================= */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* ---------- Header/footer-dependent logic ----------
     header.html / footer.html are injected asynchronously by
     components/include.js, so anything that touches elements
     living inside #site-header or #site-footer waits for the
     "components:loaded" event it fires. */
  function initHeaderDependent() {
    var navbar = document.querySelector(".navbar-scuppet");
    var progressBar = document.querySelector(".progress-bar-scroll");

    function onScroll() {
      if (navbar) {
        if (window.scrollY > 24) {
          navbar.classList.add("is-scrolled");
        } else {
          navbar.classList.remove("is-scrolled");
        }
      }
      if (progressBar) {
        var doc = document.documentElement;
        var scrollTop = window.scrollY;
        var height = doc.scrollHeight - doc.clientHeight;
        var pct = height > 0 ? (scrollTop / height) * 100 : 0;
        progressBar.style.width = pct + "%";
      }
    }
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();

    /* ---------- Active nav link by current page ---------- */
    var path = window.location.pathname.split("/").pop() || "index.html";
    document.querySelectorAll(".nav-links a, .mobile-nav-links a").forEach(function (a) {
      var href = a.getAttribute("href");
      if (href === path || (path === "" && href === "index.html")) {
        a.classList.add("active");
      }
    });
  }

  if (document.querySelector("#site-header")) {
    document.addEventListener("components:loaded", initHeaderDependent);
  } else {
    // Fallback: header markup already inline on the page (no #site-header mount).
    initHeaderDependent();
  }

  /* ---------- Smooth scroll for on-page anchors ---------- */
  document.querySelectorAll('a[href^="#"]').forEach(function (a) {
    a.addEventListener("click", function (e) {
      var id = a.getAttribute("href");
      if (id.length > 1) {
        var target = document.querySelector(id);
        if (target) {
          e.preventDefault();
          target.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        }
      }
    });
  });

  /* ---------- Scroll reveal ---------- */
  var revealEls = document.querySelectorAll(".reveal, .reveal-stagger, .process-track");
  if ("IntersectionObserver" in window && !reduceMotion) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -60px 0px" }
    );
    revealEls.forEach(function (el) {
      io.observe(el);
    });
  } else {
    revealEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  /* ---------- Hero entrance (single orchestrated sequence) ---------- */
  window.addEventListener("DOMContentLoaded", function () {
    var hero = document.querySelector(".hero");
    if (hero) {
      requestAnimationFrame(function () {
        hero.querySelectorAll(".reveal, .reveal-stagger").forEach(function (el, i) {
          setTimeout(function () {
            el.classList.add("is-visible");
          }, reduceMotion ? 0 : 80 * i);
        });
      });
    }
  });

  /* ---------- Testimonial carousel (custom, home page) ---------- */
  var testiShell = document.querySelector("[data-testimonials]");
  if (testiShell) {
    var slides = testiShell.querySelectorAll(".testi-slide");
    var dotsWrap = testiShell.querySelector(".testi-dots");
    var prevBtn = testiShell.querySelector("[data-testi-prev]");
    var nextBtn = testiShell.querySelector("[data-testi-next]");
    var current = 0;
    var timer = null;

    slides.forEach(function (_, i) {
      var dot = document.createElement("button");
      dot.type = "button";
      dot.setAttribute("aria-label", "Show testimonial " + (i + 1));
      if (i === 0) dot.classList.add("is-active");
      dot.addEventListener("click", function () {
        goTo(i);
        restart();
      });
      dotsWrap.appendChild(dot);
    });

    function goTo(i) {
      slides[current].classList.remove("is-active");
      dotsWrap.children[current].classList.remove("is-active");
      current = (i + slides.length) % slides.length;
      slides[current].classList.add("is-active");
      dotsWrap.children[current].classList.add("is-active");
    }

    function restart() {
      if (timer) clearInterval(timer);
      if (!reduceMotion) {
        timer = setInterval(function () {
          goTo(current + 1);
        }, 6500);
      }
    }

    if (prevBtn) prevBtn.addEventListener("click", function () { goTo(current - 1); restart(); });
    if (nextBtn) nextBtn.addEventListener("click", function () { goTo(current + 1); restart(); });

    restart();
  }

  /* ---------- Contact form validation ---------- */
  var contactForm = document.querySelector("[data-contact-form]");
  if (contactForm) {
    var successBox = document.querySelector("[data-form-success]");
    var serviceSelect = contactForm.querySelector("#cf-service");
    var otherServiceWrap = contactForm.querySelector("[data-other-service-wrap]");
    var otherServiceInput = contactForm.querySelector("#cf-service-other");

    var validators = {
      name: function (v) { return v.trim().length >= 2; },
      email: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); },
      phone: function (v) { return v.trim() === "" || /^[\d\s()+-]{7,}$/.test(v.trim()); },
      company: function () { return true; },
      service: function (v) { return v.trim() !== ""; },
      serviceOther: function (v) {
        if (serviceSelect && serviceSelect.value === "Other") { return v.trim().length > 0; }
        return true;
      },
      message: function (v) { return v.trim().length >= 10; }
    };

    function syncOtherServiceField() {
      if (!serviceSelect || !otherServiceWrap || !otherServiceInput) return;
      var isOther = serviceSelect.value === "Other";
      otherServiceWrap.classList.toggle("is-visible", isOther);
      if (!isOther) {
        otherServiceInput.value = "";
        otherServiceInput.classList.remove("is-invalid");
      }
    }

    if (serviceSelect) {
      serviceSelect.addEventListener("change", syncOtherServiceField);
      syncOtherServiceField();
    }

    function validateField(field) {
      var name = field.name;
      var validator = validators[name];
      if (!validator) return true;
      var valid = validator(field.value);
      field.classList.toggle("is-invalid", !valid);
      return valid;
    }

    contactForm.querySelectorAll("input, select, textarea").forEach(function (field) {
      field.addEventListener("blur", function () { validateField(field); });
      field.addEventListener("input", function () {
        if (field.classList.contains("is-invalid")) validateField(field);
      });
    });

    contactForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var fields = contactForm.querySelectorAll("input, select, textarea");
      var allValid = true;
      fields.forEach(function (field) {
        if (!validateField(field)) allValid = false;
      });

      if (allValid) {
        contactForm.reset();
        fields.forEach(function (f) { f.classList.remove("is-invalid"); });
        syncOtherServiceField();
        if (successBox) {
          successBox.classList.add("is-visible");
          successBox.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "center" });
          successBox.setAttribute("tabindex", "-1");
          successBox.focus();
        }
      } else {
        var firstInvalid = contactForm.querySelector(".is-invalid");
        if (firstInvalid) firstInvalid.focus();
      }
    });
  }

  /* ---------- Subtle cursor interaction (desktop only, pointer:fine) ---------- */
  if (window.matchMedia("(pointer:fine)").matches && !reduceMotion) {
    var dot = document.createElement("div");
    dot.className = "cursor-dot";
    document.body.appendChild(dot);
    var active = false;

    document.addEventListener("mousemove", function (e) {
      dot.style.left = e.clientX + "px";
      dot.style.top = e.clientY + "px";
      if (!active) {
        active = true;
        dot.classList.add("is-active");
      }
    });
    document.addEventListener("mouseleave", function () {
      dot.classList.remove("is-active");
      active = false;
    });

    document.querySelectorAll("a, button, .service-block, .audience-item").forEach(function (el) {
      el.addEventListener("mouseenter", function () {
        dot.style.transform = "translate(-50%,-50%) scale(2.4)";
      });
      el.addEventListener("mouseleave", function () {
        dot.style.transform = "translate(-50%,-50%) scale(1)";
      });
    });
  }

  /* ---------- Subtle hero parallax on scroll ---------- */
  var heroEl = document.querySelector(".hero");
  if (heroEl && !reduceMotion) {
    window.addEventListener(
      "scroll",
      function () {
        var y = window.scrollY;
        if (y < window.innerHeight) {
          heroEl.style.backgroundPositionY = (50 + y * 0.02) + "%";
        }
      },
      { passive: true }
    );
  }
})();
