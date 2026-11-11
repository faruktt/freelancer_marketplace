</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div style="display:flex;align-items:center;gap:8px;">
      <?php $footerLogo = branding_asset_url('site_logo'); if ($footerLogo !== ''): ?>
        <img src="<?= e($footerLogo) ?>" alt="<?= e(get_setting('site_name', 'Kraftr')) ?>" class="brand-logo-footer">
      <?php endif; ?>
      <strong style="color:var(--ink);"><?= e(get_setting('site_name', 'Kraftr')) ?></strong>
      <span>· Freelance Services Marketplace</span>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
      <a href="index.php">Explore</a>
      <?php if (!empty($me)): ?>
        <a href="<?= e(panel_url($me)) ?>"><?= ucfirst($me['role']) ?> Panel</a>
        <a href="messages.php">Messages</a>
      <?php else: ?>
        <a href="buyer-login.php">Buyer Login</a>
        <a href="seller-login.php">Seller Login</a>
        <a href="admin-login.php">Admin Login</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<!-- Global Auth Modal -->
<div class="modal-backdrop" id="authModal">
  <div class="modal-card">
    <div class="modal-head">
      <div class="tabs">
        <button type="button" class="tab-btn active" data-auth-tab="login" onclick="switchAuthTab('login')">Sign In</button>
        <button type="button" class="tab-btn" data-auth-tab="register" onclick="switchAuthTab('register')">Create Account</button>
      </div>
      <button type="button" class="icon-btn" data-close-modal="authModal"><span class="ms ms-sm">close</span></button>
    </div>

    <div id="authLoginPane" style="display:grid;gap:14px;">
      <form id="authLoginForm" class="form-grid">
        <div class="field">
          <label>Email or Username</label>
          <input type="text" name="login" class="control" placeholder="you@example.com" required>
        </div>
        <div class="field">
          <label>Password</label>
          <input type="password" name="password" class="control" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
      </form>

      <div style="border-top:1px solid var(--line);padding-top:12px;">
        <div class="muted" style="font-size:11.5px;font-weight:700;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.04em;">Dedicated Role Portals</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">
          <a href="buyer-login.php" class="btn btn-outline btn-sm">
            <span class="ms ms-sm">shopping_bag</span> Buyer
          </a>
          <a href="seller-login.php" class="btn btn-outline btn-sm">
            <span class="ms ms-sm">storefront</span> Seller
          </a>
          <a href="admin-login.php" class="btn btn-outline btn-sm">
            <span class="ms ms-sm">admin_panel_settings</span> Admin
          </a>
        </div>
      </div>
    </div>

    <div id="authRegisterPane" style="display:none;gap:12px;">
      <form id="authRegisterForm" class="form-grid" enctype="multipart/form-data">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" class="control" placeholder="Taylor Morgan" required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" class="control" placeholder="taylor@domain.com" required>
        </div>
        <div class="form-row-2">
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" class="control" minlength="6" placeholder="Min 6 chars" required>
          </div>
          <div class="field">
            <label>Account Type</label>
            <select name="role" class="control">
              <option value="buyer">Buyer (Hire Talent)</option>
              <option value="seller">Seller (Post Gigs)</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>Profile Photo (optional)</label>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="control" style="padding:6px;">
        </div>
        <button type="submit" class="btn btn-primary btn-lg">Create Free Account</button>
      </form>
    </div>
  </div>
</div>

<script src="assets/js/app.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/app.js') ?>"></script>
<?php if (isset($_GET['auth']) && !$me): ?>
<script>openAuthModal(<?= json_encode($_GET['auth'] === 'register' ? 'register' : 'login') ?>);</script>
<?php endif; ?>
</body>
</html>

