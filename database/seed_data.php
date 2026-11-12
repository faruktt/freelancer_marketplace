<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = db();
$baseDir = dirname(__DIR__);

// Ensure directories
$avatarUploadDir = $baseDir . '/uploads/avatars';
$gigUploadDir = $baseDir . '/uploads/gigs';
if (!is_dir($avatarUploadDir)) {
    mkdir($avatarUploadDir, 0755, true);
}
if (!is_dir($gigUploadDir)) {
    mkdir($gigUploadDir, 0755, true);
}

// Copy images
$assetAvatars = [
    'seller-1.jpg' => $baseDir . '/assets/images/avatar-1.jpg',
    'seller-2.jpg' => $baseDir . '/assets/images/avatar-2.jpg',
    'seller-3.jpg' => $baseDir . '/assets/images/avatar-3.jpg',
    'seller-4.jpg' => $baseDir . '/assets/images/avatar-4.jpg',
    'seller-5.jpg' => $baseDir . '/assets/images/avatar-5.jpg',
    'buyer-1.jpg'  => $baseDir . '/assets/images/avatar-6.jpg',
    'buyer-2.jpg'  => $baseDir . '/assets/images/avatar-1.jpg',
    'buyer-3.jpg'  => $baseDir . '/assets/images/avatar-2.jpg',
    'buyer-4.jpg'  => $baseDir . '/assets/images/avatar-3.jpg',
    'buyer-5.jpg'  => $baseDir . '/assets/images/avatar-4.jpg',
];

foreach ($assetAvatars as $destName => $srcFile) {
    if (file_exists($srcFile)) {
        copy($srcFile, $avatarUploadDir . '/' . $destName);
    }
}

for ($i = 1; $i <= 10; $i++) {
    $srcGig = $baseDir . "/assets/images/gig-{$i}.jpg";
    if (file_exists($srcGig)) {
        copy($srcGig, $gigUploadDir . "/gig-{$i}.jpg");
    }
}

$commonPasswordHash = password_hash('password123', PASSWORD_DEFAULT);

// 1. Five Sellers
$sellers = [
    [
        'name' => 'Rahim Ahmed',
        'username' => 'rahim_dev',
        'email' => 'rahim@kraftr.io',
        'title' => 'Full-Stack Web & Next.js Developer',
        'bio' => 'Over 6 years of experience building scalable web applications with React, Next.js, Node.js and PHP. Passionate about high performance, clean architecture, and responsive UX.',
        'skills' => 'React, Next.js, PHP, Laravel, Node.js, MySQL',
        'country' => 'Bangladesh',
        'seller_level' => 'top_rated',
        'rating_avg' => 4.95,
        'review_count' => 38,
        'avatar' => 'uploads/avatars/seller-1.jpg',
    ],
    [
        'name' => 'Sarah Jenkins',
        'username' => 'sarah_designs',
        'email' => 'sarah@kraftr.io',
        'title' => 'Senior UI/UX & Product Designer',
        'bio' => 'Certified UX designer specializing in design systems, mobile app interfaces, and SaaS dashboards in Figma. I turn complex problems into intuitive user experiences.',
        'skills' => 'Figma, UI/UX, Design Systems, Mobile App Design, Prototyping',
        'country' => 'United States',
        'seller_level' => 'pro',
        'rating_avg' => 5.00,
        'review_count' => 54,
        'avatar' => 'uploads/avatars/seller-2.jpg',
    ],
    [
        'name' => 'Tanvir Hossain',
        'username' => 'tanvir_ai',
        'email' => 'tanvir@kraftr.io',
        'title' => 'AI & Machine Learning Specialist',
        'bio' => 'Data Scientist and Python engineer building custom LLM agents, OpenAI integrations, RAG pipelines, and automated data processing tools.',
        'skills' => 'Python, AI/ML, LangChain, OpenAI, PyTorch, FastAPIs',
        'country' => 'Bangladesh',
        'seller_level' => 'rising',
        'rating_avg' => 4.90,
        'review_count' => 22,
        'avatar' => 'uploads/avatars/seller-3.jpg',
    ],
    [
        'name' => 'Elena Rostova',
        'username' => 'elena_motion',
        'email' => 'elena@kraftr.io',
        'title' => 'Motion Graphics & 3D Brand Artist',
        'bio' => 'Creative director and animator crafting viral promotional videos, 3D product visualizations, and stunning logo animations in After Effects and Blender.',
        'skills' => 'After Effects, Blender, 3D Animation, Motion Graphics, Premiere Pro',
        'country' => 'Germany',
        'seller_level' => 'top_rated',
        'rating_avg' => 4.98,
        'review_count' => 41,
        'avatar' => 'uploads/avatars/seller-4.jpg',
    ],
    [
        'name' => 'David Chen',
        'username' => 'david_seo',
        'email' => 'david@kraftr.io',
        'title' => 'Growth Marketer & Technical SEO Expert',
        'bio' => 'Helping startups and eCommerce brands scale organic traffic and conversions with audit-backed SEO strategies, conversion copywriting, and analytics.',
        'skills' => 'SEO, Content Strategy, Google Analytics, Copywriting, Conversion Rate',
        'country' => 'Singapore',
        'seller_level' => 'rising',
        'rating_avg' => 4.88,
        'review_count' => 19,
        'avatar' => 'uploads/avatars/seller-5.jpg',
    ],
];

