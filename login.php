<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = current_user();
if ($me) {
    header('Location: ' . panel_url($me));
    exit;
}

$roleMeta = [
    'buyer' => [
        'title' => 'Buyer Sign In',
        'subtitle' => 'Hire talent, place orders & manage milestones.',
        'icon' => 'shopping_bag',
        'badge' => 'badge-cyan',
        'btn' => 'Sign In as Buyer',
        'placeholder' => 'buyer@example.com or username',
    ],
    'seller' => [
        'title' => 'Seller Sign In',
        'subtitle' => 'Manage gigs, active deliveries & wallet earnings.',
        'icon' => 'storefront',
        'badge' => 'badge-indigo',
        'btn' => 'Sign In as Seller',
        'placeholder' => 'seller@example.com or username',
    ],
    'admin' => [
        'title' => 'Admin Sign In',
        'subtitle' => 'Marketplace operations, user moderation & settings.',
        'icon' => 'admin_panel_settings',
        'badge' => 'badge-amber',
        'btn' => 'Sign In as Admin',
        'placeholder' => 'admin@domain.com or username',
    ],
];

$initialRole = strtolower(trim((string)($_GET['role'] ?? 'buyer')));
if (!isset($roleMeta[$initialRole])) {
    $initialRole = 'buyer';
}
$meta = $roleMeta[$initialRole];

$pageTitle = $meta['title'];
$activeNav = 'login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="auth-shell">
    <div class="card card-pad">

      <!-- Role Selector Buttons on this single page -->
      <div class="role-tabs" id="roleTabs">
        <?php foreach ($roleMeta as $k => $rm): ?>
          <button type="button" class="role-tab <?= $k === $initialRole ? 'active' : '' ?>" data-role="<?= e($k) ?>">
            <span class="ms ms-sm"><?= e($rm['icon']) ?></span>
            <span><?= ucfirst($k) ?></span>
          </button>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
        <span class="badge <?= e($meta['badge']) ?>" id="roleBadge" style="width:42px;height:42px;padding:0;justify-content:center;border-radius:12px;">
          <span class="ms" id="roleIcon" style="font-size:22px;"><?= e($meta['icon']) ?></span>
        </span>
        <div>
          <h1 id="roleTitle" style="margin:0;font-size:20px;font-weight:800;letter-spacing:-0.02em;"><?= e($meta['title']) ?></h1>
          <div id="roleSubtitle" class="muted" style="font-size:12.5px;"><?= e($meta['subtitle']) ?></div>
        </div>
      </div>

      <form id="loginPageForm" class="form-grid">
        <input type="hidden" name="expected_role" id="loginExpectedRole" value="<?= e($initialRole) ?>">
        <div class="field">
          <label>Email or Username</label>
          <input type="text" name="login" id="loginInput" class="control" placeholder="<?= e($meta['placeholder']) ?>" autocomplete="username" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" class="control" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" id="loginSubmitBtn">
          <span class="ms ms-sm">login</span>
          <span id="loginSubmitText"><?= e($meta['btn']) ?></span>
        </button>
      </form>

      <div id="roleFooter" class="muted" style="border-top:1px solid var(--line);margin-top:18px;padding-top:14px;text-align:center;font-size:13px;">
        <?php if ($initialRole === 'admin'): ?>
          <span>Platform administrator credentials required.</span>
        <?php else: ?>
          Need a <span id="roleWord"><?= e($initialRole) ?></span> account?
          <a href="register.php?role=<?= e($initialRole) ?>" id="regLink" style="color:var(--primary);font-weight:700;">Create Account</a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
const roleMeta = <?= json_encode($roleMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function setRole(role) {
  const data = roleMeta[role];
  if (!data) return;

  document.querySelectorAll('#roleTabs .role-tab').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.role === role);
  });

  const badge = document.getElementById('roleBadge');
  if (badge) {
    badge.className = 'badge ' + data.badge;
  }
  const icon = document.getElementById('roleIcon');
  if (icon) icon.textContent = data.icon;
  const title = document.getElementById('roleTitle');
  if (title) title.textContent = data.title;
  const subtitle = document.getElementById('roleSubtitle');
  if (subtitle) subtitle.textContent = data.subtitle;

  const expectedRoleInput = document.getElementById('loginExpectedRole');
  if (expectedRoleInput) expectedRoleInput.value = role;

  const loginInput = document.getElementById('loginInput');
  if (loginInput) loginInput.placeholder = data.placeholder;

  const submitText = document.getElementById('loginSubmitText');
  if (submitText) submitText.textContent = data.btn;

  const roleFooter = document.getElementById('roleFooter');
  if (roleFooter) {
    if (role === 'admin') {
      roleFooter.innerHTML = '<span>Platform administrator credentials required.</span>';
    } else {
      roleFooter.innerHTML = 'Need a ' + role + ' account? <a href="register.php?role=' + role + '" style="color:var(--primary);font-weight:700;">Create Account</a>';
    }
  }

  // Update URL query string without reloading page
  const url = new URL(window.location.href);
  url.searchParams.set('role', role);
  window.history.replaceState({}, '', url.toString());
}

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('#roleTabs .role-tab').forEach(btn => {
    btn.addEventListener('click', () => setRole(btn.dataset.role));
  });

  const form = document.getElementById('loginPageForm');
  const btn = document.getElementById('loginSubmitBtn');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ms ms-sm">sync</span> Signing in...'; }
    const fd = new FormData(form);
    fd.append('action', 'login');
    const res = await apiPost('api/auth.php', fd);
    if (res.ok) {
      window.location.href = res.redirect || 'index.php';
    } else {
      if (btn) {
        btn.disabled = false;
        const curRole = document.getElementById('loginExpectedRole')?.value || 'buyer';
        const curMeta = roleMeta[curRole] || roleMeta.buyer;
        btn.innerHTML = '<span class="ms ms-sm">login</span> <span id="loginSubmitText">' + curMeta.btn + '</span>';
      }
      toast(res.error || 'Sign in failed', 'err');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
