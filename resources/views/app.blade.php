<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Powerbook.ai — AI Semantic Shopping Search</title>
    <meta name="description" content="Describe what you want to buy in natural language. Powerbook.ai finds the best matches across marketplaces worldwide.">
    <meta name="keywords" content="AI shopping, semantic search, marketplace, cars, books, electronics, Powerbook">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://powerbook.ai">

    <meta property="og:type" content="website">
    <meta property="og:url" content="https://powerbook.ai">
    <meta property="og:title" content="Powerbook.ai — Describe it. Powerbook finds it.">
    <meta property="og:description" content="AI-powered semantic shopping engine. Search cars, books, art, electronics and more in natural language.">
    <meta property="og:image" content="https://powerbook.ai/og-image.png">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Powerbook.ai">
    <meta name="twitter:description" content="AI marketplace assistant — natural language product search.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Powerbook.ai",
        "url": "https://powerbook.ai",
        "description": "AI-powered semantic shopping and marketplace search engine",
        "applicationCategory": "ShoppingApplication",
        "operatingSystem": "Any"
    }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-slate-950 text-slate-100">
    <div id="app"></div>
</body>
</html>
