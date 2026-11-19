<?php
declare(strict_types=1);

class WebAuthn
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getRpId(): string
    {
        $configured = $this->config['webauthn']['rp_id'] ?? null;
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Strip port if present (e.g. localhost:8088 -> localhost)
        $host = preg_replace('/:\d+$/', '', $host);
        return strtolower($host ?: 'localhost');
    }

    public function getRpName(): string
    {
        return (string)($this->config['webauthn']['rp_name'] ?? 'GSSF Family Match Tracker');
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid base64url encoding');
        }
        return $decoded;
    }

    public function generateChallenge(): string
    {
        return self::base64UrlEncode(random_bytes(32));
    }

    public function verifyRegistration(
        string $clientDataJsonB64,
        string $attestationObjectB64,
        string $expectedChallengeB64
    ): array {
        $clientDataRaw = self::base64UrlDecode($clientDataJsonB64);
        $clientData = json_decode($clientDataRaw, true);
        if (!is_array($clientData)) {
            throw new RuntimeException('Invalid clientDataJSON');
        }

        if (($clientData['type'] ?? '') !== 'webauthn.create') {
            throw new RuntimeException('Invalid WebAuthn ceremony type');
        }
        if (!hash_equals($expectedChallengeB64, (string)($clientData['challenge'] ?? ''))) {
            throw new RuntimeException('Challenge mismatch');
        }
        $this->verifyOrigin((string)($clientData['origin'] ?? ''));

        $attRaw = self::base64UrlDecode($attestationObjectB64);
        $offset = 0;
        $att = $this->decodeCbor($attRaw, $offset);
        if (!is_array($att) || !isset($att['authData'])) {
            throw new RuntimeException('Invalid attestationObject');
        }

        $authData = (string)$att['authData'];
        if (strlen($authData) < 55) {
            throw new RuntimeException('Authenticator data too short');
        }

        $rpIdHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $this->getRpId(), true), $rpIdHash)) {
            throw new RuntimeException('RP ID hash mismatch');
        }

        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0) {
            throw new RuntimeException('User presence flag not set');
        }
        if (($flags & 0x40) === 0) {
            throw new RuntimeException('Attested credential data missing');
        }

        $signCount = unpack('N', substr($authData, 33, 4))[1];
        $credIdLen = unpack('n', substr($authData, 53, 2))[1];
        $credId = substr($authData, 55, $credIdLen);
        if (strlen($credId) !== $credIdLen) {
            throw new RuntimeException('Invalid credential ID length');
        }

        $coseOffset = 55 + $credIdLen;
        $coseKey = $this->decodeCbor($authData, $coseOffset);
        if (!is_array($coseKey)) {
            throw new RuntimeException('Invalid COSE public key');
        }

        $alg = (int)($coseKey[3] ?? 0);
        $pem = $this->coseKeyToPem($coseKey);

        return [
            'credential_id' => self::base64UrlEncode($credId),
            'public_key_pem' => $pem,
            'cose_alg' => $alg,
            'sign_count' => (int)$signCount,
        ];
    }

    public function verifyAuthentication(
        string $clientDataJsonB64,
        string $authenticatorDataB64,
        string $signatureB64,
        string $expectedChallengeB64,
        string $publicKeyPem,
        int $coseAlg,
        int $storedSignCount
    ): array {
        $clientDataRaw = self::base64UrlDecode($clientDataJsonB64);
        $clientData = json_decode($clientDataRaw, true);
        if (!is_array($clientData)) {
            throw new RuntimeException('Invalid clientDataJSON');
        }

        if (($clientData['type'] ?? '') !== 'webauthn.get') {
            throw new RuntimeException('Invalid WebAuthn ceremony type');
        }
        if (!hash_equals($expectedChallengeB64, (string)($clientData['challenge'] ?? ''))) {
            throw new RuntimeException('Challenge mismatch');
        }
        $this->verifyOrigin((string)($clientData['origin'] ?? ''));

        $authData = self::base64UrlDecode($authenticatorDataB64);
        if (strlen($authData) < 37) {
            throw new RuntimeException('Authenticator data too short');
        }

        $rpIdHash = substr($authData, 0, 32);
        if (!hash_equals(hash('sha256', $this->getRpId(), true), $rpIdHash)) {
            throw new RuntimeException('RP ID hash mismatch');
        }

        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0) {
            throw new RuntimeException('User presence flag not set');
        }

        $newSignCount = (int)unpack('N', substr($authData, 33, 4))[1];
        if ($storedSignCount > 0 && $newSignCount > 0 && $newSignCount <= $storedSignCount) {
            throw new RuntimeException('Passkey sign count anomaly detected');
        }

        $signature = self::base64UrlDecode($signatureB64);
        $signedData = $authData . hash('sha256', $clientDataRaw, true);

        $algo = match ($coseAlg) {
            -7, -257 => OPENSSL_ALGO_SHA256,
            -35 => OPENSSL_ALGO_SHA384,
            -36 => OPENSSL_ALGO_SHA512,
            default => throw new RuntimeException('Unsupported COSE algorithm: ' . $coseAlg),
        };

        $ok = openssl_verify($signedData, $signature, $publicKeyPem, $algo);
        if ($ok !== 1) {
            throw new RuntimeException('Passkey signature verification failed');
        }

        return ['sign_count' => $newSignCount];
    }

    private function verifyOrigin(string $origin): void
    {
        $parsed = parse_url($origin);
        $originHost = strtolower((string)($parsed['host'] ?? ''));
        $rpId = $this->getRpId();
        if ($originHost === '' || ($originHost !== $rpId && !str_ends_with($originHost, '.' . $rpId))) {
            throw new RuntimeException('Origin mismatch: ' . $origin);
        }
    }

    private function coseKeyToPem(array $cose): string
    {
        $kty = (int)($cose[1] ?? 0);

        // EC2 (P-256, ES256)
        if ($kty === 2) {
            $crv = (int)($cose[-1] ?? 0);
            if ($crv !== 1) {
                throw new RuntimeException('Only P-256 EC curve is supported');
            }
            $x = (string)($cose[-2] ?? '');
            $y = (string)($cose[-3] ?? '');
            if (strlen($x) !== 32 || strlen($y) !== 32) {
                throw new RuntimeException('Invalid EC coordinate length');
            }
            // SubjectPublicKeyInfo prefix for P-256 uncompressed point
            $derHeader = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
            $der = $derHeader . $x . $y;
            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        }

        // RSA (RS256)
        if ($kty === 3) {
            $n = (string)($cose[-1] ?? '');
            $e = (string)($cose[-2] ?? '');
            if ($n === '' || $e === '') {
                throw new RuntimeException('Invalid RSA COSE key');
            }
            $nInt = $this->derInteger($n);
            $eInt = $this->derInteger($e);
            $rsaSeq = $this->derSequence($nInt . $eInt);
            $algId = hex2bin('300d06092a864886f70d0101010500');
            $bitString = $this->derTag(0x03, "\x00" . $rsaSeq);
            $der = $this->derSequence($algId . $bitString);
            return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        }

        throw new RuntimeException('Unsupported COSE key type: ' . $kty);
    }

    private function derLength(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $bytes = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function derTag(int $tag, string $content): string
    {
        return chr($tag) . $this->derLength(strlen($content)) . $content;
    }

    private function derSequence(string $content): string
    {
        return $this->derTag(0x30, $content);
    }

    private function derInteger(string $unsignedBytes): string
    {
        if (ord($unsignedBytes[0]) >= 0x80) {
            $unsignedBytes = "\x00" . $unsignedBytes;
        }
        return $this->derTag(0x02, $unsignedBytes);
    }

    private function decodeCbor(string $data, int &$offset): mixed
    {
        if ($offset >= strlen($data)) {
            throw new RuntimeException('Unexpected end of CBOR data');
        }
        $initial = ord($data[$offset++]);
        $major = $initial >> 5;
        $info = $initial & 0x1F;

        $val = $this->cborAdditional($data, $offset, $info);

        switch ($major) {
            case 0: // unsigned int
                return $val;
            case 1: // negative int
                return -1 - $val;
            case 2: // byte string
                $s = substr($data, $offset, $val);
                $offset += $val;
                return $s;
            case 3: // text string
                $s = substr($data, $offset, $val);
                $offset += $val;
                return $s;
            case 4: // array
                $arr = [];
                for ($i = 0; $i < $val; $i++) {
                    $arr[] = $this->decodeCbor($data, $offset);
                }
                return $arr;
            case 5: // map
                $map = [];
                for ($i = 0; $i < $val; $i++) {
                    $k = $this->decodeCbor($data, $offset);
                    $v = $this->decodeCbor($data, $offset);
                    $map[$k] = $v;
                }
                return $map;
            case 7: // simple / float
                return $val;
            default:
                throw new RuntimeException('Unsupported CBOR major type: ' . $major);
        }
    }

    private function cborAdditional(string $data, int &$offset, int $info): int
    {
        if ($info < 24) {
            return $info;
        }
        if ($info === 24) {
            return ord($data[$offset++]);
        }
        if ($info === 25) {
            $v = unpack('n', substr($data, $offset, 2))[1];
            $offset += 2;
            return $v;
        }
        if ($info === 26) {
            $v = unpack('N', substr($data, $offset, 4))[1];
            $offset += 4;
            return $v;
        }
        if ($info === 27) {
            $v = unpack('J', substr($data, $offset, 8))[1];
            $offset += 8;
            return $v;
        }
        throw new RuntimeException('Unsupported CBOR additional info: ' . $info);
    }
}
