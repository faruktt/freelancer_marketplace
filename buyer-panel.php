<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = require_role('buyer', 'login.php');
$uid = (int)$me['id'];
$pdo = db();

$orderFilter = $_GET['status'] ?? '';
$ordersStmt = $pdo->prepare('
    SELECT o.*, u.name AS peer_name, u.username AS peer_username, u.avatar AS peer_avatar, u.seller_level AS peer_level, u.is_verified AS peer_verified, g.cover_image
    FROM orders o
    JOIN users u ON u.id = o.seller_id
    LEFT JOIN gigs g ON g.id = o.gig_id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
');
$ordersStmt->execute([$uid]);
$allOrders = $ordersStmt->fetchAll();

$buyerOrders = $orderFilter !== ''
    ? array_values(array_filter($allOrders, fn($o) => $orderFilter === 'active'
        ? in_array($o['status'], ['pending', 'in_progress', 'delivered', 'revision'], true)
        : $o['status'] === $orderFilter))
    : $allOrders;

$savedStmt = $pdo->prepare('
    SELECT g.*, c.name AS category_name, u.name AS seller_name, u.username AS seller_username
    FROM favorites f
    JOIN gigs g ON g.id = f.gig_id
    JOIN categories c ON c.id = g.category_id
    JOIN users u ON u.id = g.seller_id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
');
$savedStmt->execute([$uid]);
$savedGigs = $savedStmt->fetchAll();

$bActive = count(array_filter($allOrders, fn($o) => in_array($o['status'], ['pending', 'in_progress', 'delivered', 'revision'], true)));
$bDelivered = count(array_filter($allOrders, fn($o) => $o['status'] === 'delivered'));
$bSpent = array_reduce($allOrders, fn($c, $o) => $c + ($o['status'] !== 'cancelled' ? (float)$o['total_paid'] : 0), 0.0);

$pageTitle = 'Buyer Panel';
$activeNav = 'buyer';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div>
      <h1 style="margin:0;font-size:20px;font-weight:800;letter-spacing:-0.02em;">Buyer Panel</h1>
      <div class="muted" style="font-size:12.5px;">Welcome back, <?= e($me['name']) ?> · Manage your orders, wallet &amp; saved talent</div>
    </div>

    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="openModal('topupModal')">
        <span class="ms ms-sm">add_card</span> Add Funds
      </button>
      <a href="messages.php" class="btn btn-outline btn-sm">
        <span class="ms ms-sm">chat_bubble</span> Messages
      </a>
      <a href="index.php" class="btn btn-primary btn-sm">
        <span class="ms ms-sm">explore</span> Browse Gigs
      </a>
    </div>
  </div>

  <!-- Buyer KPI Strip -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Active Orders</div>
        <div class="kpi-val"><?= $bActive ?></div>
      </div>
      <span class="kpi-icon badge-indigo"><span class="ms">receipt_long</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Ready for Review</div>
        <div class="kpi-val"><?= $bDelivered ?></div>
      </div>
      <span class="kpi-icon badge-cyan"><span class="ms">inventory_2</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Total Spent</div>
        <div class="kpi-val"><?= money_short($bSpent) ?></div>
      </div>
      <span class="kpi-icon badge-emerald"><span class="ms">payments</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Saved Gigs</div>
        <div class="kpi-val"><?= count($savedGigs) ?></div>
      </div>
      <span class="kpi-icon badge-rose"><span class="ms">favorite</span></span>
    </div>
  </div>

  <!-- Buyer Orders Table -->
  <div class="card" style="margin-bottom:18px;">
    <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
      <strong style="font-size:14.5px;">My Orders (<?= count($allOrders) ?>)</strong>
      <div class="tabs">
        <a href="buyer-panel.php" class="tab-btn <?= $orderFilter === '' ? 'active' : '' ?>">All</a>
        <a href="buyer-panel.php?status=active" class="tab-btn <?= $orderFilter === 'active' ? 'active' : '' ?>">Active</a>
        <a href="buyer-panel.php?status=delivered" class="tab-btn <?= $orderFilter === 'delivered' ? 'active' : '' ?>">Delivered</a>
        <a href="buyer-panel.php?status=completed" class="tab-btn <?= $orderFilter === 'completed' ? 'active' : '' ?>">Completed</a>
      </div>
    </div>
    <?php if (empty($buyerOrders)): ?>
      <div class="empty-state">
        <span class="ms">shopping_bag</span>
        <div style="font-weight:700;color:var(--ink);">No orders found</div>
        <div style="font-size:12.5px;margin:4px 0 12px;">Explore vetted freelancers and place an order in seconds.</div>
        <a href="index.php" class="btn btn-primary btn-sm">Explore Marketplace</a>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Service &amp; Seller</th>
              <th>Status</th>
              <th>Updated</th>
              <th>Total</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($buyerOrders as $o):
              [$stLabel, $stClass, $stIcon] = status_meta($o['status']);
            ?>
              <tr>
                <td class="mono" style="font-weight:700;font-size:12px;">
                  <a href="order.php?id=<?= (int)$o['id'] ?>" style="color:var(--primary);"><?= e($o['order_code']) ?></a>
                </td>
                <td>
                  <div style="font-weight:700;max-width:300px;" class="truncate">
                    <a href="order.php?id=<?= (int)$o['id'] ?>"><?= e($o['gig_title']) ?></a>
                  </div>
                  <div class="muted" style="font-size:12px;display:flex;align-items:center;gap:5px;margin-top:2px;">
                    <img src="<?= e(avatar_url($o['peer_avatar'])) ?>" alt="" class="avatar" style="width:16px;height:16px;">
                    <span><?= e($o['peer_name']) ?></span>
                    <?php if (($o['peer_level'] ?? '') === 'pro' && (int)($o['peer_verified'] ?? 0) === 1): ?>
                      <span class="ms ms-sm ms-fill" style="color:var(--primary);font-size:14px;" title="Pro Verified">verified</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td><span class="badge <?= $stClass ?>"><span class="ms ms-sm"><?= $stIcon ?></span><?= $stLabel ?></span></td>
                <td class="muted" style="font-size:12px;"><?= e(time_ago($o['updated_at'])) ?></td>
                <td class="mono" style="font-weight:700;"><?= money($o['total_paid']) ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php if ($o['status'] === 'delivered'): ?>
                    <button type="button" class="btn btn-emerald btn-sm" onclick="quickCompleteOrder(<?= (int)$o['id'] ?>)">Approve</button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-outline btn-sm" onclick="startChat(<?= (int)$o['seller_id'] ?>, <?= (int)$o['gig_id'] ?>, <?= (int)$o['id'] ?>)" title="Message Seller">
                    <span class="ms ms-sm">chat</span>
                  </button>
                  <a href="order.php?id=<?= (int)$o['id'] ?>" class="btn btn-dark btn-sm">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Saved Gigs -->
  <div class="card card-pad">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <strong style="font-size:14.5px;">Saved Gigs (<?= count($savedGigs) ?>)</strong>
    </div>
    <?php if (empty($savedGigs)): ?>
      <div class="muted" style="font-size:13px;">Heart any gig while browsing to bookmark it here.</div>
    <?php else: ?>
      <div class="gig-grid">
        <?php foreach ($savedGigs as $g): ?>
          <article class="gig-card">
            <div class="gig-thumb">
              <a href="gig.php?id=<?= (int)$g['id'] ?>"><img src="<?= e($g['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt=""></a>
              <button type="button" class="fav-btn active" onclick="toggleFav(<?= (int)$g['id'] ?>, this).then(() => location.reload())">
                <span class="ms ms-sm ms-fill">favorite</span>
              </button>
            </div>
            <div class="gig-body">
              <a href="gig.php?id=<?= (int)$g['id'] ?>" class="gig-title"><?= e($g['title']) ?></a>
              <div class="gig-meta-row">
                <span class="muted" style="font-size:12px;"><?= e($g['seller_name']) ?></span>
                <span class="price-tag"><?= money_short($g['price']) ?></span>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Wallet Top-up Modal -->
<div class="modal-backdrop" id="topupModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Add Wallet Funds</h3>
      <button type="button" class="icon-btn" data-close-modal="topupModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="topupForm" class="form-grid">
      <div class="field">
        <label>Amount ($ USD)</label>
        <input type="number" name="amount" class="control" min="10" max="5000" value="250" required>
      </div>
      <button type="submit" class="btn btn-emerald btn-lg">Add Funds to Wallet</button>
    </form>
  </div>
</div>

<script>
async function quickCompleteOrder(id) {
  const res = await apiPost('api/orders.php', { action: 'complete', order_id: id });
  if (res.ok) {
    toast('Order approved & funds released!');
    setTimeout(() => location.href = `order.php?id=${id}`, 500);
  } else {
    toast(res.error || 'Failed', 'err');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('topupForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'topup');
    const res = await apiPost('api/auth.php', fd);
    if (res.ok) {
      toast('Funds added to wallet!');
      setTimeout(() => location.reload(), 400);
    } else {
      toast(res.error || 'Failed', 'err');
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

