<?php

declare(strict_types=1);

namespace OCA\VCardAPI\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap
{
    public const APP_ID = 'contact_vcard_api';

    public function __construct(array $urlParams = [])
    {
        parent::__construct(self::APP_ID, $urlParams);
    }

    public function register(IRegistrationContext $context): void
    {
        // Register services, event listeners, etc. here if needed
    }

    public function boot(IBootContext $context): void
    {
        // Runtime initialization if needed
    }
}
