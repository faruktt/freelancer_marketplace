<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$me = current_user();
$username = trim((string)($_GET['u'] ?? ($me['username'] ?? '')));

$stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: index.php');
    exit;
}

$isOwner = $me && (int)$me['id'] === (int)$profile['id'];
$pid = (int)$profile['id'];

$gigsStmt = $pdo->prepare("
    SELECT g.*, c.name AS category_name, c.icon AS category_icon
    FROM gigs g
    JOIN categories c ON c.id = g.category_id
    WHERE g.seller_id = ? AND g.status = 'active'
    ORDER BY g.orders_count DESC, g.created_at DESC
");
$gigsStmt->execute([$pid]);
$gigs = $gigsStmt->fetchAll();

$revStmt = $pdo->prepare('
    SELECT r.*, u.name AS buyer_name, u.avatar AS buyer_avatar, g.title AS gig_title
    FROM reviews r
    JOIN users u ON u.id = r.buyer_id
    JOIN gigs g ON g.id = r.gig_id
    WHERE r.seller_id = ? AND r.is_visible = 1
    ORDER BY r.created_at DESC
    LIMIT 12
');
$revStmt->execute([$pid]);
$reviews = $revStmt->fetchAll();

$skills = array_values(array_filter(array_map('trim', explode(',', (string)$profile['skills']))));

$pageTitle = $profile['name'];
$activeNav = $isOwner ? 'dashboard' : 'explore';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="card card-pad" style="margin-bottom:18px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <div class="avatar-edit-wrap">
          <img id="profileAvatarImg" src="<?= e($profile['avatar'] ?: 'assets/images/avatar-1.jpg') ?>" alt="<?= e($profile['name']) ?>" class="avatar avatar-xl">
          <?php if ($isOwner): ?>
            <label class="avatar-cam-btn" title="Change profile photo">
              <span class="ms ms-sm" style="font-size:15px;">photo_camera</span>
              <input type="file" id="quickAvatarInput" accept="image/jpeg,image/png,image/webp" style="display:none;">
            </label>
          <?php endif; ?>
        </div>
        <div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <h1 style="margin:0;font-size:20px;font-weight:800;"><?= e($profile['name']) ?></h1>
            <?php if ((int)$profile['is_verified'] === 1): ?>
              <span class="ms ms-fill" style="color:var(--primary);" title="Verified">verified</span>
            <?php endif; ?>
            <?php if ($profile['role'] === 'admin'): ?>
              <span class="badge badge-amber">Admin</span>
            <?php elseif ($profile['role'] === 'buyer'): ?>
              <span class="badge badge-cyan">Buyer</span>
            <?php else: ?>
              <span class="badge badge-indigo"><?= e(seller_level_label($profile['seller_level'])) ?></span>
            <?php endif; ?>
          </div>
          <div class="muted" style="font-size:13px;margin-top:2px;">
            <?= e($profile['title'] ?: ucfirst($profile['role'])) ?> · <?= e($profile['country']) ?> · Member since <?= date('M Y', strtotime($profile['created_at'])) ?>
          </div>
          <?php if ($profile['role'] === 'seller'): ?>
            <div style="display:flex;align-items:center;gap:12px;margin-top:8px;font-size:12.5px;">
              <span class="rating-pill">
                <span class="ms ms-fill">star</span> <?= number_format((float)$profile['rating_avg'], 1) ?>
                <span class="cnt">(<?= (int)$profile['review_count'] ?> reviews)</span>
              </span>
              <span class="muted"><strong><?= count($gigs) ?></strong> active gigs</span>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div style="display:flex;gap:8px;">
        <?php if ($isOwner): ?>
          <button type="button" class="btn btn-outline btn-sm" onclick="openModal('editProfileModal')">
            <span class="ms ms-sm">edit</span> Edit Profile
          </button>
          <a href="<?= e(panel_url($me)) ?>" class="btn btn-primary btn-sm"><?= ucfirst($me['role']) ?> Panel</a>
        <?php else: ?>
          <button type="button" class="btn btn-primary" onclick="<?= $me ? 'startChat(' . $pid . ')' : "openAuthModal('login')" ?>">
            <span class="ms ms-sm">chat_bubble</span> Message <?= e(explode(' ', $profile['name'])[0]) ?>
          </button>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($profile['bio']): ?>
      <p style="margin:14px 0 0;color:var(--ink-2);font-size:13.5px;max-width:780px;"><?= e($profile['bio']) ?></p>
    <?php endif; ?>

    <?php if (!empty($skills)): ?>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:12px;">
        <?php foreach ($skills as $sk): ?>
          <span class="badge badge-slate"><?= e($sk) ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($gigs)): ?>
    <h2 style="margin:0 0 12px;font-size:16px;font-weight:800;">Services by <?= e($profile['name']) ?></h2>
    <div class="gig-grid" style="margin-bottom:20px;">
      <?php foreach ($gigs as $g): ?>
        <article class="gig-card">
          <div class="gig-thumb">
            <a href="gig.php?id=<?= (int)$g['id'] ?>"><img src="<?= e($g['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt=""></a>
            <span class="gig-cat-tag"><span class="ms ms-sm"><?= e($g['category_icon']) ?></span><?= e($g['category_name']) ?></span>
          </div>
          <div class="gig-body">
            <a href="gig.php?id=<?= (int)$g['id'] ?>" class="gig-title"><?= e($g['title']) ?></a>
            <div class="gig-meta-row">
              <span class="rating-pill"><span class="ms ms-fill">star</span><?= number_format((float)$g['rating_avg'], 1) ?> <span class="cnt">(<?= (int)$g['review_count'] ?>)</span></span>
              <span class="price-tag"><?= money_short($g['price']) ?></span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card card-pad">
    <h3 style="margin:0 0 12px;font-size:15px;font-weight:800;">Client Reviews (<?= count($reviews) ?>)</h3>
    <?php if (empty($reviews)): ?>
      <div class="muted" style="font-size:13px;">No reviews yet.</div>
    <?php else: ?>
      <div style="display:grid;gap:12px;">
        <?php foreach ($reviews as $r): ?>
          <div style="padding-top:10px;border-top:1px solid var(--line);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <img src="<?= e($r['buyer_avatar'] ?: 'assets/images/avatar-5.jpg') ?>" alt="" class="avatar">
                <strong style="font-size:13px;"><?= e($r['buyer_name']) ?></strong>
                <span class="muted" style="font-size:12px;">on <?= e($r['gig_title']) ?></span>
              </div>
              <span class="rating-pill"><span class="ms ms-fill">star</span> <?= (int)$r['rating'] ?>.0</span>
            </div>
            <p style="margin:0;font-size:13px;color:var(--ink-2);"><?= e($r['comment']) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($isOwner): ?>
<div class="modal-backdrop" id="editProfileModal">
  <div class="modal-card">
    <div class="modal-head">
      <h3>Edit Profile</h3>
      <button type="button" class="icon-btn" data-close-modal="editProfileModal"><span class="ms ms-sm">close</span></button>
    </div>
    <form id="editProfileForm" class="form-grid" enctype="multipart/form-data">
      <div class="form-row-2">
        <div class="field">
          <label>Display Name</label>
          <input type="text" name="name" class="control" value="<?= e($profile['name']) ?>" required>
        </div>
        <div class="field">
          <label>Country</label>
          <input type="text" name="country" class="control" value="<?= e($profile['country']) ?>">
        </div>
      </div>
      <div class="field">
        <label>Headline / Role Title</label>
        <input type="text" name="title" class="control" value="<?= e($profile['title']) ?>">
      </div>
      <div class="field">
        <label>Skills (comma separated)</label>
        <input type="text" name="skills" class="control" value="<?= e($profile['skills']) ?>">
      </div>
      <div class="field">
        <label>Bio</label>
        <textarea name="bio" class="control"><?= e($profile['bio']) ?></textarea>
      </div>
      <div class="form-row-2">
        <div class="field">
          <label>Profile Avatar</label>
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="control" style="padding:6px;">
        </div>
        <div class="field">
          <label>New Password (optional)</label>
          <input type="password" name="new_password" minlength="6" placeholder="Leave blank to keep" class="control">
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg">Save Changes</button>
    </form>
  </div>
</div>
<script>
document.getElementById('editProfileForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'update_profile');
  const res = await apiPost('api/auth.php', fd);
  if (res.ok) location.reload();
  else toast(res.error || 'Update failed', 'err');
});

document.getElementById('quickAvatarInput')?.addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('action', 'upload_avatar');
  fd.append('avatar', file);
  const res = await apiPost('api/auth.php', fd);
  if (res.ok) {
    document.getElementById('profileAvatarImg').src = res.avatar;
    toast('Profile photo updated');
    setTimeout(() => location.reload(), 400);
  } else {
    toast(res.error || 'Upload failed', 'err');
  }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

