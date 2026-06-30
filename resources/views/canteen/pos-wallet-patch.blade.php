{{-- Temporary POS wallet display until frontend assets are rebuilt (npm run build). --}}
<script>
(function () {
    let cachedEligibility = null;

    function formatMoney(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n.toFixed(2) : '0.00';
    }

    function applyWalletPatch(data) {
        if (!data || data.wallet_balance == null) {
            return;
        }

        cachedEligibility = data;
        const balance = formatMoney(data.wallet_balance);

        const limits = document.querySelector('.pos-student-bar__limits');
        if (limits) {
            let chip = limits.querySelector('[data-pos-wallet-chip]');
            if (!chip) {
                chip = document.createElement('div');
                chip.setAttribute('data-pos-wallet-chip', '1');
                chip.className = 'pos-student-bar__limit-chip pos-student-bar__limit-chip--wallet';
                chip.style.cssText = 'background:#eff6ff;border:1px solid #93c5fd;min-width:7.5rem;text-align:center;padding:0.3rem 0.65rem;border-radius:8px;font-size:0.75rem;order:-1;';
                limits.insertBefore(chip, limits.firstChild);
            }

            chip.innerHTML = '<span class="label" style="display:block;color:#64748b"><i class="bi bi-wallet2 me-1"></i>رصيد المحفظة</span>'
                + '<strong style="color:#1d4ed8;font-size:1.1rem">' + balance + ' <small style="font-size:0.7rem">ج.م</small></strong>';

            const chips = limits.querySelectorAll('.pos-student-bar__limit-chip:not([data-pos-wallet-chip])');
            if (chips[0]) {
                const limit = data.daily_limit?.limit;
                const strong = chips[0].querySelector('strong');
                if (strong) {
                    strong.textContent = (limit == null || limit === '') ? 'بدون حد' : limit;
                }
            }

            if (chips[1]) {
                const limit = data.daily_limit?.limit;
                const spent = data.daily_limit?.spent;
                if (limit == null || limit === '') {
                    if (spent && parseFloat(spent) > 0) {
                        const label = chips[1].querySelector('.label');
                        const strong = chips[1].querySelector('strong');
                        if (label) label.textContent = 'مصروف اليوم';
                        if (strong) strong.textContent = formatMoney(spent);
                        chips[1].style.display = '';
                    } else {
                        chips[1].style.display = 'none';
                    }
                } else {
                    chips[1].style.display = '';
                    const strong = chips[1].querySelector('strong');
                    if (strong) {
                        strong.textContent = data.daily_limit?.remaining ?? '—';
                    }
                }
            }
        }

        const summary = document.querySelector('.pos-student-summary');
        if (summary) {
            let box = summary.querySelector('[data-pos-wallet-box]');
            if (!box) {
                box = document.createElement('div');
                box.setAttribute('data-pos-wallet-box', '1');
                box.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:0.35rem;padding:0.45rem 0.55rem;margin-bottom:0.35rem;border-radius:8px;background:#eff6ff;border:1px solid #93c5fd;font-size:0.85rem;';
                const limitsRow = summary.querySelector('.pos-student-summary__limits');
                if (limitsRow) {
                    summary.insertBefore(box, limitsRow);
                } else {
                    summary.appendChild(box);
                }
            }

            box.innerHTML = '<span><i class="bi bi-wallet2"></i> رصيد المحفظة</span>'
                + '<strong style="color:#1d4ed8;font-size:1.05rem">' + balance + ' ج.م</strong>';

            const limitsRow = summary.querySelector('.pos-student-summary__limits');
            if (limitsRow) {
                const spans = limitsRow.querySelectorAll('span');
                spans.forEach(function (span) {
                    if (span.textContent.indexOf('حد') === 0 || span.textContent.indexOf('حد يومي') === 0) {
                        const limit = data.daily_limit?.limit;
                        const strong = span.querySelector('strong');
                        if (strong) {
                            strong.textContent = (limit == null || limit === '') ? 'بدون حد' : limit;
                        }
                    }
                });
            }
        }
    }

    function clearWalletPatch() {
        cachedEligibility = null;
        document.querySelectorAll('[data-pos-wallet-chip],[data-pos-wallet-box]').forEach(function (el) {
            el.remove();
        });
    }

    function hookAxios() {
        if (!window.axios || window.axios.__posWalletHooked) {
            return !!window.axios;
        }

        window.axios.__posWalletHooked = true;

        window.axios.interceptors.response.use(function (response) {
            const url = String(response.config?.url || '');

            if (url.indexOf('/eligibility') !== -1 && response.data) {
                setTimeout(function () {
                    applyWalletPatch(response.data);
                }, 0);
            }

            if (url.indexOf('/sales') !== -1
                && String(response.config?.method || '').toLowerCase() === 'post'
                && response.status >= 200
                && response.status < 300
                && cachedEligibility) {
                setTimeout(function () {
                    if (window.axios && cachedEligibility.student) {
                        const ref = cachedEligibility.student.student_id_ref
                            || document.querySelector('.pos-student-bar__meta span')?.textContent?.trim();
                        if (ref) {
                            window.axios.get('/canteen/api/students/' + ref + '/eligibility')
                                .then(function (res) { applyWalletPatch(res.data); })
                                .catch(function () {});
                        }
                    }
                }, 100);
            }

            return response;
        });

        return true;
    }

    function boot() {
        if (!hookAxios()) {
            setTimeout(boot, 300);
            return;
        }

        const observer = new MutationObserver(function () {
            if (!cachedEligibility) {
                return;
            }

            const limits = document.querySelector('.pos-student-bar__limits');
            if (!limits) {
                return;
            }

            if (!limits.querySelector('[data-pos-wallet-chip]')) {
                applyWalletPatch(cachedEligibility);
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('click', function (event) {
            const target = event.target;
            if (target && target.closest && target.closest('.pos-student-bar__change')) {
                clearWalletPatch();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
