<?php

class Encryption {
    private static ?string $key = null;

    private static function getKey(): string {
        if (self::$key === null) {
            $rawKey = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'FallbackSecretKeyForGinnaBeauty2026!';
            // Ensure the key is exactly 32 bytes
            if (strlen($rawKey) !== 32) {
                self::$key = hash('sha256', $rawKey, true);
            } else {
                self::$key = $rawKey;
            }
        }
        return self::$key;
    }

    /**
     * Encrypt a plaintext value.
     */
    public static function encrypt(string $plaintext): string {
        $key = self::getKey();
        $cipher = 'aes-256-cbc';
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $ciphertext = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new Exception('Encryption failed.');
        }
        
        // Encrypt-then-MAC (HMAC-SHA256)
        $hmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        
        return base64_encode($iv . $hmac . $ciphertext);
    }

    /**
     * Decrypt a ciphertext value.
     */
    public static function decrypt(?string $payload): ?string {
        if ($payload === null || $payload === '') {
            return null;
        }

        $key = self::getKey();
        $cipher = 'aes-256-cbc';
        
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }
        
        $ivLength = openssl_cipher_iv_length($cipher);
        $hmacLength = 32; // HMAC-SHA256 is 32 bytes
        
        if (strlen($decoded) < ($ivLength + $hmacLength)) {
            return null;
        }
        
        $iv = substr($decoded, 0, $ivLength);
        $hmac = substr($decoded, $ivLength, $hmacLength);
        $ciphertext = substr($decoded, $ivLength + $hmacLength);
        
        $calculatedHmac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
        if (!hash_equals($hmac, $calculatedHmac)) {
            return null; // Tampered or wrong key
        }
        
        $decrypted = openssl_decrypt($ciphertext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }
}
