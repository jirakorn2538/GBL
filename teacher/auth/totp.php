<?php
/**
 * Money Life - Pure PHP TOTP (RFC 6238) Implementation
 * No external composer dependencies required
 */

class TOTP {
    private static $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random 16-character Base32 secret
     */
    public static function generateSecret($length = 16) {
        $secret = '';
        $charsCount = strlen(self::$base32Chars);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, $charsCount - 1)];
        }
        return $secret;
    }

    /**
     * Decode Base32 string to binary string
     */
    public static function base32Decode($base32) {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            if ($char === '=' || $char === ' ') continue;
            $val = strpos(self::$base32Chars, $char);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }

    /**
     * Calculate TOTP code for a given timestamp
     */
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);
        // Pack time into 8-byte binary (big-endian)
        $timeBytes = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBytes, $secretKey, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);

        $unpacked = unpack('N', $truncatedHash);
        $binaryCode = $unpacked[1] & 0x7FFFFFFF;

        $otp = $binaryCode % 1000000;
        return str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify user submitted TOTP with +-1 time window (drift tolerance of 30s)
     */
    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get otpauth:// URI for QR Code scanner
     */
    public static function getOtpAuthUrl($accountName, $secret, $issuer = 'MoneyLife') {
        $account = rawurlencode($accountName);
        $iss = rawurlencode($issuer);
        return "otpauth://totp/{$iss}:{$account}?secret={$secret}&issuer={$iss}&algorithm=SHA1&digits=6&period=30";
    }
}
