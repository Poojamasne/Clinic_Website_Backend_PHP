<?php

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT {
    private static $secret;
    private static $algorithm = 'HS256';
    
    public static function init() {
        self::$secret = getenv('JWT_SECRET') ?: 'default_secret_key';
    }
    
    public static function encode($payload) {
        $issuedAt = time();
        $expiresIn = getenv('JWT_EXPIRES_IN') ?: 604800; // 7 days
        $expirationTime = $issuedAt + $expiresIn;
        
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $expirationTime;
        
        return FirebaseJWT::encode($payload, self::$secret, self::$algorithm);
    }
    
    public static function decode($token) {
        try {
            $decoded = FirebaseJWT::decode($token, new Key(self::$secret, self::$algorithm));
            return (array) $decoded;
        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new Exception('Token expired. Please login again.');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new Exception('Invalid token signature.');
        } catch (Exception $e) {
            throw new Exception('Invalid token.');
        }
    }
    
    public static function verify($token) {
        try {
            self::decode($token);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

// Initialize JWT
JWT::init();