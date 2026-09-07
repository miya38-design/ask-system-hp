/* ============================================
   Particle Network Animation
   customer1st.co.jp 風の背景アニメーション
   ============================================ */

class ParticleNetwork {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');
    this.particles = [];
    this.mouse = { x: null, y: null, radius: 150 };
    this.animationId = null;

    // Config
    this.config = {
      particleCount: this.getParticleCount(),
      colors: [
        { r: 37, g: 99, b: 235, a: 0.5 },   // Blue
        { r: 96, g: 165, b: 250, a: 0.4 },   // Light blue
        { r: 16, g: 185, b: 129, a: 0.45 },  // Green
        { r: 52, g: 211, b: 153, a: 0.35 },  // Light green
      ],
      lineColor: { r: 37, g: 99, b: 235 },
      lineAlpha: 0.08,
      lineDistance: 180,
      speed: { min: 0.2, max: 0.6 },
      sizeRange: { min: 1.5, max: 4 },
      mouseInteraction: true,
    };

    this.init();
    this.bindEvents();
    this.animate();
  }

  getParticleCount() {
    const w = window.innerWidth;
    if (w < 480) return 35;
    if (w < 768) return 50;
    if (w < 1200) return 80;
    return 110;
  }

  init() {
    this.resize();
    this.particles = [];
    const count = this.getParticleCount();
    for (let i = 0; i < count; i++) {
      this.particles.push(this.createParticle());
    }
  }

  createParticle() {
    const color = this.config.colors[Math.floor(Math.random() * this.config.colors.length)];
    const size = this.randomBetween(this.config.sizeRange.min, this.config.sizeRange.max);
    return {
      x: Math.random() * this.canvas.width,
      y: Math.random() * this.canvas.height,
      vx: (Math.random() - 0.5) * this.randomBetween(this.config.speed.min, this.config.speed.max) * 2,
      vy: (Math.random() - 0.5) * this.randomBetween(this.config.speed.min, this.config.speed.max) * 2,
      size: size,
      baseSize: size,
      color: color,
      pulse: Math.random() * Math.PI * 2,
      pulseSpeed: 0.01 + Math.random() * 0.02,
    };
  }

  randomBetween(min, max) {
    return min + Math.random() * (max - min);
  }

  resize() {
    const dpr = window.devicePixelRatio || 1;
    this.canvas.width = window.innerWidth * dpr;
    this.canvas.height = window.innerHeight * dpr;
    this.canvas.style.width = window.innerWidth + 'px';
    this.canvas.style.height = window.innerHeight + 'px';
    this.ctx.scale(dpr, dpr);
  }

  bindEvents() {
    window.addEventListener('resize', () => {
      this.resize();
      // Adjust particle count on resize
      const targetCount = this.getParticleCount();
      while (this.particles.length < targetCount) {
        this.particles.push(this.createParticle());
      }
      while (this.particles.length > targetCount) {
        this.particles.pop();
      }
    });

    if (this.config.mouseInteraction) {
      window.addEventListener('mousemove', (e) => {
        this.mouse.x = e.clientX;
        this.mouse.y = e.clientY;
      });

      window.addEventListener('mouseout', () => {
        this.mouse.x = null;
        this.mouse.y = null;
      });
    }
  }

  drawParticle(p) {
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    this.ctx.fillStyle = `rgba(${p.color.r}, ${p.color.g}, ${p.color.b}, ${p.color.a})`;
    this.ctx.fill();

    // Glow effect
    this.ctx.beginPath();
    this.ctx.arc(p.x, p.y, p.size * 2.5, 0, Math.PI * 2);
    this.ctx.fillStyle = `rgba(${p.color.r}, ${p.color.g}, ${p.color.b}, ${p.color.a * 0.15})`;
    this.ctx.fill();
  }

  drawLine(p1, p2, distance) {
    const maxDist = this.config.lineDistance;
    const alpha = this.config.lineAlpha * (1 - distance / maxDist);

    // Use gradient line between two particle colors
    const gradient = this.ctx.createLinearGradient(p1.x, p1.y, p2.x, p2.y);
    gradient.addColorStop(0, `rgba(${p1.color.r}, ${p1.color.g}, ${p1.color.b}, ${alpha})`);
    gradient.addColorStop(1, `rgba(${p2.color.r}, ${p2.color.g}, ${p2.color.b}, ${alpha})`);

    this.ctx.beginPath();
    this.ctx.moveTo(p1.x, p1.y);
    this.ctx.lineTo(p2.x, p2.y);
    this.ctx.strokeStyle = gradient;
    this.ctx.lineWidth = 0.8;
    this.ctx.stroke();
  }

  update() {
    const w = window.innerWidth;
    const h = window.innerHeight;

    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];

      // Pulse size
      p.pulse += p.pulseSpeed;
      p.size = p.baseSize + Math.sin(p.pulse) * 0.5;

      // Move
      p.x += p.vx;
      p.y += p.vy;

      // Boundary bounce (with some padding)
      if (p.x < -10) p.x = w + 10;
      if (p.x > w + 10) p.x = -10;
      if (p.y < -10) p.y = h + 10;
      if (p.y > h + 10) p.y = -10;

      // Mouse interaction – gently push particles away
      if (this.mouse.x !== null && this.mouse.y !== null) {
        const dx = p.x - this.mouse.x;
        const dy = p.y - this.mouse.y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < this.mouse.radius) {
          const force = (this.mouse.radius - dist) / this.mouse.radius;
          p.x += dx * force * 0.02;
          p.y += dy * force * 0.02;
        }
      }
    }
  }

  draw() {
    this.ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);

    // Draw lines between nearby particles
    for (let i = 0; i < this.particles.length; i++) {
      for (let j = i + 1; j < this.particles.length; j++) {
        const dx = this.particles[i].x - this.particles[j].x;
        const dy = this.particles[i].y - this.particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < this.config.lineDistance) {
          this.drawLine(this.particles[i], this.particles[j], dist);
        }
      }
    }

    // Draw particles
    for (const p of this.particles) {
      this.drawParticle(p);
    }
  }

  animate() {
    this.update();
    this.draw();
    this.animationId = requestAnimationFrame(() => this.animate());
  }

  destroy() {
    if (this.animationId) {
      cancelAnimationFrame(this.animationId);
    }
  }
}

// Export for use
window.ParticleNetwork = ParticleNetwork;
