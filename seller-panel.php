<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = require_role('seller', 'seller-login.php');
$uid = (int)$me['id'];
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

$sellerGigsStmt = $pdo->prepare('
    SELECT g.*, c.name AS category_name
    FROM gigs g
    JOIN categories c ON c.id = g.category_id
    WHERE g.seller_id = ?
    ORDER BY g.created_at DESC
');
$sellerGigsStmt->execute([$uid]);
$sellerGigs = $sellerGigsStmt->fetchAll();

$sellerOrdersStmt = $pdo->prepare('
    SELECT o.*, u.name AS peer_name, u.username AS peer_username, u.avatar AS peer_avatar
    FROM orders o
    JOIN users u ON u.id = o.buyer_id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC
');
$sellerOrdersStmt->execute([$uid]);
$sellerOrders = $sellerOrdersStmt->fetchAll();

$payoutsStmt = $pdo->prepare('SELECT * FROM payout_requests WHERE seller_id = ? ORDER BY created_at DESC LIMIT 12');
$payoutsStmt->execute([$uid]);
$payouts = $payoutsStmt->fetchAll();

$sActiveOrders = count(array_filter($sellerOrders, fn($o) => in_array($o['status'], ['pending', 'in_progress', 'delivered', 'revision'], true)));
$sActiveGigs = count(array_filter($sellerGigs, fn($g) => $g['status'] === 'active'));

$pageTitle = 'Seller Panel';
$activeNav = 'seller';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div>
      <h1 style="margin:0;font-size:20px;font-weight:800;letter-spacing:-0.02em;">Seller Panel</h1>
      <div class="muted" style="font-size:12.5px;">Welcome back, <?= e($me['name']) ?> · Manage your gigs, deliveries &amp; withdrawals</div>
    </div>

    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;">
      <button type="button" class="btn btn-outline btn-sm" onclick="openModal('payoutModal')">
        <span class="ms ms-sm">payments</span> Withdraw
      </button>
      <a href="messages.php" class="btn btn-outline btn-sm">
        <span class="ms ms-sm">chat_bubble</span> Messages
      </a>
      <button type="button" class="btn btn-primary btn-sm" onclick="openGigForm()">
        <span class="ms ms-sm">add</span> Post New Gig
      </button>
    </div>
  </div>

  <!-- Seller KPI Strip -->
  <div class="kpi-grid">
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Available Balance</div>
        <div class="kpi-val"><?= money($me['wallet_balance']) ?></div>
      </div>
      <span class="kpi-icon badge-emerald"><span class="ms">account_balance_wallet</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Total Earnings</div>
        <div class="kpi-val"><?= money_short($me['earnings_total']) ?></div>
      </div>
      <span class="kpi-icon badge-indigo"><span class="ms">trending_up</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Active Orders</div>
        <div class="kpi-val"><?= $sActiveOrders ?></div>
      </div>
      <span class="kpi-icon badge-cyan"><span class="ms">bolt</span></span>
    </div>
    <div class="kpi-card">
      <div>
        <div class="kpi-label">Active Gigs / Rating</div>
        <div class="kpi-val"><?= $sActiveGigs ?> · ★<?= number_format((float)$me['rating_avg'], 1) ?></div>
      </div>
      <span class="kpi-icon badge-amber"><span class="ms ms-fill">star</span></span>
    </div>
  </div>

  <!-- Client Orders -->
  <div class="card" style="margin-bottom:18px;">
    <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
      <strong style="font-size:14.5px;">Client Orders (<?= count($sellerOrders) ?>)</strong>
      <span class="muted" style="font-size:12px;">Deliver work to earn and withdraw payouts</span>
    </div>
    <?php if (empty($sellerOrders)): ?>
      <div class="empty-state">
        <span class="ms">inbox</span>
        <div style="font-weight:700;color:var(--ink);">No client orders yet</div>
        <div style="font-size:12.5px;margin-top:4px;">Create clear gigs to start receiving buyer orders.</div>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Order</th>
              <th>Gig &amp; Buyer</th>
              <th>Status</th>
              <th>Net Payout</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sellerOrders as $o):
              [$stLabel, $stClass, $stIcon] = status_meta($o['status']);
            ?>
              <tr>
                <td class="mono" style="font-weight:700;font-size:12px;">
                  <a href="order.php?id=<?= (int)$o['id'] ?>" style="color:var(--primary);"><?= e($o['order_code']) ?></a>
                </td>
                <td>
                  <div style="font-weight:700;max-width:280px;" class="truncate"><?= e($o['gig_title']) ?></div>
                  <div class="muted" style="font-size:12px;display:flex;align-items:center;gap:5px;margin-top:2px;">
                    <img src="<?= e($o['peer_avatar'] ?: 'assets/images/avatar-5.jpg') ?>" alt="" class="avatar" style="width:16px;height:16px;">
                    <span><?= e($o['peer_name']) ?> · <?= e(time_ago($o['created_at'])) ?></span>
                  </div>
                </td>
                <td><span class="badge <?= $stClass ?>"><span class="ms ms-sm"><?= $stIcon ?></span><?= $stLabel ?></span></td>
                <td class="mono" style="font-weight:700;"><?= money($o['seller_net']) ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php if ($o['status'] === 'pending'): ?>
                    <button type="button" class="btn btn-primary btn-sm" onclick="sellerStartOrder(<?= (int)$o['id'] ?>)">Start</button>
                  <?php endif; ?>
                  <?php if (in_array($o['status'], ['pending', 'in_progress', 'revision'], true)): ?>
                    <button type="button" class="btn btn-emerald btn-sm" onclick="openDeliverModal(<?= (int)$o['id'] ?>, '<?= e($o['order_code']) ?>')">Deliver</button>
                  <?php endif; ?>
                  <a href="order.php?id=<?= (int)$o['id'] ?>" class="btn btn-dark btn-sm">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Seller Gigs -->
  <div class="card" style="margin-bottom:18px;">
    <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
      <strong style="font-size:14.5px;">My Gigs (<?= count($sellerGigs) ?>)</strong>
      <button type="button" class="btn btn-primary btn-sm" onclick="openGigForm()">
        <span class="ms ms-sm">add</span> New Gig
      </button>
    </div>
    <?php if (empty($sellerGigs)): ?>
      <div class="empty-state">
        <span class="ms">storefront</span>
        <div style="font-weight:700;color:var(--ink);">Create your first gig</div>
        <div style="font-size:12.5px;margin:4px 0 12px;">Set your scope, delivery time, and fixed price.</div>
        <button type="button" class="btn btn-primary btn-sm" onclick="openGigForm()">Post a Gig</button>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Gig</th>
              <th>Category</th>
              <th>Price</th>
              <th>Stats</th>
              <th>Status</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($sellerGigs as $g):
              [$stLabel, $stClass, $stIcon] = status_meta($g['status']);
            ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= e($g['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt="" style="width:52px;height:34px;border-radius:6px;object-fit:cover;border:1px solid var(--line);">
                    <div style="min-width:0;">
                      <a href="gig.php?id=<?= (int)$g['id'] ?>" style="font-weight:700;display:block;max-width:280px;" class="truncate"><?= e($g['title']) ?></a>
                      <span class="muted" style="font-size:11.5px;"><?= (int)$g['delivery_days'] ?>d delivery · <?= (int)$g['revisions'] ?> rev</span>
                    </div>
                  </div>
                </td>
                <td><?= e($g['category_name']) ?></td>
                <td class="mono" style="font-weight:700;"><?= money_short($g['price']) ?></td>
                <td class="muted" style="font-size:12px;">★ <?= number_format((float)$g['rating_avg'], 1) ?> · <?= (int)$g['orders_count'] ?> orders · <?= (int)$g['views_count'] ?> views</td>
                <td><span class="badge <?= $stClass ?>"><span class="ms ms-sm"><?= $stIcon ?></span><?= $stLabel ?></span></td>
                <td style="text-align:right;white-space:nowrap;">
                  <button type="button" class="btn btn-outline btn-sm" onclick="openGigForm(<?= (int)$g['id'] ?>)">Edit</button>
                  <?php if (in_array($g['status'], ['active', 'paused'], true)): ?>
                    <button type="button" class="btn btn-outline btn-sm" onclick="toggleGigStatus(<?= (int)$g['id'] ?>)">
                      <?= $g['status'] === 'active' ? 'Pause' : 'Activate' ?>
                    </button>
                  <?php endif; ?>
                  <button type="button" class="btn btn-rose btn-sm" onclick="deleteGig(<?= (int)$g['id'] ?>)">
                    <span class="ms ms-sm">delete</span>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Withdraw Requests History -->
  <div class="card">
    <div style="padding:14px 16px;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;">
      <strong style="font-size:14.5px;">Withdraw &amp; Payout History (<?= count($payouts) ?>)</strong>
      <button type="button" class="btn btn-outline btn-sm" onclick="openModal('payoutModal')">
        <span class="ms ms-sm">payments</span> Request Withdraw
      </button>
    </div>
    <?php if (empty($payouts)): ?>
      <div class="muted" style="padding:20px 16px;font-size:13px;">No withdrawal requests yet.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Method</th><th>Destination</th><th>Amount</th><th>Status</th><th>Date</th></tr>
          </thead>
          <tbody>
            <?php foreach ($payouts as $p):
              [$stLabel, $stClass, $stIcon] = status_meta($p['status']);
            ?>
              <tr>
                <td style="font-weight:600;"><?= e($p['method']) ?></td>
                <td class="muted"><?= e($p['account_info']) ?></td>
                <td class="mono" style="font-weight:700;"><?= money($p['amount']) ?></td>
                <td><span class="badge <?= $stClass ?>"><?= $stLabel ?></span></td>
                <td class="muted" style="font-size:12px;"><?= e(time_ago($p['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Post / Edit Gig Modal -->
<div class="modal-backdrop" id="gigFormModal">
  <div class="modal-card modal-lg">
    <div class="modal-head">
      <h3 id="gigModalTitle">Post New Gig</h3>
      <button type="button" class="icon-btn" data-close-modal="gigFormModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="gigForm" class="form-grid" enctype="multipart/form-data">
      <input type="hidden" name="id" id="gigIdInput" value="">
      <div class="field">
        <label>Gig Title</label>
        <input type="text" name="title" id="gigTitleInput" class="control" placeholder="e.g. Build a modern responsive SaaS dashboard in PHP & JS" required>
      </div>
      <div class="form-row-2">
        <div class="field">
          <label>Category</label>
          <select name="category_id" id="gigCatInput" class="control" required>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Price ($ USD)</label>
          <input type="number" name="price" id="gigPriceInput" class="control" min="5" max="10000" step="1" value="95" required>
        </div>
      </div>
      <div class="form-row-2">
        <div class="field">
          <label>Delivery Days</label>
          <input type="number" name="delivery_days" id="gigDaysInput" class="control" min="1" max="90" value="3" required>
        </div>
        <div class="field">
          <label>Revisions Included</label>
          <input type="number" name="revisions" id="gigRevInput" class="control" min="0" max="20" value="2" required>
        </div>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" id="gigDescInput" class="control" placeholder="Outline scope, deliverables, and workflow..." required></textarea>
      </div>
      <div class="form-row-2">
        <div class="field">
          <label>What's Included (one per line)</label>
          <textarea name="features" id="gigFeatInput" class="control" placeholder="Responsive layout&#10;Source code included&#10;Deployment support"></textarea>
        </div>
        <div class="field">
          <label>Tags (comma separated) &amp; Cover</label>
          <input type="text" name="tags" id="gigTagsInput" class="control" placeholder="php,ui,mysql,design" style="margin-bottom:8px;">
          <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" class="control" style="padding:6px;">
          <select name="cover_preset" id="gigPresetInput" class="control" style="margin-top:8px;">
            <option value="">Or pick cover photo preset...</option>
            <?php
            $presetLabels = [
              1 => 'Web & PHP Code', 2 => 'UI Wireframe', 3 => 'AI & Neural Tech',
              4 => 'Video Editing Timeline', 5 => 'Branding & Palette', 6 => 'Software IDE',
              7 => 'Copywriting Desk', 8 => 'SEO & Growth Charts', 9 => 'Security Hardware',
              10 => 'Mobile App UI', 11 => '3D Abstract Render', 12 => 'Analytics Dashboard',
            ];
            for ($i = 1; $i <= 12; $i++): ?>
              <option value="assets/images/gig-<?= $i ?>.jpg">Cover #<?= $i ?> · <?= $presetLabels[$i] ?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:4px;">
        <button type="button" class="btn btn-outline" data-close-modal="gigFormModal">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Gig</button>
      </div>
    </form>
  </div>
</div>

<!-- Deliver Work Modal -->
<div class="modal-backdrop" id="deliverModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Deliver Order <span id="deliverOrderLabel" class="mono"></span></h3>
      <button type="button" class="icon-btn" data-close-modal="deliverModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="deliverForm" class="form-grid" enctype="multipart/form-data">
      <input type="hidden" name="order_id" id="deliverOrderId">
      <div class="field">
        <label>Delivery Summary</label>
        <textarea name="delivery_note" class="control" placeholder="Share links, notes, and instructions for the buyer..." required></textarea>
      </div>
      <div class="field">
        <label>Attach Deliverable File (optional: zip, pdf, png, jpg, txt)</label>
        <input type="file" name="delivery_file" class="control" style="padding:6px;">
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Submit Delivery</button>
    </form>
  </div>
</div>

<!-- Withdraw Modal -->
<div class="modal-backdrop" id="payoutModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Request Withdraw</h3>
      <button type="button" class="icon-btn" data-close-modal="payoutModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="payoutForm" class="form-grid">
      <div class="field">
        <label>Amount (Available: <?= money($me['wallet_balance']) ?>)</label>
        <input type="number" name="amount" class="control" min="10" step="0.01" max="<?= (float)$me['wallet_balance'] ?>" value="<?= min(100, (float)$me['wallet_balance']) ?>" required>
      </div>
      <div class="field">
        <label>Withdraw Method</label>
        <select name="method" class="control">
          <option>Bank Transfer</option>
          <option>PayPal</option>
          <option>Wise</option>
          <option>bKash / Nagad</option>
        </select>
      </div>
      <div class="field">
        <label>Account / Email / Number</label>
        <input type="text" name="account_info" class="control" placeholder="acct@example.com or account number..." required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Submit Withdraw Request</button>
    </form>
  </div>
</div>

<script>
async function openGigForm(gigId = 0) {
  const form = document.getElementById('gigForm');
  form.reset();
  document.getElementById('gigIdInput').value = '';
  document.getElementById('gigModalTitle').textContent = gigId ? 'Edit Gig' : 'Post New Gig';

  if (gigId) {
    const res = await apiGet(`api/gigs.php?action=get&id=${gigId}`);
    if (!res.ok) return toast(res.error || 'Could not load gig', 'err');
    const g = res.gig;
    document.getElementById('gigIdInput').value = g.id;
    document.getElementById('gigTitleInput').value = g.title;
    document.getElementById('gigCatInput').value = g.category_id;
    document.getElementById('gigPriceInput').value = g.price;
    document.getElementById('gigDaysInput').value = g.delivery_days;
    document.getElementById('gigRevInput').value = g.revisions;
    document.getElementById('gigDescInput').value = g.description;
    document.getElementById('gigFeatInput').value = g.features || '';
    document.getElementById('gigTagsInput').value = g.tags || '';
  }
  openModal('gigFormModal');
}

async function toggleGigStatus(id) {
  const res = await apiPost('api/gigs.php', { action: 'toggle_status', id });
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
}

async function deleteGig(id) {
  if (!confirm('Delete this gig permanently?')) return;
  const res = await apiPost('api/gigs.php', { action: 'delete', id });
  if (res.ok) location.reload();
  else toast(res.error || 'Delete failed', 'err');
}

async function sellerStartOrder(id) {
  const res = await apiPost('api/orders.php', { action: 'start', order_id: id });
  if (res.ok) location.reload();
  else toast(res.error || 'Failed', 'err');
}

function openDeliverModal(id, code) {
  document.getElementById('deliverOrderId').value = id;
  document.getElementById('deliverOrderLabel').textContent = code;
  openModal('deliverModal');
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('gigForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const isEdit = Boolean(fd.get('id'));
    fd.append('action', isEdit ? 'update' : 'create');
    const res = await apiPost('api/gigs.php', fd);
    if (res.ok) {
      toast(res.message || 'Saved');
      setTimeout(() => location.href = 'seller-panel.php', 500);
    } else {
      toast(res.error || 'Failed to save gig', 'err');
    }
  });

  document.getElementById('deliverForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'deliver');
    const res = await apiPost('api/orders.php', fd);
    if (res.ok) location.reload();
    else toast(res.error || 'Delivery failed', 'err');
  });

  document.getElementById('payoutForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('action', 'request_payout');
    const res = await apiPost('api/orders.php', fd);
    if (res.ok) {
      toast('Withdraw request submitted!');
      setTimeout(() => location.reload(), 500);
    } else {
      toast(res.error || 'Failed', 'err');
    }
  });

  <?php if (isset($_GET['new_gig'])): ?>
  openGigForm();
  <?php elseif (!empty($_GET['edit_gig'])): ?>
  openGigForm(<?= (int)$_GET['edit_gig'] ?>);
  <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

