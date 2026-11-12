<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$gigId = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('
    SELECT g.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
           u.id AS seller_id, u.name AS seller_name, u.username AS seller_username,
           u.avatar AS seller_avatar, u.title AS seller_title, u.bio AS seller_bio,
           u.country AS seller_country, u.is_verified, u.seller_level,
           u.rating_avg AS seller_rating, u.review_count AS seller_reviews, u.created_at AS seller_since
    FROM gigs g
    JOIN categories c ON c.id = g.category_id
    JOIN users u ON u.id = g.seller_id
    WHERE g.id = ?
');
$stmt->execute([$gigId]);
$gig = $stmt->fetch();

$me = current_user();
if (!$gig || ($gig['status'] !== 'active' && (!$me || ((int)$me['id'] !== (int)$gig['seller_id'] && $me['role'] !== 'admin')))) {
    header('Location: index.php');
    exit;
}

$pdo->prepare('UPDATE gigs SET views_count = views_count + 1 WHERE id = ?')->execute([$gigId]);

$revStmt = $pdo->prepare('
    SELECT r.*, u.name AS buyer_name, u.username AS buyer_username, u.avatar AS buyer_avatar
    FROM reviews r
    JOIN users u ON u.id = r.buyer_id
    WHERE r.gig_id = ? AND r.is_visible = 1
    ORDER BY r.created_at DESC
');
$revStmt->execute([$gigId]);
$reviews = $revStmt->fetchAll();

$isFav = false;
if ($me) {
    $fStmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND gig_id = ?');
    $fStmt->execute([(int)$me['id'], $gigId]);
    $isFav = (bool)$fStmt->fetchColumn();
}

$features = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$gig['features']))));
$tags = array_values(array_filter(array_map('trim', explode(',', (string)$gig['tags']))));

