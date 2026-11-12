<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$me = require_login();
$uid = (int)$me['id'];
$orderId = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('
    SELECT o.*,
           b.name AS buyer_name, b.username AS buyer_username, b.avatar AS buyer_avatar, b.seller_level AS buyer_level, b.is_verified AS buyer_verified,
           s.name AS seller_name, s.username AS seller_username, s.avatar AS seller_avatar, s.seller_level AS seller_level, s.is_verified AS seller_verified,
           g.cover_image
    FROM orders o
    JOIN users b ON b.id = o.buyer_id
    JOIN users s ON s.id = o.seller_id
    LEFT JOIN gigs g ON g.id = o.gig_id
    WHERE o.id = ?
');
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order || ($uid !== (int)$order['buyer_id'] && $uid !== (int)$order['seller_id'] && $me['role'] !== 'admin')) {
    header('Location: ' . panel_url($me));
    exit;
}

$isBuyer = $uid === (int)$order['buyer_id'];
$isSeller = $uid === (int)$order['seller_id'];

$evStmt = $pdo->prepare('SELECT e.*, u.name AS actor_name FROM order_events e LEFT JOIN users u ON u.id = e.actor_id WHERE e.order_id = ? ORDER BY e.created_at ASC, e.id ASC');
$evStmt->execute([$orderId]);
$events = $evStmt->fetchAll();

$revStmt = $pdo->prepare('SELECT * FROM reviews WHERE order_id = ?');
$revStmt->execute([$orderId]);
$review = $revStmt->fetch();

[$stLabel, $stClass, $stIcon] = status_meta($order['status']);
$stepOrder = ['pending' => 1, 'in_progress' => 2, 'revision' => 2, 'delivered' => 3, 'completed' => 4];
$stepNum = $stepOrder[$order['status']] ?? 0;

