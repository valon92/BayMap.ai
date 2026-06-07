<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#f5f7fa">

    <title>BuyMap.ai — AI Semantic Shopping Search</title>
    <meta name="description" content="Describe what you want to buy in natural language. BuyMap.ai finds the best matches across marketplaces worldwide.">
    <meta name="keywords" content="AI shopping, semantic search, marketplace, cars, books, electronics, BuyMap">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://buymap.ai">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://buymap.ai">
    <meta property="og:title" content="BuyMap.ai — Describe it. BuyMap finds it.">
    <meta property="og:description" content="AI-powered semantic shopping engine. Search cars, books, art, electronics and more in natural language.">
    <meta property="og:image" content="https://buymap.ai/og-image.png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="BuyMap.ai">
    <meta name="twitter:description" content="AI marketplace assistant — natural language product search.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "BuyMap.ai",
        "url": "https://buymap.ai",
        "description": "AI-powered semantic shopping and marketplace search engine",
        "applicationCategory": "ShoppingApplication",
        "operatingSystem": "Any"
    }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-[#f5f7fa] text-slate-900">
    <div id="app"></div>
</body>
</html>
