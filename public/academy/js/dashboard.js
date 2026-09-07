/* ============================================
   Dashboard JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {
  // Initialize particle animation on dashboard too
  if (document.getElementById('particle-canvas')) {
    new ParticleNetwork('particle-canvas');
  }

  const config = window.FirebaseConfig;

  // Try to initialize Firebase
  const firebaseReady = config.initFirebase();
  config.isDemoMode = !firebaseReady;

  // UI Elements
  const dashboardEl = document.getElementById('dashboard');
  const logoutBtn = document.getElementById('logout-btn');

  // ============================
  //  Auth Gate統合
  // ============================
  // 認証ゲートからのログインコールバック
  window.onAuthGateLogin = function(session) {
    // セッション情報からロールを判定してダッシュボードを表示
    let userData;
    const email = session.email || '';

    if (email.includes('instructor') || email.includes('講師')) {
      userData = config.demoUsers.instructor;
    } else if (email.includes('student') || email.includes('生徒')) {
      userData = config.demoUsers.student;
    } else if (email.includes('admin') || email.includes('管理')) {
      // 管理者はadmin.htmlへリダイレクト
      window.location.href = 'admin.html';
      return;
    } else {
      userData = config.demoUsers.parent;
    }

    showDashboard(userData);
  };

  // Logout - AuthGateと連携
  if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
      AuthGate.logout();
    });
  }

  // 既にセッションがある場合の初期化確認
  const existingSession = window.AuthGate ? AuthGate.getSession() : null;
  if (existingSession) {
    // onAuthGateLogin が auth-gate.js から呼ばれるのを待つ
  }

  function showDashboard(user) {
    if (dashboardEl) dashboardEl.classList.add('active');

    // Update user info
    updateUserInfo(user);

    // Setup tabs
    setupTabs(user.role);

    // Load content based on role
    if (user.role === 'parent') {
      loadParentDashboard();
    }
    if (user.role === 'instructor') {
      loadInstructorDashboard(user);
    }
    loadStudentDashboard();
  }

  function updateUserInfo(user) {
    const nameEl = document.querySelector('.user-name');
    const roleEl = document.querySelector('.user-role');
    const avatarEl = document.querySelector('.dash-avatar');

    if (nameEl) nameEl.textContent = user.displayName || user.email;
    const roleLabels = { parent: '保護者', student: '生徒', instructor: '講師' };
    if (roleEl) roleEl.textContent = roleLabels[user.role] || user.role;
    if (avatarEl) avatarEl.textContent = (user.displayName || 'U').charAt(0);
  }

  // Tab switching
  function setupTabs(role) {
    const tabs = document.querySelectorAll('.dash-tab');
    const panels = document.querySelectorAll('.dash-panel');

    // Show appropriate tabs based on role
    tabs.forEach(tab => {
      const tabRole = tab.dataset.role;
      if (tabRole === 'parent' && role !== 'parent' && role !== 'instructor') {
        tab.style.display = 'none';
      } else if (tabRole === 'instructor' && role !== 'instructor') {
        tab.style.display = 'none';
      } else {
        tab.style.display = '';
      }
    });

    // Set default active tab
    const defaultTabMap = { parent: 'parent', student: 'student', instructor: 'instructor' };
    const defaultTab = defaultTabMap[role] || 'student';
    activateTab(defaultTab);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        activateTab(tab.dataset.panel);
      });
    });

    function activateTab(panelName) {
      tabs.forEach(t => t.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));

      const activeTab = document.querySelector(`.dash-tab[data-panel="${panelName}"]`);
      const activePanel = document.getElementById(`panel-${panelName}`);

      if (activeTab) activeTab.classList.add('active');
      if (activePanel) activePanel.classList.add('active');
    }
  }

  /* =====================
     Parent Dashboard
     ===================== */
  function loadParentDashboard() {
    loadProgressChart();
    loadCalendar();
    loadMessages();
    loadMissions();
  }

  // Radar Chart (using Chart.js)
  function loadProgressChart() {
    const ctx = document.getElementById('progress-chart');
    if (!ctx) return;

    const data = config.demoProgress;

    new Chart(ctx, {
      type: 'radar',
      data: {
        labels: ['算数', '国語', '英語', '理科', '社会', 'IT'],
        datasets: [{
          label: '学習進捗',
          data: [data.math, data.japanese, data.english, data.science, data.social, data.it],
          backgroundColor: 'rgba(37, 99, 235, 0.15)',
          borderColor: 'rgba(37, 99, 235, 0.8)',
          borderWidth: 2,
          pointBackgroundColor: 'rgba(37, 99, 235, 1)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 5,
          pointHoverRadius: 7,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          r: {
            beginAtZero: true,
            max: 100,
            ticks: {
              stepSize: 20,
              font: { size: 11 },
              backdropColor: 'transparent'
            },
            grid: {
              color: 'rgba(0,0,0,0.06)'
            },
            angleLines: {
              color: 'rgba(0,0,0,0.06)'
            },
            pointLabels: {
              font: { size: 13, weight: '600', family: "'Noto Sans JP', sans-serif" },
              color: '#475569'
            }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(30, 41, 59, 0.9)',
            titleFont: { family: "'Noto Sans JP', sans-serif" },
            bodyFont: { family: "'Noto Sans JP', sans-serif" },
            callbacks: {
              label: (ctx) => `${ctx.label}: ${ctx.raw}点`
            }
          }
        }
      }
    });
  }

  // Calendar
  function loadCalendar() {
    const calendarGrid = document.getElementById('calendar-grid');
    const calendarTitle = document.getElementById('calendar-title');
    if (!calendarGrid || !calendarTitle) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();

    calendarTitle.textContent = `${year}年 ${month + 1}月`;

    // Day headers
    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    let html = '';
    dayNames.forEach(d => {
      html += `<div class="calendar-day-header">${d}</div>`;
    });

    // First day of month
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    // Get schedule data
    const schedules = config.generateDemoSchedules();
    const scheduleMap = {};
    schedules.forEach(s => { scheduleMap[s.date] = s; });

    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
      html += '<div class="calendar-day"></div>';
    }

    // Days
    for (let d = 1; d <= daysInMonth; d++) {
      const schedule = scheduleMap[d];
      const date = new Date(year, month, d);
      const dow = date.getDay();
      const isWeekend = dow === 0 || dow === 6;

      let availHtml = '';
      if (schedule) {
        const symbols = { available: '○', limited: '△', full: '×' };
        const classes = { available: 'open', limited: 'limited', full: 'full' };
        availHtml = `<span class="availability ${classes[schedule.status]}">${symbols[schedule.status]}</span>`;
      } else if (isWeekend) {
        availHtml = '<span class="availability" style="color:#94A3B8;font-size:0.6rem;">休</span>';
      }

      html += `<div class="calendar-day${d === now.getDate() ? ' today' : ''}">
        <span class="day-number" style="${isWeekend ? 'color:#94A3B8' : ''}">${d}</span>
        ${availHtml}
      </div>`;
    }

    calendarGrid.innerHTML = html;
  }

  // Messages
  function loadMessages() {
    const list = document.getElementById('message-list');
    if (!list) return;

    let html = '';
    config.demoMessages.forEach(msg => {
      html += `
        <div class="message-item">
          <div class="msg-header">
            <span class="msg-sender">${msg.sender}</span>
            <span class="msg-date">${msg.date}</span>
          </div>
          <div class="msg-body">${msg.body}</div>
        </div>
      `;
    });
    list.innerHTML = html;
  }

  /* =====================
     Student Dashboard
     ===================== */
  function loadStudentDashboard() {
    loadStudentStatus();
    loadStampCard();
  }

  // Student status (Level & EXP)
  function loadStudentStatus() {
    const student = config.demoUsers.student;

    const lvNumber = document.getElementById('lv-number');
    const lvName = document.getElementById('student-name');
    const expText = document.getElementById('exp-text');
    const expFill = document.getElementById('exp-fill');
    const expCurrent = document.getElementById('exp-current');
    const expNext = document.getElementById('exp-next');

    if (lvNumber) lvNumber.textContent = student.level;
    if (lvName) lvName.textContent = student.displayName;
    if (expText) expText.textContent = `EXP: ${student.exp} / ${student.expToNext}`;

    const expPercent = (student.exp / student.expToNext) * 100;
    if (expFill) {
      setTimeout(() => {
        expFill.style.width = `${expPercent}%`;
      }, 500);
    }
    if (expCurrent) expCurrent.textContent = student.exp;
    if (expNext) expNext.textContent = student.expToNext;
  }

  // Stamp card
  function loadStampCard() {
    const grid = document.getElementById('stamp-grid');
    const progress = document.getElementById('stamp-progress');
    if (!grid) return;

    const att = config.demoAttendance;
    let html = '';

    for (let i = 1; i <= att.totalRequired; i++) {
      const isEarned = i <= att.stampCount;
      const isGift = i === att.totalRequired;

      if (isGift) {
        if (isEarned) {
          html += `<div class="stamp-slot earned gift">🎁</div>`;
        } else {
          html += `<div class="stamp-slot gift">🎁</div>`;
        }
      } else if (isEarned) {
        html += `<div class="stamp-slot earned">⭐</div>`;
      } else {
        html += `<div class="stamp-slot">${i}</div>`;
      }
    }

    grid.innerHTML = html;
    if (progress) {
      progress.textContent = `${att.stampCount} / ${att.totalRequired} 回出席 ${att.stampCount >= att.totalRequired ? '🎉 ギフト獲得！' : ''}`;
    }
  }

  // Missions
  function loadMissions() {
    const list = document.getElementById('mission-list-items');
    if (!list) return;

    let html = '';
    config.demoMissions.forEach(mission => {
      html += `
        <div class="mission-item ${mission.completed ? 'completed' : ''}" data-id="${mission.id}">
          <div class="mission-checkbox">${mission.completed ? '✓' : ''}</div>
          <div class="mission-content">
            <div class="mission-title">${mission.title}</div>
            <div class="mission-exp">+${mission.expReward} EXP</div>
          </div>
        </div>
      `;
    });
    list.innerHTML = html;

    // Click to toggle mission
    list.querySelectorAll('.mission-item').forEach(item => {
      item.addEventListener('click', () => {
        const id = parseInt(item.dataset.id);
        const mission = config.demoMissions.find(m => m.id === id);
        if (mission && !mission.completed) {
          mission.completed = true;
          item.classList.add('completed');
          item.querySelector('.mission-checkbox').textContent = '✓';

          // Add EXP animation
          const student = config.demoUsers.student;
          student.exp += mission.expReward;

          // Check level up
          if (student.exp >= student.expToNext) {
            student.level++;
            student.exp -= student.expToNext;
            student.expToNext = Math.floor(student.expToNext * 1.2);
            showLevelUp(student.level);
          }

          // Update status
          loadStudentStatus();

          // Show EXP gain notification
          showExpGain(mission.expReward, item);
        }
      });
    });
  }

  // EXP gain floating text
  function showExpGain(amount, sourceEl) {
    const popup = document.createElement('div');
    popup.textContent = `+${amount} EXP`;
    popup.style.cssText = `
      position: fixed;
      color: #2563EB;
      font-weight: 800;
      font-size: 1.1rem;
      font-family: 'Inter', sans-serif;
      pointer-events: none;
      z-index: 999;
      animation: expFloat 1.5s ease-out forwards;
    `;

    const rect = sourceEl.getBoundingClientRect();
    popup.style.left = rect.right - 30 + 'px';
    popup.style.top = rect.top + 'px';

    // Add animation keyframes if needed
    if (!document.getElementById('exp-float-style')) {
      const style = document.createElement('style');
      style.id = 'exp-float-style';
      style.textContent = `
        @keyframes expFloat {
          0% { opacity: 1; transform: translateY(0); }
          100% { opacity: 0; transform: translateY(-60px); }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(popup);
    setTimeout(() => popup.remove(), 1500);
  }

  // Level up animation
  function showLevelUp(newLevel) {
    const overlay = document.createElement('div');
    overlay.style.cssText = `
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      display: flex; align-items: center; justify-content: center;
      z-index: 9999;
      animation: fadeIn 0.3s ease;
    `;

    overlay.innerHTML = `
      <div style="
        text-align: center;
        color: white;
        animation: levelUpPop 0.6s ease-out;
      ">
        <div style="font-size: 1rem; letter-spacing: 0.2em; opacity: 0.7; margin-bottom: 0.5rem;">LEVEL UP!</div>
        <div style="
          font-family: 'Inter', sans-serif;
          font-size: 5rem;
          font-weight: 900;
          background: linear-gradient(135deg, #60A5FA, #34D399);
          -webkit-background-clip: text;
          -webkit-text-fill-color: transparent;
          line-height: 1;
        ">LV.${newLevel}</div>
        <div style="font-size: 0.9rem; opacity: 0.7; margin-top: 1rem;">おめでとう！🎉</div>
      </div>
    `;

    if (!document.getElementById('levelup-style')) {
      const style = document.createElement('style');
      style.id = 'levelup-style';
      style.textContent = `
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes levelUpPop {
          0% { transform: scale(0.5); opacity: 0; }
          60% { transform: scale(1.1); opacity: 1; }
          100% { transform: scale(1); opacity: 1; }
        }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(overlay);

    overlay.addEventListener('click', () => overlay.remove());
    setTimeout(() => overlay.remove(), 3000);
  }

  /* =====================
     Instructor Dashboard
     ===================== */
  function loadInstructorDashboard(user) {
    // Update instructor info
    const nameEl = document.getElementById('instructor-name');
    const subjectsEl = document.getElementById('instructor-subjects');
    const areaEl = document.getElementById('instructor-area');
    if (nameEl) nameEl.textContent = user.displayName;
    if (subjectsEl && user.subjects) subjectsEl.textContent = user.subjects.join('・');
    if (areaEl && user.area) areaEl.textContent = user.area;

    loadInstructorSchedule();
    loadMessageForm();
    loadSentMessages();
  }

  // --- Instructor Schedule ---
  let instrScheduleData = {};
  let selectedSchedDate = null;

  function loadInstructorSchedule() {
    const grid = document.getElementById('sched-calendar-grid');
    const title = document.getElementById('sched-calendar-title');
    if (!grid || !title) return;

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    title.textContent = `${year}年 ${month + 1}月`;

    // Init schedule data from demo
    const schedules = config.generateDemoSchedules();
    instrScheduleData = {};
    schedules.forEach(s => { instrScheduleData[s.date] = s; });

    renderInstructorCalendar(year, month);
  }

  function renderInstructorCalendar(year, month) {
    const grid = document.getElementById('sched-calendar-grid');
    if (!grid) return;

    const dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    let html = '';
    dayNames.forEach(d => {
      html += `<div class="calendar-day-header">${d}</div>`;
    });

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const now = new Date();

    for (let i = 0; i < firstDay; i++) {
      html += '<div class="calendar-day"></div>';
    }

    for (let d = 1; d <= daysInMonth; d++) {
      const date = new Date(year, month, d);
      const dow = date.getDay();
      const isWeekend = dow === 0 || dow === 6;
      const isToday = d === now.getDate() && month === now.getMonth() && year === now.getFullYear();
      const schedule = instrScheduleData[d];

      let availHtml = '';
      if (schedule) {
        const symbols = { available: '○', limited: '△', full: '×' };
        const classes = { available: 'open', limited: 'limited', full: 'full' };
        availHtml = `<span class="availability ${classes[schedule.status]}">${symbols[schedule.status]}</span>`;
      } else if (isWeekend) {
        availHtml = '<span class="availability" style="color:#94A3B8;font-size:0.6rem;">休</span>';
      }

      const clickable = !isWeekend ? 'sched-clickable' : '';
      const selected = selectedSchedDate === d ? 'sched-selected' : '';

      html += `<div class="calendar-day ${isToday ? 'today' : ''} ${clickable} ${selected}" data-day="${d}">
        <span class="day-number" style="${isWeekend ? 'color:#94A3B8' : ''}">${d}</span>
        ${availHtml}
      </div>`;
    }

    grid.innerHTML = html;

    // Add click handlers
    grid.querySelectorAll('.sched-clickable').forEach(cell => {
      cell.addEventListener('click', () => {
        const day = parseInt(cell.dataset.day);
        selectScheduleDate(day, year, month);
      });
    });
  }

  function selectScheduleDate(day, year, month) {
    selectedSchedDate = day;

    // Highlight selected
    document.querySelectorAll('#sched-calendar-grid .sched-selected').forEach(el => {
      el.classList.remove('sched-selected');
    });
    const cell = document.querySelector(`#sched-calendar-grid .calendar-day[data-day="${day}"]`);
    if (cell) cell.classList.add('sched-selected');

    // Show edit form
    const placeholder = document.getElementById('edit-panel-placeholder');
    const form = document.getElementById('edit-panel-form');
    const dateTitle = document.getElementById('edit-date-title');
    if (placeholder) placeholder.style.display = 'none';
    if (form) form.style.display = 'block';
    if (dateTitle) dateTitle.textContent = `${year}年${month + 1}月${day}日`;

    // Load existing data
    const schedule = instrScheduleData[day];
    const currentStatus = schedule ? schedule.status : 'available';

    // Highlight current status
    document.querySelectorAll('.status-option').forEach(btn => {
      btn.classList.remove('active');
      if (btn.dataset.status === currentStatus) {
        btn.classList.add('active');
      }
    });

    const capacityInput = document.getElementById('edit-capacity');
    if (capacityInput) capacityInput.value = schedule ? schedule.capacity : 10;

    const noteInput = document.getElementById('edit-note');
    if (noteInput) noteInput.value = schedule?.note || '';
  }

  // Status option click
  document.querySelectorAll('.status-option').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      document.querySelectorAll('.status-option').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // Save schedule
  const saveBtn = document.getElementById('btn-save-schedule');
  if (saveBtn) {
    saveBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!selectedSchedDate) return;

      const activeStatusBtn = document.querySelector('.status-option.active');
      const status = activeStatusBtn ? activeStatusBtn.dataset.status : 'available';
      const capacity = parseInt(document.getElementById('edit-capacity')?.value || '10');
      const note = document.getElementById('edit-note')?.value || '';

      instrScheduleData[selectedSchedDate] = {
        date: selectedSchedDate,
        status: status,
        capacity: capacity,
        note: note,
        currentBookings: status === 'full' ? capacity : status === 'limited' ? Math.floor(capacity * 0.8) : Math.floor(capacity * 0.3)
      };

      // Re-render calendar
      const now = new Date();
      renderInstructorCalendar(now.getFullYear(), now.getMonth());

      // Show success toast
      showToast('✅ スケジュールを保存しました');
    });
  }

  // --- Message Form ---
  function loadMessageForm() {
    const select = document.getElementById('msg-recipient');
    if (!select) return;

    // Populate recipients
    let options = '<option value="">選択してください...</option>';
    config.demoStudentsList.forEach(s => {
      options += `<option value="${s.uid}">${s.parentName}（${s.name}さんの保護者）</option>`;
    });
    select.innerHTML = options;

    // Template buttons
    document.querySelectorAll('.msg-template-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const textarea = document.getElementById('msg-body');
        if (textarea) textarea.value = btn.dataset.template;
      });
    });
  }

  // Message form submit
  const msgForm = document.getElementById('message-form');
  if (msgForm) {
    msgForm.addEventListener('submit', (e) => {
      e.preventDefault();

      const select = document.getElementById('msg-recipient');
      const bodyEl = document.getElementById('msg-body');
      if (!select || !bodyEl) return;

      const recipientUid = select.value;
      const body = bodyEl.value.trim();
      if (!recipientUid || !body) return;

      const recipientText = select.options[select.selectedIndex].text;
      const now = new Date();
      const dateStr = `${now.getFullYear()}/${String(now.getMonth() + 1).padStart(2, '0')}/${String(now.getDate()).padStart(2, '0')}`;

      // Add to sent messages
      config.demoSentMessages.unshift({
        id: Date.now(),
        to: recipientText,
        date: dateStr,
        body: body
      });

      // Reset form
      msgForm.reset();

      // Refresh sent messages
      loadSentMessages();

      // Show success toast
      showToast('✉️ メッセージを送信しました');
    });
  }

  // --- Sent Messages ---
  function loadSentMessages() {
    const list = document.getElementById('sent-message-list');
    const countEl = document.getElementById('sent-count');
    if (!list) return;

    const messages = config.demoSentMessages;
    if (countEl) countEl.textContent = `${messages.length}件`;

    let html = '';
    messages.forEach(msg => {
      html += `
        <div class="sent-message-item">
          <div class="msg-header">
            <span class="msg-sender">宛先: ${msg.to}</span>
            <span class="msg-date">${msg.date}</span>
          </div>
          <div class="msg-body">${msg.body}</div>
        </div>
      `;
    });
    list.innerHTML = html || '<p style="color:var(--text-light);text-align:center;padding:2rem;">送信済みメッセージはありません</p>';
  }

  // --- Toast notification ---
  function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }

  // Demo mode banner
  if (config.isDemoMode) {
    const banner = document.createElement('div');
    banner.style.cssText = `
      position: fixed; bottom: 0; left: 0; right: 0;
      background: linear-gradient(135deg, #1E40AF, #059669);
      color: white; text-align: center;
      padding: 8px; font-size: 0.8rem;
      z-index: 9999; font-weight: 500;
    `;
    banner.innerHTML = '🔒 限定公開サイト — デモモードで動作中';
    document.body.appendChild(banner);
  }
});
