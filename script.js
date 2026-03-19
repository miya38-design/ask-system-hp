// ============================================
// アスクシステム LP - JavaScript
// ============================================

document.addEventListener('DOMContentLoaded', () => {
  // --- Hamburger Menu ---
  const hamburger = document.getElementById('hamburger');
  const nav = document.getElementById('nav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      hamburger.classList.toggle('active');
      nav.classList.toggle('active');
    });
    // Close menu on link click
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        nav.classList.remove('active');
      });
    });
  }

  // --- Sticky CTA Bar ---
  const stickyCta = document.getElementById('sticky-cta');
  const hero = document.getElementById('hero');
  if (stickyCta && hero) {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (!entry.isIntersecting) {
          stickyCta.classList.add('visible');
        } else {
          stickyCta.classList.remove('visible');
        }
      },
      { threshold: 0.1 }
    );
    observer.observe(hero);
  }

  // --- Scroll Animations (Fade In) ---
  const fadeElements = document.querySelectorAll('.fade-in');
  if (fadeElements.length > 0) {
    const fadeObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            fadeObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: '0px 0px -50px 0px' }
    );
    fadeElements.forEach(el => fadeObserver.observe(el));
  }

  // --- Counter Animation ---
  const statNumbers = document.querySelectorAll('.stat-number');
  if (statNumbers.length > 0) {
    const counterObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const target = parseInt(el.getAttribute('data-count'), 10);
            animateCounter(el, target);
            counterObserver.unobserve(el);
          }
        });
      },
      { threshold: 0.5 }
    );
    statNumbers.forEach(el => counterObserver.observe(el));
  }

  function animateCounter(el, target) {
    const duration = 1500;
    const startTime = performance.now();
    function update(currentTime) {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(eased * target);
      el.textContent = current.toLocaleString();
      if (progress < 1) {
        requestAnimationFrame(update);
      }
    }
    requestAnimationFrame(update);
  }

  // --- Smooth Scroll for anchor links ---
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const targetId = anchor.getAttribute('href');
      if (targetId === '#') return;
      const targetEl = document.querySelector(targetId);
      if (targetEl) {
        e.preventDefault();
        const headerHeight = document.querySelector('.header').offsetHeight;
        const y = targetEl.getBoundingClientRect().top + window.pageYOffset - headerHeight;
        window.scrollTo({ top: y, behavior: 'smooth' });
      }
    });
  });

  // --- Hero Particles ---
  const particlesContainer = document.getElementById('hero-particles');
  if (particlesContainer) {
    for (let i = 0; i < 20; i++) {
      const particle = document.createElement('div');
      particle.style.cssText = `
        position:absolute;
        width:${Math.random() * 6 + 2}px;
        height:${Math.random() * 6 + 2}px;
        background:rgba(255,255,255,${Math.random() * 0.3 + 0.1});
        border-radius:50%;
        left:${Math.random() * 100}%;
        top:${Math.random() * 100}%;
        animation:float ${Math.random() * 6 + 4}s ease-in-out infinite;
        animation-delay:${Math.random() * 5}s;
      `;
      particlesContainer.appendChild(particle);
    }
    // Add float animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes float {
        0%, 100% { transform: translateY(0) translateX(0); opacity: 0.3; }
        25% { transform: translateY(-20px) translateX(10px); opacity: 0.8; }
        50% { transform: translateY(-40px) translateX(-5px); opacity: 0.5; }
        75% { transform: translateY(-20px) translateX(15px); opacity: 0.7; }
      }
    `;
    document.head.appendChild(style);
  }

  // --- Contact Form ---
  const form = document.getElementById('contact-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = document.getElementById('form-name').value.trim();
      const message = document.getElementById('form-message').value.trim();
      if (!name || !message) {
        alert('お名前とお問い合わせ内容は必須です。');
        return;
      }
      // Show success message
      const btn = document.getElementById('form-submit');
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-check"></i> 送信しました（デモ）';
      btn.style.background = 'var(--color-line)';
      btn.disabled = true;
      setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = '';
        btn.disabled = false;
        form.reset();
      }, 3000);
    });
  }

  // --- Header scroll effect ---
  let lastScroll = 0;
  const header = document.getElementById('header');
  window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    if (currentScroll > 100) {
      header.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
    } else {
      header.style.boxShadow = '0 1px 10px rgba(0,0,0,0.06)';
    }
    lastScroll = currentScroll;
  });
});
