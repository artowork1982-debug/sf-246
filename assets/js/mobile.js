// assets/js/mobile.js
(function () {
    'use strict';

    function checkOrientation() {
        const warning = document.getElementById('sfRotateWarning');
        if (!warning) return;

        const isLandscape = window.innerWidth > window.innerHeight;
        const isMobile = window.innerWidth <= 900 || window.innerHeight <= 500;

        warning.style.display = (isLandscape && isMobile) ? 'flex' : 'none';
    }

    function setVH() {
        // Set CSS variable for viewport height (iOS support)
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', vh + 'px');
        // Do not set fixed height values on elements
    }

    window.addEventListener('resize', function () {
        checkOrientation();
        setVH();
    });

    window.addEventListener('orientationchange', function () {
        setTimeout(function () {
            checkOrientation();
            setVH();
        }, 100);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            checkOrientation();
            setVH();
        });
    } else {
        checkOrientation();
        setVH();
    }
})();