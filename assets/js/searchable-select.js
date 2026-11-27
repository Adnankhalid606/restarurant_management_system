/**
 * Reusable Searchable Select Component
 * Arena + Café POS
 *
 * Usage:
 * 1. Automatically initializes on any <select class="searchable-select-enabled">
 *    or <select data-searchable="true">
 * 2. Programmatically: SearchableSelect.init(selectElement, options);
 */
(function() {
    'use strict';

    class SearchableSelect {
        constructor(selectElement, userOptions = {}) {
            if (!selectElement || selectElement._searchableSelectInstance) {
                return;
            }

            this.select = selectElement;
            this.select._searchableSelectInstance = this;
            this.options = Object.assign({
                placeholder: this.select.getAttribute('data-placeholder') || 'Type or select an option...',
                noResultsText: 'No matches found',
                inputClass: 'form-control form-control-sm'
            }, userOptions);

            this.portalMenu = this.select.getAttribute('data-searchable-portal') !== 'false';
            this.isOpen = false;
            this.focusedIndex = -1;
            this.items = []; // { value, label, group, disabled, element }

            this.buildUI();
            this.syncFromSelect();
            this.bindEvents();
        }

        buildUI() {
            // Hide native select visually but keep in DOM for form submission & validation
            this.select.style.display = 'none';

            // Wrapper container
            this.container = document.createElement('div');
            this.container.className = 'searchable-select';

            // Search input field
            this.input = document.createElement('input');
            this.input.type = 'text';
            this.input.className = `${this.options.inputClass} searchable-select-input`;
            this.input.placeholder = this.options.placeholder;
            this.input.autocomplete = 'off';
            this.input.setAttribute('role', 'combobox');
            this.input.setAttribute('aria-expanded', 'false');
            this.input.setAttribute('aria-haspopup', 'listbox');

            // Dropdown arrow indicator
            this.arrow = document.createElement('span');
            this.arrow.className = 'searchable-select-arrow';
            this.arrow.innerHTML = '&#9662;'; // Downward triangle

            // Menu container
            this.menu = document.createElement('div');
            this.menu.className = 'searchable-select-menu';
            this.menu.setAttribute('role', 'listbox');

            this.container.appendChild(this.input);
            this.container.appendChild(this.arrow);

            if (this.portalMenu && document.body) {
                this.menu.classList.add('searchable-select-menu-portal');
                document.body.appendChild(this.menu);
            } else {
                this.container.appendChild(this.menu);
            }

            // Insert container after native select
            this.select.parentNode.insertBefore(this.container, this.select.nextSibling);
        }

        syncFromSelect() {
            this.items = [];
            let currentGroup = '';

            Array.from(this.select.children).forEach(child => {
                if (child.tagName === 'OPTGROUP') {
                    currentGroup = child.label || '';
                    Array.from(child.children).forEach(option => {
                        this.items.push({
                            value: option.value,
                            label: option.textContent.trim(),
                            group: currentGroup,
                            disabled: option.disabled,
                            data: Object.assign({}, option.dataset),
                            optionEl: option
                        });
                    });
                } else if (child.tagName === 'OPTION') {
                    this.items.push({
                        value: child.value,
                        label: child.textContent.trim(),
                        group: '',
                        disabled: child.disabled,
                        data: Object.assign({}, child.dataset),
                        optionEl: child
                    });
                }
            });

            // Set current displayed text from selected option
            const selectedOpt = this.select.options[this.select.selectedIndex];
            if (selectedOpt && selectedOpt.value !== '') {
                this.input.value = selectedOpt.textContent.trim();
            } else {
                this.input.value = '';
            }
        }

        renderMenu(query = '') {
            this.menu.innerHTML = '';
            const normalizedQuery = query.toLowerCase().trim();

            const filteredItems = this.items.filter(item => {
                if (item.value === '') return false; // Skip empty placeholder options from list
                if (!normalizedQuery) return true;
                const matchLabel = item.label.toLowerCase().includes(normalizedQuery);
                const matchGroup = item.group && item.group.toLowerCase().includes(normalizedQuery);
                return matchLabel || matchGroup;
            });

            if (filteredItems.length === 0) {
                const emptyEl = document.createElement('div');
                emptyEl.className = 'searchable-select-empty';
                emptyEl.textContent = this.options.noResultsText;
                this.menu.appendChild(emptyEl);
                this.renderedOptions = [];
                this.focusedIndex = -1;
                this.positionPortalMenu();
                return;
            }

            let lastGroup = null;
            this.renderedOptions = [];

            filteredItems.forEach(item => {
                // If group changed, render group header
                if (item.group && item.group !== lastGroup) {
                    lastGroup = item.group;
                    const groupHeader = document.createElement('div');
                    groupHeader.className = 'searchable-select-group-header';
                    groupHeader.textContent = item.group;
                    this.menu.appendChild(groupHeader);
                }

                const optEl = document.createElement('div');
                optEl.className = 'searchable-select-option';
                optEl.setAttribute('role', 'option');
                optEl.setAttribute('data-value', item.value);

                if (item.disabled) {
                    optEl.classList.add('is-disabled');
                    optEl.setAttribute('aria-disabled', 'true');
                }

                if (this.select.value === item.value) {
                    optEl.classList.add('is-selected');
                    optEl.setAttribute('aria-selected', 'true');
                }

                // Safely set text content
                optEl.textContent = item.label;

                optEl.addEventListener('mousedown', (e) => {
                    e.preventDefault(); // Prevent blur on input
                    if (!item.disabled) {
                        this.selectOption(item);
                    }
                });

                this.menu.appendChild(optEl);
                this.renderedOptions.push({
                    element: optEl,
                    item: item
                });
            });

            this.focusedIndex = -1;
            this.positionPortalMenu();
        }

        positionPortalMenu() {
            if (!this.portalMenu || !this.isOpen) return;

            const inputRect = this.input.getBoundingClientRect();
            // If input is scrolled completely outside the viewport, close menu
            if (inputRect.bottom < 0 || inputRect.top > window.innerHeight) {
                this.close();
                return;
            }

            const spaceBelow = window.innerHeight - inputRect.bottom - 8;
            const spaceAbove = inputRect.top - 8;
            const openAbove = spaceBelow < 160 && spaceAbove > spaceBelow;

            const availableSpace = openAbove ? spaceAbove : spaceBelow;
            const effectiveMaxHeight = Math.min(260, Math.max(100, availableSpace));
            this.menu.style.maxHeight = `${effectiveMaxHeight}px`;

            const menuHeight = Math.min(this.menu.scrollHeight || effectiveMaxHeight, effectiveMaxHeight);
            const top = openAbove ? (inputRect.top - menuHeight - 4) : (inputRect.bottom + 4);
            const left = Math.min(
                Math.max(8, inputRect.left),
                Math.max(8, window.innerWidth - inputRect.width - 8)
            );

            this.menu.style.left = `${left}px`;
            this.menu.style.top = `${Math.max(8, top)}px`;
            this.menu.style.width = `${inputRect.width}px`;
        }

        open() {
            if (this.isOpen) return;

            // Re-read native options whenever the menu opens. Some pages add or
            // replace options after the component's initial construction.
            this.syncFromSelect();
            this.isOpen = true;
            this.container.classList.add('is-open');
            this.input.setAttribute('aria-expanded', 'true');
            if (this.portalMenu) {
                this.menu.style.display = 'block';
            }
            // When opening, select input text so user can immediately type to replace or filter
            this.input.select();
            // Opening should show the complete eligible option list. The selected
            // label is selected in the input so typing immediately replaces it.
            this.renderMenu('');
        }

        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            this.container.classList.remove('is-open');
            this.input.setAttribute('aria-expanded', 'false');
            if (this.portalMenu) {
                this.menu.style.display = 'none';
            }

            // Restore displayed label from selected value in native select
            const selectedOpt = this.select.options[this.select.selectedIndex];
            if (selectedOpt && selectedOpt.value !== '') {
                this.input.value = selectedOpt.textContent.trim();
            } else {
                this.input.value = '';
            }
        }

        selectOption(item) {
            this.select.value = item.value;
            this.input.value = item.label;
            this.close();

            // Trigger change event on native select for any external listeners
            const event = new Event('change', { bubbles: true });
            this.select.dispatchEvent(event);
        }

        bindEvents() {
            // Toggle on input click or focus
            this.input.addEventListener('focus', () => {
                this.open();
            });

            this.input.addEventListener('click', () => {
                if (!this.isOpen) {
                    this.open();
                }
            });

            // Filtering on input typing
            this.input.addEventListener('input', () => {
                if (!this.isOpen) {
                    this.isOpen = true;
                    this.container.classList.add('is-open');
                    this.input.setAttribute('aria-expanded', 'true');
                    if (this.portalMenu) {
                        this.menu.style.display = 'block';
                    }
                }
                this.renderMenu(this.input.value);
            });

            // Keyboard navigation
            this.input.addEventListener('keydown', (e) => {
                if (!this.isOpen) {
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') {
                        e.preventDefault();
                        this.open();
                    }
                    return;
                }

                const optionsCount = this.renderedOptions ? this.renderedOptions.length : 0;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (optionsCount > 0) {
                        this.setFocusedIndex(this.findNextSelectableIndex(this.focusedIndex, 1));
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (optionsCount > 0) {
                        this.setFocusedIndex(this.findNextSelectableIndex(this.focusedIndex, -1));
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (this.focusedIndex >= 0 && this.focusedIndex < optionsCount) {
                        const target = this.renderedOptions[this.focusedIndex];
                        if (target && !target.item.disabled) {
                            this.selectOption(target.item);
                        }
                    } else if (optionsCount === 1) {
                        const target = this.renderedOptions[0];
                        if (target && !target.item.disabled) {
                            this.selectOption(target.item);
                        }
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    this.close();
                } else if (e.key === 'Tab') {
                    this.close();
                }
            });

            // Click outside detection
            document.addEventListener('click', (e) => {
                if (this.isOpen && !this.container.contains(e.target) && !this.menu.contains(e.target) && e.target !== this.select) {
                    this.close();
                }
            });

            if (this.portalMenu) {
                this.repositionPortalMenu = () => this.positionPortalMenu();
                window.addEventListener('resize', this.repositionPortalMenu);
                window.addEventListener('scroll', this.repositionPortalMenu, true);
            }

            // If external code alters the native select value
            this.select.addEventListener('change', () => {
                const selectedOpt = this.select.options[this.select.selectedIndex];
                if (selectedOpt && selectedOpt.value !== '') {
                    this.input.value = selectedOpt.textContent.trim();
                } else {
                    this.input.value = '';
                }
            });
        }

        findNextSelectableIndex(current, direction) {
            const count = this.renderedOptions.length;
            if (count === 0) return -1;
            let next = current + direction;
            while (next >= 0 && next < count) {
                if (!this.renderedOptions[next].item.disabled) {
                    return next;
                }
                next += direction;
            }
            return current;
        }

        setFocusedIndex(index) {
            if (!this.renderedOptions || this.renderedOptions.length === 0) return;
            this.renderedOptions.forEach((opt, idx) => {
                if (idx === index) {
                    opt.element.classList.add('is-focused');
                    opt.element.scrollIntoView({ block: 'nearest' });
                } else {
                    opt.element.classList.remove('is-focused');
                }
            });
            this.focusedIndex = index;
        }

        static init(target, options = {}) {
            if (typeof target === 'string') {
                document.querySelectorAll(target).forEach(el => new SearchableSelect(el, options));
            } else if (target instanceof Element) {
                return new SearchableSelect(target, options);
            }
        }

        static autoInit() {
            document.querySelectorAll('select.searchable-select-enabled, select[data-searchable="true"]').forEach(el => {
                SearchableSelect.init(el);
            });
        }
    }

    // Expose globally
    window.SearchableSelect = SearchableSelect;

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SearchableSelect.autoInit());
    } else {
        SearchableSelect.autoInit();
    }
})();
