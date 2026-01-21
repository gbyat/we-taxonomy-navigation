(function () {
    console.log('Taxonomy List view.js loaded');

    // Use event delegation for dropdowns - works even if elements are added dynamically
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('we-taxonomy-select')) {
            const select = e.target;
            const autoNavigate = select.getAttribute('data-auto-navigate') === '1';

            console.log('Dropdown changed:', select.value, 'auto-navigate:', autoNavigate);

            if (autoNavigate && select.value) {
                console.log('Navigating to:', select.value);
                window.location.href = select.value;
            }
        }
    });

    // Use event delegation for buttons
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('we-taxonomy-select-button')) {
            const button = e.target;
            const block = button.closest('.we-taxonomy-dropdown');
            if (block) {
                const select = block.querySelector('.we-taxonomy-select');
                if (select && select.value) {
                    console.log('Button clicked, navigating to:', select.value);
                    window.location.href = select.value;
                }
            }
        }
    });

    // Filter functionality
    const initFilters = () => {
        const blocks = document.querySelectorAll('.we-taxonomy-filterable');
        console.log('Found', blocks.length, 'filterable blocks');

        blocks.forEach((block) => {
            if (block.dataset.initialized) {
                return;
            }
            block.dataset.initialized = 'true';

            const input = block.querySelector('.we-taxonomy-filter');
            const list = block.querySelector('.we-taxonomy-list');
            if (!input || !list) {
                return;
            }
            const items = list.querySelectorAll('li');
            input.addEventListener('input', () => {
                const query = input.value.toLowerCase();
                items.forEach((item) => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(query) ? '' : 'none';
                });
            });
        });
    };

    // Initialize filters on DOM ready
    const init = () => {
        console.log('Initializing filters...');
        initFilters();

        const dropdowns = document.querySelectorAll('.we-taxonomy-dropdown');
        console.log('Found', dropdowns.length, 'dropdown blocks');
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
