<?php

test('health check endpoint responds with 200 ok and does not leak environment secrets', function () {
    $response = $this->get('/up');

    $response->assertOk();

    $content = $response->getContent();

    expect($content)->not->toBeEmpty();
    // Verify no secret environment variables or credentials leaked
    expect($content)->not->toContain('APP_KEY');
    expect($content)->not->toContain('DB_PASSWORD');
    expect($content)->not->toContain('base64:');
});
