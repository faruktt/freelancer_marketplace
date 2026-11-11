<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$admin = require_admin();
$pdo = db();

$tab = $_GET['tab'] ?? 'overview';
$validTabs = ['overview', 'users', 'gigs', 'orders', 'categories', 'reviews', 'messages', 'payouts', 'settings'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'overview';
}

// Aggregate KPIs
$kpi = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM users) AS users_total,
      (SELECT COUNT(*) FROM users WHERE role = 'seller') AS sellers_total,
      (SELECT COUNT(*) FROM gigs WHERE status = 'active') AS gigs_active,
      (SELECT COUNT(*) FROM gigs WHERE status = 'pending') AS gigs_pending,
      (SELECT COUNT(*) FROM orders) AS orders_total,
      (SELECT COUNT(*) FROM orders WHERE status = 'disputed') AS orders_disputed,
      (SELECT COALESCE(SUM(total_paid), 0) FROM orders WHERE status <> 'cancelled') AS gmv,
      (SELECT COALESCE(SUM(platform_fee), 0) FROM orders WHERE status = 'completed') AS revenue,
      (SELECT COUNT(*) FROM payout_requests WHERE status = 'pending') AS payouts_pending
")->fetch();

$q = trim((string)($_GET['q'] ?? ''));

$pageTitle = 'Admin Control';
$activeNav = 'admin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
    <div>
      <h1 style="margin:0;font-size:20px;font-weight:800;letter-spacing:-0.02em;">Admin Control Center</h1>
      <div class="muted" style="font-size:12.5px;">Full platform management across users, gigs, orders, messaging & fees.</div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
      <?php if ((int)$kpi['orders_disputed'] > 0): ?>
        <a href="admin.php?tab=orders&status=disputed" class="badge badge-rose">
          <span class="ms ms-sm">gavel</span> <?= (int)$kpi['orders_disputed'] ?> Disputed
        </a>
      <?php endif; ?>
      <?php if ((int)$kpi['gigs_pending'] > 0): ?>
        <a href="admin.php?tab=gigs&status=pending" class="badge badge-amber">
          <span class="ms ms-sm">hourglass_top</span> <?= (int)$kpi['gigs_pending'] ?> Pending Gigs
        </a>
      <?php endif; ?>
      <?php if ((int)$kpi['payouts_pending'] > 0): ?>
        <a href="admin.php?tab=payouts" class="badge badge-indigo">
          <span class="ms ms-sm">payments</span> <?= (int)$kpi['payouts_pending'] ?> Payouts
        </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="admin-shell">
    <!-- Admin Sidebar -->
    <nav class="admin-nav">
      <?php
      $navItems = [
          ['overview', 'dashboard', 'Overview', null, 'badge-slate'],
          ['users', 'group', 'Buyers & Sellers', (int)$kpi['users_total'], 'badge-slate'],
          ['gigs', 'work', 'Gigs Approval', (int)$kpi['gigs_pending'] > 0 ? ((int)$kpi['gigs_pending'] . ' new') : (int)$kpi['gigs_active'], (int)$kpi['gigs_pending'] > 0 ? 'badge-amber' : 'badge-slate'],
          ['orders', 'receipt_long', 'Orders', (int)$kpi['orders_total'], 'badge-slate'],
          ['categories', 'category', 'Categories', null, 'badge-slate'],
          ['reviews', 'star', 'Reviews', null, 'badge-slate'],
          ['messages', 'forum', 'Messages', null, 'badge-slate'],
          ['payouts', 'account_balance', 'Withdraws', (int)$kpi['payouts_pending'] ?: null, 'badge-indigo'],
          ['settings', 'tune', 'Site Settings', null, 'badge-slate'],
      ];
      foreach ($navItems as [$id, $ic, $lbl, $cnt, $bCls]):
      ?>
        <a href="admin.php?tab=<?= $id ?>" class="admin-nav-btn <?= $tab === $id ? 'active' : '' ?>">
          <span style="display:inline-flex;align-items:center;gap:8px;">
            <span class="ms ms-sm"><?= $ic ?></span>
            <span><?= $lbl ?></span>
          </span>
          <?php if ($cnt !== null): ?>
            <span class="badge <?= $bCls ?>"><?= $cnt ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Admin Main Panel -->
    <div>
      <?php if ($tab === 'overview'):
        $recentOrders = $pdo->query('SELECT o.*, b.name AS buyer_name, s.name AS seller_name FROM orders o JOIN users b ON b.id = o.buyer_id JOIN users s ON s.id = o.seller_id ORDER BY o.created_at DESC LIMIT 6')->fetchAll();
        $statusBreakdown = $pdo->query('SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status')->fetchAll();
      ?>
        <div class="kpi-grid">
          <div class="kpi-card">
            <div><div class="kpi-label">Total Users</div><div class="kpi-val"><?= (int)$kpi['users_total'] ?></div></div>
            <span class="kpi-icon badge-indigo"><span class="ms">group</span></span>
          </div>
          <div class="kpi-card">
            <div><div class="kpi-label">Active Gigs</div><div class="kpi-val"><?= (int)$kpi['gigs_active'] ?></div></div>
            <span class="kpi-icon badge-cyan"><span class="ms">work</span></span>
          </div>
          <div class="kpi-card">
            <div><div class="kpi-label">Gross Volume</div><div class="kpi-val"><?= money_short($kpi['gmv']) ?></div></div>
            <span class="kpi-icon badge-emerald"><span class="ms">monitoring</span></span>
          </div>
          <div class="kpi-card">
            <div><div class="kpi-label">Platform Revenue</div><div class="kpi-val"><?= money($kpi['revenue']) ?></div></div>
            <span class="kpi-icon badge-amber"><span class="ms">payments</span></span>
          </div>
        </div>

        <div class="form-row-2" style="margin-bottom:16px;">
          <div class="card card-pad">
            <strong style="font-size:14px;display:block;margin-bottom:12px;">Order Status Mix</strong>
            <div style="display:grid;gap:9px;">
              <?php
              $tot = max(1, (int)$kpi['orders_total']);
              foreach ($statusBreakdown as $sb):
                [$sl, $sc] = status_meta($sb['status']);
                $pct = round(((int)$sb['cnt'] / $tot) * 100);
              ?>
                <div>
                  <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px;">
                    <span class="badge <?= $sc ?>"><?= $sl ?></span>
                    <span class="mono" style="font-weight:700;"><?= (int)$sb['cnt'] ?> (<?= $pct ?>%)</span>
                  </div>
                  <div style="height:6px;background:var(--surface-2);border-radius:99px;overflow:hidden;">
                    <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:99px;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card card-pad">
            <strong style="font-size:14px;display:block;margin-bottom:12px;">Quick Admin Shortcuts</strong>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <a href="admin.php?tab=users" class="btn btn-outline"><span class="ms ms-sm">group</span> Buyers &amp; Sellers</a>
              <a href="admin.php?tab=gigs&status=pending" class="btn btn-outline"><span class="ms ms-sm">verified</span> Approve Gigs (<?= (int)$kpi['gigs_pending'] ?>)</a>
              <a href="admin.php?tab=payouts" class="btn btn-outline"><span class="ms ms-sm">payments</span> Withdraws (<?= (int)$kpi['payouts_pending'] ?>)</a>
              <a href="admin.php?tab=settings" class="btn btn-outline"><span class="ms ms-sm">tune</span> Site Settings</a>
            </div>
          </div>
        </div>

        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:14px;">Latest Platform Orders</strong>
            <a href="admin.php?tab=orders" class="muted" style="font-size:12px;font-weight:600;">View all →</a>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Code</th><th>Gig</th><th>Buyer → Seller</th><th>Total</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($recentOrders as $o):
                  [$sl, $sc] = status_meta($o['status']);
                ?>
                  <tr>
                    <td class="mono" style="font-weight:700;"><a href="order.php?id=<?= (int)$o['id'] ?>" style="color:var(--primary);"><?= e($o['order_code']) ?></a></td>
                    <td style="max-width:240px;" class="truncate"><?= e($o['gig_title']) ?></td>
                    <td style="font-size:12px;"><?= e($o['buyer_name']) ?> → <?= e($o['seller_name']) ?></td>
                    <td class="mono" style="font-weight:700;"><?= money($o['total_paid']) ?></td>
                    <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'users'):
        $roleFilter = $_GET['role'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR username LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
        if ($roleFilter !== '') { $where[] = 'role = ?'; $params[] = $roleFilter; }
        $uStmt = $pdo->prepare('SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC');
        $uStmt->execute($params);
        $users = $uStmt->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center;">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <div class="tabs">
                <a href="admin.php?tab=users" class="tab-btn <?= $roleFilter === '' ? 'active' : '' ?>">All</a>
                <a href="admin.php?tab=users&role=buyer" class="tab-btn <?= $roleFilter === 'buyer' ? 'active' : '' ?>">Buyers</a>
                <a href="admin.php?tab=users&role=seller" class="tab-btn <?= $roleFilter === 'seller' ? 'active' : '' ?>">Sellers</a>
                <a href="admin.php?tab=users&role=admin" class="tab-btn <?= $roleFilter === 'admin' ? 'active' : '' ?>">Admins</a>
              </div>
              <form style="display:flex;gap:8px;" method="get">
                <input type="hidden" name="tab" value="users">
                <?php if ($roleFilter !== ''): ?><input type="hidden" name="role" value="<?= e($roleFilter) ?>"><?php endif; ?>
                <input type="search" name="q" value="<?= e($q) ?>" class="input-sm" placeholder="Search buyers &amp; sellers...">
              </form>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="openModal('adminCreateUserModal')">
              <span class="ms ms-sm">person_add</span> Add User
            </button>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>User</th><th>Role / Level</th><th>Wallet</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
              <tbody>
                <?php foreach ($users as $u):
                  [$sl, $sc] = status_meta($u['status']);
                ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:9px;">
                        <img src="<?= e($u['avatar'] ?: 'assets/images/avatar-1.jpg') ?>" class="avatar" alt="">
                        <div>
                          <div style="font-weight:700;display:flex;align-items:center;gap:4px;">
                            <a href="profile.php?u=<?= e($u['username']) ?>"><?= e($u['name']) ?></a>
                            <?php if ((int)$u['is_verified'] === 1): ?><span class="ms ms-sm ms-fill" style="color:var(--primary);">verified</span><?php endif; ?>
                          </div>
                          <div class="muted" style="font-size:11.5px;"><?= e($u['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <span class="badge badge-indigo" style="text-transform:capitalize;"><?= e($u['role']) ?></span>
                      <span class="muted" style="font-size:11.5px;">· <?= e($u['seller_level']) ?></span>
                    </td>
                    <td class="mono" style="font-weight:700;"><?= money($u['wallet_balance']) ?></td>
                    <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <button type="button" class="btn btn-outline btn-sm" onclick='openUserEdit(<?= json_encode([
                        "id" => (int)$u["id"], "name" => $u["name"], "role" => $u["role"],
                        "status" => $u["status"], "seller_level" => $u["seller_level"],
                        "is_verified" => (int)$u["is_verified"], "wallet_balance" => (float)$u["wallet_balance"]
                      ]) ?>)'>Edit</button>
                      <?php if ((int)$u['id'] !== (int)$admin['id']): ?>
                        <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('user_delete', {id: <?= (int)$u['id'] ?>}, 'Delete this user?')">
                          <span class="ms ms-sm">delete</span>
                        </button>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'gigs'):
        $stFilter = $_GET['status'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(g.title LIKE ? OR u.name LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
        if ($stFilter !== '') { $where[] = 'g.status = ?'; $params[] = $stFilter; }
        $gStmt = $pdo->prepare("SELECT g.*, c.name AS category_name, u.name AS seller_name FROM gigs g JOIN categories c ON c.id = g.category_id JOIN users u ON u.id = g.seller_id WHERE " . implode(' AND ', $where) . " ORDER BY (g.status = 'pending') DESC, g.id DESC");
        $gStmt->execute($params);
        $gigs = $gStmt->fetchAll();
        $allCats = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center;">
            <div class="tabs">
              <a href="admin.php?tab=gigs" class="tab-btn <?= $stFilter === '' ? 'active' : '' ?>">All</a>
              <a href="admin.php?tab=gigs&status=pending" class="tab-btn <?= $stFilter === 'pending' ? 'active' : '' ?>">Pending (<?= (int)$kpi['gigs_pending'] ?>)</a>
              <a href="admin.php?tab=gigs&status=active" class="tab-btn <?= $stFilter === 'active' ? 'active' : '' ?>">Active</a>
              <a href="admin.php?tab=gigs&status=paused" class="tab-btn <?= $stFilter === 'paused' ? 'active' : '' ?>">Paused</a>
              <a href="admin.php?tab=gigs&status=rejected" class="tab-btn <?= $stFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
            </div>
            <form style="display:flex;gap:8px;" method="get">
              <input type="hidden" name="tab" value="gigs">
              <?php if ($stFilter !== ''): ?><input type="hidden" name="status" value="<?= e($stFilter) ?>"><?php endif; ?>
              <input type="search" name="q" value="<?= e($q) ?>" class="input-sm" placeholder="Search gigs or sellers...">
            </form>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Gig</th><th>Seller</th><th>Price</th><th>Featured</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
              <tbody>
                <?php foreach ($gigs as $g):
                  [$sl, $sc] = status_meta($g['status']);
                ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:9px;">
                        <img src="<?= e($g['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" style="width:46px;height:30px;border-radius:6px;object-fit:cover;" alt="">
                        <div style="min-width:0;">
                          <a href="gig.php?id=<?= (int)$g['id'] ?>" style="font-weight:700;max-width:240px;display:block;" class="truncate"><?= e($g['title']) ?></a>
                          <span class="muted" style="font-size:11.5px;"><?= e($g['category_name']) ?> · <?= (int)$g['orders_count'] ?> orders</span>
                        </div>
                      </div>
                    </td>
                    <td><?= e($g['seller_name']) ?></td>
                    <td class="mono" style="font-weight:700;"><?= money_short($g['price']) ?></td>
                    <td>
                      <button type="button" class="btn btn-sm <?= (int)$g['is_featured'] ? 'btn-primary' : 'btn-outline' ?>" onclick="adminPost('gig_feature', {id: <?= (int)$g['id'] ?>})">
                        <span class="ms ms-sm <?= (int)$g['is_featured'] ? 'ms-fill' : '' ?>">star</span>
                      </button>
                    </td>
                    <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <button type="button" class="btn btn-outline btn-sm" onclick='openGigEdit(<?= json_encode([
                        "id" => (int)$g["id"], "title" => $g["title"], "category_id" => (int)$g["category_id"],
                        "price" => (float)$g["price"], "delivery_days" => (int)$g["delivery_days"],
                        "status" => $g["status"], "is_featured" => (int)$g["is_featured"]
                      ]) ?>)'>Edit</button>
                      <?php if ($g['status'] !== 'active'): ?>
                        <button type="button" class="btn btn-emerald btn-sm" onclick="adminPost('gig_status', {id: <?= (int)$g['id'] ?>, status: 'active'})">Approve</button>
                      <?php else: ?>
                        <button type="button" class="btn btn-outline btn-sm" onclick="adminPost('gig_status', {id: <?= (int)$g['id'] ?>, status: 'paused'})">Pause</button>
                      <?php endif; ?>
                      <button type="button" class="btn btn-outline btn-sm" onclick="adminPost('gig_status', {id: <?= (int)$g['id'] ?>, status: 'rejected'})">Reject</button>
                      <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('gig_delete', {id: <?= (int)$g['id'] ?>}, 'Delete gig?')">
                        <span class="ms ms-sm">delete</span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'orders'):
        $stFilter = $_GET['status'] ?? '';
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(o.order_code LIKE ? OR o.gig_title LIKE ? OR b.name LIKE ? OR s.name LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%", "%$q%"); }
        if ($stFilter !== '') { $where[] = 'o.status = ?'; $params[] = $stFilter; }
        $oStmt = $pdo->prepare('SELECT o.*, b.name AS buyer_name, s.name AS seller_name FROM orders o JOIN users b ON b.id = o.buyer_id JOIN users s ON s.id = o.seller_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.id DESC');
        $oStmt->execute($params);
        $orders = $oStmt->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <form style="display:flex;gap:8px;flex-wrap:wrap;" method="get">
              <input type="hidden" name="tab" value="orders">
              <input type="search" name="q" value="<?= e($q) ?>" class="input-sm" placeholder="Order code, buyer, seller...">
              <select name="status" class="select-sm" onchange="this.form.submit()">
                <option value="">All Status</option>
                <?php foreach (['pending', 'in_progress', 'delivered', 'revision', 'disputed', 'completed', 'cancelled'] as $st): ?>
                  <option value="<?= $st ?>" <?= $stFilter === $st ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $st)) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Code</th><th>Gig</th><th>Parties</th><th>Amount / Fee</th><th>Status</th><th style="text-align:right;">Resolve</th></tr></thead>
              <tbody>
                <?php foreach ($orders as $o):
                  [$sl, $sc] = status_meta($o['status']);
                ?>
                  <tr>
                    <td class="mono" style="font-weight:700;"><a href="order.php?id=<?= (int)$o['id'] ?>" style="color:var(--primary);"><?= e($o['order_code']) ?></a></td>
                    <td style="max-width:210px;" class="truncate"><?= e($o['gig_title']) ?></td>
                    <td style="font-size:12px;">B: <?= e($o['buyer_name']) ?><br><span class="muted">S: <?= e($o['seller_name']) ?></span></td>
                    <td class="mono" style="font-size:12px;"><strong><?= money($o['total_paid']) ?></strong><br><span class="muted">Fee <?= money($o['platform_fee']) ?></span></td>
                    <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <?php if (!in_array($o['status'], ['completed', 'cancelled'], true)): ?>
                        <button type="button" class="btn btn-emerald btn-sm" onclick="adminPost('order_resolve', {id: <?= (int)$o['id'] ?>, resolution: 'force_complete'}, 'Complete and pay seller?')">Complete</button>
                        <button type="button" class="btn btn-outline btn-sm" onclick="adminPost('order_resolve', {id: <?= (int)$o['id'] ?>, resolution: 'refund_cancel'}, 'Cancel and refund buyer?')">Refund</button>
                      <?php endif; ?>
                      <a href="order.php?id=<?= (int)$o['id'] ?>" class="btn btn-dark btn-sm">Open</a>
                      <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('order_delete', {id: <?= (int)$o['id'] ?>}, 'Delete order record?')">
                        <span class="ms ms-sm">delete</span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'categories'):
        $cats = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM gigs g WHERE g.category_id = c.id) AS gig_cnt FROM categories c ORDER BY c.sort_order ASC, c.id ASC')->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;">
            <strong style="font-size:14.5px;">Marketplace Categories (<?= count($cats) ?>)</strong>
            <button type="button" class="btn btn-primary btn-sm" onclick="openCategoryModal()">
              <span class="ms ms-sm">add</span> Add Category
            </button>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Category</th><th>Google Icon</th><th>Order</th><th>Gigs</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
              <tbody>
                <?php foreach ($cats as $c): ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:8px;">
                        <span class="kpi-icon" style="width:30px;height:30px;background:<?= e($c['accent_color']) ?>1A;color:<?= e($c['accent_color']) ?>;">
                          <span class="ms ms-sm"><?= e($c['icon']) ?></span>
                        </span>
                        <strong><?= e($c['name']) ?></strong>
                      </div>
                    </td>
                    <td class="mono" style="font-size:12px;"><?= e($c['icon']) ?></td>
                    <td class="mono"><?= (int)$c['sort_order'] ?></td>
                    <td class="mono"><?= (int)$c['gig_cnt'] ?></td>
                    <td><span class="badge <?= $c['is_active'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $c['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <button type="button" class="btn btn-outline btn-sm" onclick='openCategoryModal(<?= json_encode($c) ?>)'>Edit</button>
                      <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('category_delete', {id: <?= (int)$c['id'] ?>}, 'Delete category?')">
                        <span class="ms ms-sm">delete</span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'reviews'):
        $revs = $pdo->query('SELECT r.*, u.name AS buyer_name, g.title AS gig_title FROM reviews r JOIN users u ON u.id = r.buyer_id JOIN gigs g ON g.id = r.gig_id ORDER BY r.id DESC')->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);">
            <strong style="font-size:14.5px;">Platform Reviews (<?= count($revs) ?>)</strong>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Buyer</th><th>Gig</th><th>Rating</th><th>Comment</th><th>Status</th><th style="text-align:right;">Manage</th></tr></thead>
              <tbody>
                <?php foreach ($revs as $r): ?>
                  <tr>
                    <td style="font-weight:600;"><?= e($r['buyer_name']) ?></td>
                    <td style="max-width:200px;" class="truncate"><?= e($r['gig_title']) ?></td>
                    <td><span class="rating-pill"><span class="ms ms-fill">star</span> <?= (int)$r['rating'] ?></span></td>
                    <td style="max-width:260px;font-size:12.5px;"><?= e($r['comment']) ?></td>
                    <td><span class="badge <?= $r['is_visible'] ? 'badge-emerald' : 'badge-slate' ?>"><?= $r['is_visible'] ? 'Visible' : 'Hidden' ?></span></td>
                    <td style="text-align:right;white-space:nowrap;">
                      <button type="button" class="btn btn-outline btn-sm" onclick="adminPost('review_toggle', {id: <?= (int)$r['id'] ?>})"><?= $r['is_visible'] ? 'Hide' : 'Show' ?></button>
                      <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('review_delete', {id: <?= (int)$r['id'] ?>}, 'Delete review?')"><span class="ms ms-sm">delete</span></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'messages'):
        $msgs = $pdo->query('SELECT m.*, s.name AS sender_name, c.user_one_id, c.user_two_id FROM messages m JOIN users s ON s.id = m.sender_id JOIN conversations c ON c.id = m.conversation_id ORDER BY m.id DESC LIMIT 80')->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);">
            <strong style="font-size:14.5px;">Recent Messages Moderation</strong>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Sender</th><th>Message</th><th>Sent</th><th style="text-align:right;">Action</th></tr></thead>
              <tbody>
                <?php foreach ($msgs as $m): ?>
                  <tr>
                    <td style="font-weight:700;"><?= e($m['sender_name']) ?></td>
                    <td style="max-width:440px;"><?= e($m['body']) ?></td>
                    <td class="muted" style="font-size:12px;"><?= e(time_ago($m['created_at'])) ?></td>
                    <td style="text-align:right;">
                      <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('message_delete', {id: <?= (int)$m['id'] ?>}, 'Remove message?')">
                        <span class="ms ms-sm">delete</span>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($tab === 'payouts'):
        $stFilter = $_GET['status'] ?? '';
        $pSql = "SELECT p.*, u.name AS seller_name, u.email AS seller_email FROM payout_requests p JOIN users u ON u.id = p.seller_id";
        $pParams = [];
        if ($stFilter !== '' && in_array($stFilter, ['pending', 'approved', 'rejected'], true)) {
            $pSql .= ' WHERE p.status = ?';
            $pParams[] = $stFilter;
        }
        $pSql .= " ORDER BY (p.status = 'pending') DESC, p.id DESC";
        $pStmt = $pdo->prepare($pSql);
        $pStmt->execute($pParams);
        $pays = $pStmt->fetchAll();
      ?>
        <div class="card">
          <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <strong style="font-size:14.5px;">Withdraw &amp; Payout Requests</strong>
            <div class="tabs">
              <a href="admin.php?tab=payouts" class="tab-btn <?= $stFilter === '' ? 'active' : '' ?>">All</a>
              <a href="admin.php?tab=payouts&status=pending" class="tab-btn <?= $stFilter === 'pending' ? 'active' : '' ?>">Pending (<?= (int)$kpi['payouts_pending'] ?>)</a>
              <a href="admin.php?tab=payouts&status=approved" class="tab-btn <?= $stFilter === 'approved' ? 'active' : '' ?>">Approved</a>
              <a href="admin.php?tab=payouts&status=rejected" class="tab-btn <?= $stFilter === 'rejected' ? 'active' : '' ?>">Rejected</a>
            </div>
          </div>
          <?php if (empty($pays)): ?>
            <div class="empty-state"><span class="ms">payments</span><div>No payout requests yet.</div></div>
          <?php else: ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Seller</th><th>Method / Details</th><th>Amount</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                  <?php foreach ($pays as $p):
                    [$sl, $sc] = status_meta($p['status']);
                  ?>
                    <tr>
                      <td><strong><?= e($p['seller_name']) ?></strong><div class="muted" style="font-size:11.5px;"><?= e($p['seller_email']) ?></div></td>
                      <td><strong><?= e($p['method']) ?></strong><div class="muted" style="font-size:12px;"><?= e($p['account_info']) ?></div></td>
                      <td class="mono" style="font-weight:700;"><?= money($p['amount']) ?></td>
                      <td><span class="badge <?= $sc ?>"><?= $sl ?></span></td>
                      <td style="text-align:right;white-space:nowrap;">
                        <?php if ($p['status'] === 'pending'): ?>
                          <button type="button" class="btn btn-emerald btn-sm" onclick="adminPost('payout_action', {id: <?= (int)$p['id'] ?>, decision: 'approve'})">Approve</button>
                          <button type="button" class="btn btn-rose btn-sm" onclick="adminPost('payout_action', {id: <?= (int)$p['id'] ?>, decision: 'reject'}, 'Reject and refund to seller wallet?')">Reject</button>
                        <?php else: ?>
                          <span class="muted" style="font-size:12px;">Processed</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <?php elseif ($tab === 'settings'):
        $s = get_settings();
        $curLogo = branding_asset_url('site_logo');
        $curFavicon = branding_asset_url('site_favicon');
      ?>
        <div class="card card-pad">
          <h3 style="margin:0 0 14px;font-size:15px;font-weight:800;">Platform Configuration</h3>
          <form id="adminSettingsForm" class="form-grid" style="max-width:680px;" enctype="multipart/form-data">
            <div class="form-row-2">
              <div class="field">
                <label>Website Logo (PNG, JPG, WEBP)</label>
                <div class="brand-preview-card">
                  <div class="brand-preview-thumb wide" id="logoPreviewBox">
                    <?php if ($curLogo !== ''): ?>
                      <img src="<?= e($curLogo) ?>" alt="Logo preview">
                    <?php else: ?>
                      <span class="brand-mark"><span class="ms ms-sm ms-fill">bolt</span></span>
                    <?php endif; ?>
                  </div>
                  <div style="flex:1;min-width:0;display:grid;gap:6px;">
                    <input type="file" name="site_logo" id="siteLogoInput" accept="image/png,image/jpeg,image/webp" class="control" style="padding:5px 8px;font-size:12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                      <label style="display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;color:var(--ink-2);text-transform:none;letter-spacing:0;cursor:pointer;">
                        <input type="checkbox" name="logo_show_name" value="1" <?= ($s['logo_show_name'] ?? '1') === '1' ? 'checked' : '' ?>>
                        Show name beside logo
                      </label>
                      <button type="button" class="btn btn-ghost btn-sm" id="resetLogoBtn" style="height:24px;padding:0 8px;font-size:11.5px;color:var(--rose);" <?= $curLogo === '' ? 'disabled' : '' ?>>Reset</button>
                    </div>
                  </div>
                </div>
                <input type="hidden" name="remove_logo" id="removeLogoInput" value="0">
              </div>

              <div class="field">
                <label>Favicon (PNG, ICO, JPG, WEBP)</label>
                <div class="brand-preview-card">
                  <div class="brand-preview-thumb" id="faviconPreviewBox">
                    <?php if ($curFavicon !== ''): ?>
                      <img src="<?= e($curFavicon) ?>" alt="Favicon preview" style="width:28px;height:28px;">
                    <?php else: ?>
                      <span class="brand-mark"><span class="ms ms-sm ms-fill">bolt</span></span>
                    <?php endif; ?>
                  </div>
                  <div style="flex:1;min-width:0;display:grid;gap:6px;">
                    <input type="file" name="site_favicon" id="siteFaviconInput" accept=".png,.ico,.jpg,.jpeg,.webp,image/png,image/x-icon,image/vnd.microsoft.icon,image/jpeg,image/webp" class="control" style="padding:5px 8px;font-size:12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                      <span class="muted" style="font-size:11.5px;">Browser tab icon (32x32+ recommended)</span>
                      <button type="button" class="btn btn-ghost btn-sm" id="resetFaviconBtn" style="height:24px;padding:0 8px;font-size:11.5px;color:var(--rose);" <?= $curFavicon === '' ? 'disabled' : '' ?>>Reset</button>
                    </div>
                  </div>
                </div>
                <input type="hidden" name="remove_favicon" id="removeFaviconInput" value="0">
              </div>
            </div>

            <div class="form-row-2">
              <div class="field">
                <label>Marketplace Name</label>
                <input type="text" name="site_name" class="control" value="<?= e($s['site_name'] ?? 'Kraftr') ?>" required>
              </div>
              <div class="field">
                <label>Platform Commission (%)</label>
                <input type="number" name="commission_pct" class="control" min="0" max="50" value="<?= e($s['commission_pct'] ?? '10') ?>" required>
              </div>
            </div>
            <div class="form-row-2">
              <div class="field">
                <label>Min Gig Price ($)</label>
                <input type="number" name="min_gig_price" class="control" min="1" value="<?= e($s['min_gig_price'] ?? '5') ?>">
              </div>
              <div class="field">
                <label>Max Gig Price ($)</label>
                <input type="number" name="max_gig_price" class="control" min="10" value="<?= e($s['max_gig_price'] ?? '10000') ?>">
              </div>
            </div>
            <div class="field">
              <label>Global Announcement Banner (leave blank to hide)</label>
              <input type="text" name="announcement" class="control" value="<?= e($s['announcement'] ?? '') ?>" placeholder="e.g. Zero fee weekend for verified sellers!">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;">
              <input type="checkbox" name="auto_approve_gigs" value="1" <?= ($s['auto_approve_gigs'] ?? '0') === '1' ? 'checked' : '' ?>>
              Auto-approve new seller gigs immediately (uncheck for Admin approval)
            </label>
            <div>
              <button type="submit" class="btn btn-primary btn-lg">Save Platform Settings</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Admin Create User Modal -->
