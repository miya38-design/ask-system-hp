/* ============================================
   Main JavaScript - Landing Page
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
  // Initialize particle animation
  new ParticleNetwork('particle-canvas');

  // Sticky header
  initStickyHeader();

  // Mobile menu
  initMobileMenu();

  // Smooth scroll
  initSmoothScroll();

  // Scroll animations (Intersection Observer)
  initScrollAnimations();

  // Animate counters in hero stats
  initCounterAnimation();

  // Share buttons
  initShareButtons();
});

/* --- Sticky Header --- */
function initStickyHeader() {
  const header = document.querySelector('.site-header');
  if (!header) return;

  let lastScroll = 0;

  window.addEventListener('scroll', () => {
    const currentScroll = window.scrollY;

    if (currentScroll > 60) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }

    lastScroll = currentScroll;
  }, { passive: true });
}

/* --- Mobile Menu --- */
function initMobileMenu() {
  const btn = document.querySelector('.mobile-menu-btn');
  const nav = document.querySelector('.nav-links');
  if (!btn || !nav) return;

  btn.setAttribute('aria-expanded', 'false');

  btn.addEventListener('click', () => {
    btn.classList.toggle('active');
    nav.classList.toggle('active');
    const isOpen = nav.classList.contains('active');
    btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    document.body.style.overflow = isOpen ? 'hidden' : '';
  });

  // Close menu on link click
  nav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      btn.classList.remove('active');
      nav.classList.remove('active');
      btn.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    });
  });
}

/* --- Smooth Scroll --- */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        const headerHeight = document.querySelector('.site-header')?.offsetHeight || 80;
        const targetPos = target.getBoundingClientRect().top + window.scrollY - headerHeight;
        window.scrollTo({
          top: targetPos,
          behavior: 'smooth'
        });
      }
    });
  });
}

/* --- Scroll Animations --- */
function initScrollAnimations() {
  const elements = document.querySelectorAll('.fade-in, .fade-in-left, .fade-in-right');
  if (!elements.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
      if (entry.isIntersecting) {
        // Stagger animation
        setTimeout(() => {
          entry.target.classList.add('is-visible');
        }, index * 100);
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  });

  elements.forEach(el => observer.observe(el));
}

/* --- Counter Animation --- */
function initCounterAnimation() {
  const counters = document.querySelectorAll('.stat-number');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateCounter(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(el => observer.observe(el));
}

function animateCounter(element) {
  const target = parseInt(element.dataset.count || element.textContent, 10);
  const suffix = element.dataset.suffix || '';
  const duration = 2000;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);

    // Ease out cubic
    const eased = 1 - Math.pow(1 - progress, 3);
    const current = Math.floor(eased * target);

    element.textContent = current.toLocaleString() + suffix;

    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      element.textContent = target.toLocaleString() + suffix;
    }
  }

  requestAnimationFrame(update);
}

/* --- Share Buttons --- */
function initShareButtons() {
  const siteUrl = window.location.href;
  const siteTitle = 'ASKデジタルアカデミー';
  const siteDesc = '小学3〜6年生向けに、5教科学習・タイピング・AI学習を通して自分で学ぶ力を育てます。';

  // LINE share
  const lineBtn = document.getElementById('share-line');
  if (lineBtn) {
    lineBtn.addEventListener('click', () => {
      const lineUrl = `https://social-plugins.line.me/lineit/share?url=${encodeURIComponent(siteUrl)}`;
      window.open(lineUrl, '_blank', 'width=600,height=500');
    });
  }

  // X (Twitter) share
  const xBtn = document.getElementById('share-x');
  if (xBtn && !xBtn.disabled) {
    xBtn.addEventListener('click', () => {
      const tweetUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(siteTitle + ' - ' + siteDesc)}&url=${encodeURIComponent(siteUrl)}`;
      window.open(tweetUrl, '_blank', 'width=600,height=500');
    });
  }

  // Facebook share
  const fbBtn = document.getElementById('share-facebook');
  if (fbBtn && !fbBtn.disabled) {
    fbBtn.addEventListener('click', () => {
      const fbUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(siteUrl)}`;
      window.open(fbUrl, '_blank', 'width=600,height=500');
    });
  }

  // URL Copy
  const copyBtn = document.getElementById('share-copy');
  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(siteUrl);
        showShareToast('✅ URLをコピーしました！');
      } catch (err) {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = siteUrl;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showShareToast('✅ URLをコピーしました！');
      }
    });
  }
}

/* --- Share Toast Notification --- */
function showShareToast(message) {
  // Remove existing toast
  const existing = document.querySelector('.share-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'share-toast';
  toast.textContent = message;
  document.body.appendChild(toast);

  requestAnimationFrame(() => toast.classList.add('show'));

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 2500);
}
