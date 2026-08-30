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

// Theme resolution — visitor cookie wins, otherwise the site-wide
// default from site_settings, then hardcoded 'dark'. Only two values
// are honoured; anything else falls back to 'dark' so a stray cookie
// can't leave the page unstyled.
$cookieTheme = (string) ($_COOKIE['sh-theme'] ?? '');
$defaultTheme = (string) setting('site_theme', 'dark');
$theme = in_array($cookieTheme, ['dark','light'], true)
    ? $cookieTheme
    : (in_array($defaultTheme, ['dark','light'], true) ? $defaultTheme : 'dark');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
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
    <!-- Theme colour scales resolve through CSS variables so the
         same Tailwind classes (bg-navy-950, text-beige-100, etc.)
         render differently under [data-theme="light"] without every
         template needing to change. Values are space-separated RGB
         triplets — that's the Tailwind 3 shape needed for opacity
         modifiers (bg-navy-950/40 becomes rgb(var(...) / 0.4)). -->
    <style>
      :root, [data-theme="dark"] {
        --c-navy-950: 10 16 39;      /* base bg */
        --c-navy-900: 15 23 42;      /* raised panels */
        --c-navy-800: 26 34 64;      /* inputs / chips */
        --c-beige-100: 246 239 229;  /* primary text */
        --c-beige-200: 236 225 205;  /* secondary text */
        --c-gold-500: 201 164 106;   /* accent */
        --c-gold-400: 216 185 126;
        --c-gold-300: 231 210 163;
        color-scheme: dark;
      }
      [data-theme="light"] {
        /* Warm cream base — same beige palette the dark theme uses
           for text, now flipped to be the background. Text becomes
           the deep navy. Gold shifts one notch darker so it holds
           contrast on cream. */
        --c-navy-950: 250 245 234;   /* bg (was navy-950) */
        --c-navy-900: 241 232 213;   /* panels */
        --c-navy-800: 226 212 184;   /* inputs / borders on cream */
        --c-beige-100: 26 32 61;     /* primary text (was beige-100) */
        --c-beige-200: 42 51 88;
        --c-gold-500: 166 125 59;
        --c-gold-400: 184 148 90;
        --c-gold-300: 203 172 122;
        color-scheme: light;
      }
    </style>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              navy: {
                950: 'rgb(var(--c-navy-950) / <alpha-value>)',
                900: 'rgb(var(--c-navy-900) / <alpha-value>)',
                800: 'rgb(var(--c-navy-800) / <alpha-value>)',
              },
              gold: {
                500: 'rgb(var(--c-gold-500) / <alpha-value>)',
                400: 'rgb(var(--c-gold-400) / <alpha-value>)',
                300: 'rgb(var(--c-gold-300) / <alpha-value>)',
              },
              beige: {
                100: 'rgb(var(--c-beige-100) / <alpha-value>)',
                200: 'rgb(var(--c-beige-200) / <alpha-value>)',
              },
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
    <style>
      [x-cloak]{display:none!important}

      /* Native date / time inputs pick up the resolved colour scheme
         from :root's color-scheme property (set alongside the palette
         variables above). The picker-indicator glyph only needs
         inverting on dark — light-mode leaves it as-is. */
      input[type="date"]::-webkit-calendar-picker-indicator,
      input[type="time"]::-webkit-calendar-picker-indicator,
      input[type="datetime-local"]::-webkit-calendar-picker-indicator,
      input[type="month"]::-webkit-calendar-picker-indicator,
      input[type="week"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.85; }
      input[type="date"]::-webkit-calendar-picker-indicator:hover,
      input[type="time"]::-webkit-calendar-picker-indicator:hover,
      input[type="datetime-local"]::-webkit-calendar-picker-indicator:hover,
      input[type="month"]::-webkit-calendar-picker-indicator:hover,
      input[type="week"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }
      [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
      [data-theme="dark"] input[type="time"]::-webkit-calendar-picker-indicator,
      [data-theme="dark"] input[type="datetime-local"]::-webkit-calendar-picker-indicator,
      [data-theme="dark"] input[type="month"]::-webkit-calendar-picker-indicator,
      [data-theme="dark"] input[type="week"]::-webkit-calendar-picker-indicator {
        filter: invert(1) brightness(1.4);
      }
    </style>
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