$pageTitle = $gig['title'];
$activeNav = 'explore';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--muted);margin-bottom:14px;">
    <a href="index.php" style="font-weight:600;">Explore</a>
    <span class="ms ms-sm">chevron_right</span>
    <a href="index.php?category=<?= e($gig['category_slug']) ?>" style="font-weight:600;"><?= e($gig['category_name']) ?></a>
    <span class="ms ms-sm">chevron_right</span>
    <span class="truncate" style="max-width:340px;"><?= e($gig['title']) ?></span>
  </div>

  <div class="gig-detail-grid">
    <!-- Left Content -->
    <div style="display:grid;gap:16px;">
      <div class="card card-pad">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;">
          <span class="badge badge-indigo">
            <span class="ms ms-sm"><?= e($gig['category_icon']) ?></span>
            <?= e($gig['category_name']) ?>
          </span>
          <?php if ($gig['status'] !== 'active'): ?>
            <span class="badge badge-amber">Status: <?= e(ucfirst($gig['status'])) ?></span>
          <?php endif; ?>
        </div>

        <h1 style="margin:0 0 12px;font-size:22px;font-weight:800;letter-spacing:-0.02em;line-height:1.25;">
          <?= e($gig['title']) ?>
        </h1>

        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:14px;padding-bottom:14px;border-bottom:1px solid var(--line);margin-bottom:14px;">
          <a href="profile.php?u=<?= e($gig['seller_username']) ?>" style="display:flex;align-items:center;gap:9px;">
            <img src="<?= e(avatar_url($gig['seller_avatar'])) ?>" alt="" class="avatar avatar-md">
            <div>
              <div style="display:flex;align-items:center;gap:4px;font-weight:700;font-size:13.5px;">
                <?= e($gig['seller_name']) ?>
                <?php if ($gig['seller_level'] === 'pro' && (int)$gig['is_verified'] === 1): ?>
                  <span class="ms ms-sm ms-fill" style="color:var(--primary);" title="Pro Verified">verified</span>
                <?php endif; ?>
              </div>
              <div class="muted" style="font-size:12px;"><?= e($gig['seller_title'] ?: seller_level_label($gig['seller_level'])) ?></div>
            </div>
          </a>

          <div style="display:flex;align-items:center;gap:14px;margin-left:auto;font-size:12.5px;">
            <span class="rating-pill">
              <span class="ms ms-fill">star</span>
              <span><?= number_format((float)$gig['rating_avg'], 1) ?></span>
              <span class="cnt">(<?= (int)$gig['review_count'] ?>)</span>
            </span>
            <span class="muted"><strong style="color:var(--ink);"><?= (int)$gig['orders_count'] ?></strong> orders</span>
          </div>
        </div>

        <div style="border-radius:12px;overflow:hidden;border:1px solid var(--line);margin-bottom:16px;aspect-ratio:16/10;background:#E2E8F0;">
          <img src="<?= e($gig['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt="<?= e($gig['title']) ?>" style="width:100%;height:100%;object-fit:cover;">
        </div>

        <h3 style="margin:0 0 8px;font-size:15px;font-weight:800;">About this gig</h3>
        <p style="margin:0;color:var(--ink-2);white-space:pre-line;line-height:1.65;"><?= e($gig['description']) ?></p>

        <?php if (!empty($tags)): ?>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:14px;">
            <?php foreach ($tags as $tag): ?>
              <a href="index.php?q=<?= urlencode($tag) ?>" class="badge badge-slate">#<?= e($tag) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Seller Info -->
      <div class="card card-pad">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
          <div style="display:flex;align-items:center;gap:12px;">
            <img src="<?= e(avatar_url($gig['seller_avatar'])) ?>" alt="" class="avatar avatar-lg">
            <div>
              <div style="display:flex;align-items:center;gap:6px;font-size:15px;font-weight:800;">
                <a href="profile.php?u=<?= e($gig['seller_username']) ?>"><?= e($gig['seller_name']) ?></a>
                <?php if ($gig['seller_level'] === 'pro' && (int)$gig['is_verified'] === 1): ?>
                  <span class="ms ms-sm ms-fill" style="color:var(--primary);" title="Pro Verified">verified</span>
                <?php endif; ?>
                <span class="badge badge-indigo"><?= e(seller_level_label($gig['seller_level'])) ?></span>
              </div>
              <div class="muted" style="font-size:12.5px;margin-top:2px;">
                <?= e($gig['seller_country']) ?> · Member since <?= date('M Y', strtotime($gig['seller_since'])) ?> · ★ <?= number_format((float)$gig['seller_rating'], 1) ?> (<?= (int)$gig['seller_reviews'] ?>)
              </div>
            </div>
          </div>
          <?php if (!$me || (int)$me['id'] !== (int)$gig['seller_id']): ?>
            <button type="button" class="btn btn-outline" onclick="startChat(<?= (int)$gig['seller_id'] ?>, <?= (int)$gig['id'] ?>)">
              <span class="ms ms-sm">chat</span> Contact Seller
            </button>
          <?php endif; ?>
        </div>
        <?php if ($gig['seller_bio']): ?>
          <p style="margin:12px 0 0;color:var(--ink-2);font-size:13px;"><?= e($gig['seller_bio']) ?></p>
        <?php endif; ?>
      </div>

      <!-- Reviews -->
      <div class="card card-pad">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
          <h3 style="margin:0;font-size:15px;font-weight:800;">Reviews (<?= count($reviews) ?>)</h3>
          <span class="rating-pill"><span class="ms ms-fill">star</span> <?= number_format((float)$gig['rating_avg'], 1) ?> avg</span>
        </div>

        <?php if (empty($reviews)): ?>
          <div class="muted" style="font-size:13px;">No reviews yet for this gig.</div>
        <?php else: ?>
          <div style="display:grid;gap:12px;">
            <?php foreach ($reviews as $r): ?>
              <div style="padding-top:12px;border-top:1px solid var(--line);">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                  <div style="display:flex;align-items:center;gap:8px;">
                    <img src="<?= e(avatar_url($r['buyer_avatar'])) ?>" alt="" class="avatar">
                    <strong style="font-size:13px;"><?= e($r['buyer_name']) ?></strong>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;">
                    <span class="rating-pill"><span class="ms ms-fill">star</span> <?= (int)$r['rating'] ?>.0</span>
                    <span class="muted" style="font-size:11.5px;"><?= e(time_ago($r['created_at'])) ?></span>
                  </div>
                </div>
                <p style="margin:0;font-size:13px;color:var(--ink-2);"><?= e($r['comment']) ?></p>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sticky Order Sidebar -->
    <aside class="sticky-box">
      <div class="card card-pad">
        <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:10px;">
          <span class="muted" style="font-size:12.5px;font-weight:700;">Standard Package</span>
          <span style="font-size:24px;font-weight:800;font-family:var(--font-mono);"><?= money_short($gig['price']) ?></span>
        </div>

        <div style="display:flex;align-items:center;gap:14px;font-size:12.5px;font-weight:700;color:var(--ink-2);padding:10px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);">
          <span style="display:inline-flex;align-items:center;gap:5px;">
            <span class="ms ms-sm">schedule</span> <?= (int)$gig['delivery_days'] ?>-day delivery
          </span>
          <span style="display:inline-flex;align-items:center;gap:5px;">
            <span class="ms ms-sm">autorenew</span> <?= (int)$gig['revisions'] ?> revisions
          </span>
        </div>

        <?php if (!empty($features)): ?>
          <ul class="feature-list">
            <?php foreach ($features as $feat): ?>
              <li><span class="ms ms-fill">check_circle</span><span><?= e($feat) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div style="display:grid;gap:8px;margin-top:14px;">
          <?php if ($gig['status'] !== 'active'):
            [$gstL, $gstC, $gstI] = status_meta($gig['status']);
          ?>
            <div style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 10px;border-radius:9px;background:var(--surface-2);border:1px solid var(--line);">
              <span class="badge <?= $gstC ?>"><span class="ms ms-sm"><?= $gstI ?></span><?= $gstL ?></span>
            </div>
          <?php endif; ?>
          <?php if ($me && (int)$me['id'] === (int)$gig['seller_id']): ?>
            <a href="seller-panel.php?edit_gig=<?= (int)$gig['id'] ?>" class="btn btn-dark btn-lg">
              <span class="ms ms-sm">edit</span> Edit Your Gig
            </a>
          <?php elseif ($me && $me['role'] === 'admin'): ?>
            <a href="admin.php?tab=gigs<?= $gig['status'] === 'pending' ? '&status=pending' : '' ?>" class="btn btn-dark btn-lg">
              <span class="ms ms-sm">admin_panel_settings</span> Review in Admin Panel
            </a>
          <?php elseif ($me && $me['role'] !== 'buyer'): ?>
            <div class="muted" style="font-size:12px;padding:10px 12px;border-radius:10px;background:var(--surface-2);border:1px solid var(--line);text-align:center;">
              Sign in with a <a href="login.php" style="color:var(--primary);font-weight:700;">Buyer account</a> to place orders.
            </div>
          <?php else: ?>
            <button type="button" class="btn btn-primary btn-lg" onclick="<?= $me ? "openModal('orderModal')" : "window.location.href='login.php'" ?>">
              <span class="ms ms-sm">shopping_bag</span> Continue (<?= money_short($gig['price']) ?>)
            </button>
            <button type="button" class="btn btn-outline" onclick="<?= $me ? 'startChat(' . (int)$gig['seller_id'] . ', ' . (int)$gig['id'] . ')' : "window.location.href='login.php'" ?>">
              <span class="ms ms-sm">chat_bubble</span> Message Seller
            </button>
          <?php endif; ?>
          <?php if (!$me || $me['role'] === 'buyer'): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="toggleFav(<?= (int)$gig['id'] ?>, this)">
              <span class="ms ms-sm <?= $isFav ? 'ms-fill' : '' ?>">favorite</span>
              <span><?= $isFav ? 'Saved in Favorites' : 'Save Gig' ?></span>
            </button>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </div>
