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
        <a href="login.php">Sign In</a>
        <a href="register.php">Create Account</a>
      <?php endif; ?>
    </div>
  </div>
</footer>

<script src="assets/js/app.js?v=<?= filemtime(dirname(__DIR__) . '/assets/js/app.js') ?>"></script>
</body>
</html>