<div class="modal-backdrop" id="adminCreateUserModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Add User</h3>
      <button type="button" class="icon-btn" data-close-modal="adminCreateUserModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="adminCreateUserForm" class="form-grid">
      <div class="field"><label>Name</label><input type="text" name="name" class="control" required></div>
      <div class="field"><label>Email</label><input type="email" name="email" class="control" required></div>
      <div class="form-row-2">
        <div class="field"><label>Password</label><input type="password" name="password" class="control" minlength="6" placeholder="Min 6 chars" required></div>
        <div class="field"><label>Role</label><select name="role" class="control"><option value="buyer">Buyer</option><option value="seller">Seller</option><option value="admin">Admin</option></select></div>
      </div>
      <div class="field"><label>Initial Wallet ($)</label><input type="number" name="wallet_balance" class="control" value="500"></div>
      <button type="submit" class="btn btn-primary btn-lg">Create User</button>
    </form>
  </div>
</div>

<!-- Admin Edit User Modal -->
<div class="modal-backdrop" id="adminEditUserModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Edit User <span id="editUserTitle"></span></h3>
      <button type="button" class="icon-btn" data-close-modal="adminEditUserModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="adminEditUserForm" class="form-grid">
      <input type="hidden" name="id" id="euId">
      <div class="form-row-2">
        <div class="field"><label>Role</label><select name="role" id="euRole" class="control"><option value="buyer">Buyer</option><option value="seller">Seller</option><option value="admin">Admin</option></select></div>
        <div class="field"><label>Status</label><select name="status" id="euStatus" class="control"><option value="active">Active</option><option value="suspended">Suspended</option><option value="banned">Banned</option></select></div>
      </div>
      <div class="form-row-2">
        <div class="field"><label>Seller Level</label><select name="seller_level" id="euLevel" class="control"><option value="new">New</option><option value="rising">Rising</option><option value="top_rated">Top Rated</option><option value="pro">Pro</option></select></div>
        <div class="field"><label>Wallet Balance ($)</label><input type="number" step="0.01" name="wallet_balance" id="euWallet" class="control"></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;">
        <input type="checkbox" name="is_verified" id="euVerified" value="1"> Verified Badge
      </label>
      <button type="submit" class="btn btn-primary btn-lg">Update User</button>
    </form>
  </div>
