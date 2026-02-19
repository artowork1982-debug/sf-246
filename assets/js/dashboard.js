// assets/js/dashboard.js
(function () {
    'use strict';

    // Time filter functionality
    function initTimeFilter() {
        const filterButtons = document.querySelectorAll('.sf-time-filter-btn');
        const monthSelect = document.getElementById('sf-filter-month');
        const yearSelect = document.getElementById('sf-filter-year');
        const statsContainer = document.querySelector('.sf-dashboard-stats-section');

        if (!statsContainer) return;

        // Get current date values once
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        // Handle quick selection buttons
        filterButtons.forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();

                const period = this.dataset.period || 'thisyear';

                // Update active state
                filterButtons.forEach(b => b.classList.remove('sf-active'));
                this.classList.add('sf-active');

                // Set dropdowns based on period
                if (monthSelect && yearSelect) {
                    switch (period) {
                        case 'thismonth':
                            monthSelect.value = currentMonth;
                            yearSelect.value = currentYear;
                            break;
                        case 'thisyear':
                            monthSelect.value = '';
                            yearSelect.value = currentYear;
                            break;
                        case 'all':
                            monthSelect.value = '';
                            yearSelect.value = '';
                            break;
                        // For 3months and 6months, clear dropdowns and use period parameter
                        default:
                            monthSelect.value = '';
                            yearSelect.value = '';
                            break;
                    }
                }

                // Fetch stats using period
                fetchStats({ period: period });
            });
        });

        // Handle month dropdown change
        if (monthSelect) {
            monthSelect.addEventListener('change', function () {
                // Clear active state from buttons
                filterButtons.forEach(b => b.classList.remove('sf-active'));

                const month = this.value;
                let year = yearSelect ? yearSelect.value : '';

                // If month is selected but no year, default to current year
                if (month && !year) {
                    if (yearSelect) {
                        yearSelect.value = currentYear;
                        year = currentYear;
                    }
                }

                // If both month and year are selected, or just year
                if (month || year) {
                    fetchStats({ month: month, year: year });
                }
            });
        }

        // Handle year dropdown change
        if (yearSelect) {
            yearSelect.addEventListener('change', function () {
                // Clear active state from buttons
                filterButtons.forEach(b => b.classList.remove('sf-active'));

                const year = this.value;
                const month = monthSelect ? monthSelect.value : '';

                // Fetch with month and year parameters
                fetchStats({ month: month, year: year });
            });
        }

        // Fetch stats function
        function fetchStats(params) {
            // Show loading state
            statsContainer.style.opacity = '0.5';
            statsContainer.style.pointerEvents = 'none';

            // Build query string
            const queryParams = new URLSearchParams();
            if (params.period) {
                queryParams.set('period', params.period);
            }
            if (params.month) {
                queryParams.set('month', params.month);
            }
            if (params.year) {
                queryParams.set('year', params.year);
            }

            // Use window.SF_BASE_URL if available
            const baseUrl = (window.SF_BASE_URL || '').replace(/\/$/, '');
            const apiUrl = `${baseUrl}/app/api/dashboard-stats.php?${queryParams.toString()}`;

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    updateStats(data);
                    statsContainer.style.opacity = '1';
                    statsContainer.style.pointerEvents = 'auto';
                })
                .catch(error => {
                    console.error('Failed to fetch stats:', error);
                    statsContainer.style.opacity = '1';
                    statsContainer.style.pointerEvents = 'auto';
                });
        }
    }

    // Update statistics on page
    function updateStats(data) {
        // Update type statistics
        const redCount = document.querySelector('[data-stat="red"]');
        const yellowCount = document.querySelector('[data-stat="yellow"]');
        const totalCount = document.querySelector('[data-stat="total"]');

        if (redCount) redCount.textContent = data.originalStats.red || 0;
        if (yellowCount) yellowCount.textContent = data.originalStats.yellow || 0;
        if (totalCount) totalCount.textContent = data.originalStats.total || 0;

        // Update worksite statistics
        updateWorksiteStats(data.worksiteStats);
    }

    // Update worksite bars
    function updateWorksiteStats(worksiteStats) {
        const container = document.querySelector('.sf-worksite-bars');
        if (!container) return;

        const maxCount = worksiteStats.length > 0
            ? Math.max(...worksiteStats.map(ws => ws.count))
            : 1;

        // Clear existing content
        container.innerHTML = '';

        // Get base URL once (outside the loop)
        const baseUrl = (window.SF_BASE_URL || '').replace(/\/$/, '');

        // Add all worksites (will show/hide based on expanded state)
        worksiteStats.forEach((ws, index) => {
            const barWidth = maxCount > 0 ? Math.round((ws.count / maxCount) * 100) : 0;
            const row = document.createElement('a');
            row.href = `${baseUrl}/index.php?page=list&site=${encodeURIComponent(ws.site)}`;
            row.className = `sf-worksite-bar-row ${index >= 5 ? 'sf-worksite-hidden' : ''}`;
            row.style.setProperty('--bar-delay', `${index * 0.08}s`);

            row.innerHTML = `
                <span class="sf-worksite-name">${escapeHtml(ws.site)}</span>
                <div class="sf-worksite-bar-wrap">
                    <div class="sf-worksite-bar" style="--bar-width: ${barWidth}%;">
                        <span class="sf-worksite-count">${ws.count}</span>
                    </div>
                </div>
            `;

            container.appendChild(row);
        });

        // Update show all button visibility
        const showAllBtn = document.querySelector('.sf-worksite-show-all');
        if (showAllBtn) {
            showAllBtn.style.display = worksiteStats.length > 5 ? 'flex' : 'none';
        }
    }

    // Toggle worksite list expansion
    function initWorksiteToggle() {
        const showAllBtn = document.querySelector('.sf-worksite-show-all');
        if (!showAllBtn) return;

        showAllBtn.addEventListener('click', function (e) {
            e.preventDefault();

            const hiddenItems = document.querySelectorAll('.sf-worksite-hidden');
            const isExpanded = this.classList.contains('sf-expanded');

            if (isExpanded) {
                // Collapse
                hiddenItems.forEach(item => {
                    item.style.display = 'none';
                });
                this.classList.remove('sf-expanded');
                this.querySelector('.sf-toggle-text').textContent = this.dataset.showText;
                this.querySelector('.sf-toggle-icon').textContent = '▼';
            } else {
                // Expand
                hiddenItems.forEach(item => {
                    item.style.display = 'flex';
                });
                this.classList.add('sf-expanded');
                this.querySelector('.sf-toggle-text').textContent = this.dataset.hideText;
                this.querySelector('.sf-toggle-icon').textContent = '▲';
            }
        });
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initTimeFilter();
            initWorksiteToggle();
        });
    } else {
        initTimeFilter();
        initWorksiteToggle();
    }
})();