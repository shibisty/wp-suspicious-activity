(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof WP_SAD_FiltersConfig === 'undefined') {
            return;
        }

        var form = document.querySelector('.wp-sad-filters[data-sad-filters-form]');
        if (!form) {
            return;
        }

        var storageKey = WP_SAD_FiltersConfig.storageKey;
        var restoreFlagKey = storageKey + '_restored';

        function getSavedFilters() {
            try {
                var raw = localStorage.getItem(storageKey);
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        function saveCurrentFilters() {
            try {
                var data = {};
                var formData = new FormData(form);
                formData.forEach(function (value, key) {
                    if (key === 'page') return;
                    data[key] = value;
                });
                localStorage.setItem(storageKey, JSON.stringify(data));
            } catch (e) {
                // localStorage недоступний (приватний режим тощо) — просто ігноруємо
            }
        }

        function clearSavedFilters() {
            try {
                localStorage.removeItem(storageKey);
                sessionStorage.removeItem(restoreFlagKey);
            } catch (e) {
                // ігноруємо
            }
        }

        // Якщо в URL немає жодного параметра фільтра, а в localStorage є
        // збережені значення — один раз підставляємо їх і перезаходимо,
        // щоб фільтри не скидались при звичайному перезаході на сторінку.
        var url = new URL(window.location.href);
        var hasAnyFilterParam = false;

        Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name && el.name !== 'page') {
                var val = url.searchParams.get(el.name);
                if (val !== null && val !== '') {
                    hasAnyFilterParam = true;
                }
            }
        });

        var alreadyRestoredThisSession = false;
        try {
            alreadyRestoredThisSession = !!sessionStorage.getItem(restoreFlagKey);
        } catch (e) {
            // ігноруємо
        }

        if (!hasAnyFilterParam && !alreadyRestoredThisSession) {
            var saved = getSavedFilters();
            if (saved && Object.keys(saved).some(function (k) { return saved[k]; })) {
                Object.keys(saved).forEach(function (key) {
                    if (saved[key]) {
                        url.searchParams.set(key, saved[key]);
                    }
                });
                try {
                    sessionStorage.setItem(restoreFlagKey, '1');
                } catch (e) {
                    // ігноруємо
                }
                window.location.replace(url.toString());
                return;
            }
        }

        form.addEventListener('submit', saveCurrentFilters);

        document.querySelectorAll('.wp-sad-reset-filters').forEach(function (btn) {
            btn.addEventListener('click', function () {
                clearSavedFilters();
                // посилання саме веде на "чистий" URL сторінки — стандартна навігація
            });
        });
    });
})();
