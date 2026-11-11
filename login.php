<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$role = $loginRole ?? (string)($_GET['role'] ?? 'buyer');
$roleMeta = [
    'buyer' => [
        'title' => 'Buyer Sign In',
        'subtitle' => 'Order services, message talent & track milestones.',
        'icon' => 'shopping_bag',
        'badge' => 'badge-cyan',
        'url' => 'buyer-login.php',
        'target' => 'buyer-panel.php',
        'placeholder' => 'you@example.com',
    ],
    'seller' => [
        'title' => 'Seller Sign In',
        'subtitle' => 'Manage gigs, active deliveries & wallet earnings.',
        'icon' => 'storefront',
        'badge' => 'badge-indigo',
        'url' => 'seller-login.php',
        'target' => 'seller-panel.php',
        'placeholder' => 'you@example.com',
    ],
    'admin' => [
        'title' => 'Admin Sign In',
        'subtitle' => 'Marketplace operations, moderation & settings.',
        'icon' => 'admin_panel_settings',
        'badge' => 'badge-amber',
        'url' => 'admin-login.php',
        'target' => 'admin-panel.php',
        'placeholder' => 'admin@domain.com',
    ],
];

if (!isset($roleMeta[$role])) {
    $role = 'buyer';
}
$meta = $roleMeta[$role];

$me = current_user();
if ($me && $me['role'] === $role) {
    header('Location: ' . $meta['target']);
    exit;
}

$pageTitle = $meta['title'];
$activeNav = '';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="auth-shell">
    <div class="card card-pad">
      <div class="role-tabs">
        <?php foreach ($roleMeta as $k => $rm): ?>
          <a href="<?= e($rm['url']) ?>" class="role-tab <?= $k === $role ? 'active' : '' ?>">
            <span class="ms ms-sm"><?= e($rm['icon']) ?></span>
            <span><?= ucfirst($k) ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
        <span class="badge <?= e($meta['badge']) ?>" style="width:40px;height:40px;padding:0;justify-content:center;border-radius:11px;">
          <span class="ms"><?= e($meta['icon']) ?></span>
        </span>
        <div>
          <h1 style="margin:0;font-size:19px;font-weight:800;letter-spacing:-0.02em;"><?= e($meta['title']) ?></h1>
          <div class="muted" style="font-size:12.5px;"><?= e($meta['subtitle']) ?></div>
        </div>
      </div>

      <?php if ($me): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 12px;border-radius:10px;background:var(--amber-soft);color:#92400E;font-size:12px;font-weight:600;margin-bottom:12px;">
          <span>Signed in as <?= e($me['name']) ?> (<?= e($me['role']) ?>)</span>
          <div style="display:flex;gap:6px;">
            <a href="<?= e(panel_url($me)) ?>" class="btn btn-outline btn-sm" style="height:26px;padding:0 9px;font-size:11.5px;">My Panel</a>
            <button type="button" class="btn btn-outline btn-sm" style="height:26px;padding:0 9px;font-size:11.5px;" onclick="logoutUser()">Sign Out</button>
          </div>
        </div>
      <?php endif; ?>

      <form id="roleLoginForm" class="form-grid">
        <input type="hidden" name="expected_role" value="<?= e($role) ?>">
        <div class="field">
          <label>Email or Username</label>
          <input type="text" name="login" class="control" placeholder="<?= e($meta['placeholder']) ?>" autocomplete="username" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" class="control" placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">
          <span class="ms ms-sm">login</span>
          <span>Sign In as <?= ucfirst($role) ?></span>
        </button>
      </form>

      <?php if ($role !== 'admin'): ?>
        <div class="muted" style="border-top:1px solid var(--line);margin-top:16px;padding-top:14px;text-align:center;font-size:12.5px;">
          Need a <?= e($role) ?> account?
          <a href="index.php?auth=register" style="color:var(--primary);font-weight:700;" onclick="event.preventDefault();openAuthModal('register');">Create Account</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('roleLoginForm');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action', 'login');
    const res = await apiPost('api/auth.php', fd);
    if (res.ok) {
      window.location.href = res.redirect || <?= json_encode($meta['target']) ?>;
    } else {
      toast(res.error || 'Sign in failed', 'err');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
