<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = require_login();
$uid = (int)$me['id'];
$activeConvId = (int)($_GET['c'] ?? 0);

$peersStmt = db()->prepare("SELECT id, name, username, avatar, role, title FROM users WHERE id <> ? AND status = 'active' ORDER BY role DESC, name ASC LIMIT 40");
$peersStmt->execute([$uid]);
$peers = $peersStmt->fetchAll();

$pageTitle = 'Messages';
$activeNav = 'messages';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="chat-shell">
    <!-- Left Thread Sidebar -->
    <aside class="chat-sidebar">
      <div class="chat-search">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
          <strong style="font-size:14.5px;">Inbox</strong>
          <div style="display:flex;align-items:center;gap:6px;">
            <span class="muted" style="font-size:11.5px;" id="threadCount">Loading...</span>
            <button type="button" class="btn btn-outline btn-sm" style="height:26px;padding:0 8px;font-size:11.5px;" onclick="openModal('newChatModal')">
              <span class="ms ms-sm">edit_square</span> New
            </button>
          </div>
        </div>
        <input type="search" id="threadSearch" class="control" style="padding:6px 10px;font-size:12.5px;" placeholder="Filter conversations...">
      </div>
      <div class="thread-list" id="threadList"></div>
    </aside>

    <!-- Right Active Conversation -->
    <section class="chat-main">
      <div class="chat-header" id="chatHeader">
        <div class="muted" style="font-size:13px;">Select a conversation</div>
      </div>

      <div class="chat-feed" id="chatFeed">
        <div class="empty-state" style="margin:auto;">
          <span class="ms">chat_bubble_outline</span>
          <div style="font-weight:700;color:var(--ink);">Direct Freelancer Messaging</div>
          <div style="font-size:12.5px;">Discuss project scope, share files, and track orders smoothly.</div>
        </div>
      </div>

      <form class="chat-composer" id="chatComposer"<?= $activeConvId > 0 ? '' : ' style="display:none;"' ?> enctype="multipart/form-data">
        <input type="hidden" name="conversation_id" id="composerConvId" value="<?= $activeConvId > 0 ? $activeConvId : '' ?>">
        <label class="icon-btn" title="Attach file" style="cursor:pointer;margin:0;">
          <span class="ms ms-sm">attach_file</span>
          <input type="file" name="attachment" id="chatFileInput" style="display:none;">
        </label>
        <span id="chatFileName" class="badge badge-indigo" style="display:none;"></span>
        <input type="text" name="body" id="chatBodyInput" placeholder="Write a message..." autocomplete="off">
        <button type="submit" class="btn btn-primary btn-sm" style="height:36px;border-radius:99px;padding:0 16px;">
          <span class="ms ms-sm">send</span>
          <span>Send</span>
        </button>
      </form>
    </section>
  </div>
</div>

