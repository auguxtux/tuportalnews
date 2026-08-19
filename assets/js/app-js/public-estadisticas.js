(() => {
  "use strict";

  const root = document.documentElement;
  const body = document.body;
  const themeToggle = document.getElementById("themeToggle");
  const themeIcon = themeToggle?.querySelector(".theme-icon");
  const menuToggle = document.getElementById("menuToggle");
  const mainNav = document.getElementById("mainNav");
  const chapterMenuToggle = document.getElementById("chapterMenuToggle");
  const chapterMenu = document.getElementById("chapterMenu");
  const backToTop = document.getElementById("backToTop");
  const readingProgress = document.getElementById("readingProgress");
  const readingProgressText = document.getElementById("readingProgressText");

  const THEME_KEY = "recursos-didacticos-theme";

  function getPreferredTheme() {
    const savedTheme = localStorage.getItem(THEME_KEY);

    if (savedTheme === "light" || savedTheme === "dark") {
      return savedTheme;
    }

    return window.matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light";
  }

  function applyTheme(theme) {
    root.dataset.theme = theme;

    if (themeIcon) {
      themeIcon.textContent = theme === "dark" ? "☀" : "☾";
    }

    if (themeToggle) {
      const nextTheme = theme === "dark" ? "claro" : "oscuro";
      themeToggle.setAttribute("aria-label", `Activar modo ${nextTheme}`);
      themeToggle.setAttribute("title", `Activar modo ${nextTheme}`);
    }
  }

  applyTheme(getPreferredTheme());

  themeToggle?.addEventListener("click", () => {
    const currentTheme = root.dataset.theme === "dark" ? "dark" : "light";
    const nextTheme = currentTheme === "dark" ? "light" : "dark";

    localStorage.setItem(THEME_KEY, nextTheme);
    applyTheme(nextTheme);
  });

  function closeMainMenu() {
    if (!mainNav || !menuToggle) {
      return;
    }

    mainNav.classList.remove("open");
    menuToggle.setAttribute("aria-expanded", "false");
    body.classList.remove("menu-open");
  }

  menuToggle?.addEventListener("click", () => {
    if (!mainNav) {
      return;
    }

    const isOpen = mainNav.classList.toggle("open");
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    body.classList.toggle("menu-open", isOpen);
  });

  mainNav?.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", closeMainMenu);
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 940) {
      closeMainMenu();
    }
  });

  chapterMenuToggle?.addEventListener("click", () => {
    if (!chapterMenu) {
      return;
    }

    const collapsed = chapterMenu.classList.toggle("collapsed");
    chapterMenuToggle.setAttribute("aria-expanded", String(!collapsed));
  });

  backToTop?.addEventListener("click", () => {
    window.scrollTo({
      top: 0,
      behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ? "auto"
        : "smooth"
    });
  });

  function updateReadingProgress() {
    const documentElement = document.documentElement;
    const scrollable = documentElement.scrollHeight - window.innerHeight;

    const progress = scrollable > 0
      ? Math.min(100, Math.max(0, (window.scrollY / scrollable) * 100))
      : 0;

    if (readingProgress) {
      readingProgress.style.width = `${progress}%`;
    }

    if (readingProgressText) {
      readingProgressText.textContent = `${Math.round(progress)} % leído`;
    }
  }

  updateReadingProgress();

  window.addEventListener("scroll", updateReadingProgress, { passive: true });
  window.addEventListener("resize", updateReadingProgress);

  const topNavLinks = [
    ...document.querySelectorAll(".main-nav a")
  ];

  const sectionNavLinks = [
    ...document.querySelectorAll(".chapter-link[data-section]"),
    ...document.querySelectorAll(".sidebar-nav a[data-section]")
  ];

  const observedSections = [
    ...document.querySelectorAll(".section-chapter[id]")
  ];

  function markActiveSection(sectionId) {
    const activeHash = `#${sectionId}`;

    topNavLinks.forEach((link) => {
      link.classList.toggle(
        "active",
        link.getAttribute("href") === activeHash
      );
    });

    sectionNavLinks.forEach((link) => {
      link.classList.toggle(
        "active",
        link.getAttribute("href") === activeHash
      );
    });
  }

  if ("IntersectionObserver" in window && observedSections.length) {
    const observer = new IntersectionObserver(
      (entries) => {
        const visibleEntries = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

        if (!visibleEntries.length) {
          return;
        }

        markActiveSection(visibleEntries[0].target.id);
      },
      {
        rootMargin: "-22% 0px -62% 0px",
        threshold: [0.05, 0.15, 0.3, 0.5]
      }
    );

    observedSections.forEach((section) => observer.observe(section));
  }

  sectionNavLinks.forEach((link) => {
    link.addEventListener("click", () => {
      const sectionId = link.dataset.section;

      if (sectionId) {
        markActiveSection(sectionId);
      }
    });
  });
})();