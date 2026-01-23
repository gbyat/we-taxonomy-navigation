(function () {
    // Use event delegation for dropdowns - works even if elements are added dynamically
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('we-taxonomy-select')) {
            const select = e.target;
            const autoNavigate = select.getAttribute('data-auto-navigate') === '1';

            if (autoNavigate && select.value) {
                window.location.href = select.value;
            }
        }
    });

    // Use event delegation for buttons
    document.addEventListener('click', function (e) {
        // Check if click is on button or inside button (for text content)
        const button = e.target.closest('.we-taxonomy-select-button');
        if (button) {
            e.preventDefault();
            e.stopPropagation();

            // Try multiple strategies to find the select element
            let select = null;

            // Strategy 1: Find via dropdown container
            const block = button.closest('.we-taxonomy-dropdown');
            if (block) {
                select = block.querySelector('.we-taxonomy-select');
            }

            // Strategy 2: Find via parent container (in case wrapper divs exist)
            if (!select) {
                let parent = button.parentElement;
                let depth = 0;
                while (parent && depth < 5) {
                    select = parent.querySelector('.we-taxonomy-select');
                    if (select) {
                        break;
                    }
                    parent = parent.parentElement;
                    depth++;
                }
            }

            // Strategy 3: Find the closest select with the class (fallback)
            if (!select) {
                // Find all selects and get the one closest to the button
                const allSelects = document.querySelectorAll('.we-taxonomy-select');
                if (allSelects.length > 0) {
                    // Get button position
                    const buttonRect = button.getBoundingClientRect();
                    let closestSelect = null;
                    let closestDistance = Infinity;

                    allSelects.forEach(function (s) {
                        const selectRect = s.getBoundingClientRect();
                        const distance = Math.abs(buttonRect.top - selectRect.top) + Math.abs(buttonRect.left - selectRect.left);
                        if (distance < closestDistance) {
                            closestDistance = distance;
                            closestSelect = s;
                        }
                    });

                    if (closestSelect && closestDistance < 200) { // Within 200px
                        select = closestSelect;
                    }
                }
            }

            if (select) {
                // Get the selected value
                const selectedValue = select.value;
                // Navigate if a valid URL is selected (not empty and not the placeholder)
                if (selectedValue && selectedValue !== '') {
                    window.location.href = selectedValue;
                }
            }
        }
    });

    // Filter functionality
    const initFilters = () => {
        const blocks = document.querySelectorAll('.we-taxonomy-filterable');

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
        initFilters();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
