<?php

namespace MigrateToFlarum\RenumberPosts;

use Flarum\Extend;

return [
    (new Extend\Console())
        ->command(Commands\RenumberCommand::class),
];