$sellerIds = [];
$userInsertStmt = $pdo->prepare('
    INSERT INTO users (name, username, email, password_hash, role, status, avatar, title, bio, skills, country, is_verified, seller_level, wallet_balance, earnings_total, rating_avg, review_count)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        password_hash = VALUES(password_hash),
        role = VALUES(role),
        avatar = VALUES(avatar),
        title = VALUES(title),
        bio = VALUES(bio),
        skills = VALUES(skills),
        country = VALUES(country),
        is_verified = VALUES(is_verified),
        seller_level = VALUES(seller_level),
        rating_avg = VALUES(rating_avg),
        review_count = VALUES(review_count)
');

foreach ($sellers as $s) {
    $isPro = ($s['seller_level'] === 'pro') ? 1 : 0;
    $userInsertStmt->execute([
        $s['name'],
        $s['username'],
        $s['email'],
        $commonPasswordHash,
        'seller',
        'active',
        $s['avatar'],
        $s['title'],
        $s['bio'],
        $s['skills'],
        $s['country'],
        $isPro,
        $s['seller_level'],
        0.00,
        rand(850, 4500),
        $s['rating_avg'],
        $s['review_count'],
    ]);
    $uid = (int)$pdo->query("SELECT id FROM users WHERE username = '{$s['username']}'")->fetchColumn();
    $sellerIds[$s['username']] = $uid;
    echo "Seller created: {$s['name']} (@{$s['username']}) -> ID: {$uid}\n";
}

// 2. Five Buyers
$buyers = [
    [
        'name' => 'Michael Scott',
        'username' => 'michael_buyer',
        'email' => 'michael@kraftr.io',
        'country' => 'United States',
        'wallet_balance' => 450.00,
        'avatar' => 'uploads/avatars/buyer-1.jpg',
    ],
    [
        'name' => 'Nadia Rahman',
        'username' => 'nadia_buyer',
        'email' => 'nadia@kraftr.io',
        'country' => 'Bangladesh',
        'wallet_balance' => 300.00,
        'avatar' => 'uploads/avatars/buyer-2.jpg',
    ],
    [
        'name' => 'Alexander Wright',
        'username' => 'alex_buyer',
        'email' => 'alex@kraftr.io',
        'country' => 'United Kingdom',
        'wallet_balance' => 600.00,
        'avatar' => 'uploads/avatars/buyer-3.jpg',
    ],
    [
        'name' => 'Sophie Dubois',
        'username' => 'sophie_buyer',
        'email' => 'sophie@kraftr.io',
        'country' => 'France',
        'wallet_balance' => 250.00,
        'avatar' => 'uploads/avatars/buyer-4.jpg',
    ],
    [
        'name' => 'Kavita Patel',
        'username' => 'kavita_buyer',
        'email' => 'kavita@kraftr.io',
        'country' => 'Canada',
        'wallet_balance' => 500.00,
        'avatar' => 'uploads/avatars/buyer-5.jpg',
    ],
];

foreach ($buyers as $b) {
    $userInsertStmt->execute([
        $b['name'],
        $b['username'],
        $b['email'],
        $commonPasswordHash,
        'buyer',
        'active',
        $b['avatar'],
        'Verified Buyer',
        'Looking for experienced freelancers for ongoing agency and product projects.',
        'Project Management, Agile',
        $b['country'],
        0,
        'new',
        $b['wallet_balance'],
        0.00,
        0.00,
        0,
    ]);
    $uid = (int)$pdo->query("SELECT id FROM users WHERE username = '{$b['username']}'")->fetchColumn();
    echo "Buyer created: {$b['name']} (@{$b['username']}) -> ID: {$uid} (Wallet: \${$b['wallet_balance']})\n";
}

// 3. Ten Gigs
$catMap = [];
foreach ($pdo->query('SELECT id, slug FROM categories')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $catMap[$c['slug']] = (int)$c['id'];
}

$gigs = [
    [
        'seller' => 'rahim_dev',
        'category_slug' => 'web-app-dev',
        'title' => 'Build Modern Full-Stack Web Application with React and Node.js',
        'slug' => 'build-modern-full-stack-web-application-react-nodejs',
        'description' => "Are you looking for a production-ready, highly scalable web application built with modern architecture?\n\nI specialize in crafting complete full-stack web applications using React, Node.js, Express, and modern databases (PostgreSQL/MySQL/MongoDB).\n\nWhat I provide:\n- Responsive, pixel-perfect frontend designed for all screen sizes\n- Robust RESTful or GraphQL API backend\n- Secure user authentication (JWT, OAuth, Session)\n- Database schema design & optimization\n- Clean, maintainable, and thoroughly commented source code\n- Full deployment to Vercel, Railway, DigitalOcean or AWS\n\nLet's discuss your project requirements before ordering!",
        'price' => 120.00,
        'delivery_days' => 4,
        'revisions' => 3,
        'features' => "Custom RESTful API\nResponsive Mobile UI\nAuthentication & Database Setup\nClean Modular Code\nDeployment Support",
        'tags' => 'React, Node.js, Full Stack, Web App, JavaScript',
        'cover_image' => 'uploads/gigs/gig-1.jpg',
        'rating_avg' => 4.95,
        'review_count' => 28,
        'orders_count' => 42,
        'views_count' => 580,
        'is_featured' => 1,
    ],
    [
        'seller' => 'rahim_dev',
        'category_slug' => 'web-app-dev',
        'title' => 'Develop High Performance Next.js 14 Web Application with Tailwind CSS',
        'slug' => 'develop-high-performance-nextjs-14-web-application-tailwind',
        'description' => "Supercharge your online presence with a blazing-fast Next.js application.\n\nNext.js 14 App Router combined with Tailwind CSS delivers lightning performance, perfect Google Lighthouse scores, and outstanding SEO rankings.\n\nIncluded in this gig:\n- Next.js 14 with Server Components & Server Actions\n- Modern styling using Tailwind CSS and Radix UI\n- Dynamic routing & optimized image loading\n- SEO meta tags, OpenGraph, and sitemap generation\n- API route integration and third-party webhooks\n\nOrder now to take your website to the next level!",
        'price' => 95.00,
        'delivery_days' => 3,
        'revisions' => 2,
        'features' => "Server Side Rendering (SSR)\nSEO Optimized Structure\nTailwind CSS Styling\nDark Mode Support\nFast Loading Speed",
        'tags' => 'Next.js, React, Tailwind CSS, Frontend, Web Design',
        'cover_image' => 'uploads/gigs/gig-2.jpg',
        'rating_avg' => 4.90,
        'review_count' => 14,
        'orders_count' => 22,
        'views_count' => 340,
        'is_featured' => 0,
    ],
    [
        'seller' => 'sarah_designs',
        'category_slug' => 'ui-ux-design',
        'title' => 'Complete UI/UX Design for Mobile App and SaaS Platform in Figma',
        'slug' => 'complete-ui-ux-design-mobile-app-saas-platform-figma',
        'description' => "Transform your business idea into an aesthetically stunning and user-friendly digital product.\n\nWith over 7 years in product design, I design intuitive user flows, polished UI mockups, and interactive prototypes that delight users and drive conversions.\n\nWhat you will receive:\n- High-fidelity Figma UI design for Mobile or Web SaaS\n- Comprehensive Component Library with auto-layout\n- Clickable and shareable interactive prototype\n- Exported assets (SVG, PNG, font guidelines)\n- Structured handover ready for frontend developers",
        'price' => 150.00,
        'delivery_days' => 5,
        'revisions' => 4,
        'features' => "Interactive Figma Prototype\nComponent Design System\nMobile & Desktop Responsive\nDeveloper-Ready Handover\nUnlimited Icons & Assets",
        'tags' => 'UI/UX, Figma, Mobile App, Web Design, Prototype',
        'cover_image' => 'uploads/gigs/gig-3.jpg',
        'rating_avg' => 5.00,
        'review_count' => 32,
        'orders_count' => 51,
        'views_count' => 720,
        'is_featured' => 1,
    ],
    [
        'seller' => 'sarah_designs',
        'category_slug' => '3d-brand',
        'title' => 'Minimalist Brand Identity & Logo Design with Complete Styleguide',
        'slug' => 'minimalist-brand-identity-logo-design-complete-styleguide',
        'description' => "A memorable brand starts with a timeless logo. I create distinctive minimalist brand identities that resonate with your target audience.\n\nServices included:\n- 3 unique and creative logo concepts based on your vision\n- Full vector source files (AI, EPS, SVG, PDF, PNG)\n- Curated color palette and typography pairing\n- 15+ page brand styleguide book with dos and don'ts\n- Social media profile & banner kit",
        'price' => 85.00,
        'delivery_days' => 3,
        'revisions' => 3,
        'features' => "3 Custom Logo Concepts\nVector Source Files (AI, SVG, PDF)\nColor Palette & Typography\nBrand Guidelines Book\nSocial Media Kit",
        'tags' => 'Branding, Logo Design, Styleguide, Minimalist, Identity',
        'cover_image' => 'uploads/gigs/gig-4.jpg',
        'rating_avg' => 4.95,
        'review_count' => 24,
        'orders_count' => 36,
        'views_count' => 490,
        'is_featured' => 0,
    ],
    [
        'seller' => 'tanvir_ai',
        'category_slug' => 'ai-data',
        'title' => 'Build Custom AI Agent and ChatGPT OpenAI Integration for Business',
        'slug' => 'build-custom-ai-agent-chatgpt-openai-integration-business',
        'description' => "Harness the power of Artificial Intelligence to automate your workflows and support.\n\nI build custom AI agents, automated chatbots, and RAG (Retrieval Augmented Generation) knowledge pipelines powered by OpenAI GPT-4, Anthropic Claude, and LangChain.\n\nDeliverables:\n- Custom AI chatbot trained on your company documents / PDF / website\n- Vector search integration (Pinecone, ChromaDB, PGVector)\n- Python backend with FastAPI and REST endpoints\n- Streamlit or web UI for testing and administration\n- Secure API key management and rate limit safeguards",
        'price' => 180.00,
        'delivery_days' => 4,
        'revisions' => 3,
        'features' => "OpenAI GPT-4 API Integration\nRAG Vector Database Knowledgebase\nPython & FastAPI Backend\nWebhook Automations\nComplete Documentation",
        'tags' => 'AI, ChatGPT, OpenAI, Python, Machine Learning',
        'cover_image' => 'uploads/gigs/gig-5.jpg',
        'rating_avg' => 4.92,
        'review_count' => 18,
        'orders_count' => 29,
        'views_count' => 610,
        'is_featured' => 1,
    ],
    [
        'seller' => 'tanvir_ai',
        'category_slug' => 'ai-data',
        'title' => 'Python Data Analysis, Web Scraping and Automated ETL Pipelines',
        'slug' => 'python-data-analysis-web-scraping-automated-etl-pipelines',
        'description' => "Turn raw web data into actionable business intelligence.\n\nI develop fast, resilient web scrapers and automated ETL scripts in Python using Playwright, BeautifulSoup, and Pandas.\n\nHighlights:\n- Scraping data from JavaScript-heavy single page apps\n- Bypassing pagination and dynamic scroll limitations\n- Data sanitization, deduplication, and structuring\n- Export to Excel, CSV, Google Sheets, or MySQL\n- Scheduled automation scripts that run 24/7",
        'price' => 75.00,
        'delivery_days' => 2,
        'revisions' => 2,
        'features' => "Automated Web Scraping Script\nData Cleaning & Preprocessing\nInteractive Dashboard Visuals\nExport to CSV/Excel/SQL\nScheduled Cron Execution",
        'tags' => 'Python, Web Scraping, Data Science, Pandas, Automation',
        'cover_image' => 'uploads/gigs/gig-6.jpg',
        'rating_avg' => 4.88,
        'review_count' => 12,
        'orders_count' => 19,
        'views_count' => 290,
        'is_featured' => 0,
    ],
    [
        'seller' => 'elena_motion',
        'category_slug' => 'video-motion',
        'title' => 'Create Engaging 2D Explainer and Promotional Motion Graphics Video',
        'slug' => 'create-engaging-2d-explainer-promotional-motion-graphics-video',
        'description' => "Capture attention and explain complex concepts in seconds with a high-energy animated video.\n\nFrom scriptwriting to final sound design, I create eye-catching 2D motion graphics videos tailored for product launches, SaaS promos, and social ads.\n\nWhat is included:\n- Custom vector graphics illustrated to match your brand\n- Smooth 60fps animations in Adobe After Effects\n- Professional AI/Human voiceover sync\n- Royalty-free cinematic music and sound effects (SFX)\n- Delivered in 1080p Full HD or 4K with source files",
        'price' => 135.00,
        'delivery_days' => 4,
        'revisions' => 3,
        'features' => "Custom Script & Storyboard\nProfessional Voiceover Sync\nDynamic Sound Effects\n1080p Full HD / 4K Render\nCommercial Usage Rights",
        'tags' => 'Motion Graphics, Explainer Video, Animation, After Effects, Promo',
        'cover_image' => 'uploads/gigs/gig-7.jpg',
        'rating_avg' => 4.98,
        'review_count' => 27,
        'orders_count' => 39,
        'views_count' => 540,
        'is_featured' => 1,
    ],
    [
        'seller' => 'elena_motion',
        'category_slug' => '3d-brand',
        'title' => 'Photorealistic 3D Product Rendering and 360 Spin Animation in Blender',
        'slug' => 'photorealistic-3d-product-rendering-360-spin-animation-blender',
        'description' => "Elevate your eCommerce store with photorealistic 3D product visualizations that sell.\n\nUsing Blender and Cycles render engine, I turn packaging sketches or CAD models into studio-quality marketing assets for Amazon, Shopify, and social campaigns.\n\nDeliverables:\n- 3D product modeling and high-res PBR texturing\n- Studio lighting, transparent background & lifestyle scenes\n- Seamless 360-degree turntable video animation\n- 4K ultra HD still renders from multiple angles\n- Blender .blend project file included",
        'price' => 160.00,
        'delivery_days' => 5,
        'revisions' => 3,
        'features' => "High-Resolution 3D Model\nStudio Lighting & Textures\n360 Turntable Animation\nAmazon/Shopify Ready Images\nSource Blender File Included",
        'tags' => '3D Product, Blender, 3D Modeling, Animation, Rendering',
        'cover_image' => 'uploads/gigs/gig-8.jpg',
        'rating_avg' => 4.96,
        'review_count' => 19,
        'orders_count' => 27,
        'views_count' => 410,
        'is_featured' => 0,
    ],
    [
        'seller' => 'david_seo',
        'category_slug' => 'growth-seo',
        'title' => 'Complete Technical SEO Audit and Ranking Optimization Strategy',
        'slug' => 'complete-technical-seo-audit-ranking-optimization-strategy',
        'description' => "Stop losing traffic to competitors. Get your website ranking on Page 1 of Google with a comprehensive technical SEO roadmap.\n\nI will audit your entire site architecture, identify indexing bottlenecks, analyze keyword opportunities, and provide actionable fixes.\n\nIncluded in the audit:\n- Deep crawl analysis using Screaming Frog & Semrush\n- Core Web Vitals, page speed & mobile usability evaluation\n- Missing meta tags, duplicate content, and redirect chains\n- High-intent keyword opportunity map with search volumes\n- Step-by-step action plan prioritized by revenue impact",
        'price' => 110.00,
        'delivery_days' => 3,
        'revisions' => 2,
        'features' => "In-Depth On-Page & Technical Audit\nHigh-Volume Keyword Research\nCompetitor Backlink Analysis\nFix Recommendations Roadmap\nSchema Markup Implementation",
        'tags' => 'SEO, Technical SEO, Keyword Research, Growth, Traffic',
        'cover_image' => 'uploads/gigs/gig-9.jpg',
        'rating_avg' => 4.90,
        'review_count' => 16,
        'orders_count' => 25,
        'views_count' => 380,
        'is_featured' => 0,
    ],
    [
        'seller' => 'david_seo',
        'category_slug' => 'copywriting',
        'title' => 'High-Converting Landing Page Copywriting and Email Sales Sequences',
        'slug' => 'high-converting-landing-page-copywriting-email-sales-sequences',
        'description' => "Words that turn visitors into paying customers. I write persuasive, psychologically grounded copy that speaks directly to your ideal customer's pain points.\n\nWhat I write:\n- Hero section headlines with high-impact emotional hooks\n- Benefit-driven product section copy that overcomes objections\n- Compelling social proof framing & FAQ section\n- High-converting call-to-action (CTA) buttons\n- 5-part automated email welcome & sales sequence",
        'price' => 65.00,
        'delivery_days' => 2,
        'revisions' => 3,
        'features' => "Compelling Headline Variations\nPersuasive Value Proposition\nClear Call-to-Action (CTA) Hooks\n5-Part Automated Email Flow\nCompetitor Market Research",
        'tags' => 'Copywriting, Landing Page, Sales Copy, Email Marketing, Content',
        'cover_image' => 'uploads/gigs/gig-10.jpg',
        'rating_avg' => 4.86,
        'review_count' => 15,
        'orders_count' => 23,
        'views_count' => 330,
        'is_featured' => 0,
    ],
];

// Delete previous seed gigs if they exist with same slugs to avoid duplicates
$gigInsertStmt = $pdo->prepare('
    INSERT INTO gigs (seller_id, category_id, title, slug, description, price, delivery_days, revisions, features, tags, cover_image, status, is_featured, rating_avg, review_count, orders_count, views_count)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "active", ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        seller_id = VALUES(seller_id),
        category_id = VALUES(category_id),
        title = VALUES(title),
        description = VALUES(description),
        price = VALUES(price),
        delivery_days = VALUES(delivery_days),
        revisions = VALUES(revisions),
        features = VALUES(features),
        tags = VALUES(tags),
        cover_image = VALUES(cover_image),
        status = VALUES(status),
        is_featured = VALUES(is_featured),
        rating_avg = VALUES(rating_avg),
        review_count = VALUES(review_count),
        orders_count = VALUES(orders_count),
        views_count = VALUES(views_count)
');

foreach ($gigs as $idx => $g) {
    $sellerId = $sellerIds[$g['seller']];
    $catId = $catMap[$g['category_slug']] ?? 1;

    $gigInsertStmt->execute([
        $sellerId,
        $catId,
        $g['title'],
        $g['slug'],
        $g['description'],
        $g['price'],
        $g['delivery_days'],
        $g['revisions'],
        $g['features'],
        $g['tags'],
        $g['cover_image'],
        $g['is_featured'],
        $g['rating_avg'],
        $g['review_count'],
        $g['orders_count'],
        $g['views_count'],
    ]);

    $gid = (int)$pdo->query("SELECT id FROM gigs WHERE slug = '{$g['slug']}'")->fetchColumn();
    $num = $idx + 1;
    echo "Gig #{$num} stored: {$g['title']} (ID: {$gid} | Seller ID: {$sellerId} | Cat ID: {$catId} | Price: \${$g['price']} | Image: {$g['cover_image']})\n";
}

echo "\nAll 5 sellers, 5 buyers, and 10 gigs seeded successfully!\n";