$pageTitle = 'Order ' . $order['order_code'];
$activeNav = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <a href="<?= e(panel_url($me)) ?>" class="icon-btn"><span class="ms ms-sm">arrow_back</span></a>
      <div>
        <div style="display:flex;align-items:center;gap:8px;">
          <h1 style="margin:0;font-size:18px;font-weight:800;" class="mono"><?= e($order['order_code']) ?></h1>
          <span class="badge <?= $stClass ?>"><span class="ms ms-sm"><?= $stIcon ?></span><?= $stLabel ?></span>
        </div>
        <div class="muted" style="font-size:12px;">Placed <?= e(time_ago($order['created_at'])) ?> · Due <?= date('M j, Y', strtotime($order['due_at'] ?: $order['created_at'])) ?></div>
      </div>
    </div>

    <div style="display:flex;align-items:center;gap:8px;">
      <?php if ($isBuyer || $isSeller):
        $peerId = $isBuyer ? (int)$order['seller_id'] : (int)$order['buyer_id'];
      ?>
        <button type="button" class="btn btn-outline btn-sm" onclick="startChat(<?= $peerId ?>, <?= (int)$order['gig_id'] ?>, <?= (int)$order['id'] ?>)">
          <span class="ms ms-sm">chat_bubble</span> Message <?= $isBuyer ? 'Seller' : 'Buyer' ?>
        </button>
      <?php endif; ?>
      <?php if ($me['role'] === 'admin'): ?>
        <a href="admin.php?tab=orders" class="btn btn-dark btn-sm">Manage in Admin</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($stepNum > 0): ?>
    <div class="stepper">
      <div class="step-item <?= $stepNum >= 1 ? ($stepNum === 1 ? 'current' : 'done') : '' ?>">
        <span class="ms ms-sm ms-fill">receipt_long</span> Placed
      </div>
      <div class="step-item <?= $stepNum >= 2 ? ($stepNum === 2 ? 'current' : 'done') : '' ?>">
        <span class="ms ms-sm ms-fill">bolt</span> <?= $order['status'] === 'revision' ? 'In Revision' : 'In Progress' ?>
      </div>
      <div class="step-item <?= $stepNum >= 3 ? ($stepNum === 3 ? 'current' : 'done') : '' ?>">
        <span class="ms ms-sm ms-fill">inventory_2</span> Delivered
      </div>
      <div class="step-item <?= $stepNum >= 4 ? 'done' : '' ?>">
        <span class="ms ms-sm ms-fill">check_circle</span> Completed
      </div>
    </div>
  <?php endif; ?>

  <div class="gig-detail-grid">
    <!-- Left Column -->
    <div style="display:grid;gap:16px;">
      <!-- Gig & Requirements -->
      <div class="card card-pad">
        <div style="display:flex;gap:12px;align-items:center;padding-bottom:12px;border-bottom:1px solid var(--line);margin-bottom:12px;">
          <img src="<?= e($order['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt="" style="width:74px;height:48px;border-radius:8px;object-fit:cover;border:1px solid var(--line);">
          <div style="min-width:0;">
            <a href="gig.php?id=<?= (int)$order['gig_id'] ?>" style="font-weight:700;font-size:14.5px;"><?= e($order['gig_title']) ?></a>
            <div class="muted" style="font-size:12px;margin-top:4px;display:flex;align-items:center;flex-wrap:wrap;gap:10px;">
              <a href="profile.php?u=<?= e($order['buyer_username']) ?>" style="display:inline-flex;align-items:center;gap:5px;">
                <img src="<?= e(avatar_url($order['buyer_avatar'])) ?>" alt="" class="avatar" style="width:18px;height:18px;">
                <span>Buyer: <strong style="color:var(--ink);"><?= e($order['buyer_name']) ?></strong></span>
                <?php if (($order['buyer_level'] ?? '') === 'pro' && (int)($order['buyer_verified'] ?? 0) === 1): ?>
                  <span class="ms ms-sm ms-fill" style="color:var(--primary);font-size:14px;" title="Pro Verified">verified</span>
                <?php endif; ?>
              </a>
              <a href="profile.php?u=<?= e($order['seller_username']) ?>" style="display:inline-flex;align-items:center;gap:5px;">
                <img src="<?= e(avatar_url($order['seller_avatar'])) ?>" alt="" class="avatar" style="width:18px;height:18px;">
                <span>Seller: <strong style="color:var(--ink);"><?= e($order['seller_name']) ?></strong></span>
                <?php if (($order['seller_level'] ?? '') === 'pro' && (int)($order['seller_verified'] ?? 0) === 1): ?>
                  <span class="ms ms-sm ms-fill" style="color:var(--primary);font-size:14px;" title="Pro Verified">verified</span>
                <?php endif; ?>
              </a>
            </div>
          </div>
        </div>
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Buyer Requirements</div>
        <p style="margin:0;color:var(--ink-2);white-space:pre-line;"><?= e($order['requirements']) ?></p>
      </div>

      <!-- Delivery Section -->
      <?php if ($order['delivery_note'] || $order['delivery_file']): ?>
        <div class="card card-pad" style="border-color:#A7F3D0;background:#F8FFFC;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <strong style="display:flex;align-items:center;gap:6px;color:#065F46;">
              <span class="ms ms-sm ms-fill">inventory_2</span> Latest Delivery
            </strong>
            <span class="muted" style="font-size:12px;"><?= e(time_ago($order['delivered_at'])) ?></span>
          </div>
          <p style="margin:0 0 10px;color:var(--ink-2);white-space:pre-line;"><?= e($order['delivery_note']) ?></p>
          <?php if ($order['delivery_file']): ?>
            <a href="<?= e($order['delivery_file']) ?>" download class="btn btn-outline btn-sm">
              <span class="ms ms-sm">download</span> Download Deliverable (<?= e(basename($order['delivery_file'])) ?>)
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Review Box (Buyer on completed order) -->
      <?php if ($order['status'] === 'completed'): ?>
        <div class="card card-pad">
          <h3 style="margin:0 0 10px;font-size:15px;font-weight:800;">Order Review</h3>
          <?php if ($review): ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
              <span class="rating-pill"><span class="ms ms-fill">star</span> <?= (int)$review['rating'] ?>.0 / 5.0</span>
              <span class="muted" style="font-size:12px;">Submitted <?= e(time_ago($review['created_at'])) ?></span>
            </div>
            <p style="margin:0;color:var(--ink-2);"><?= e($review['comment']) ?></p>
          <?php elseif ($isBuyer): ?>
            <form id="reviewForm" class="form-grid">
              <input type="hidden" name="order_id" value="<?= $orderId ?>">
              <div class="field">
                <label>Rating</label>
                <div style="display:flex;gap:6px;" id="starPicker">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="btn btn-outline btn-sm star-choice" data-val="<?= $i ?>">
                      <span class="ms ms-sm ms-fill" style="color:var(--amber);">star</span> <?= $i ?>
                    </button>
                  <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="5">
              </div>
              <div class="field">
                <label>Your Feedback</label>
                <textarea name="comment" class="control" placeholder="Share your experience working with this freelancer..." required></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Submit Review</button>
            </form>
          <?php else: ?>
            <div class="muted" style="font-size:13px;">Waiting for buyer feedback.</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Timeline -->
      <div class="card card-pad">
        <h3 style="margin:0 0 12px;font-size:14.5px;font-weight:800;">Activity Timeline</h3>
        <div class="timeline">
          <?php foreach ($events as $ev): ?>
            <div class="timeline-item">
              <span class="timeline-dot"><span class="ms ms-sm">history</span></span>
              <div style="flex:1;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                  <strong style="font-size:13px;text-transform:capitalize;"><?= e(str_replace('_', ' ', $ev['event_type'])) ?></strong>
                  <span class="muted" style="font-size:11.5px;"><?= e(time_ago($ev['created_at'])) ?></span>
                </div>
                <?php if ($ev['note']): ?>
                  <div class="muted" style="font-size:12.5px;margin-top:2px;"><?= e($ev['note']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Right Action Panel -->
    <aside class="sticky-box">
      <div class="card card-pad" style="display:grid;gap:12px;">
        <div style="font-weight:800;font-size:14.5px;">Order Summary</div>
        <div style="display:grid;gap:6px;font-size:13px;padding-bottom:10px;border-bottom:1px solid var(--line);">
          <div style="display:flex;justify-content:space-between;"><span class="muted">Gig Price</span><strong class="mono"><?= money($order['price']) ?></strong></div>
          <div style="display:flex;justify-content:space-between;"><span class="muted">Revisions Left</span><strong class="mono"><?= (int)$order['revisions_left'] ?></strong></div>
          <?php if ($isSeller): ?>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Platform Fee</span><span class="mono">-<?= money($order['platform_fee']) ?></span></div>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Net Earnings</span><strong class="mono" style="color:#047857;"><?= money($order['seller_net']) ?></strong></div>
          <?php else: ?>
            <div style="display:flex;justify-content:space-between;"><span class="muted">Total Paid</span><strong class="mono"><?= money($order['total_paid']) ?></strong></div>
          <?php endif; ?>
        </div>

        <?php if ($isSeller && in_array($order['status'], ['pending', 'in_progress', 'revision'], true)): ?>
          <?php if ($order['status'] === 'pending'): ?>
            <button type="button" class="btn btn-outline" onclick="orderAction('start')">
              <span class="ms ms-sm">play_arrow</span> Start Working
            </button>
          <?php endif; ?>
          <form id="orderDeliverForm" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <div class="field">
              <label>Deliver Work</label>
              <textarea name="delivery_note" class="control" placeholder="Delivery message or repo/preview link..." required></textarea>
            </div>
            <input type="file" name="delivery_file" class="control" style="padding:6px;">
            <button type="submit" class="btn btn-primary">
              <span class="ms ms-sm">upload_file</span> Submit Delivery
            </button>
          </form>
        <?php endif; ?>

        <?php if ($isBuyer && $order['status'] === 'delivered'): ?>
          <button type="button" class="btn btn-emerald btn-lg" onclick="orderAction('complete')">
            <span class="ms ms-sm">check_circle</span> Approve & Release Payout
          </button>
          <?php if ((int)$order['revisions_left'] > 0): ?>
            <form id="revisionForm" class="form-grid" style="padding-top:8px;border-top:1px solid var(--line);">
              <input type="hidden" name="order_id" value="<?= $orderId ?>">
              <div class="field">
                <label>Need changes? (<?= (int)$order['revisions_left'] ?> left)</label>
                <textarea name="revision_note" class="control" placeholder="Specify what needs adjusting..." required></textarea>
              </div>
              <button type="submit" class="btn btn-outline">Request Revision</button>
            </form>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (($isBuyer && $order['status'] === 'pending') || ($isSeller && in_array($order['status'], ['pending', 'in_progress'], true))): ?>
          <button type="button" class="btn btn-rose btn-sm" onclick="if(confirm('Cancel and refund order?')) orderAction('cancel')">
            Cancel & Refund Order
          </button>
        <?php endif; ?>

        <?php if (($isBuyer || $isSeller) && in_array($order['status'], ['in_progress', 'delivered', 'revision'], true)): ?>
          <button type="button" class="btn btn-ghost btn-sm" onclick="openDispute()">
            <span class="ms ms-sm">gavel</span> Open Dispute
          </button>
        <?php endif; ?>
      </div>
    </aside>
  </div>
</div>

<script>
async function orderAction(action, extra = {}) {
  const res = await apiPost('api/orders.php', { action, order_id: <?= $orderId ?>, ...extra });
  if (res.ok) location.reload();
  else toast(res.error || 'Action failed', 'err');
}

function openDispute() {
  const reason = prompt('State the dispute reason for Admin review:');
  if (reason && reason.trim()) orderAction('dispute', { reason: reason.trim() });
}

const delivForm = document.getElementById('orderDeliverForm');
if (delivForm) {
  delivForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(delivForm);
    fd.append('action', 'deliver');
    const res = await apiPost('api/orders.php', fd);
    if (res.ok) location.reload();
    else toast(res.error || 'Delivery failed', 'err');
  });
}

const revForm = document.getElementById('revisionForm');
if (revForm) {
  revForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(revForm);
    fd.append('action', 'request_revision');
    const res = await apiPost('api/orders.php', fd);
    if (res.ok) location.reload();
    else toast(res.error || 'Failed', 'err');
  });
}

document.querySelectorAll('.star-choice').forEach(btn => {
  btn.addEventListener('click', () => {
    const val = btn.dataset.val;
    document.getElementById('ratingInput').value = val;
    document.querySelectorAll('.star-choice').forEach(b => {
      b.classList.toggle('btn-primary', Number(b.dataset.val) <= Number(val));
      b.classList.toggle('btn-outline', Number(b.dataset.val) > Number(val));
    });
  });
});

const reviewForm = document.getElementById('reviewForm');
if (reviewForm) {
  reviewForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(reviewForm);
    fd.append('action', 'review');
    const res = await apiPost('api/orders.php', fd);
    if (res.ok) location.reload();
    else toast(res.error || 'Review failed', 'err');
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

