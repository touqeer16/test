#!/usr/bin/env php
<?php

    // Usage:
    //   php detect.php https://example.com
    //   php detect.php https://example.com --json # for JSON output

    if ($argc < 2) {
    echo "Usage: php detect.php <url> [--json]\n";
    exit(1);
    }

    $url      = $argv[1];
    $jsonMode = in_array('--json', $argv, true);

    // Simple color helpers (only for CLI mode)
    function color($text, $colorCode)
    {
    return "\033[" . $colorCode . "m" . $text . "\033[0m";
    }

    function green($text)
    {return color($text, '32');}
    function yellow($text)
    {return color($text, '33');}
    function cyan($text)
    {return color($text, '36');}
    function red($text)
    {return color($text, '31');}

    // Fetch URL with cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 FrameworkDetector");
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);

    $response = curl_exec($ch);

    if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    if ($jsonMode) {
        echo json_encode([
            'url'   => $url,
            'error' => $error,
        ], JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo red("Error fetching URL: $error\n");
    }
    exit(1);
    }

    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers    = substr($response, 0, $headerSize);
    $body       = substr($response, $headerSize);
    $info       = curl_getinfo($ch);
    curl_close($ch);

    // Normalize
    $h = strtolower($headers);
    $b = strtolower($body);

    // Parsed structures
    $detected = [
    'url'        => $url,
    'status'     => $info['http_code'] ?? null,
    'server'     => null,
    'cdn'        => [],
    'waf'        => [],
    'cms'        => [],
    'frameworks' => [],
    'frontend'   => [],
    'ecommerce'  => [],
    'plugins'    => [],
    'analytics'  => [],
    ];

    // Helper: add unique
    function add_unique(&$arr, $value)
    {
    if (! in_array($value, $arr, true)) {
        $arr[] = $value;
    }
    }

    // Extract Server header
    if (preg_match('/^server:\s*(.+)$/mi', $headers, $m)) {
    $detected['server'] = trim($m[1]);
    }

    /* -----------------------------------------
   CMS / SITE BUILDERS
------------------------------------------*/

    // WordPress
    if (
    strpos($h, 'x-pingback') !== false ||
    strpos($h, 'wp-json') !== false ||
    strpos($b, 'wp-content') !== false ||
    strpos($b, 'wp-includes') !== false ||
    strpos($b, 'content="wordpress') !== false
    ) {
    add_unique($detected['cms'], 'WordPress');
    }

    // Joomla
    if (strpos($h, 'x-content-powered-by: joomla') !== false ||
    strpos($b, 'content="joomla') !== false) {
    add_unique($detected['cms'], 'Joomla');
    }

    // Drupal
    if (strpos($h, 'x-generator: drupal') !== false ||
    strpos($b, 'drupal') !== false) {
    add_unique($detected['cms'], 'Drupal');
    }

    // Magento
    if (strpos($b, 'mage-cache') !== false ||
    strpos($h, 'set-cookie: mage') !== false ||
    strpos($b, 'magento') !== false) {
    add_unique($detected['cms'], 'Magento');
    }

    // Shopify
    if (strpos($h, 'x-shopify') !== false ||
    strpos($b, 'cdn.shopify.com') !== false ||
    strpos($b, 'shopify') !== false) {
    add_unique($detected['cms'], 'Shopify');
    }

    // PrestaShop
    if (strpos($b, 'prestashop') !== false ||
    strpos($h, 'set-cookie: prestashop') !== false) {
    add_unique($detected['cms'], 'PrestaShop');
    }

    // OpenCart
    if (strpos($b, 'index.php?route=common/home') !== false ||
    strpos($b, 'opencart') !== false) {
    add_unique($detected['cms'], 'OpenCart');
    }

    // Wix
    if (strpos($b, 'wix.com') !== false ||
    strpos($h, 'x-wix-request-id') !== false) {
    add_unique($detected['cms'], 'Wix');
    }

    // Squarespace
    if (strpos($b, 'static1.squarespace.com') !== false ||
    strpos($b, 'squarespace') !== false) {
    add_unique($detected['cms'], 'Squarespace');
    }

    // Weebly
    if (strpos($b, 'weeblycloud') !== false ||
    strpos($b, 'weebly') !== false) {
    add_unique($detected['cms'], 'Weebly');
    }

    // Ghost
    if (strpos($b, 'content="ghost') !== false ||
    strpos($b, 'ghost-sdk') !== false) {
    add_unique($detected['cms'], 'Ghost');
    }

    // Blogger
    if (strpos($b, 'blogger.com') !== false ||
    strpos($b, 'blogspot.com') !== false) {
    add_unique($detected['cms'], 'Blogger');
    }

    // Webflow
    if (strpos($b, 'webflow.js') !== false ||
    strpos($b, 'webflow.com') !== false) {
    add_unique($detected['cms'], 'Webflow');
    }

    /* -----------------------------------------
   WORDPRESS PLUGINS / THEMES
------------------------------------------*/

    // WooCommerce
    if (strpos($b, 'woocommerce') !== false ||
    strpos($b, 'wp-content/plugins/woocommerce') !== false) {
    add_unique($detected['ecommerce'], 'WooCommerce');
    add_unique($detected['plugins'], 'WooCommerce');
    }

    // Elementor
    if (strpos($b, 'wp-content/plugins/elementor') !== false) {
    add_unique($detected['plugins'], 'Elementor');
    }

    // WPBakery
    if (strpos($b, 'wp-content/plugins/js_composer') !== false ||
    strpos($b, 'wpbakery') !== false) {
    add_unique($detected['plugins'], 'WPBakery Page Builder');
    }

    // Yoast SEO
    if (strpos($b, 'wp-content/plugins/wordpress-seo') !== false ||
    strpos($b, 'yoast') !== false) {
    add_unique($detected['plugins'], 'Yoast SEO');
    }

    // Jetpack
    if (strpos($b, 'wp-content/plugins/jetpack') !== false) {
    add_unique($detected['plugins'], 'Jetpack');
    }

    // Contact Form 7
    if (strpos($b, 'wp-content/plugins/contact-form-7') !== false) {
    add_unique($detected['plugins'], 'Contact Form 7');
    }

    // Divi
    if (strpos($b, 'wp-content/themes/divi') !== false) {
    add_unique($detected['plugins'], 'Divi Theme');
    }

    /* -----------------------------------------
   PHP FRAMEWORKS
------------------------------------------*/

    // CodeIgniter
    if (strpos($h, 'ci_session') !== false) {
    add_unique($detected['frameworks'], 'CodeIgniter');
    }

    // Laravel
    if (
    strpos($h, 'laravel_session') !== false ||
    strpos($h, 'xsrf-token') !== false ||
    preg_match('/\{\{.*?\}\}/', $b) ||
    preg_match('/@csrf|@foreach|@if/', $b)
    ) {
    add_unique($detected['frameworks'], 'Laravel');
    }

    // Symfony
    if (strpos($h, 'x-debug-token') !== false ||
    strpos($h, 'x-symfony') !== false) {
    add_unique($detected['frameworks'], 'Symfony');
    }

    // Yii
    if (strpos($b, 'yii-debug-toolbar') !== false ||
    strpos($h, 'yii') !== false) {
    add_unique($detected['frameworks'], 'Yii');
    }

    // CakePHP
    if (strpos($h, 'cakephp') !== false ||
    strpos($b, 'cakephp') !== false) {
    add_unique($detected['frameworks'], 'CakePHP');
    }

    // Zend / Laminas
    if (strpos($b, 'zend framework') !== false ||
    strpos($b, 'laminas') !== false) {
    add_unique($detected['frameworks'], 'Zend / Laminas');
    }

    // Slim
    if (strpos($b, 'slim framework') !== false) {
    add_unique($detected['frameworks'], 'Slim');
    }

    // Generic PHP
    if (strpos($h, 'x-powered-by: php') !== false) {
    add_unique($detected['frameworks'], 'Generic PHP');
    }

    /* -----------------------------------------
   PYTHON FRAMEWORKS
------------------------------------------*/

    // Django
    if (strpos($h, 'csrftoken') !== false ||
    strpos($h, 'sessionid') !== false ||
    strpos($b, 'csrfmiddlewaretoken') !== false) {
    add_unique($detected['frameworks'], 'Django');
    }

    // Flask
    if (strpos($h, 'werkzeug') !== false ||
    strpos($h, 'flask') !== false) {
    add_unique($detected['frameworks'], 'Flask');
    }

    // FastAPI
    if (strpos($h, 'fastapi') !== false ||
    strpos($b, 'fastapi') !== false) {
    add_unique($detected['frameworks'], 'FastAPI');
    }

    // Tornado
    if (strpos($b, 'tornado.web') !== false) {
    add_unique($detected['frameworks'], 'Tornado');
    }

    /* -----------------------------------------
   RUBY FRAMEWORKS
------------------------------------------*/

    // Ruby on Rails
    if (strpos($h, 'x-runtime') !== false ||
    strpos($h, 'x-request-id') !== false ||
    strpos($b, 'rails') !== false) {
    add_unique($detected['frameworks'], 'Ruby on Rails');
    }

    // Sinatra
    if (strpos($b, 'sinatra') !== false) {
    add_unique($detected['frameworks'], 'Sinatra');
    }

    /* -----------------------------------------
   NODE.JS FRAMEWORKS
------------------------------------------*/

    // Express.js
    if (strpos($h, 'x-powered-by: express') !== false) {
    add_unique($detected['frameworks'], 'Express.js');
    }

    // Next.js
    if (strpos($b, '__next_data__') !== false) {
    add_unique($detected['frameworks'], 'Next.js');
    }

    // Nuxt.js
    if (strpos($b, 'nuxt.config') !== false ||
    strpos($b, 'nuxt.js') !== false ||
    strpos($b, 'data-n-head') !== false) {
    add_unique($detected['frameworks'], 'Nuxt.js');
    }

    // NestJS
    if (strpos($b, 'nestjs') !== false) {
    add_unique($detected['frameworks'], 'NestJS');
    }

    // Sails.js
    if (strpos($b, 'sails.js') !== false) {
    add_unique($detected['frameworks'], 'Sails.js');
    }

    /* -----------------------------------------
   JAVA FRAMEWORKS
------------------------------------------*/

    // Spring Boot
    if (strpos($h, 'x-application-context') !== false ||
    strpos($b, 'whitelabel error page') !== false) {
    add_unique($detected['frameworks'], 'Spring Boot');
    }

    // JSP / Java Server Pages
    if (strpos($b, '.jsp') !== false ||
    strpos($h, 'jsessionid') !== false) {
    add_unique($detected['frameworks'], 'Java JSP');
    }

    // Struts
    if (strpos($b, 'struts') !== false) {
    add_unique($detected['frameworks'], 'Apache Struts');
    }

    /* -----------------------------------------
   .NET / MICROSOFT STACK
------------------------------------------*/

    // ASP.NET
    if (strpos($h, 'x-aspnet-version') !== false ||
    strpos($h, 'x-powered-by: asp.net') !== false ||
    strpos($h, 'asp.net') !== false) {
    add_unique($detected['frameworks'], 'ASP.NET');
    }

    // Blazor
    if (strpos($b, 'blazor.webassembly.js') !== false) {
    add_unique($detected['frameworks'], 'Blazor');
    }

    /* -----------------------------------------
   FRONTEND FRAMEWORKS
------------------------------------------*/

    // React
    if (strpos($b, 'react-dom.production.min.js') !== false ||
    strpos($b, 'react') !== false) {
    add_unique($detected['frontend'], 'React');
    }

    // Vue.js
    if (strpos($b, 'vue.runtime.esm.js') !== false ||
    strpos($b, 'vue.js') !== false) {
    add_unique($detected['frontend'], 'Vue.js');
    }

    // Angular
    if (preg_match('/runtime\.[a-z0-9]+\.js/', $b) &&
    preg_match('/main\.[a-z0-9]+\.js/', $b)) {
    add_unique($detected['frontend'], 'Angular');
    }

    // Svelte
    if (strpos($b, 'svelte') !== false &&
    strpos($b, 'svelte.dev') !== false) {
    add_unique($detected['frontend'], 'Svelte');
    }

    // Alpine.js
    if (strpos($b, 'alpinejs') !== false ||
    strpos($b, 'x-data=') !== false) {
    add_unique($detected['frontend'], 'Alpine.js');
    }

    /* -----------------------------------------
   CDN DETECTION
------------------------------------------*/

    // Cloudflare
    if (strpos($h, 'cf-ray') !== false ||
    strpos($h, 'cloudflare') !== false) {
    add_unique($detected['cdn'], 'Cloudflare');
    }

    // Akamai
    if (strpos($h, 'akamai') !== false ||
    strpos($h, 'x-akamai') !== false) {
    add_unique($detected['cdn'], 'Akamai');
    }

    // Fastly
    if (strpos($h, 'fastly') !== false ||
    strpos($h, 'x-served-by: cache-') !== false) {
    add_unique($detected['cdn'], 'Fastly');
    }

    // CloudFront
    if (strpos($h, 'cloudfront') !== false ||
    strpos($h, 'x-amz-cf-id') !== false) {
    add_unique($detected['cdn'], 'Amazon CloudFront');
    }

    // StackPath
    if (strpos($h, 'stackpath') !== false) {
    add_unique($detected['cdn'], 'StackPath');
    }

    /* -----------------------------------------
   WAF / SECURITY
------------------------------------------*/

    // Cloudflare WAF
    if (in_array('Cloudflare', $detected['cdn'], true)) {
    add_unique($detected['waf'], 'Cloudflare WAF');
    }

    // Sucuri
    if (strpos($h, 'x-sucuri-id') !== false ||
    strpos($h, 'sucuri') !== false) {
    add_unique($detected['waf'], 'Sucuri');
    }

    // Imperva
    if (strpos($h, 'x-cdn: imperva') !== false ||
    strpos($h, 'incapsula') !== false) {
    add_unique($detected['waf'], 'Imperva / Incapsula');
    }

    // ModSecurity
    if (strpos($h, 'mod_security') !== false ||
    strpos($h, 'modsecurity') !== false) {
    add_unique($detected['waf'], 'ModSecurity');
    }

    /* -----------------------------------------
   SERVER DETECTION (MORE SPECIFIC)
------------------------------------------*/

    if ($detected['server']) {
    $s = strtolower($detected['server']);
    if (strpos($s, 'nginx') !== false) {
        // already implicit, but keep as-is
    } elseif (strpos($s, 'apache') !== false) {
        // same
    } elseif (strpos($s, 'litespeed') !== false) {
        // same
    } elseif (strpos($s, 'microsoft-iis') !== false || strpos($s, 'iis') !== false) {
        // same
    } elseif (strpos($s, 'caddy') !== false) {
        // same
    }
    }

    /* -----------------------------------------
   E-COMMERCE (NON-WP)
------------------------------------------*/

    // BigCommerce
    if (strpos($b, 'bigcommerce') !== false) {
    add_unique($detected['ecommerce'], 'BigCommerce');
    }

    // Ecwid
    if (strpos($b, 'ecwid') !== false) {
    add_unique($detected['ecommerce'], 'Ecwid');
    }

    /* -----------------------------------------
   ANALYTICS
------------------------------------------*/

    // Google Analytics
    if (strpos($b, 'www.google-analytics.com/analytics.js') !== false ||
    strpos($b, 'gtag(') !== false ||
    strpos($b, 'googletagmanager.com/gtm.js') !== false) {
    add_unique($detected['analytics'], 'Google Analytics / GTM');
    }

    // Matomo
    if (strpos($b, 'matomo.js') !== false ||
    strpos($b, 'piwik.js') !== false) {
    add_unique($detected['analytics'], 'Matomo (Piwik)');
    }

    // Cloudflare Analytics
    if (strpos($b, 'static.cloudflareinsights.com') !== false) {
    add_unique($detected['analytics'], 'Cloudflare Analytics');
    }

    /* -----------------------------------------
   OUTPUT
------------------------------------------*/

    if ($jsonMode) {
    echo json_encode($detected, JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
    }

    // CLI colored output
    echo cyan("Checking: {$detected['url']}\n");
    echo "HTTP Status: " . ($detected['status'] ?? 'unknown') . "\n";

    if ($detected['server']) {
    echo "Server: " . yellow($detected['server']) . "\n";
    }

    echo "\n";

    if (! empty($detected['cdn'])) {
    echo green("CDN:\n");
    foreach ($detected['cdn'] as $c) {
        echo "  - $c\n";
    }
    echo "\n";
    }

    if (! empty($detected['waf'])) {
    echo green("WAF / Security:\n");
    foreach ($detected['waf'] as $w) {
        echo "  - $w\n";
    }
    echo "\n";
    }

    if (! empty($detected['cms'])) {
    echo green("CMS / Site Builders:\n");
    foreach ($detected['cms'] as $c) {
        echo "  - $c\n";
    }
    echo "\n";
    }

    if (! empty($detected['frameworks'])) {
    echo green("Back-end Frameworks:\n");
    foreach ($detected['frameworks'] as $f) {
        echo "  - $f\n";
    }
    echo "\n";
    }

    if (! empty($detected['frontend'])) {
    echo green("Front-end Frameworks:\n");
    foreach ($detected['frontend'] as $f) {
        echo "  - $f\n";
    }
    echo "\n";
    }

    if (! empty($detected['ecommerce'])) {
    echo green("E-commerce Platforms:\n");
    foreach ($detected['ecommerce'] as $e) {
        echo "  - $e\n";
    }
    echo "\n";
    }

    if (! empty($detected['plugins'])) {
    echo green("WordPress Plugins / Themes:\n");
    foreach ($detected['plugins'] as $p) {
        echo "  - $p\n";
    }
    echo "\n";
    }

    if (! empty($detected['analytics'])) {
    echo green("Analytics:\n");
    foreach ($detected['analytics'] as $a) {
        echo "  - $a\n";
    }
    echo "\n";
    }

    if (
    empty($detected['cms']) &&
    empty($detected['frameworks']) &&
    empty($detected['frontend']) &&
    empty($detected['ecommerce']) &&
    empty($detected['plugins'])
    ) {
    echo red("No clear framework/CMS detected.\n");
    }

echo "\nDone.\n";
