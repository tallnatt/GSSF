document.addEventListener('DOMContentLoaded', () => {
    // Modal open / close helpers
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

    // Live GSSF stage total calculation
    const stageInputs = document.querySelectorAll('.js-stage-input');
    const totalPreview = document.getElementById('stage-total-preview');

    const updateStageTotal = () => {
        if (!totalPreview) return;
        const five = parseFloat(document.getElementById('stage-five')?.value || '0') || 0;
        const plates = parseFloat(document.getElementById('stage-plates')?.value || '0') || 0;
        const glockm = parseFloat(document.getElementById('stage-glockm')?.value || '0') || 0;
        const penalties = parseFloat(document.getElementById('stage-penalties')?.value || '0') || 0;
        const total = five + plates + glockm + penalties;
        totalPreview.textContent = total.toFixed(2) + 's';
    };

    stageInputs.forEach((input) => {
        input.addEventListener('input', updateStageTotal);
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

    function resetResultForm() {
        const title = document.getElementById('result-modal-title');
        if (title) title.textContent = 'Add Event Time';
        const setVal = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.value = val;
        };
        setVal('result-id', '0');
        setVal('result-match-name', '');
        setVal('result-match-date', new Date().toISOString().slice(0, 10));
        setVal('result-location', '');
        setVal('stage-five', '');
        setVal('stage-plates', '');
        setVal('stage-glockm', '');
        setVal('stage-penalties', '0.00');
        setVal('result-notes', '');
        updateStageTotal();
    }

    // Edit Result buttons
    document.querySelectorAll('.js-edit-result').forEach((btn) => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.getAttribute('data-result') || '{}');
            const title = document.getElementById('result-modal-title');
            if (title) title.textContent = 'Edit Event Time';

            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val ?? '';
            };

            setVal('result-id', data.id);
            setVal('result-user', data.user_id);
            setVal('result-division', data.division);
            setVal('result-match-name', data.match_name);
            setVal('result-match-date', data.match_date);
            setVal('result-location', data.location);
            setVal('stage-five', Number(data.five_to_glock || 0).toFixed(2));
            setVal('stage-plates', Number(data.glock_the_plates || 0).toFixed(2));
            setVal('stage-glockm', Number(data.glock_m || 0).toFixed(2));
            setVal('stage-penalties', Number(data.penalties || 0).toFixed(2));
            setVal('result-notes', data.notes);
            updateStageTotal();
            openModal('result-modal');
        });
    });

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
        setVal('user-color', '#1d4ed8');
        setVal('user-role', 'member');
        setVal('user-password', '');
        const activeBox = document.getElementById('user-active');
        if (activeBox) activeBox.checked = true;
    }

    // Edit User buttons
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
            setVal('user-color', data.color || '#1d4ed8');
            setVal('user-role', data.role || 'member');
            setVal('user-password', '');
            const activeBox = document.getElementById('user-active');
            if (activeBox) activeBox.checked = Number(data.active) === 1;
            openModal('user-modal');
        });
    });

    // Saved match quick-fill in Match Times modal
    const savedMatchSelect = document.getElementById('result-saved-match');
    if (savedMatchSelect) {
        savedMatchSelect.addEventListener('change', () => {
            const opt = savedMatchSelect.options[savedMatchSelect.selectedIndex];
            if (!opt || !opt.value) return;
            const nameEl = document.getElementById('result-match-name');
            const dateEl = document.getElementById('result-match-date');
            const locEl = document.getElementById('result-location');
            if (nameEl) nameEl.value = opt.getAttribute('data-name') || '';
            if (dateEl) dateEl.value = opt.getAttribute('data-date') || '';
            if (locEl) locEl.value = opt.getAttribute('data-location') || '';
        });
    }

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
