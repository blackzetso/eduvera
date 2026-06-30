<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <meta name="csrf-token" content="{{ csrf_token() }}">


        <!-- Meta Tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="author" content="StackBros">
        <meta name="description" content="Eduport- LMS, Education and Course Theme">

        <!-- Debug: أول سطر ينفذ في الصفحة - إذا ظهر في الكونسول فالصفحة الصحيحة تُحمّل -->
        <script>console.log('[App] 1. Blade/HTML loaded');</script>
        <!-- Dark mode -->
        <script>
            // Force light mode as default - clear any dark mode preference
            if (localStorage.getItem('theme') === 'dark') {
                localStorage.setItem('theme', 'light')
            }
            const storedTheme = localStorage.getItem('theme') || 'light'

            const getPreferredTheme = () => {
                return storedTheme === 'light' ? 'light' : 'light'
            }

            const setTheme = function (theme) {
                if (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark')
                } else {
                    document.documentElement.setAttribute('data-bs-theme', theme)
                }
            }

            setTheme(getPreferredTheme())

            window.addEventListener('DOMContentLoaded', () => {
                var el = document.querySelector('.theme-icon-active');
                if(el != 'undefined' && el != null) {
                    const showActiveTheme = theme => {
                    const activeThemeIcon = document.querySelector('.theme-icon-active use')
                    const btnToActive = document.querySelector(`[data-bs-theme-value="${theme}"]`)
                    const svgOfActiveBtn = btnToActive.querySelector('.mode-switch use').getAttribute('href')

                    document.querySelectorAll('[data-bs-theme-value]').forEach(element => {
                        element.classList.remove('active')
                    })

                    btnToActive.classList.add('active')
                    activeThemeIcon.setAttribute('href', svgOfActiveBtn)
                }

                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                    if (storedTheme !== 'light' || storedTheme !== 'dark') {
                        setTheme(getPreferredTheme())
                    }
                })

                showActiveTheme(getPreferredTheme())

                document.querySelectorAll('[data-bs-theme-value]')
                    .forEach(toggle => {
                        toggle.addEventListener('click', () => {
                            const theme = toggle.getAttribute('data-bs-theme-value')
                            localStorage.setItem('theme', theme)
                            setTheme(theme)
                            showActiveTheme(theme)
                        })
                    })

                }
            })

        </script>

        <!-- Favicon -->
        <link rel="shortcut icon" href="{{ asset('front/theme1/images/favicon.ico') }}">

        <!-- Google Font -->
        <link rel="preconnect" href="https://fonts.googleapis.com/">
        <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Almarai:wght@300;400;700;800&display=swap">

        <!-- Plugins CSS -->
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/font-awesome/css/all.min.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/bootstrap-icons/bootstrap-icons.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/glightbox/css/glightbox.css') }}">
        {{-- <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/quill/css/quill.snow.css') }}"> --}}
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/apexcharts/css/apexcharts.css') }}">
	    <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/overlay-scrollbar/css/overlayscrollbars.min.css') }}">
        <!-- Theme CSS -->
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/css/custome.css') }}">
        {{-- <link rel="stylesheet" type="text/css" href="{{ asset('front/theme1/vendor/choices/css/choices.min.css') }}"> --}}

        <!-- Plugins CSS -->

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <!-- يظهر فوراً حتى لو Vue لم يعمل - لو شفت "جاري التحميل" والصبغة فاضية فالمشكلة من JS أو الـ build -->
        <div id="app-loading-msg" style="padding:12px 20px; background:#e8e8e8; color:#333; font-family: monospace; font-size: 14px; text-align: center;">جاري التحميل...</div>
        @inertia
        <!-- رسالة تظهر لو التطبيق لم يعلق خلال ثانيتين (تشخيص الشاشة البيضاء) -->
        <div id="app-debug-fallback" style="display:none; padding:1rem; margin:1rem; background:#fee; color:#c00; font-family: monospace; font-size: 14px; border: 1px solid #c00;">
            <strong>[Debug] التطبيق لم يبدأ.</strong><br>
            1) افتح <a href="/build-check" target="_blank">/build-check</a> وتأكد أن manifest.json موجود.<br>
            2) في Developer Tools → Network تحقق: هل ملف app-xxx.js يرجع 200 أم 404؟<br>
            3) إن كان الطلب يذهب إلى <code>localhost:5173</code> بدون تشغيل Vite: أوقف السيرفر ثم شغّل <code>composer serve</code> أو <code>composer dev</code>.
        </div>
        <script>
            (function () {
                console.log('[App] 2. Body script running');
                function hideLoading() {
                    var loadingMsg = document.getElementById('app-loading-msg');
                    if (loadingMsg) loadingMsg.style.display = 'none';
                }
                window.addEventListener('inertia-mounted', hideLoading);
                document.addEventListener('DOMContentLoaded', function () {
                    var root = document.getElementById('app');
                    console.log('[App] 3. DOM ready. Inertia root #app:', root ? 'found' : 'missing');
                    setTimeout(function () {
                        var appRoot = document.getElementById('app');
                        var fallback = document.getElementById('app-debug-fallback');
                        var loadingMsg = document.getElementById('app-loading-msg');
                        var mounted = window.__appMounted || (appRoot && appRoot.childElementCount > 0);
                        if (fallback && appRoot && !mounted) {
                            hideLoading();
                            fallback.style.display = 'block';
                            console.warn('[App] 4. Vue did not mount - showing fallback message');
                        } else if (loadingMsg && mounted) {
                            hideLoading();
                        }
                    }, 2000);
                });
            })();
        </script>
        <!-- Back to top -->
        <div class="back-top"><i class="bi bi-arrow-up-short position-absolute top-50 start-50 translate-middle"></i></div>

        {{-- Theme/vendor scripts: must not target or replace the Inertia/Vue root element (e.g. #app) to avoid breaking the SPA mount. --}}
        <!-- Bootstrap JS -->
        <script src="{{ asset('front/theme1/vendor/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>



        <!-- Vendors -->
        {{-- <script src="{{ asset('front/theme1/vendor/choices/js/choices.min.js') }}"></script> --}}
        <script src="{{ asset('front/theme1/vendor/glightbox/js/glightbox.js') }}"></script>
        {{-- <script src="{{ asset('front/theme1/vendor/quill/js/quill.min.js') }}"></script> --}}
        <script src="{{ asset('front/theme1/vendor/purecounterjs/dist/purecounter_vanilla.js') }}"></script>

        <script src="{{ asset('front/theme1/vendor/apexcharts/js/apexcharts.min.js') }}"></script>
        <script src="{{ asset('front/theme1/vendor/overlay-scrollbar/js/overlayscrollbars.min.js') }}"></script>

        <!-- Template Functions -->
        <script src="{{ asset('front/theme1/js/functions.js') }}"></script>

        @if(request()->is('canteen/pos'))
            @include('canteen.pos-wallet-patch')
        @endif
    </body>
</html>