</div>

<!-- Admin Edit Gig Modal -->
<?php if ($tab === 'gigs'): ?>
<div class="modal-backdrop" id="adminEditGigModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Edit Gig</h3>
      <button type="button" class="icon-btn" data-close-modal="adminEditGigModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="adminEditGigForm" class="form-grid">
      <input type="hidden" name="id" id="egId">
      <div class="field"><label>Title</label><input type="text" name="title" id="egTitle" class="control" required></div>
      <div class="form-row-2">
        <div class="field"><label>Category</label><select name="category_id" id="egCat" class="control"><?php foreach ($allCats as $ac): ?><option value="<?= (int)$ac['id'] ?>"><?= e($ac['name']) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Status</label><select name="status" id="egStatus" class="control"><option value="active">Active</option><option value="pending">Pending</option><option value="paused">Paused</option><option value="rejected">Rejected</option></select></div>
      </div>
      <div class="form-row-2">
        <div class="field"><label>Price ($)</label><input type="number" step="0.01" name="price" id="egPrice" class="control" required></div>
        <div class="field"><label>Delivery Days</label><input type="number" name="delivery_days" id="egDays" class="control" min="1" required></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;">
        <input type="checkbox" name="is_featured" id="egFeatured" value="1"> Featured Gig
      </label>
      <button type="submit" class="btn btn-primary btn-lg">Save Gig</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Admin Category Modal -->
