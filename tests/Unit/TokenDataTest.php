<?php

use FactuTica\FactuticaCR\Services\Hacienda\Idp\TokenData;

it('creates token from response data', function () {
    $token = TokenData::fromResponse([
        'access_token'       => 'test-access-token',
        'refresh_token'      => 'test-refresh-token',
        'expires_in'         => 300,
        'refresh_expires_in' => 1800,
    ]);

    expect($token->accessToken)->toBe('test-access-token');
    expect($token->refreshToken)->toBe('test-refresh-token');
    expect($token->accessExpiresAt)->toBeGreaterThan(time());
    expect($token->refreshExpiresAt)->toBeGreaterThan(time());
});

it('detects expired access token', function () {
    $token = new TokenData(
        accessToken: 'expired',
        refreshToken: 'refresh',
        accessExpiresAt: time() - 100,
        refreshExpiresAt: time() + 1800,
    );

    expect($token->isAccessExpired())->toBeTrue();
    expect($token->isRefreshExpired())->toBeFalse();
});

it('detects expired refresh token', function () {
    $token = new TokenData(
        accessToken: 'expired',
        refreshToken: 'expired-refresh',
        accessExpiresAt: time() - 100,
        refreshExpiresAt: time() - 100,
    );

    expect($token->isAccessExpired())->toBeTrue();
    expect($token->isRefreshExpired())->toBeTrue();
});

it('considers margin for expiration check', function () {
    $token = new TokenData(
        accessToken: 'almost-expired',
        refreshToken: 'refresh',
        accessExpiresAt: time() + 10, // 10 seconds left, but 30s margin
        refreshExpiresAt: time() + 1800,
    );

    expect($token->isAccessExpired(margin: 30))->toBeTrue();
    expect($token->isAccessExpired(margin: 5))->toBeFalse();
});

it('detects valid token as not expired', function () {
    $token = TokenData::fromResponse([
        'access_token'       => 'valid',
        'refresh_token'      => 'valid-refresh',
        'expires_in'         => 300,
        'refresh_expires_in' => 1800,
    ]);

    expect($token->isAccessExpired())->toBeFalse();
    expect($token->isRefreshExpired())->toBeFalse();
});