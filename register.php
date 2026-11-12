<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = current_user();
if ($me) {
    header('Location: ' . panel_url($me));
    exit;
}

$pageTitle = 'Create Account';
$activeNav = 'register';
$regRole = (isset($_GET['role']) && $_GET['role'] === 'seller') ? 'seller' : 'buyer';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="auth-shell">
    <div class="card card-pad">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
        <span class="badge badge-indigo" style="width:42px;height:42px;padding:0;justify-content:center;border-radius:12px;">
          <span class="ms" style="font-size:22px;">person_add</span>
        </span>
        <div>
          <h1 style="margin:0;font-size:20px;font-weight:800;letter-spacing:-0.02em;">Create Free Account</h1>
          <div class="muted" style="font-size:12.5px;">Join Kraftr to hire skilled freelancers or offer your services.</div>
        </div>
      </div>

      <form id="registerPageForm" class="form-grid" enctype="multipart/form-data">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" class="control" placeholder="e.g. Taylor Morgan" required>
        </div>
        <div class="field">
          <label>Email Address</label>
          <input type="email" name="email" class="control" placeholder="taylor@example.com" autocomplete="email" required>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" class="control" minlength="6" placeholder="Min 6 characters" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label>Account Type</label>
            <select name="role" class="control" required>
              <option value="buyer" <?= $regRole === 'buyer' ? 'selected' : '' ?>>Buyer (Hire Talent)</option>
              <option value="seller" <?= $regRole === 'seller' ? 'selected' : '' ?>>Seller (Post Gigs &amp; Work)</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Profile Photo <span style="font-weight:normal;color:var(--muted);">(Optional)</span></label>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="control" style="padding:7px;">
        </div>
        <button type="submit" class="btn btn-primary btn-lg" id="regSubmitBtn">
          <span class="ms ms-sm">person_add</span>
          <span>Create Account</span>
        </button>
      </form>

      <div class="muted" style="border-top:1px solid var(--line);margin-top:18px;padding-top:14px;text-align:center;font-size:13px;">
        Already have an account?
        <a href="login.php?role=<?= e($regRole) ?>" style="color:var(--primary);font-weight:700;">Sign In</a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('registerPageForm');
  const btn = document.getElementById('regSubmitBtn');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ms ms-sm">sync</span> Creating account...'; }
    const fd = new FormData(form);
    fd.append('action', 'register');
    const res = await apiPost('api/auth.php', fd);
    if (res.ok) {
      toast('Account created successfully!');
      window.location.href = res.redirect || 'index.php';
    } else {
      if (btn) { btn.disabled = false; btn.innerHTML = '<span class="ms ms-sm">person_add</span> Create Account'; }
      toast(res.error || 'Registration failed', 'err');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

