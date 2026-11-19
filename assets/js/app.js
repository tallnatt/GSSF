document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('btn-theme-toggle');
    if (themeToggleBtn) {
        const syncThemeLabel = () => {
            const cur = document.documentElement.getAttribute('data-theme') || 'light';
            themeToggleBtn.textContent = cur === 'dark' ? 'Light Mode' : 'Dark Mode';
        };
        syncThemeLabel();
        themeToggleBtn.addEventListener('click', () => {
            const cur = document.documentElement.getAttribute('data-theme') || 'light';
            const next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('gssf_theme', next);
            syncThemeLabel();
        });
    }

    const openModal = (modalId) => {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('is-open');
        }
    };

    const closeAllModals = () => {
        document.querySelectorAll('.modal.is-open').forEach((m) => m.classList.remove('is-open'));
    };

    document.querySelectorAll('[data-open-modal]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-open-modal');
            if (targetId === 'result-modal') {
                resetResultForm();
            } else if (targetId === 'user-modal') {
                resetUserForm();
            } else if (targetId === 'match-modal') {
                resetMatchForm();
            }
            openModal(targetId);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((el) => {
        el.addEventListener('click', closeAllModals);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // -------------------------------------------------------------------------
    // Live GSSF Stage & Match Total Calculations
    // -------------------------------------------------------------------------
    const numVal = (el) => parseFloat(el?.value || '0') || 0;
    const intVal = (id) => Math.max(0, parseInt(document.getElementById(id)?.value || '0', 10) || 0);

    const calcFiveOrGlockmPenalties = (prefix) => {
        return (
            intVal(`${prefix}-pen-1`) * 1 +
            intVal(`${prefix}-pen-3`) * 3 +
            intVal(`${prefix}-pen-miss`) * 10 +
            intVal(`${prefix}-pen-proc`) * 10
        );
    };

    const calcPlatesPenalties = () => {
        return intVal('plates-pen-miss') * 10 + intVal('plates-pen-proc') * 10;
    };

    const calcStringsSum = (stringSelector) => {
        let sum = 0;
        document.querySelectorAll(stringSelector).forEach((inp) => {
            sum += numVal(inp);
        });
        return sum;
    };

    const updateAllStageTotals = () => {
        const fivePen = calcFiveOrGlockmPenalties('five');
        const fiveTotal = calcStringsSum('.js-five-string') + fivePen;

        const platesPen = calcPlatesPenalties();
        const platesTotal = calcStringsSum('.js-plates-string') + platesPen;

        const glockmPen = calcFiveOrGlockmPenalties('glockm');
        const glockmTotal = calcStringsSum('.js-glockm-string') + glockmPen;

        const matchTotal = fiveTotal + platesTotal + glockmTotal;

        const setTxt = (id, text) => {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        };

        setTxt('stage-five-pen-total', `+${fivePen.toFixed(2)}s`);
        setTxt('stage-five-total', `${fiveTotal.toFixed(2)}s`);
        setTxt('stage-plates-pen-total', `+${platesPen.toFixed(2)}s`);
        setTxt('stage-plates-total', `${platesTotal.toFixed(2)}s`);
        setTxt('stage-glockm-pen-total', `+${glockmPen.toFixed(2)}s`);
        setTxt('stage-glockm-total', `${glockmTotal.toFixed(2)}s`);
        setTxt('stage-total-preview', `${matchTotal.toFixed(2)}s`);
    };

    document.querySelectorAll('.js-stage-field').forEach((input) => {
        input.addEventListener('input', updateAllStageTotals);
    });

    // Sync default division & filter matches when picking shooter
    const resultUserSelect = document.getElementById('result-user');
    const resultDivisionSelect = document.getElementById('result-division');
    const resultMatchSelect = document.getElementById('result-match-id');

    const allMatchOptions = resultMatchSelect
        ? Array.from(resultMatchSelect.options)
            .filter((opt) => opt.value !== '')
            .map((opt) => {
                let shooters = [];
                try {
                    shooters = JSON.parse(opt.getAttribute('data-shooters') || '[]').map((id) => Number(id));
                } catch (_) {
                    shooters = [];
                }
                return {
                    value: opt.value,
                    text: opt.textContent,
                    name: opt.getAttribute('data-name') || '',
                    date: opt.getAttribute('data-date') || '',
                    shooters,
                };
            })
        : [];

    const filterMatchesForSelectedUser = () => {
        if (!resultUserSelect || !resultMatchSelect) return;
        const selectedUid = parseInt(resultUserSelect.value || '0', 10);
        const prevVal = resultMatchSelect.value;

        const matching = allMatchOptions.filter(
            (m) => selectedUid > 0 && m.shooters.includes(selectedUid)
        );

        resultMatchSelect.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = matching.length > 0
            ? '— Select a match —'
            : '— No matches assigned to this shooter —';
        resultMatchSelect.appendChild(placeholder);

        let restored = false;
        matching.forEach((m) => {
            const opt = document.createElement('option');
            opt.value = m.value;
            opt.textContent = m.text;
            opt.setAttribute('data-name', m.name);
            opt.setAttribute('data-date', m.date);
            resultMatchSelect.appendChild(opt);
            if (m.value === prevVal) {
                restored = true;
            }
        });

        resultMatchSelect.value = restored ? prevVal : '';
    };

    if (resultUserSelect) {
        filterMatchesForSelectedUser();
        resultUserSelect.addEventListener('change', () => {
            filterMatchesForSelectedUser();
            const resultId = parseInt(document.getElementById('result-id')?.value || '0', 10);
            if (resultId === 0 && resultDivisionSelect) {
                const selectedOpt = resultUserSelect.options[resultUserSelect.selectedIndex];
                const defaultDiv = selectedOpt?.getAttribute('data-division');
                if (defaultDiv) {
                    resultDivisionSelect.value = defaultDiv;
                }
            }
        });
    }

    function setInputsFromArray(selector, values, defaultVal = '') {
        const inputs = Array.from(document.querySelectorAll(selector));
        inputs.forEach((inp, idx) => {
            if (values && idx < values.length && values[idx] !== null && values[idx] !== undefined) {
                inp.value = Number(values[idx]).toFixed(2);
            } else {
                inp.value = defaultVal;
            }
        });
    }

    function resetResultForm() {
        const title = document.getElementById('result-modal-title');
        if (title) title.textContent = 'Add Match Time';
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        setVal('result-id', '0');
        setVal('result-match-id', '');
        setVal('result-gun', '');
        setInputsFromArray('.js-five-string', [], '');
        setVal('five-pen-0', '0');
        setVal('five-pen-1', '0');
        setVal('five-pen-3', '0');
        setVal('five-pen-miss', '0');
        setVal('five-pen-proc', '0');
        setInputsFromArray('.js-plates-string', [], '');
        setVal('plates-pen-miss', '0');
        setVal('plates-pen-proc', '0');
        setInputsFromArray('.js-glockm-string', [], '');
        setVal('glockm-pen-0', '0');
        setVal('glockm-pen-1', '0');
        setVal('glockm-pen-3', '0');
        setVal('glockm-pen-miss', '0');
        setVal('glockm-pen-proc', '0');
        filterMatchesForSelectedUser();
        updateAllStageTotals();
    }

    // Edit Result buttons
    document.querySelectorAll('.js-edit-result').forEach((btn) => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-result') || '{}');
            const title = document.getElementById('result-modal-title');
            if (title) title.textContent = 'Edit Match Time';

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val ?? '';
            };

            setVal('result-id', data.id);
            setVal('result-user', data.user_id);
            filterMatchesForSelectedUser();
            setVal('result-division', data.division);
            setVal('result-gun', data.gun || '');

            // Match dropdown selection by match_name & match_date
            if (resultMatchSelect) {
                let matchedVal = '';
                Array.from(resultMatchSelect.options).forEach((opt) => {
                    if (
                        !opt.disabled &&
                        opt.getAttribute('data-name') === data.match_name &&
                        opt.getAttribute('data-date') === data.match_date
                    ) {
                        matchedVal = opt.value;
                    }
                });
                resultMatchSelect.value = matchedVal;
            }

            let details = null;
            if (data.stage_details) {
                try {
                    details = JSON.parse(data.stage_details);
                } catch (_) {
                    details = null;
                }
            }

            if (details && details.five_to_glock) {
                setInputsFromArray('.js-five-string', details.five_to_glock.strings || [], '0.00');
                const pc = details.five_to_glock.penalty_counts || {};
                setVal('five-pen-0', pc.p0 ?? 0);
                setVal('five-pen-1', pc.p1 ?? 0);
                setVal('five-pen-3', pc.p3 ?? 0);
                setVal('five-pen-miss', pc.miss ?? 0);
                setVal('five-pen-proc', pc.proc ?? 0);
            } else {
                setInputsFromArray('.js-five-string', [Number(data.five_to_glock || 0), 0, 0], '0.00');
                setVal('five-pen-0', 0);
                setVal('five-pen-1', 0);
                setVal('five-pen-3', 0);
                setVal('five-pen-miss', 0);
                setVal('five-pen-proc', 0);
            }

            if (details && details.glock_the_plates) {
                setInputsFromArray('.js-plates-string', details.glock_the_plates.strings || [], '0.00');
                const pc = details.glock_the_plates.penalty_counts || {};
                setVal('plates-pen-miss', pc.miss ?? 0);
                setVal('plates-pen-proc', pc.proc ?? 0);
            } else {
                setInputsFromArray('.js-plates-string', [Number(data.glock_the_plates || 0), 0, 0, 0], '0.00');
                setVal('plates-pen-miss', 0);
                setVal('plates-pen-proc', 0);
            }

            if (details && details.glock_m) {
                setInputsFromArray('.js-glockm-string', details.glock_m.strings || [], '0.00');
                const pc = details.glock_m.penalty_counts || {};
                setVal('glockm-pen-0', pc.p0 ?? 0);
                setVal('glockm-pen-1', pc.p1 ?? 0);
                setVal('glockm-pen-3', pc.p3 ?? 0);
                setVal('glockm-pen-miss', pc.miss ?? 0);
                setVal('glockm-pen-proc', pc.proc ?? 0);
            } else {
                setInputsFromArray('.js-glockm-string', [Number(data.glock_m || 0), 0, 0], '0.00');
                setVal('glockm-pen-0', 0);
                setVal('glockm-pen-1', 0);
                setVal('glockm-pen-3', 0);
                setVal('glockm-pen-miss', 0);
                setVal('glockm-pen-proc', 0);
            }

            updateAllStageTotals();
            openModal('result-modal');
        });
    });

    // -------------------------------------------------------------------------
    // User Modal & Color Palette
    // -------------------------------------------------------------------------
    function selectUserColor(hex) {
        const radios = Array.from(document.querySelectorAll('.js-user-color-radio'));
        let matched = false;
        radios.forEach((r) => {
            const isMatch = r.value.toLowerCase() === (hex || '').toLowerCase();
            r.checked = isMatch;
            if (isMatch) matched = true;
        });
        if (!matched && radios.length > 0) {
            radios[0].checked = true;
        }
    }

    function resetUserForm() {
        const title = document.getElementById('user-modal-title');
        if (title) title.textContent = 'Add Family Shooter';
        const hint = document.getElementById('user-password-hint');
        if (hint) hint.textContent = '(required for new user)';

        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };

        setVal('user-id', '0');
        setVal('user-display-name', '');
        setVal('user-username', '');
        setVal('user-division', 'Amateur Civilian');
        selectUserColor('#1d4ed8');
        setVal('user-role', 'member');
        setVal('user-password', '');
        const activeBox = document.getElementById('user-active');
        if (activeBox) activeBox.checked = true;
    }

    document.querySelectorAll('.js-edit-user').forEach((btn) => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-user') || '{}');
            const title = document.getElementById('user-modal-title');
            if (title) title.textContent = 'Edit Family Shooter';
            const hint = document.getElementById('user-password-hint');
            if (hint) hint.textContent = '(leave blank to keep current password)';

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val ?? '';
            };

            setVal('user-id', data.id);
            setVal('user-display-name', data.display_name);
            setVal('user-username', data.username);
            setVal('user-division', data.default_division || 'Amateur Civilian');
            selectUserColor(data.color || '#1d4ed8');
            setVal('user-role', data.role || 'member');
            setVal('user-password', '');
            const activeBox = document.getElementById('user-active');
            if (activeBox) activeBox.checked = Number(data.active) === 1;
            openModal('user-modal');
        });
    });

    // -------------------------------------------------------------------------
    // Match Modal
    // -------------------------------------------------------------------------
    function resetMatchForm() {
        const title = document.getElementById('match-modal-title');
        if (title) title.textContent = 'Add Match';
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        setVal('match-id', '0');
        setVal('match-name', '');
        setVal('match-date', new Date().toISOString().slice(0, 10));
        setVal('match-location', '');
        document.querySelectorAll('.js-match-shooter-cb').forEach((cb) => {
            cb.checked = false;
        });
    }

    document.querySelectorAll('.js-edit-match').forEach((btn) => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-match') || '{}');
            const title = document.getElementById('match-modal-title');
            if (title) title.textContent = 'Edit Match';
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val ?? '';
            };
            setVal('match-id', data.id);
            setVal('match-name', data.match_name);
            setVal('match-date', data.match_date);
            setVal('match-location', data.location);

            const selectedIds = new Set((data.shooter_ids || []).map((id) => Number(id)));
            document.querySelectorAll('.js-match-shooter-cb').forEach((cb) => {
                cb.checked = selectedIds.has(Number(cb.value));
            });
            openModal('match-modal');
        });
    });
});