<div class="modal-backdrop" id="adminCatModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3 id="catModalTitle">Save Category</h3>
      <button type="button" class="icon-btn" data-close-modal="adminCatModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="adminCatForm" class="form-grid">
      <input type="hidden" name="id" id="catId">
      <div class="field"><label>Category Name</label><input type="text" name="name" id="catName" class="control" required></div>
      <div class="form-row-2">
        <div class="field"><label>Google Icon Name (e.g. code, palette)</label><input type="text" name="icon" id="catIcon" class="control" value="category" required></div>
        <div class="field"><label>Accent Color</label><input type="color" name="accent_color" id="catColor" class="control" value="#4F46E5" style="height:38px;padding:3px;"></div>
      </div>
      <div class="form-row-2">
        <div class="field"><label>Sort Order</label><input type="number" name="sort_order" id="catOrder" class="control" value="0"></div>
        <div class="field" style="display:flex;align-items:flex-end;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:8px;">
            <input type="checkbox" name="is_active" id="catActive" value="1" checked> Active
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Save Category</button>
    </form>
  </div>
</div>

<script>
async function adminPost(action, data = {}, confirmMsg = '') {
  if (confirmMsg && !confirm(confirmMsg)) return;
  const res = await apiPost('api/admin.php', { action, ...data });
  if (res.ok) {
    toast(res.message || 'Updated');
    setTimeout(() => location.reload(), 350);
  } else {
    toast(res.error || 'Action failed', 'err');
  }
}

