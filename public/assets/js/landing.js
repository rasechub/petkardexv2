/**
 * PetKardex v2 — Landing Page JavaScript
 * Features: Slider, CountUp, IntersectionObserver, Canvas Particles,
 *           Navbar shrink, Smooth scroll, Form validation, Lazy loading
 */

'use strict';

/* ============================================================
   1. DOM READY
   ============================================================ */
document.addEventListener('DOMContentLoaded', () => {
  initNavbar();
  initHeroSlider();
  initParticles();
  initIntersectionObserver();
  initCounters();
  initContactForm();
  initDynamicCopyright();
  initSmoothScroll();
});

/* ============================================================
   2. NAVBAR — Shrink + Active Link on Scroll
   ============================================================ */
function initNavbar() {
  const nav = document.getElementById('mainNav');
  if (!nav) return;

  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link-pk[href^="#"]');

  window.addEventListener('scroll', () => {
    // Shrink
    if (window.scrollY > 60) {
      nav.classList.add('scrolled');
    } else {
      nav.classList.remove('scrolled');
    }

    // Active section highlight
    let current = '';
    sections.forEach(section => {
      const sectionTop = section.offsetTop - 100;
      if (window.scrollY >= sectionTop) {
        current = section.getAttribute('id');
      }
    });

    navLinks.forEach(link => {
      link.classList.remove('active');
      if (link.getAttribute('href') === '#' + current) {
        link.classList.add('active');
      }
    });
  }, { passive: true });
}

/* ============================================================
   3. HERO SLIDER — Auto + Manual dots
   ============================================================ */
function initHeroSlider() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots   = document.querySelectorAll('.dot');
  if (!slides.length) return;

  let current  = 0;
  let interval = null;

  function goTo(index) {
    slides[current].classList.remove('active');
    dots[current] && dots[current].classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current] && dots[current].classList.add('active');
  }

  function startAuto() {
    interval = setInterval(() => goTo(current + 1), 5000);
  }

  function stopAuto() {
    clearInterval(interval);
  }

  dots.forEach((dot, i) => {
    dot.addEventListener('click', () => {
      stopAuto();
      goTo(i);
      startAuto();
    });
  });

  // Init first slide
  slides[0].classList.add('active');
  dots[0] && dots[0].classList.add('active');
  startAuto();
}

/* ============================================================
   4. CANVAS PARTICLES — Paws, Hearts, Stars
   ============================================================ */
function initParticles() {
  const canvas = document.getElementById('particleCanvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  let particles = [];
  let raf;

  function resize() {
    canvas.width  = canvas.offsetWidth;
    canvas.height = canvas.offsetHeight;
  }

  resize();
  window.addEventListener('resize', resize, { passive: true });

  const SYMBOLS = ['🐾', '❤️', '⭐', '✨', '🐕', '🐈'];
  const COUNT   = 28;

  function createParticle() {
    return {
      x:      Math.random() * canvas.width,
      y:      canvas.height + 30,
      vx:     (Math.random() - 0.5) * 0.6,
      vy:     -(Math.random() * 0.8 + 0.3),
      size:   Math.random() * 18 + 10,
      alpha:  Math.random() * 0.5 + 0.2,
      symbol: SYMBOLS[Math.floor(Math.random() * SYMBOLS.length)],
      rot:    Math.random() * Math.PI * 2,
      rotV:   (Math.random() - 0.5) * 0.02,
    };
  }

  for (let i = 0; i < COUNT; i++) {
    const p = createParticle();
    p.y = Math.random() * canvas.height; // distribute initially
    particles.push(p);
  }

  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    particles.forEach(p => {
      ctx.save();
      ctx.globalAlpha = p.alpha;
      ctx.font        = `${p.size}px serif`;
      ctx.textAlign   = 'center';
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.fillText(p.symbol, 0, 0);
      ctx.restore();

      p.x   += p.vx;
      p.y   += p.vy;
      p.rot += p.rotV;
      p.alpha *= 0.9995;

      if (p.y < -40 || p.alpha < 0.02) {
        Object.assign(p, createParticle());
      }
    });

    raf = requestAnimationFrame(draw);
  }

  draw();

  // Pause when hero not visible (perf)
  const heroObserver = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) {
      if (!raf) draw();
    } else {
      cancelAnimationFrame(raf);
      raf = null;
    }
  }, { threshold: 0.05 });

  const hero = document.getElementById('hero');
  if (hero) heroObserver.observe(hero);
}

