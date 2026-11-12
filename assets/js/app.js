(() => {
  'use strict';

  const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

  window.esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  window.toast = (message, type = 'ok') => {
    let stack = document.getElementById('toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'toast-stack';
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    const icon = type === 'ok' ? 'check_circle' : 'error';
    el.innerHTML = `<span class="ms ms-sm ms-fill">${icon}</span><span></span>`;
    el.querySelector('span:last-child').textContent = message;
    stack.appendChild(el);
    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(6px)';
      el.style.transition = 'all .2s ease';
      setTimeout(() => el.remove(), 220);
    }, 3000);
  };

  window.apiPost = async (url, data = {}) => {
    let body;
    if (data instanceof FormData) {
      body = data;
      if (!body.has('csrf_token')) body.append('csrf_token', csrf());
    } else {
      body = new FormData();
      Object.entries(data).forEach(([k, v]) => {
        if (v !== undefined && v !== null) body.append(k, v);
      });
      body.append('csrf_token', csrf());
    }
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf() },
      body,
    });
    const json = await res.json().catch(() => ({ ok: false, error: 'Server error' }));
    if (!res.ok && json.code === 'UNAUTHENTICATED') {
      window.location.href = 'login.php';
    }
    return json;
  };

  window.apiGet = async (url) => {
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    return res.json().catch(() => ({ ok: false, error: 'Server error' }));
  };

  window.openModal = (id) => {
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
  };

  window.closeModal = (id) => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
  };

  window.openAuthModal = (tab = 'login') => {
    window.location.href = tab === 'register' ? 'register.php' : 'login.php';
  };

  window.logoutUser = async () => {
    const res = await apiPost('api/auth.php', { action: 'logout' });
    if (res.ok) window.location.href = res.redirect || 'index.php';
  };

  window.toggleFav = async (gigId, btn) => {
    const res = await apiPost('api/gigs.php', { action: 'toggle_favorite', gig_id: gigId });
    if (!res.ok) {
      if (res.code !== 'UNAUTHENTICATED') toast(res.error || 'Failed', 'err');
      return;
    }
    if (btn) {
      btn.classList.toggle('active', res.favorited);
      const ic = btn.querySelector('.ms');
      if (ic) ic.classList.toggle('ms-fill', res.favorited);
    }
    toast(res.favorited ? 'Saved to favorites' : 'Removed from favorites');
  };

  window.startChat = async (recipientId, gigId = '', orderId = '') => {
    const res = await apiPost('api/messages.php', {
      action: 'start',
      recipient_id: recipientId,
      gig_id: gigId,
      order_id: orderId,
    });
    if (res.ok) {
      window.location.href = res.redirect;
    } else if (res.code !== 'UNAUTHENTICATED') {
      toast(res.error || 'Could not start chat', 'err');
    }
  };

  // Global modal close handlers
  document.addEventListener('click', (e) => {
    if (e.target.classList?.contains('modal-backdrop')) {
      e.target.classList.remove('open');
    }
    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn) {
      closeModal(closeBtn.dataset.closeModal);
    }
    const menuWrap = document.getElementById('userMenuWrap');
    const menuDrop = document.getElementById('userDropdown');
    if (menuWrap && menuDrop) {
      if (e.target.closest('#userMenuBtn')) {
        menuDrop.classList.toggle('open');
      } else if (!e.target.closest('#userMenuWrap')) {
        menuDrop.classList.remove('open');
      }
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.open').forEach(m => m.classList.remove('open'));
    }
  });

  // Auth Forms
  const loginForm = document.getElementById('authLoginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(loginForm);
      fd.append('action', 'login');
      const res = await apiPost('api/auth.php', fd);
      if (res.ok) {
        window.location.href = res.redirect || 'index.php';
      } else {
        toast(res.error || 'Sign in failed', 'err');
      }
    });
  }

  const regForm = document.getElementById('authRegisterForm');
  if (regForm) {
    regForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(regForm);
      fd.append('action', 'register');
      const res = await apiPost('api/auth.php', fd);
      if (res.ok) {
        window.location.href = res.redirect || 'index.php';
      } else {
        toast(res.error || 'Registration failed', 'err');
      }
    });
  }

  // Global Navbar search submits to index.php?q=... if not on index
  const navSearch = document.getElementById('globalNavSearch');
  if (navSearch) {
    navSearch.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !window.onMarketplaceHome) {
        e.preventDefault();
        const q = encodeURIComponent(navSearch.value.trim());
        window.location.href = q ? `index.php?q=${q}` : 'index.php';
      }
    });
  }

  // Live unread messages badge
  const unreadEl = document.getElementById('navUnreadBadge');
  if (unreadEl && document.body.dataset.auth === '1') {
    const refreshUnread = async () => {
      const res = await apiGet('api/messages.php?action=unread');
      if (res.ok) {
        const n = Number(res.unread || 0);
        unreadEl.textContent = n;
        unreadEl.style.display = n > 0 ? 'inline-flex' : 'none';
      }
    };
    setInterval(refreshUnread, 12000);
  }
})();

