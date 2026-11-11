<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? get_setting('site_name', 'Kraftr');
$activeNav = $activeNav ?? '';
$siteName = get_setting('site_name', 'Kraftr');
$siteLogoUrl = branding_asset_url('site_logo');
$siteFaviconUrl = branding_asset_url('site_favicon');
$showLogoName = get_setting('logo_show_name', '1') === '1';
$defaultFavicon = 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#4F46E5"/><stop offset="1" stop-color="#06B6D4"/></linearGradient></defs><rect width="32" height="32" rx="9" fill="url(#g)"/><path d="M17.5 6L9 18h6.2l-1.2 8L23 14h-6.2z" fill="#fff"/></svg>');
$announcement = trim(get_setting('announcement', ''));
$me = current_user();
$unreadCount = $me ? get_unread_count((int)$me['id']) : 0;
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
  <link rel="icon" href="<?= e($siteFaviconUrl !== '' ? $siteFaviconUrl : $defaultFavicon) ?>">
  <link rel="shortcut icon" href="<?= e($siteFaviconUrl !== '' ? $siteFaviconUrl : $defaultFavicon) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,300..700,0..1,-50..200" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/app.css?v=<?= filemtime(dirname(__DIR__) . '/assets/css/app.css') ?>">
</head>
<body data-auth="<?= $me ? '1' : '0' ?>">

<?php if ($announcement !== ''): ?>
  <div class="announce-bar">
    <span class="ms ms-sm ms-fill">campaign</span>
    <span><?= e($announcement) ?></span>
  </div>
<?php endif; ?>

<header class="topbar">
  <div class="container topbar-inner">
    <a href="index.php" class="brand">
      <?php if ($siteLogoUrl !== ''): ?>
        <img src="<?= e($siteLogoUrl) ?>" alt="<?= e($siteName) ?>" class="brand-logo-img">
        <?php if ($showLogoName): ?><span><?= e($siteName) ?></span><?php endif; ?>
      <?php else: ?>
        <span class="brand-mark"><span class="ms ms-sm ms-fill">bolt</span></span>
        <span><?= e($siteName) ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-search">
      <span class="ms ms-sm">search</span>
      <input id="globalNavSearch" type="search" value="<?= e($_GET['q'] ?? '') ?>" placeholder="Search gigs, skills, sellers...">
    </div>

    <nav class="nav-links">
      <a href="index.php" class="nav-link <?= $activeNav === 'explore' ? 'active' : '' ?>">
        <span class="ms ms-sm">explore</span>
        <span>Explore</span>
      </a>

      <?php if ($me): ?>
        <?php if ($me['role'] === 'buyer'): ?>
          <a href="buyer-panel.php" class="nav-link <?= $activeNav === 'buyer' || $activeNav === 'dashboard' ? 'active' : '' ?>">
            <span class="ms ms-sm">shopping_bag</span>
            <span>Buyer Panel</span>
          </a>
        <?php elseif ($me['role'] === 'seller'): ?>
          <a href="seller-panel.php" class="nav-link <?= $activeNav === 'seller' || $activeNav === 'dashboard' ? 'active' : '' ?>">
            <span class="ms ms-sm">storefront</span>
            <span>Seller Panel</span>
          </a>
        <?php endif; ?>

        <a href="messages.php" class="nav-link <?= $activeNav === 'messages' ? 'active' : '' ?>">
          <span class="ms ms-sm">chat_bubble</span>
          <span>Messages</span>
          <span id="navUnreadBadge" class="nav-badge" style="<?= $unreadCount > 0 ? '' : 'display:none' ?>"><?= $unreadCount ?></span>
        </a>

        <?php if ($me['role'] === 'admin'): ?>
          <a href="admin-panel.php" class="nav-link <?= $activeNav === 'admin' ? 'active' : '' ?>">
            <span class="ms ms-sm">admin_panel_settings</span>
            <span>Admin Panel</span>
          </a>
        <?php elseif ($me['role'] === 'seller'): ?>
          <a href="seller-panel.php?new_gig=1" class="btn btn-primary btn-sm" style="margin-left:4px;">
            <span class="ms ms-sm">add</span>
            <span>Post Gig</span>
          </a>
        <?php endif; ?>

        <span class="wallet-chip" title="Wallet Balance">
          <span class="ms ms-sm">account_balance_wallet</span>
          <span id="navWalletVal"><?= money($me['wallet_balance']) ?></span>
        </span>

        <div class="user-menu" id="userMenuWrap">
          <button type="button" class="avatar-btn" id="userMenuBtn">
            <img src="<?= e($me['avatar'] ?: 'assets/images/avatar-1.jpg') ?>" alt="<?= e($me['name']) ?>" class="avatar">
            <span style="font-size:12.5px;font-weight:700;max-width:95px;" class="truncate"><?= e($me['name']) ?></span>
            <span class="ms ms-sm">expand_more</span>
          </button>
          <div class="dropdown-menu" id="userDropdown">
            <div class="dropdown-head">
              <div style="font-weight:700;font-size:13px;" class="truncate"><?= e($me['name']) ?></div>
              <div class="muted" style="font-size:11.5px;text-transform:capitalize;"><?= e($me['role']) ?> · @<?= e($me['username']) ?></div>
            </div>
            <?php if ($me['role'] === 'admin'): ?>
              <a href="admin-panel.php" class="dropdown-item">
                <span class="ms ms-sm">admin_panel_settings</span> Admin Panel
              </a>
            <?php elseif ($me['role'] === 'seller'): ?>
              <a href="seller-panel.php" class="dropdown-item">
                <span class="ms ms-sm">storefront</span> Seller Panel
              </a>
            <?php else: ?>
              <a href="buyer-panel.php" class="dropdown-item">
                <span class="ms ms-sm">shopping_bag</span> Buyer Panel
              </a>
            <?php endif; ?>
            <a href="profile.php?u=<?= e($me['username']) ?>" class="dropdown-item">
              <span class="ms ms-sm">person</span> Profile & Settings
            </a>
            <button type="button" class="dropdown-item danger" onclick="logoutUser()">
              <span class="ms ms-sm">logout</span> Sign Out
            </button>
          </div>
        </div>
      <?php else: ?>
        <button type="button" class="btn btn-ghost btn-sm" onclick="openAuthModal('login')">Sign In</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAuthModal('register')">Join Free</button>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="page-main">

