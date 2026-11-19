document.addEventListener('DOMContentLoaded', () => {
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

    const calcStageSum = (stringSelector, penaltyId) => {
        let sum = 0;
        document.querySelectorAll(stringSelector).forEach((inp) => {
            sum += numVal(inp);
        });
        sum += numVal(document.getElementById(penaltyId));
        return sum;
    };

    const updateAllStageTotals = () => {
        const fiveTotal = calcStageSum('.js-five-string', 'five-pen');
        const platesTotal = calcStageSum('.js-plates-string', 'plates-pen');
        const glockmTotal = calcStageSum('.js-glockm-string', 'glockm-pen');
        const matchTotal = fiveTotal + platesTotal + glockmTotal;

        const fiveEl = document.getElementById('stage-five-total');
        const platesEl = document.getElementById('stage-plates-total');
        const glockmEl = document.getElementById('stage-glockm-total');
        const totalEl = document.getElementById('stage-total-preview');

        if (fiveEl) fiveEl.textContent = fiveTotal.toFixed(2) + 's';
        if (platesEl) platesEl.textContent = platesTotal.toFixed(2) + 's';
        if (glockmEl) glockmEl.textContent = glockmTotal.toFixed(2) + 's';
        if (totalEl) totalEl.textContent = matchTotal.toFixed(2) + 's';
    };

    document.querySelectorAll('.js-stage-field').forEach((input) => {
        input.addEventListener('input', updateAllStageTotals);
    });

    // Sync default division when picking shooter on new result
    const resultUserSelect = document.getElementById('result-user');
    const resultDivisionSelect = document.getElementById('result-division');
    if (resultUserSelect && resultDivisionSelect) {
        resultUserSelect.addEventListener('change', () => {
            const resultId = parseInt(document.getElementById('result-id')?.value || '0', 10);
            if (resultId === 0) {
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
        setInputsFromArray('.js-five-string', [], '');
        setVal('five-pen', '0.00');
        setInputsFromArray('.js-plates-string', [], '');
        setVal('plates-pen', '0.00');
        setInputsFromArray('.js-glockm-string', [], '');
        setVal('glockm-pen', '0.00');
        setVal('result-notes', '');
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
            setVal('result-division', data.division);
            setVal('result-notes', data.notes);

            // Match dropdown selection by match_name & match_date
            const matchSelect = document.getElementById('result-match-id');
            if (matchSelect) {
                let matchedVal = '';
                Array.from(matchSelect.options).forEach((opt) => {
                    if (
                        opt.getAttribute('data-name') === data.match_name &&
                        opt.getAttribute('data-date') === data.match_date
                    ) {
                        matchedVal = opt.value;
                    }
                });
                matchSelect.value = matchedVal;
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
                setVal('five-pen', Number(details.five_to_glock.penalties || 0).toFixed(2));
            } else {
                setInputsFromArray('.js-five-string', [Number(data.five_to_glock || 0), 0, 0], '0.00');
                setVal('five-pen', '0.00');
            }

            if (details && details.glock_the_plates) {
                setInputsFromArray('.js-plates-string', details.glock_the_plates.strings || [], '0.00');
                setVal('plates-pen', Number(details.glock_the_plates.penalties || 0).toFixed(2));
            } else {
                setInputsFromArray('.js-plates-string', [Number(data.glock_the_plates || 0), 0, 0, 0], '0.00');
                setVal('plates-pen', '0.00');
            }

            if (details && details.glock_m) {
                setInputsFromArray('.js-glockm-string', details.glock_m.strings || [], '0.00');
                setVal('glockm-pen', Number(details.glock_m.penalties || 0).toFixed(2));
            } else {
                setInputsFromArray('.js-glockm-string', [Number(data.glock_m || 0), 0, 0], '0.00');
                setVal('glockm-pen', '0.00');
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
