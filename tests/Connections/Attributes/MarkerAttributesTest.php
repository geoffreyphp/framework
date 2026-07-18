<?php

declare(strict_types=1);

use Geoffrey\Connections\Attributes\Shared;
use Geoffrey\Connections\Attributes\WithOauth;

it('instantiates marker attributes with oauth and shared without arguments', function (): void {
    $withOauth = new WithOauth;
    $shared = new Shared;

    expect($withOauth)->toBeInstanceOf(WithOauth::class);
    expect($shared)->toBeInstanceOf(Shared::class);
});