<!-- Start New Chat Modal -->
<div class="modal-backdrop" id="newChatModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>New Message</h3>
      <button type="button" class="icon-btn" data-close-modal="newChatModal"><span class="ms ms-sm">close</span></button>
    </div>
    <div style="display:grid;gap:6px;max-height:360px;overflow-y:auto;">
      <?php foreach ($peers as $p): ?>
        <button type="button" class="thread-item" style="border:1px solid var(--line);background:var(--surface);width:100%;text-align:left;" onclick="startChat(<?= (int)$p['id'] ?>)">
          <img src="<?= e(avatar_url($p['avatar'])) ?>" class="avatar avatar-md" alt="">
          <div style="flex:1;min-width:0;">
            <div style="font-weight:700;font-size:13px;"><?= e($p['name']) ?></div>
            <div class="muted truncate" style="font-size:11.5px;"><?= e($p['title'] ?: ucfirst($p['role'])) ?></div>
          </div>
          <span class="badge badge-indigo" style="text-transform:capitalize;"><?= e($p['role']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
(() => {
  let activeId = <?= $activeConvId ?>;
  let loadedConvId = 0;
  let lastMsgId = 0;
  let threadsCache = [];
  let sending = false;

  const threadList = document.getElementById('threadList');
  const threadCount = document.getElementById('threadCount');
  const chatHeader = document.getElementById('chatHeader');
  const chatFeed = document.getElementById('chatFeed');
  const composer = document.getElementById('chatComposer');
  const convInput = document.getElementById('composerConvId');
  const bodyInput = document.getElementById('chatBodyInput');
  const fileInput = document.getElementById('chatFileInput');
  const fileTag = document.getElementById('chatFileName');

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    if (f) {
      fileTag.textContent = f.name.slice(0, 18);
      fileTag.style.display = 'inline-flex';
    } else {
      fileTag.style.display = 'none';
    }
  });

  const renderThreads = () => {
    const q = document.getElementById('threadSearch').value.trim().toLowerCase();
    const list = threadsCache.filter(t => !q || t.peer_name.toLowerCase().includes(q) || (t.gig_title || '').toLowerCase().includes(q));
    threadCount.textContent = `${threadsCache.length} chats`;

    if (!list.length) {
      threadList.innerHTML = `<div class="muted" style="padding:24px;text-align:center;font-size:12.5px;">No conversations yet</div>`;
      return;
    }

    threadList.innerHTML = list.map(t => `
      <div class="thread-item ${Number(t.id) === Number(activeId) ? 'active' : ''}" onclick="selectThread(${t.id})">
        <div class="avatar-wrap">
          <img src="${esc(t.peer_avatar || 'assets/images/default-avatar.svg')}" class="avatar avatar-md" alt="">
          ${t.is_online ? '<span class="online-dot"></span>' : ''}
        </div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">
            <strong style="font-size:13px;display:inline-flex;align-items:center;gap:3px;" class="truncate">
              <span>${esc(t.peer_name)}</span>
              ${t.peer_level === 'pro' && Number(t.peer_verified) === 1 ? '<span class="ms ms-sm ms-fill" style="color:var(--primary);font-size:13px;" title="Pro Verified">verified</span>' : ''}
            </strong>
            <span class="muted" style="font-size:11px;flex-shrink:0;">${esc(t.time_ago)}</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;gap:6px;margin-top:2px;">
            <span class="muted truncate" style="font-size:12px;">${esc(t.last_body || t.last_attachment || 'Start chatting...')}</span>
            ${t.unread_count > 0 ? `<span class="nav-badge">${t.unread_count}</span>` : ''}
          </div>
        </div>
      </div>
    `).join('');
  };

  const renderHeader = (t) => {
    if (!t) return;
    chatHeader.innerHTML = `
      <div style="display:flex;align-items:center;gap:10px;min-width:0;">
        <div class="avatar-wrap">
          <img src="${esc(t.peer_avatar || 'assets/images/default-avatar.svg')}" class="avatar avatar-md" alt="">
          ${t.is_online ? '<span class="online-dot"></span>' : ''}
        </div>
        <div style="min-width:0;">
          <div style="display:flex;align-items:center;gap:4px;">
            <a href="profile.php?u=${esc(t.peer_username)}" style="font-weight:700;font-size:14px;">${esc(t.peer_name)}</a>
            ${t.peer_level === 'pro' && Number(t.peer_verified) === 1 ? '<span class="ms ms-sm ms-fill" style="color:var(--primary);font-size:14px;" title="Pro Verified">verified</span>' : ''}
          </div>
          <div class="muted" style="font-size:11.5px;">${t.is_online ? 'Online now' : esc(t.peer_title || t.peer_role)}</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:6px;flex-shrink:0;">
        ${t.gig_id ? `<a href="gig.php?id=${t.gig_id}" class="badge badge-indigo"><span class="ms ms-sm">work</span> View Gig</a>` : ''}
        ${t.order_id ? `<a href="order.php?id=${t.order_id}" class="badge badge-emerald"><span class="ms ms-sm">receipt_long</span> ${esc(t.order_code || 'Order')}</a>` : ''}
      </div>
    `;
  };

  const msgHtml = (m) => {
    let attHtml = '';
    if (m.attachment) {
      const isImg = /\.(png|jpe?g|webp|gif)$/i.test(m.attachment);
      attHtml = isImg
        ? `<a href="${esc(m.attachment)}" target="_blank" style="display:block;margin-top:6px;"><img src="${esc(m.attachment)}" style="max-width:220px;border-radius:8px;"></a>`
        : `<a href="${esc(m.attachment)}" download style="display:inline-flex;align-items:center;gap:4px;margin-top:6px;text-decoration:underline;font-size:12px;"><span class="ms ms-sm">download</span>${esc(m.attachment_name || 'Attachment')}</a>`;
    }
    return `
      <div class="msg-row ${m.is_mine ? 'mine' : 'theirs'}" data-msg-id="${m.id}">
        <div class="msg-bubble">${esc(m.body)}${attHtml}</div>
        <span class="msg-time">${esc(m.time_label)}</span>
      </div>
    `;
  };

  window.selectThread = async (id) => {
    activeId = Number(id);
    loadedConvId = activeId;
    lastMsgId = 0;
    convInput.value = activeId;
    composer.style.display = 'flex';
    const t = threadsCache.find(x => Number(x.id) === activeId);
    if (t) {
      t.unread_count = 0;
      renderHeader(t);
    }
    renderThreads();
    chatFeed.innerHTML = '<div class="muted" style="margin:auto;font-size:12.5px;">Loading messages...</div>';

    const res = await apiGet(`api/messages.php?action=messages&conversation_id=${activeId}`);
    if (!res.ok) {
      composer.style.display = 'none';
      chatFeed.innerHTML = `<div class="muted" style="margin:auto;font-size:12.5px;">${esc(res.error || 'Conversation unavailable')}</div>`;
      return;
    }
    if (!res.messages.length) {
      chatFeed.innerHTML = '<div class="muted" style="margin:auto;font-size:12.5px;">Say hello to start the conversation!</div>';
    } else {
      chatFeed.innerHTML = res.messages.map(msgHtml).join('');
      lastMsgId = Number(res.messages[res.messages.length - 1].id);
      chatFeed.scrollTop = chatFeed.scrollHeight;
    }
    updateNavBadge(res.total_unread);
  };

  const updateNavBadge = (n) => {
    const b = document.getElementById('navUnreadBadge');
    if (b) {
      b.textContent = n;
      b.style.display = n > 0 ? 'inline-flex' : 'none';
    }
  };

  const loadThreads = async () => {
    const res = await apiGet('api/messages.php?action=threads');
    if (!res.ok) return;
    threadsCache = res.threads;
    if (!activeId && threadsCache.length) {
      activeId = Number(threadsCache[0].id);
    }
    renderThreads();
    if (activeId) {
      const t = threadsCache.find(x => Number(x.id) === activeId);
      if (t) renderHeader(t);
      if (loadedConvId !== activeId) {
        selectThread(activeId);
      }
    }
    updateNavBadge(res.total_unread);
  };

  const pollNewMessages = async () => {
    if (!activeId || loadedConvId !== activeId) return;
    const res = await apiGet(`api/messages.php?action=messages&conversation_id=${activeId}&after_id=${lastMsgId}`);
    if (res.ok && res.messages.length) {
      if (lastMsgId === 0) chatFeed.innerHTML = '';
      res.messages.forEach(m => {
        if (!chatFeed.querySelector(`[data-msg-id="${m.id}"]`)) {
          chatFeed.insertAdjacentHTML('beforeend', msgHtml(m));
          lastMsgId = Math.max(lastMsgId, Number(m.id));
        }
      });
      chatFeed.scrollTop = chatFeed.scrollHeight;
      loadThreads();
    }
  };

  composer.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (sending || !activeId) return;
    if (!bodyInput.value.trim() && !fileInput.files.length) return;
    sending = true;

    const fd = new FormData(composer);
    fd.append('action', 'send');
    const res = await apiPost('api/messages.php', fd);
    sending = false;
    if (res.ok && res.message) {
      bodyInput.value = '';
      fileInput.value = '';
      fileTag.style.display = 'none';
      if (lastMsgId === 0) chatFeed.innerHTML = '';
      if (!chatFeed.querySelector(`[data-msg-id="${res.message.id}"]`)) {
        chatFeed.insertAdjacentHTML('beforeend', msgHtml(res.message));
        lastMsgId = Math.max(lastMsgId, Number(res.message.id));
      }
      chatFeed.scrollTop = chatFeed.scrollHeight;
      loadThreads();
    } else {
      toast(res.error || 'Could not send message', 'err');
    }
  });

  document.getElementById('threadSearch').addEventListener('input', renderThreads);

  const bootChat = () => {
    if (activeId) {
      composer.style.display = 'flex';
      convInput.value = activeId;
    }
    loadThreads();
    setInterval(pollNewMessages, 3000);
    setInterval(loadThreads, 9000);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootChat);
  } else {
    bootChat();
  }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

