<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pageTitle = 'Explore Gigs';
$activeNav = 'explore';
$pdo = db();

$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, id ASC')->fetchAll();

$q = trim((string)($_GET['q'] ?? ''));
$catSlug = trim((string)($_GET['category'] ?? 'all'));

$where = ["g.status = 'active'", "u.status = 'active'", 'c.is_active = 1'];
$params = [];
if ($q !== '') {
    $where[] = '(g.title LIKE ? OR g.tags LIKE ? OR g.description LIKE ? OR u.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($catSlug !== '' && $catSlug !== 'all') {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}

$stmt = $pdo->prepare('
    SELECT g.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
           u.name AS seller_name, u.username AS seller_username, u.avatar AS seller_avatar, u.seller_level, u.is_verified
    FROM gigs g
    JOIN categories c ON c.id = g.category_id
    JOIN users u ON u.id = g.seller_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY g.is_featured DESC, g.orders_count DESC, g.rating_avg DESC
    LIMIT 60
');
$stmt->execute($params);
$gigs = $stmt->fetchAll();

$me = current_user();
$favIds = [];
if ($me) {
    $fStmt = $pdo->prepare('SELECT gig_id FROM favorites WHERE user_id = ?');
    $fStmt->execute([(int)$me['id']]);
    $favIds = array_map('intval', $fStmt->fetchAll(PDO::FETCH_COLUMN));
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <!-- Category Pills -->
  <div class="cat-strip" id="catStrip">
    <button type="button" class="cat-pill <?= $catSlug === 'all' ? 'active' : '' ?>" data-cat="all">
      <span class="ms ms-sm">apps</span>
      <span>All Categories</span>
    </button>
    <?php foreach ($categories as $cat): ?>
      <button type="button" class="cat-pill <?= $catSlug === $cat['slug'] ? 'active' : '' ?>" data-cat="<?= e($cat['slug']) ?>">
        <span class="ms ms-sm" style="color:<?= e($cat['accent_color']) ?>"><?= e($cat['icon']) ?></span>
        <span><?= e($cat['name']) ?></span>
      </button>
    <?php endforeach; ?>
  </div>

  <!-- Filter & Sort Bar -->
  <div class="filter-bar">
    <div class="filter-controls">
      <input id="filterSearchMobile" type="search" class="input-sm" value="<?= e($q) ?>" placeholder="Search gigs..." style="width:170px;">
      <select id="filterPrice" class="select-sm">
        <option value="">Any Budget</option>
        <option value="100">Under $100</option>
        <option value="150">Under $150</option>
        <option value="200">Under $200</option>
        <option value="500">Under $500</option>
      </select>
      <select id="filterDelivery" class="select-sm">
        <option value="">Any Delivery</option>
        <option value="1">Express 24h</option>
        <option value="3">Up to 3 days</option>
        <option value="7">Up to 7 days</option>
      </select>
      <select id="filterRating" class="select-sm">
        <option value="">Any Rating</option>
        <option value="4.8">4.8★ & up</option>
        <option value="4.5">4.5★ & up</option>
      </select>
    </div>

    <div class="filter-controls">
      <span class="muted" style="font-size:12.5px;font-weight:600;" id="resultCount"><?= count($gigs) ?> gigs</span>
      <select id="filterSort" class="select-sm">
        <option value="recommended">Recommended</option>
        <option value="rating">Top Rated</option>
        <option value="price_asc">Price: Low to High</option>
        <option value="price_desc">Price: High to Low</option>
        <option value="newest">Newest</option>
      </select>
    </div>
  </div>

  <!-- Gigs Grid -->
  <div class="gig-grid" id="gigGrid">
    <?php if (empty($gigs)): ?>
      <div class="card empty-state" style="grid-column:1/-1;">
        <span class="ms">search_off</span>
        <div style="font-weight:700;color:var(--ink);">No gigs matched your filter</div>
        <div style="font-size:12.5px;margin-top:4px;">Try clearing search keywords or switching category.</div>
      </div>
    <?php else: ?>
      <?php foreach ($gigs as $g):
        $isFav = in_array((int)$g['id'], $favIds, true);
      ?>
        <article class="gig-card">
          <div class="gig-thumb">
            <a href="gig.php?id=<?= (int)$g['id'] ?>">
              <img src="<?= e($g['cover_image'] ?: 'assets/images/gig-1.jpg') ?>" alt="<?= e($g['title']) ?>" loading="lazy">
            </a>
            <?php if ((int)$g['is_featured'] === 1): ?>
              <span class="gig-featured-tag">FEATURED</span>
            <?php endif; ?>
            <span class="gig-cat-tag">
              <span class="ms ms-sm"><?= e($g['category_icon']) ?></span>
              <?= e($g['category_name']) ?>
            </span>
            <button type="button" class="fav-btn <?= $isFav ? 'active' : '' ?>" onclick="toggleFav(<?= (int)$g['id'] ?>, this)" title="Save gig">
              <span class="ms ms-sm <?= $isFav ? 'ms-fill' : '' ?>">favorite</span>
            </button>
          </div>
          <div class="gig-body">
            <div class="gig-seller">
              <a href="profile.php?u=<?= e($g['seller_username']) ?>" class="gig-seller-info">
                <img src="<?= e($g['seller_avatar'] ?: 'assets/images/avatar-1.jpg') ?>" alt="" class="avatar">
                <span class="truncate"><?= e($g['seller_name']) ?></span>
                <?php if ((int)$g['is_verified'] === 1): ?>
                  <span class="ms ms-sm ms-fill" style="color:var(--primary);" title="Verified">verified</span>
                <?php endif; ?>
              </a>
              <span class="badge badge-slate"><?= e(seller_level_label($g['seller_level'])) ?></span>
            </div>

            <a href="gig.php?id=<?= (int)$g['id'] ?>" class="gig-title"><?= e($g['title']) ?></a>

            <div class="gig-meta-row">
              <span class="rating-pill">
                <span class="ms ms-fill">star</span>
                <span><?= number_format((float)$g['rating_avg'], 1) ?></span>
                <span class="cnt">(<?= (int)$g['review_count'] ?>)</span>
              </span>
              <span style="display:inline-flex;align-items:center;gap:8px;">
                <span class="muted" style="font-size:11.5px;display:inline-flex;align-items:center;gap:2px;">
                  <span class="ms ms-sm">schedule</span><?= (int)$g['delivery_days'] ?>d
                </span>
                <span class="price-tag"><?= money_short($g['price']) ?></span>
              </span>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
window.onMarketplaceHome = true;
(() => {
  const state = {
    q: <?= json_encode($q) ?>,
    category: <?= json_encode($catSlug) ?>,
    max_price: '',
    delivery: '',
    rating: '',
    sort: 'recommended',
  };

  const grid = document.getElementById('gigGrid');
  const countEl = document.getElementById('resultCount');
  const navInput = document.getElementById('globalNavSearch');
  const mobInput = document.getElementById('filterSearchMobile');

  const esc = (s) => {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML;
  };

  const levelMap = { pro: 'Pro Verified', top_rated: 'Top Rated', rising: 'Rising Talent', new: 'Seller' };

  const renderGigs = (gigs) => {
    countEl.textContent = `${gigs.length} gig${gigs.length === 1 ? '' : 's'}`;
    if (!gigs.length) {
      grid.innerHTML = `
        <div class="card empty-state" style="grid-column:1/-1;">
          <span class="ms">search_off</span>
          <div style="font-weight:700;color:var(--ink);">No gigs matched your filter</div>
          <div style="font-size:12.5px;margin-top:4px;">Try clearing keywords or adjusting budget/delivery filters.</div>
        </div>`;
      return;
    }
    grid.innerHTML = gigs.map(g => {
      const price = Number(g.price) % 1 === 0 ? `$${Number(g.price).toFixed(0)}` : `$${Number(g.price).toFixed(2)}`;
      return `
        <article class="gig-card">
          <div class="gig-thumb">
            <a href="gig.php?id=${g.id}">
              <img src="${esc(g.cover_image || 'assets/images/gig-1.jpg')}" alt="${esc(g.title)}" loading="lazy">
            </a>
            ${Number(g.is_featured) === 1 ? '<span class="gig-featured-tag">FEATURED</span>' : ''}
            <span class="gig-cat-tag">
              <span class="ms ms-sm">${esc(g.category_icon)}</span>
              ${esc(g.category_name)}
            </span>
            <button type="button" class="fav-btn ${g.is_fav ? 'active' : ''}" onclick="toggleFav(${g.id}, this)" title="Save gig">
              <span class="ms ms-sm ${g.is_fav ? 'ms-fill' : ''}">favorite</span>
            </button>
          </div>
          <div class="gig-body">
            <div class="gig-seller">
              <a href="profile.php?u=${esc(g.seller_username)}" class="gig-seller-info">
                <img src="${esc(g.seller_avatar || 'assets/images/avatar-1.jpg')}" alt="" class="avatar">
                <span class="truncate">${esc(g.seller_name)}</span>
                ${Number(g.is_verified) === 1 ? '<span class="ms ms-sm ms-fill" style="color:var(--primary);">verified</span>' : ''}
              </a>
              <span class="badge badge-slate">${esc(levelMap[g.seller_level] || 'Seller')}</span>
            </div>
            <a href="gig.php?id=${g.id}" class="gig-title">${esc(g.title)}</a>
            <div class="gig-meta-row">
              <span class="rating-pill">
                <span class="ms ms-fill">star</span>
                <span>${Number(g.rating_avg).toFixed(1)}</span>
                <span class="cnt">(${g.review_count})</span>
              </span>
              <span style="display:inline-flex;align-items:center;gap:8px;">
                <span class="muted" style="font-size:11.5px;display:inline-flex;align-items:center;gap:2px;">
                  <span class="ms ms-sm">schedule</span>${g.delivery_days}d
                </span>
                <span class="price-tag">${price}</span>
              </span>
            </div>
          </div>
        </article>`;
    }).join('');
  };

  let timer;
  const fetchGigs = async () => {
    const qs = new URLSearchParams();
    Object.entries(state).forEach(([k, v]) => { if (v !== '' && v !== 'all') qs.set(k, v); });
    grid.style.opacity = '0.55';
    const res = await apiGet(`api/gigs.php?${qs.toString()}`);
    grid.style.opacity = '1';
    if (res.ok) renderGigs(res.gigs);
  };

  const debouncedFetch = () => {
    clearTimeout(timer);
    timer = setTimeout(fetchGigs, 220);
  };

  document.querySelectorAll('#catStrip .cat-pill').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#catStrip .cat-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      state.category = btn.dataset.cat;
      fetchGigs();
    });
  });

  [navInput, mobInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', () => {
      state.q = inp.value.trim();
      if (navInput && inp !== navInput) navInput.value = inp.value;
      if (mobInput && inp !== mobInput) mobInput.value = inp.value;
      debouncedFetch();
    });
  });

  document.getElementById('filterPrice').addEventListener('change', (e) => { state.max_price = e.target.value; fetchGigs(); });
  document.getElementById('filterDelivery').addEventListener('change', (e) => { state.delivery = e.target.value; fetchGigs(); });
  document.getElementById('filterRating').addEventListener('change', (e) => { state.rating = e.target.value; fetchGigs(); });
  document.getElementById('filterSort').addEventListener('change', (e) => { state.sort = e.target.value; fetchGigs(); });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

