/**
 * Summary cards horizontal scroll — swipe on mobile, arrow buttons on desktop.
 */
(function () {
    var MOBILE_BREAKPOINT = 768;

    function isSummaryMobileView() {
        return window.innerWidth <= MOBILE_BREAKPOINT;
    }

    function initSummaryCardsScroll(options) {
        options = options || {};
        var scrollStep = options.scrollStep || 200;
        var container = document.getElementById('summaryScroll');
        var leftBtn = document.getElementById('summaryLeft');
        var rightBtn = document.getElementById('summaryRight');

        if (!container || !leftBtn || !rightBtn) return;

        function updateArrows() {
            if (isSummaryMobileView()) {
                leftBtn.style.display = 'none';
                rightBtn.style.display = 'none';
                return;
            }
            var maxScroll = container.scrollWidth - container.clientWidth;
            leftBtn.style.display = container.scrollLeft > 10 ? 'flex' : 'none';
            rightBtn.style.display = container.scrollLeft < maxScroll - 10 ? 'flex' : 'none';
        }

        leftBtn.addEventListener('click', function () {
            container.scrollBy({ left: -scrollStep, behavior: 'smooth' });
        });
        rightBtn.addEventListener('click', function () {
            container.scrollBy({ left: scrollStep, behavior: 'smooth' });
        });
        container.addEventListener('scroll', updateArrows);
        window.addEventListener('resize', updateArrows);
        setTimeout(updateArrows, 500);
    }

    window.initSummaryCardsScroll = initSummaryCardsScroll;

    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('summaryScroll')) {
            initSummaryCardsScroll();
        }
    });
})();
