<?php
if (!defined('SOUNDHEAL_BOOTED')) {
    require_once __DIR__ . '/bootstrap.php';
}
$pageTitle = $pageTitle ?? config('app.name');
$pageDescription = $pageDescription ?? 'A calm, premium wellness operating system. Sound healing, breathwork and mindful experiences.';
$brandName = brand_name();

// SEO / social-share metadata. Pages may set $pageImage (a media path)
// and $pageType ('website' | 'article' | 'profile') before requiring this.
$pageType  = $pageType ?? 'website';

// Canonical URL — keep content-defining query params (e.g. ?event=ID
// so social crawlers attribute the per-event OG tags correctly) but
// drop common tracking ones that would otherwise create duplicates.
$canonicalQuery = $_GET;
foreach (['utm_source','utm_medium','utm_campaign','utm_content','utm_term','fbclid','gclid','msclkid','ref'] as $tracker) {
    unset($canonicalQuery[$tracker]);
}
$canonicalPath = (string) strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
$canonical = rtrim((string) config('app.url'), '/') . $canonicalPath
           . ($canonicalQuery ? '?' . http_build_query($canonicalQuery) : '');
$seoImageRaw = ($pageImage ?? '') !== ''
    ? (string) $pageImage
    : (string) (setting('seo_default_image', '')
        ?: setting('hero_image_path', '')
        ?: setting('about_hero_image_path', ''));
$seoImage = '';
if ($seoImageRaw !== '') {
    $m = media_src($seoImageRaw);
    $seoImage = str_starts_with($m, 'http')
        ? $m
        : rtrim((string) config('app.url'), '/') . '/' . ltrim($m, '/');
}


// Marketing tracking — admin pages set $skipTracking before requiring header.
if (empty($skipTracking)) {
    track_view();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e($brandName) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">

    <!-- Open Graph / social share -->
    <meta property="og:type" content="<?= e($pageType) ?>">
    <meta property="og:site_name" content="<?= e($brandName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:locale" content="en_US">
    <?php if ($seoImage !== ''): ?><meta property="og:image" content="<?= e($seoImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $seoImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <?php if ($seoImage !== ''): ?><meta name="twitter:image" content="<?= e($seoImage) ?>"><?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              navy:   { 950: '#0a1027', 900: '#0f172a', 800: '#1a2240' },
              gold:   { 500: '#c9a46a', 400: '#d8b97e', 300: '#e7d2a3' },
              beige:  { 100: '#f6efe5', 200: '#ece1cd' },
            },
            fontFamily: {
              serif: ['"Cormorant Garamond"', 'serif'],
              sans:  ['Inter', 'system-ui', 'sans-serif'],
            },
          }
        }
      }
    </script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-navy-950 text-beige-100 font-sans antialiased min-h-screen flex flex-col">
<?php require __DIR__ . '/navbar.php'; ?>
<main class="flex-1">
<?php
$flash = flash_render();
if ($flash !== '') {
    echo '<div class="max-w-5xl mx-auto px-4 mt-6">' . $flash . '</div>';
}
