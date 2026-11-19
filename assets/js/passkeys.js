document.addEventListener('DOMContentLoaded', () => {
    const b64UrlToBuf = (b64url) => {
        const pad = '='.repeat((4 - (b64url.length % 4)) % 4);
        const b64 = (b64url + pad).replace(/-/g, '+').replace(/_/g, '/');
        const bin = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) {
            bytes[i] = bin.charCodeAt(i);
        }
        return bytes.buffer;
    };

    const bufToB64Url = (buf) => {
        const bytes = new Uint8Array(buf);
        let bin = '';
        for (let i = 0; i < bytes.length; i++) {
            bin += String.fromCharCode(bytes[i]);
        }
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };

    const getCsrf = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="csrf_token"]')?.value
            || '';
    };

    // -------------------------------------------------------------------------
    // Passkey Sign-In (login.php)
    // -------------------------------------------------------------------------
    const passkeyLoginBtn = document.getElementById('btn-passkey-login');
    const passkeyStatus = document.getElementById('passkey-status');

    if (passkeyLoginBtn) {
        if (!window.PublicKeyCredential) {
            passkeyLoginBtn.disabled = true;
            if (passkeyStatus) {
                passkeyStatus.textContent = 'Passkeys are not supported in this browser or require HTTPS.';
                passkeyStatus.className = 'alert alert-error';
                passkeyStatus.style.display = 'block';
            }
        }

        passkeyLoginBtn.addEventListener('click', async () => {
            if (passkeyStatus) {
                passkeyStatus.style.display = 'none';
            }
            const username = document.getElementById('login-username')?.value?.trim() || '';

            try {
                passkeyLoginBtn.disabled = true;
                const optResp = await fetch('webauthn.php?action=login_options', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username }),
                });
                const options = await optResp.json();
                if (!optResp.ok) {
                    throw new Error(options.error || 'Unable to start passkey sign-in');
                }

                const publicKey = {
                    challenge: b64UrlToBuf(options.challenge),
                    rpId: options.rpId,
                    timeout: options.timeout || 60000,
                    userVerification: options.userVerification || 'preferred',
                };
                if (Array.isArray(options.allowCredentials) && options.allowCredentials.length > 0) {
                    publicKey.allowCredentials = options.allowCredentials.map((c) => ({
                        type: c.type,
                        id: b64UrlToBuf(c.id),
                    }));
                }

                const assertion = await navigator.credentials.get({ publicKey });
                if (!assertion) {
                    throw new Error('Passkey sign-in was cancelled');
                }

                const verifyResp = await fetch('webauthn.php?action=login_verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: getCsrf(),
                        id: assertion.id,
                        clientDataJSON: bufToB64Url(assertion.response.clientDataJSON),
                        authenticatorData: bufToB64Url(assertion.response.authenticatorData),
                        signature: bufToB64Url(assertion.response.signature),
                    }),
                });
                const result = await verifyResp.json();
                if (!verifyResp.ok || !result.ok) {
                    throw new Error(result.error || 'Passkey verification failed');
                }

                window.location.href = result.redirect || 'index.php';
            } catch (err) {
                if (passkeyStatus) {
                    passkeyStatus.textContent = err.message || 'Passkey sign-in failed.';
                    passkeyStatus.className = 'alert alert-error';
                    passkeyStatus.style.display = 'block';
                }
            } finally {
                passkeyLoginBtn.disabled = false;
            }
        });
    }

    // -------------------------------------------------------------------------
    // Passkey Registration & Removal (users.php)
    // -------------------------------------------------------------------------
    const registerPasskeyBtn = document.getElementById('btn-register-passkey');
    const passkeyRegStatus = document.getElementById('passkey-reg-status');

    if (registerPasskeyBtn) {
        registerPasskeyBtn.addEventListener('click', async () => {
            if (passkeyRegStatus) {
                passkeyRegStatus.style.display = 'none';
            }
            if (!window.PublicKeyCredential) {
                if (passkeyRegStatus) {
                    passkeyRegStatus.textContent = 'WebAuthn / Passkeys require a modern browser over HTTPS (or localhost).';
                    passkeyRegStatus.className = 'alert alert-error';
                    passkeyRegStatus.style.display = 'block';
                }
                return;
            }

            const labelInput = document.getElementById('passkey-label');
            const label = labelInput?.value?.trim() || 'My Device Passkey';

            try {
                registerPasskeyBtn.disabled = true;
                const optResp = await fetch('webauthn.php?action=register_options');
                const options = await optResp.json();
                if (!optResp.ok) {
                    throw new Error(options.error || 'Unable to start passkey registration');
                }

                const publicKey = {
                    challenge: b64UrlToBuf(options.challenge),
                    rp: options.rp,
                    user: {
                        id: b64UrlToBuf(options.user.id),
                        name: options.user.name,
                        displayName: options.user.displayName,
                    },
                    pubKeyCredParams: options.pubKeyCredParams,
                    timeout: options.timeout || 60000,
                    attestation: options.attestation || 'none',
                    authenticatorSelection: options.authenticatorSelection,
                    excludeCredentials: (options.excludeCredentials || []).map((c) => ({
                        type: c.type,
                        id: b64UrlToBuf(c.id),
                    })),
                };

                const credential = await navigator.credentials.create({ publicKey });
                if (!credential) {
                    throw new Error('Passkey registration was cancelled');
                }

                const verifyResp = await fetch('webauthn.php?action=register_verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf_token: getCsrf(),
                        label,
                        clientDataJSON: bufToB64Url(credential.response.clientDataJSON),
                        attestationObject: bufToB64Url(credential.response.attestationObject),
                    }),
                });
                const result = await verifyResp.json();
                if (!verifyResp.ok || !result.ok) {
                    throw new Error(result.error || 'Unable to save passkey');
                }

                window.location.reload();
            } catch (err) {
                if (passkeyRegStatus) {
                    passkeyRegStatus.textContent = err.message || 'Passkey registration failed.';
                    passkeyRegStatus.className = 'alert alert-error';
                    passkeyRegStatus.style.display = 'block';
                }
            } finally {
                registerPasskeyBtn.disabled = false;
            }
        });
    }

    document.querySelectorAll('.js-delete-passkey').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (!confirm('Remove this passkey?')) return;
            const id = parseInt(btn.getAttribute('data-id') || '0', 10);
            try {
                const resp = await fetch('webauthn.php?action=delete_passkey', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: getCsrf(), id }),
                });
                const data = await resp.json();
                if (resp.ok && data.ok) {
                    window.location.reload();
                }
            } catch (_) {
                // ignore
            }
        });
    });
});
