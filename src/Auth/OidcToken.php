<?php

namespace Coderflex\LaravelTicket\Auth;

use Coderflex\LaravelTicket\Exceptions\SingleSignOnFailed;

class OidcToken
{
    /**
     * @param  array<string, mixed>  $jwks
     * @return array<string, mixed>
     */
    public function verify(string $jwt, array $jwks, string $issuer, string $audience, string $nonce): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            throw new SingleSignOnFailed('The identity token could not be read.');
        }

        $header = $this->jsonObject($parts[0], 'The identity token header could not be read.');
        $claims = $this->jsonObject($parts[1], 'The identity token could not be read.');
        $algorithm = $header['alg'] ?? null;

        if ($algorithm !== 'RS256') {
            throw new SingleSignOnFailed('The identity token uses an unsupported signature.');
        }

        $signature = $this->base64UrlDecode($parts[2]);
        $pem = $this->key($jwks, is_string($header['kid'] ?? null) ? $header['kid'] : null);

        if (openssl_verify($parts[0].'.'.$parts[1], $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new SingleSignOnFailed('The identity token signature was rejected.', true);
        }

        $this->claims($claims, $issuer, $audience, $nonce);

        return $claims;
    }

    /**
     * @param  array<string, mixed>  $jwks
     */
    private function key(array $jwks, ?string $kid): string
    {
        $keys = $jwks['keys'] ?? null;

        if (! is_array($keys)) {
            throw new SingleSignOnFailed('The identity provider did not publish a signing key.', true);
        }

        foreach ($keys as $key) {
            if (! is_array($key) || ($key['kty'] ?? null) !== 'RSA') {
                continue;
            }

            if ($kid !== null && ($key['kid'] ?? null) !== $kid) {
                continue;
            }

            $modulus = $key['n'] ?? null;
            $exponent = $key['e'] ?? null;

            if (! is_string($modulus) || ! is_string($exponent) || $modulus === '' || $exponent === '') {
                continue;
            }

            return $this->pem($modulus, $exponent);
        }

        throw new SingleSignOnFailed('The identity provider signing key was not found.', true);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function claims(array $claims, string $issuer, string $audience, string $nonce): void
    {
        $now = time();
        $exp = $claims['exp'] ?? null;
        $nbf = $claims['nbf'] ?? null;
        $tokenIssuer = $claims['iss'] ?? null;
        $tokenAudience = $claims['aud'] ?? null;
        $tokenNonce = $claims['nonce'] ?? null;

        if (! is_numeric($exp) || ($now - 120) >= (int) $exp) {
            throw new SingleSignOnFailed('The identity token has expired.');
        }

        if (is_numeric($nbf) && ($now + 120) < (int) $nbf) {
            throw new SingleSignOnFailed('The identity token is not valid yet.');
        }

        if (! is_string($tokenIssuer) || rtrim($tokenIssuer, '/') !== rtrim($issuer, '/')) {
            throw new SingleSignOnFailed('The identity token came from an unexpected issuer.');
        }

        $audiences = is_array($tokenAudience) ? $tokenAudience : [$tokenAudience];

        if (! in_array($audience, $audiences, true)) {
            throw new SingleSignOnFailed('The identity token was issued for a different application.');
        }

        if (! is_string($tokenNonce) || ! hash_equals($nonce, $tokenNonce)) {
            throw new SingleSignOnFailed('The identity token did not match this sign-in attempt.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(string $segment, string $message): array
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        if (! is_array($decoded)) {
            throw new SingleSignOnFailed($message);
        }

        return $decoded;
    }

    private function pem(string $modulus, string $exponent): string
    {
        $modulusBytes = $this->base64UrlDecode($modulus);
        $exponentBytes = $this->base64UrlDecode($exponent);

        if ($modulusBytes === '' || $exponentBytes === '') {
            throw new SingleSignOnFailed('The identity provider signing key could not be read.', true);
        }

        $public = $this->sequence($this->integer($modulusBytes).$this->integer($exponentBytes));
        $algorithm = $this->sequence($this->objectId("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01")."\x05\x00");
        $bitString = "\x03".$this->length(strlen($public) + 1)."\x00".$public;
        $der = $this->sequence($algorithm.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function integer(string $bytes): string
    {
        if ((ord($bytes[0]) & 0x80) === 0x80) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->length(strlen($bytes)).$bytes;
    }

    private function sequence(string $bytes): string
    {
        return "\x30".$this->length(strlen($bytes)).$bytes;
    }

    private function objectId(string $bytes): string
    {
        return "\x06".$this->length(strlen($bytes)).$bytes;
    }

    private function length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $encoded = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($encoded)).$encoded;
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;

        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) ? $decoded : '';
    }
}
