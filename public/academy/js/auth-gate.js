/* ============================================
   Auth Gate - 認証ゲートモジュール
   全ページで認証を必須にする共通モジュール
   ============================================ */

(function() {
  'use strict';

  // ============================
  //  デモ認証の設定
  // ============================

  const AUTH_SESSION_KEY = 'ada_auth_session';

  // ============================
  //  認証状態管理
  // ============================
  function getSession() {
    try {
      const data = sessionStorage.getItem(AUTH_SESSION_KEY);
      return data ? JSON.parse(data) : null;
    } catch(e) {
      return null;
    }
  }

  function setSession(user) {
    sessionStorage.setItem(AUTH_SESSION_KEY, JSON.stringify({
      email: user.email,
      displayName: user.displayName,
      role: user.role,
      loginTime: Date.now()
    }));
  }

  function clearSession() {
    sessionStorage.removeItem(AUTH_SESSION_KEY);
  }

  // ============================
  //  デモ認証ロジック
  // ============================
  async function authenticateDemo(email, password) {
    try {
      const userCredential = await firebase.auth().signInWithEmailAndPassword(email, password);
      const user = userCredential.user;
      return {
        success: true,
        user: {
          email: user.email,
          displayName: user.displayName || user.email, // displayNameがない場合はemailを使用
          role: 'user' // ロールは一旦'user'で固定
        }
      };
    } catch (error) {
      console.error("LOGIN ERROR", error.code, error.message);
      let errorMessage = '認証に失敗しました。';
      switch (error.code) {
        case 'auth/user-not-found':
        case 'auth/wrong-password':
          errorMessage = 'メールアドレスまたはパスワードが正しくありません。';
          break;
        case 'auth/invalid-email':
          errorMessage = 'メールアドレスの形式が正しくありません。';
          break;
        case 'auth/user-disabled':
          errorMessage = 'このアカウントは無効になっています。';
          break;
        default:
          errorMessage = '認証エラーが発生しました。しばらくしてから再度お試しください。';
          console.error(error.code, error.message, error);
      }
      return { success: false, error: errorMessage };
    }
  }

  // ============================
  //  ログインオーバーレイ生成
  // ============================
  function createAuthGateOverlay() {
    // 既にオーバーレイが存在する場合はスキップ
    if (document.getElementById('auth-gate-overlay')) return;

    const overlay = document.createElement('div');
    overlay.id = 'auth-gate-overlay';
    overlay.className = 'auth-gate-overlay';

    overlay.innerHTML = `
      <div class="auth-gate-card">
        <div class="auth-gate-logo">
          <img src="assets/images/logo.png" alt="ADA ロゴ" width="160" height="51">
        </div>
        <div class="auth-gate-title">
          <h2>ログイン</h2>
          <p>このサイトは限定公開です。<br>アカウントをお持ちの方のみアクセスできます。</p>
        </div>

        <form id="auth-gate-form" class="auth-gate-form">
          <div class="auth-gate-field">
            <label for="auth-gate-email">メールアドレス</label>
            <input type="email" id="auth-gate-email" placeholder="例: parent@demo.ada.jp" required autocomplete="email">
          </div>
          <div class="auth-gate-field">
            <label for="auth-gate-password">パスワード</label>
            <div class="auth-gate-password-wrap">
              <input type="password" id="auth-gate-password" placeholder="パスワード" required autocomplete="current-password">
              <button type="button" class="auth-gate-toggle-pw" id="auth-gate-toggle-pw" aria-label="パスワード表示切替">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                  <circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>
          <p class="auth-gate-error" id="auth-gate-error"></p>
          <button type="submit" class="auth-gate-submit" id="auth-gate-submit">
            <span class="auth-gate-submit-text">ログイン</span>
            <span class="auth-gate-submit-spinner" style="display:none;">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
            </span>
          </button>
        </form>


        <div class="auth-gate-footer">
          <div class="auth-gate-lock">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0110 0v4"/>
            </svg>
            <span>このサイトは限定公開です</span>
          </div>
        </div>
      </div>
    `;

    document.body.prepend(overlay);

    // イベントリスナー設定
    setupAuthGateEvents(overlay);
  }

  // ============================
  //  イベント設定
  // ============================
  function setupAuthGateEvents(overlay) {
    const form = document.getElementById('auth-gate-form');
    const emailInput = document.getElementById('auth-gate-email');
    const passwordInput = document.getElementById('auth-gate-password');
    const errorEl = document.getElementById('auth-gate-error');
    const submitBtn = document.getElementById('auth-gate-submit');
    const togglePw = document.getElementById('auth-gate-toggle-pw');

    // パスワード表示トグル
    if (togglePw) {
      togglePw.addEventListener('click', () => {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
        togglePw.classList.toggle('visible', type === 'text');
      });
    }

    // デモアカウントボタン

    // フォーム送信
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const email = emailInput.value.trim();
      const password = passwordInput.value;

      if (!email || !password) {
        showError(errorEl, 'メールアドレスとパスワードを入力してください。');
        return;
      }

      // ローディング表示
      setSubmitLoading(submitBtn, true);

      // 少し遅延を入れてリアルな認証感を出す
      const result = await authenticateDemo(email, password);

        if (result.success) {
          setSession(result.user);
          hideAuthGate(overlay);
          // ページ固有のログイン後処理を呼び出す
          if (typeof window.onAuthGateLogin === 'function') {
            window.onAuthGateLogin(result.user);
          }
        } else {
          showError(errorEl, result.error);
          setSubmitLoading(submitBtn, false);
          // パスワード欄を揺らすアニメーション
          form.classList.add('shake');
          setTimeout(() => form.classList.remove('shake'), 500);
        }
    });
  }

  function showError(el, msg) {
    el.textContent = msg;
    el.style.display = 'block';
  }

  function setSubmitLoading(btn, loading) {
    const text = btn.querySelector('.auth-gate-submit-text');
    const spinner = btn.querySelector('.auth-gate-submit-spinner');
    if (loading) {
      text.style.display = 'none';
      spinner.style.display = 'inline-flex';
      btn.disabled = true;
    } else {
      text.style.display = 'inline';
      spinner.style.display = 'none';
      btn.disabled = false;
    }
  }

  // ============================
  //  表示/非表示
  // ============================
  function showAuthGate() {
    const overlay = document.getElementById('auth-gate-overlay');
    if (overlay) {
      overlay.classList.add('active');
    }
    // ページコンテンツを隠す
    document.querySelectorAll('.auth-protected').forEach(el => {
      el.style.display = 'none';
    });
    document.body.classList.add('auth-gate-locked');
  }

  function hideAuthGate(overlay) {
    if (!overlay) overlay = document.getElementById('auth-gate-overlay');
    if (overlay) {
      overlay.classList.add('fade-out');
      setTimeout(() => {
        overlay.classList.remove('active');
        overlay.classList.remove('fade-out');
        overlay.style.display = 'none';
      }, 500);
    }
    // ページコンテンツを表示
    document.querySelectorAll('.auth-protected').forEach(el => {
      el.style.display = '';
    });
    document.body.classList.remove('auth-gate-locked');
  }

  // ============================
  //  ログアウト
  // ============================
  function logout() {
    clearSession();
    const overlay = document.getElementById('auth-gate-overlay');
    if (overlay) {
      overlay.style.display = '';
      overlay.classList.add('active');
      overlay.classList.remove('fade-out');
      // フォームリセット
      const form = document.getElementById('auth-gate-form');
      if (form) form.reset();
      const errorEl = document.getElementById('auth-gate-error');
      if (errorEl) { errorEl.textContent = ''; errorEl.style.display = 'none'; }
      const submitBtn = document.getElementById('auth-gate-submit');
      if (submitBtn) setSubmitLoading(submitBtn, false);
    }
    document.querySelectorAll('.auth-protected').forEach(el => {
      el.style.display = 'none';
    });
    document.body.classList.add('auth-gate-locked');
  }

  // ============================
  //  初期化
  // ============================
  function init() {
    // 認証ゲートオーバーレイを作成
    createAuthGateOverlay();

    // 既存のセッションをチェック
    const session = getSession();

    if (session) {
      // セッションがあれば認証ゲートを非表示にし、ダッシュボードを表示
      hideAuthGate();
      const dashboardEl = document.getElementById('dashboard');
      if (dashboardEl) {
        dashboardEl.classList.add('active');
      }
      if (typeof window.onAuthGateLogin === 'function') {
        window.onAuthGateLogin(session);
      }
    } else {
      // セッションがなければ認証ゲートを表示
      showAuthGate();
    }
  }

  // ============================
  //  グローバルAPI
  // ============================
  window.AuthGate = {
    init: init,
    logout: logout,
    getSession: getSession,
    isAuthenticated: () => !!getSession()
  };

  // DOMContentLoaded で自動初期化
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