</div>

<!-- Order Checkout Modal -->
<?php if ($me && $me['role'] === 'buyer'): ?>
<div class="modal-backdrop" id="orderModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Place Order</h3>
      <button type="button" class="icon-btn" data-close-modal="orderModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="placeOrderForm" class="form-grid">
      <input type="hidden" name="gig_id" value="<?= (int)$gig['id'] ?>">
      <div style="padding:12px;background:var(--surface-2);border:1px solid var(--line);border-radius:10px;display:flex;justify-content:space-between;align-items:center;gap:10px;">
        <div style="min-width:0;">
          <div style="font-weight:700;font-size:13px;" class="truncate"><?= e($gig['title']) ?></div>
          <div class="muted" style="font-size:12px;">Delivery in <?= (int)$gig['delivery_days'] ?> days · <?= (int)$gig['revisions'] ?> revisions</div>
        </div>
        <strong class="mono" style="font-size:16px;"><?= money($gig['price']) ?></strong>
      </div>

      <div class="field">
        <label>Project Brief / Requirements</label>
        <textarea name="requirements" class="control" placeholder="Describe goals, assets, links, or preferences..." required></textarea>
      </div>

      <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px;padding:8px 10px;background:var(--emerald-soft);border-radius:8px;color:#065F46;">
        <span>Wallet Balance: <strong class="mono"><?= money($me['wallet_balance']) ?></strong></span>
        <span>Total: <strong class="mono"><?= money($gig['price']) ?></strong></span>
      </div>

      <button type="submit" class="btn btn-primary btn-lg">
        <span class="ms ms-sm">lock</span> Confirm & Pay <?= money($gig['price']) ?>
      </button>
    </form>
  </div>
</div>

<script>
document.getElementById('placeOrderForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'create');
  const res = await apiPost('api/orders.php', fd);
  if (res.ok) {
    window.location.href = res.redirect;
  } else {
    toast(res.error || 'Order failed', 'err');
  }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

