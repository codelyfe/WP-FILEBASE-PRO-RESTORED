WP-Filebase Pro Download Manager Unlocked
===============================

WP-Filebase (free). [Plugin Directory](https://wordpress.org/plugins/wp-filebase) is no longer compatiable / or working.

But...

This version is now working. 

Restored by Codelyfe on 12/16/2022

You can create issues to get help.

Fixes:
-------
- Works with PHP 8.0.1 + (Date Fixed: 11/12/23)
- Trouble uploading files (Date Fixed: 11/12/23)

Documentation
-------
Available Here https://wpfilebase.com/documentation/

-------

This may help https://github.com/rectorphp/rector
-------


Automated Upgrade
If anyone needs to upgrade dozens of create_function() cases in their code to anonymous functions, I work on a tool called Rector.

It goes through the code and replaces the create_function with anonymous functions 1:1. It's tested on 30 various cases.

Install

composer require rector/rector --dev
Setup

Let's say you want to upgrade code in the /src directory.

# rector.php
<?php

use Rector\Core\Configuration\Option;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Rector\Php72\Rector\FuncCall\CreateFunctionToAnonymousFunctionRector;

return static function (ContainerConfigurator $containerConfigurator) {
    $parameters = $containerConfigurator->parameters();
    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
    ]);

    $services = $containerConfigurator->services();
    $services->set(CreateFunctionToAnonymousFunctionRector::class);
};
Run on your code

# this is set run, it only report what it would change
vendor/bin/rector process --config rector.php --dry-run

# this actually changes the code
vendor/bin/rector process --config rector.php

# the "rector.php" config is loaded by default, so we can drop it
vendor/bin/rector process