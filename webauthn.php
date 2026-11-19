<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';
require_once __DIR__ . '/src/WebAuthn.php';

$webauthn = new WebAuthn($config);
$action = $_GET['action'] ?? '';

$inputRaw = file_get_contents('php://input');
$body = $inputRaw ? json_decode($inputRaw, true) : [];
if (!is_array($body)) {
    $body = [];
}

try {
    if ($action === 'register_options') {
        $user = $auth->user();
        if (!$user) {
            json_response(['error' => 'Unauthorized'], 401);
        }

        $challenge = $webauthn->generateChallenge();
        $_SESSION['webauthn_reg_challenge'] = $challenge;

        $existing = $storage->getPasskeysForUser((int)$user['id']);
        $exclude = array_map(fn($p) => [
            'type' => 'public-key',
            'id' => $p['credential_id'],
        ], $existing);

        json_response([
            'challenge' => $challenge,
            'rp' => [
                'name' => $webauthn->getRpName(),
                'id' => $webauthn->getRpId(),
            ],
            'user' => [
                'id' => WebAuthn::base64UrlEncode((string)$user['id']),
                'name' => $user['username'],
                'displayName' => $user['display_name'],
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
            'excludeCredentials' => $exclude,
        ]);
    }

    if ($action === 'register_verify') {
        $user = $auth->user();
        if (!$user) {
            json_response(['error' => 'Unauthorized'], 401);
        }
        if (!$auth->verifyCsrf($body['csrf_token'] ?? null)) {
            json_response(['error' => 'Invalid CSRF token'], 403);
        }

        $expectedChallenge = (string)($_SESSION['webauthn_reg_challenge'] ?? '');
        unset($_SESSION['webauthn_reg_challenge']);
        if ($expectedChallenge === '') {
            json_response(['error' => 'No active passkey registration challenge'], 400);
        }

        $verified = $webauthn->verifyRegistration(
            (string)($body['clientDataJSON'] ?? ''),
            (string)($body['attestationObject'] ?? ''),
            $expectedChallenge
        );

        $label = trim((string)($body['label'] ?? 'My Passkey'));
        $id = $storage->createPasskey(
            (int)$user['id'],
            $verified['credential_id'],
            $verified['public_key_pem'],
            $verified['cose_alg'],
            $verified['sign_count'],
            $label
        );

        json_response(['ok' => true, 'id' => $id]);
    }

    if ($action === 'login_options') {
        $challenge = $webauthn->generateChallenge();
        $_SESSION['webauthn_auth_challenge'] = $challenge;

        $allowCredentials = [];
        $username = trim((string)($body['username'] ?? ($_GET['username'] ?? '')));
        if ($username !== '') {
            $u = $storage->getUserByUsername($username);
            if ($u && !empty($u['active'])) {
                foreach ($storage->getPasskeysForUser((int)$u['id']) as $pk) {
                    $allowCredentials[] = [
                        'type' => 'public-key',
                        'id' => $pk['credential_id'],
                    ];
                }
            }
        }

        json_response([
            'challenge' => $challenge,
            'rpId' => $webauthn->getRpId(),
            'timeout' => 60000,
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
        ]);
    }

    if ($action === 'login_verify') {
        if (!$auth->verifyCsrf($body['csrf_token'] ?? null)) {
            json_response(['error' => 'Invalid CSRF token'], 403);
        }

        $expectedChallenge = (string)($_SESSION['webauthn_auth_challenge'] ?? '');
        unset($_SESSION['webauthn_auth_challenge']);
        if ($expectedChallenge === '') {
            json_response(['error' => 'No active passkey login challenge'], 400);
        }

        $credId = (string)($body['id'] ?? '');
        $passkey = $storage->getPasskeyByCredentialId($credId);
        if (!$passkey || empty($passkey['active'])) {
            json_response(['error' => 'Passkey not recognized or account inactive'], 401);
        }

        $verified = $webauthn->verifyAuthentication(
            (string)($body['clientDataJSON'] ?? ''),
            (string)($body['authenticatorData'] ?? ''),
            (string)($body['signature'] ?? ''),
            $expectedChallenge,
            $passkey['public_key_pem'],
            (int)$passkey['cose_alg'],
            (int)$passkey['sign_count']
        );

        $storage->updatePasskeyUsage((int)$passkey['id'], $verified['sign_count']);
        $auth->loginByUserId((int)$passkey['user_id']);

        json_response(['ok' => true, 'redirect' => 'index.php']);
    }

    if ($action === 'delete_passkey') {
        $user = $auth->user();
        if (!$user) {
            json_response(['error' => 'Unauthorized'], 401);
        }
        if (!$auth->verifyCsrf($body['csrf_token'] ?? null)) {
            json_response(['error' => 'Invalid CSRF token'], 403);
        }
        $id = (int)($body['id'] ?? 0);
        $deleted = $id > 0 && $storage->deletePasskey($id, (int)$user['id']);
        json_response(['ok' => $deleted]);
    }

    json_response(['error' => 'Unknown WebAuthn action'], 400);
} catch (Throwable $e) {
    json_response(['error' => $e->getMessage()], 400);
}