/* ============================================================
   5. INTERSECTION OBSERVER — reveal + stat cards
   ============================================================ */
function initIntersectionObserver() {
  // Reveal animation
  const reveals = document.querySelectorAll('.reveal');
  const revealObs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        revealObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

  reveals.forEach(el => revealObs.observe(el));

  // Lazy sections
  const lazySections = document.querySelectorAll('section');
  const lazyObs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('section-loaded');
        lazyObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.05 });

  lazySections.forEach(s => lazyObs.observe(s));
}

/* ============================================================
   6. COUNTER ANIMATION — countUp on scroll
   ============================================================ */
function initCounters() {
  const counters = document.querySelectorAll('[data-count]');
  if (!counters.length) return;

  const counterObs = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        counterObs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(el => counterObs.observe(el));
}

function animateCounter(el) {
  const target   = parseInt(el.getAttribute('data-count'), 10);
  const duration = 2000; // ms
  const start    = performance.now();
  const suffix   = el.getAttribute('data-suffix') || '';

  function step(now) {
    const elapsed  = now - start;
    const progress = Math.min(elapsed / duration, 1);
    // Ease-out cubic
    const ease     = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.floor(ease * target).toLocaleString() + suffix;
    if (progress < 1) requestAnimationFrame(step);
  }

  requestAnimationFrame(step);
}

/* ============================================================
   7. CONTACT FORM — Validation + success message
   ============================================================ */
function initContactForm() {
  const form    = document.getElementById('contactForm');
  const success = document.getElementById('formSuccess');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();

    const name    = form.querySelector('[name="nombre"]').value.trim();
    const email   = form.querySelector('[name="email"]').value.trim();
    const message = form.querySelector('[name="mensaje"]').value.trim();
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Simple validation
    let valid = true;
    form.querySelectorAll('.form-control-pk').forEach(f => f.style.borderColor = '');

    if (!name) {
      form.querySelector('[name="nombre"]').style.borderColor = '#FF6B6B';
      valid = false;
    }
    if (!emailRe.test(email)) {
      form.querySelector('[name="email"]').style.borderColor = '#FF6B6B';
      valid = false;
    }
    if (!message || message.length < 10) {
      form.querySelector('[name="mensaje"]').style.borderColor = '#FF6B6B';
      valid = false;
    }

    if (!valid) return;

    // Success
    const btn = form.querySelector('[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Enviando…';

    setTimeout(() => {
      form.reset();
      btn.disabled    = false;
      btn.textContent = 'Enviar mensaje';
      if (success) {
        success.classList.add('show');
        setTimeout(() => success.classList.remove('show'), 5000);
      }
    }, 1200);
  });
}

/* ============================================================
   8. DYNAMIC COPYRIGHT
   ============================================================ */
function initDynamicCopyright() {
  const el = document.getElementById('copyrightYear');
  if (el) el.textContent = new Date().getFullYear();
}

/* ============================================================
   9. SMOOTH SCROLL (complementary to CSS scroll-behavior)
   ============================================================ */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (!target) return;
      e.preventDefault();

      // Close mobile nav if open
      const collapse = document.getElementById('navbarMain');
      if (collapse && collapse.classList.contains('show')) {
        const toggler = document.querySelector('.navbar-toggler');
        toggler && toggler.click();
      }

      const offset = 80; // navbar height
      const top    = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });
}