function openUserEdit(u) {
  document.getElementById('editUserTitle').textContent = u.name;
  document.getElementById('euId').value = u.id;
  document.getElementById('euRole').value = u.role;
  document.getElementById('euStatus').value = u.status;
  document.getElementById('euLevel').value = u.seller_level;
  document.getElementById('euWallet').value = u.wallet_balance;
  document.getElementById('euVerified').checked = Number(u.is_verified) === 1;
  openModal('adminEditUserModal');
}

document.getElementById('adminEditUserForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'user_update');
  const res = await apiPost('api/admin.php', fd);
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
});

document.getElementById('adminCreateUserForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'user_create');
  const res = await apiPost('api/admin.php', fd);
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
});

function openGigEdit(g) {
  document.getElementById('egId').value = g.id;
  document.getElementById('egTitle').value = g.title;
  document.getElementById('egCat').value = g.category_id;
  document.getElementById('egStatus').value = g.status;
  document.getElementById('egPrice').value = g.price;
  document.getElementById('egDays').value = g.delivery_days;
  document.getElementById('egFeatured').checked = Number(g.is_featured) === 1;
  openModal('adminEditGigModal');
}

document.getElementById('adminEditGigForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'gig_update');
  const res = await apiPost('api/admin.php', fd);
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
});

