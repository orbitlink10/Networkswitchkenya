<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    @php
        $homepageBrandContent = \App\Models\HomepageContent::current();
        $siteLogoUrl = $homepageBrandContent->siteLogoUrl();
        $pageTitle = trim($__env->yieldContent('title')) ?: config('app.name', 'Network Switches Kenya');
        $pageDescription = trim($__env->yieldContent('meta_description')) ?: 'Shop PoE, managed, unmanaged, gigabit and fiber network switches in Kenya.';
        $marketCssVersion = @filemtime(public_path('assets/market.css')) ?: time();
        $canonicalUrl = trim($__env->yieldContent('canonical_url'));
        $robotsContent = trim($__env->yieldContent('robots'));
        $openGraphTitle = trim($__env->yieldContent('og_title')) ?: $pageTitle;
        $openGraphDescription = trim($__env->yieldContent('og_description')) ?: $pageDescription;
        $openGraphImage = trim($__env->yieldContent('og_image')) ?: $siteLogoUrl;
        $openGraphType = trim($__env->yieldContent('og_type')) ?: 'website';
        $organizationSchema = \App\Support\StructuredData::organization($homepageBrandContent);
        $headerPhone = $homepageBrandContent->contactPhone();
        $headerPhoneHref = $headerPhone ? 'tel:'.preg_replace('/[^\d+]+/', '', $headerPhone) : null;
        $websiteSchema = \App\Support\StructuredData::website();
        $navItems = \App\Support\SwitchCatalog::navigation();
        $adminNavItems = $homepageBrandContent->navMenuItems();
        $compareCount = count((array) session('comparison', []));
    @endphp
    <title>{!! $pageTitle !!}</title>
    <meta name="description" content="{!! $pageDescription !!}">
    <link rel="canonical" href="{!! $canonicalUrl !== '' ? $canonicalUrl : \App\Support\CanonicalUrl::current() !!}">
    @if($robotsContent !== '')
        <meta name="robots" content="{!! $robotsContent !!}">
    @endif
    <meta property="og:type" content="{!! $openGraphType !!}">
    <meta property="og:site_name" content="{{ config('app.name', 'Network Switches Kenya') }}">
    <meta property="og:title" content="{!! $openGraphTitle !!}">
    <meta property="og:description" content="{!! $openGraphDescription !!}">
    <meta property="og:url" content="{!! $canonicalUrl !== '' ? $canonicalUrl : \App\Support\CanonicalUrl::current() !!}">
    @if($openGraphImage)
        <meta property="og:image" content="{{ \App\Support\CanonicalUrl::absoluteAsset($openGraphImage) }}">
    @endif
    <meta name="twitter:card" content="{{ $openGraphImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{!! $openGraphTitle !!}">
    <meta name="twitter:description" content="{!! $openGraphDescription !!}">
    @if($openGraphImage)
        <meta name="twitter:image" content="{{ \App\Support\CanonicalUrl::absoluteAsset($openGraphImage) }}">
    @endif
    <script type="application/ld+json">@json($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    <script type="application/ld+json">@json($websiteSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    <link rel="stylesheet" href="{{ asset('assets/market.css') }}?v={{ $marketCssVersion }}">
    <link rel="stylesheet" href="{{ asset('assets/switch.css') }}?v={{ $marketCssVersion }}">
    @stack('head')
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="top-header">
    <div class="nav-wrap">
        <a href="{{ route('home') }}" class="logo" aria-label="Go to homepage">
            @if($siteLogoUrl)
                <img class="logo-image" src="{{ $siteLogoUrl }}" alt="{{ config('app.name', 'Network Switches Kenya') }}">
            @else
                <span class="logo-main logo-main--single">{{ config('app.name', 'Network Switches Kenya') }}</span>
            @endif
        </a>

        <form class="search-form" method="get" action="{{ route('home') }}" role="search">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search switches by name, model, brand or SKU" aria-label="Search products" autocomplete="off" required>
            <button type="submit">Search</button>
        </form>

        <div class="header-actions">
            <nav class="top-links top-contact-links" aria-label="Header actions">
                @if($headerPhone && $headerPhoneHref)
                    <a class="contact-link contact-link--phone" href="{{ $headerPhoneHref }}">Phone {{ $headerPhone }}</a>
                @endif
                <a class="account-link header-compare-link" href="{{ route('comparison.index') }}">Compare @if($compareCount)({{ $compareCount }})@endif</a>
                <a class="account-link header-login-link" href="{{ route('login') }}">Login</a>
            </nav>

            <nav class="top-account-links top-account-links--menu-only" aria-label="Account">
                <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open navigation menu">
                    <span class="menu-toggle-icon" aria-hidden="true"></span>
                </button>
            </nav>
        </div>
    </div>

    <nav class="primary-nav" aria-label="Main navigation">
        <div class="primary-nav-inner">
            <a class="primary-nav-link" href="{{ route('home') }}">Home</a>

            @foreach($navItems as $navItem)
                <div class="mega-menu-item">
                    <a class="primary-nav-link mega-menu-toggle" href="{{ $navItem['url'] }}" aria-haspopup="true">{{ $navItem['label'] }}</a>
                    <div class="mega-menu-panel">
                        <a class="mega-menu-parent-link" href="{{ $navItem['url'] }}">{{ $navItem['label'] }} &rarr;</a>
                        <div class="mega-menu-columns">
                            @foreach($navItem['children'] as $group)
                                <div class="mega-menu-col">
                                    <h3 class="mega-menu-heading">{{ $group['label'] }}</h3>
                                    <ul class="mega-menu-list">
                                        @foreach($group['items'] as $child)
                                            <li><a class="mega-menu-link" href="{{ \App\Support\SwitchCatalog::categoryUrl($child['slug']) }}">{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            @foreach($adminNavItems as $adminItem)
                <a class="primary-nav-link" href="{{ $adminItem['url'] }}">{{ $adminItem['label'] }}</a>
            @endforeach

            <a class="primary-nav-link" href="{{ route('deals') }}">Deals</a>
            <a class="primary-nav-link" href="{{ route('blog.index') }}">Blog</a>
            <a class="primary-nav-link" href="{{ route('pages.show', ['page' => 'contact-us']) }}">Contact</a>
            <a class="primary-nav-link primary-nav-link--accent" href="{{ route('finder') }}">Find My Switch</a>
        </div>
    </nav>
</header>

<div class="mobile-menu-backdrop" data-menu-backdrop hidden></div>
<nav id="mobile-menu" class="mobile-menu" aria-label="Main navigation" data-mobile-menu hidden>
    <div class="mobile-menu-head">
        <span class="mobile-menu-title">Menu</span>
        <button type="button" class="mobile-menu-close" data-menu-close aria-label="Close navigation menu">&times;</button>
    </div>
    <ul class="mobile-menu-list">
        <li><a class="mobile-menu-link" href="{{ route('home') }}">Home</a></li>
        @foreach($navItems as $navItem)
            <li class="mobile-menu-accordion">
                <button type="button" class="mobile-menu-link mobile-menu-accordion-toggle" aria-expanded="false" aria-controls="mobile-submenu-{{ $loop->index }}">
                    <span>{{ $navItem['label'] }}</span>
                    <span class="mobile-menu-chevron" aria-hidden="true"></span>
                </button>
                <ul id="mobile-submenu-{{ $loop->index }}" class="mobile-menu-submenu" hidden>
                    <li><a class="mobile-menu-sublink" href="{{ $navItem['url'] }}">All {{ $navItem['label'] }}</a></li>
                    @foreach($navItem['children'] as $group)
                        @foreach($group['items'] as $child)
                            <li><a class="mobile-menu-sublink" href="{{ \App\Support\SwitchCatalog::categoryUrl($child['slug']) }}">{{ $child['label'] }}</a></li>
                        @endforeach
                    @endforeach
                </ul>
            </li>
        @endforeach
        @foreach($adminNavItems as $adminItem)
            <li><a class="mobile-menu-link" href="{{ $adminItem['url'] }}">{{ $adminItem['label'] }}</a></li>
        @endforeach
        <li><a class="mobile-menu-link" href="{{ route('deals') }}">Deals</a></li>
        <li><a class="mobile-menu-link" href="{{ route('blog.index') }}">Blog</a></li>
        <li><a class="mobile-menu-link" href="{{ route('finder') }}">Switch Finder</a></li>
        <li><a class="mobile-menu-link" href="{{ route('comparison.index') }}">Compare</a></li>
        <li><a class="mobile-menu-link" href="{{ route('pages.show', ['page' => 'contact-us']) }}">Contact Us</a></li>
        <li><a class="mobile-menu-link" href="{{ route('login') }}">Login</a></li>
    </ul>
</nav>

<main class="container" id="main-content">
    @if(session('success'))
        <div class="alert success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert error">
            {{ $errors->first() }}
        </div>
    @endif
    @yield('content')
</main>

<footer class="footer">
    <div class="footer-columns">
        <div class="footer-col">
            <h3>Shop</h3>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('network-switches') }}">Network Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('poe-switches') }}">PoE Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('managed-switches') }}">Managed Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('unmanaged-switches') }}">Unmanaged Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('gigabit-switches') }}">Gigabit Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('fiber-switches') }}">Fiber Switches</a>
        </div>
        <div class="footer-col">
            <h3>By Ports</h3>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('5-port-switches') }}">5 Port Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('8-port-switches') }}">8 Port Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('16-port-switches') }}">16 Port Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('24-port-switches') }}">24 Port Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('48-port-switches') }}">48 Port Switches</a>
        </div>
        <div class="footer-col">
            <h3>By Brand</h3>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('tp-link-switches') }}">TP-Link Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('ubiquiti-switches') }}">Ubiquiti Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('mikrotik-switches') }}">MikroTik Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('d-link-switches') }}">D-Link Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('tenda-switches') }}">Tenda Switches</a>
            <a href="{{ \App\Support\SwitchCatalog::categoryUrl('cisco-switches') }}">Cisco Switches</a>
        </div>
        <div class="footer-col">
            <h3>Information</h3>
            <a href="{{ route('pages.show', ['page' => 'about-us']) }}">About Us</a>
            <a href="{{ route('pages.show', ['page' => 'contact-us']) }}">Contact Us</a>
            <a href="{{ route('pages.show', ['page' => 'delivery-policy']) }}">Delivery</a>
            <a href="{{ route('pages.show', ['page' => 'warranty-policy']) }}">Warranty</a>
            <a href="{{ route('pages.show', ['page' => 'returns-policy']) }}">Returns</a>
            <a href="{{ route('pages.show', ['page' => 'terms-and-conditions']) }}">Terms</a>
            <a href="{{ route('pages.show', ['page' => 'privacy-policy']) }}">Privacy Policy</a>
        </div>
        <div class="footer-col">
            <h3>Help</h3>
            <a href="{{ route('finder') }}">Switch Finder</a>
            <a href="{{ route('blog.index') }}">Buying Guides</a>
            <a href="{{ route('deals') }}">Deals</a>
            <a href="{{ route('pages.show', ['page' => 'contact-us']) }}">Request Quote</a>
        </div>
    </div>
    <p>&copy; {{ date('Y') }} {{ config('business.name', config('app.name', 'Network Switches Kenya')) }}</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchForm = document.querySelector('.search-form');
    var searchInput = searchForm ? searchForm.querySelector('input[name="search"]') : null;

    if (searchForm && searchInput) {
        searchForm.addEventListener('submit', function (event) {
            if (searchInput.value.trim() === '') {
                event.preventDefault();
                searchInput.setCustomValidity('Please enter a product name to search.');
                searchInput.reportValidity();
                searchInput.setCustomValidity('');
                searchInput.focus();
            }
        });
    }

    var toggle = document.querySelector('.menu-toggle');
    var menu = document.querySelector('[data-mobile-menu]');
    var backdrop = document.querySelector('[data-menu-backdrop]');
    var closeButton = document.querySelector('[data-menu-close]');

    var setMenuState = function (open) {
        if (!menu) return;
        menu.hidden = !open;
        if (backdrop) {
            backdrop.hidden = !open;
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        }
        document.documentElement.classList.toggle('menu-is-open', open);
        if (open && closeButton) {
            closeButton.focus();
        }
    };

    if (toggle) {
        toggle.addEventListener('click', function () {
            setMenuState(menu.hidden);
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function () {
            setMenuState(false);
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setMenuState(false);
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menu && !menu.hidden) {
            setMenuState(false);
            if (toggle) toggle.focus();
        }
    });

    if (menu) {
        menu.querySelectorAll('.mobile-menu-accordion-toggle').forEach(function (accordionToggle) {
            accordionToggle.addEventListener('click', function () {
                var submenu = document.getElementById(accordionToggle.getAttribute('aria-controls') || '');
                if (!submenu) {
                    return;
                }

                var isOpen = !submenu.hidden;
                submenu.hidden = isOpen;
                accordionToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
            });
        });
    }

    // Mega menu hover/focus support
    document.querySelectorAll('.mega-menu-item').forEach(function (item) {
        var toggleEl = item.querySelector('.mega-menu-toggle');
        var panel = item.querySelector('.mega-menu-panel');

        item.addEventListener('mouseenter', function () { if (panel) panel.classList.add('is-open'); });
        item.addEventListener('mouseleave', function () { if (panel) panel.classList.remove('is-open'); });

        if (toggleEl && panel) {
            toggleEl.addEventListener('click', function (event) {
                if (window.matchMedia('(hover: none)').matches) {
                    event.preventDefault();
                    panel.classList.toggle('is-open');
                }
            });
        }
    });
});
</script>
</body>
</html>
