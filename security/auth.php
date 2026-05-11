<?php
define('JWT_SECRET', 'snakho_sbahle_retha2738');

function generateJWT($userId, $userType) {
    $header = base64_encode(json_encode([
        'alg' => 'HS256',
        'typ' => 'JWT'
    ]));

    $payload = base64_encode(json_encode([
        'user_id' => $userId,
        'user_type' => $userType,
        'exp' => time() + (24 * 60 * 60)
    ]));

    $signature = base64_encode(hash_hmac(
        'sha256',
        "$header.$payload",
        JWT_SECRET,
        true
    ));

    return "$header.$payload.$signature";
}

function validateJWT($token) {
    $parts = explode('.', $token);
    
    if(count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $signature] = $parts;

    $validSignature = base64_encode(hash_hmac(
        'sha256',
        "$header.$payload",
        JWT_SECRET,
        true
    ));

    if($signature !== $validSignature) {
        return null;
    }

    $data = json_decode(base64_decode($payload), true);

    if($data['exp'] < time()) {
        return null;
    }

    return $data;
}

function getAuthUser() {
    $headers = getallheaders();
    
    if(!isset($headers['Authorization'])) {
        return null;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    return validateJWT($token);
}
?>