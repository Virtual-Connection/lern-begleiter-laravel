<?php

declare(strict_types=1);

it('redirects the home page to sources', function () {
    $this->get('/')
        ->assertRedirect(route('sources.index'));
});
