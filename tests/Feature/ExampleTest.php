<?php

declare(strict_types=1);

it('returns a successful response from the welcome page', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
});