function openCategoryModal(c = null) {
  document.getElementById('catModalTitle').textContent = c ? 'Edit Category' : 'Add Category';
  document.getElementById('catId').value = c ? c.id : '';
  document.getElementById('catName').value = c ? c.name : '';
  document.getElementById('catIcon').value = c ? c.icon : 'category';
  document.getElementById('catColor').value = c ? c.accent_color : '#4F46E5';
  document.getElementById('catOrder').value = c ? c.sort_order : '0';
  document.getElementById('catActive').checked = c ? Number(c.is_active) === 1 : true;
  openModal('adminCatModal');
}

document.getElementById('adminCatForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'category_save');
  const res = await apiPost('api/admin.php', fd);
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
});

const bindBrandingPicker = (inputId, previewId, resetId, removeId, isFavicon = false) => {
  const input = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  const resetBtn = document.getElementById(resetId);
  const removeInp = document.getElementById(removeId);
  if (!input || !preview || !resetBtn || !removeInp) return;

  const defaultMark = '<span class="brand-mark"><span class="ms ms-sm ms-fill">bolt</span></span>';
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    if (!file) return;
    removeInp.value = '0';
    resetBtn.disabled = false;
    const url = URL.createObjectURL(file);
    preview.innerHTML = `<img src="${url}" alt="Preview"${isFavicon ? ' style="width:28px;height:28px;"' : ''}>`;
  });

  resetBtn.addEventListener('click', () => {
    input.value = '';
    removeInp.value = '1';
    preview.innerHTML = defaultMark;
    resetBtn.disabled = true;
  });
};

bindBrandingPicker('siteLogoInput', 'logoPreviewBox', 'resetLogoBtn', 'removeLogoInput', false);
bindBrandingPicker('siteFaviconInput', 'faviconPreviewBox', 'resetFaviconBtn', 'removeFaviconInput', true);

document.getElementById('adminSettingsForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'settings_save');
  const res = await apiPost('api/admin.php', fd);
  if (res.ok) {
    toast('Settings saved');
    setTimeout(() => location.reload(), 350);
  } else {
    toast(res.error || 'Failed', 'err');
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

