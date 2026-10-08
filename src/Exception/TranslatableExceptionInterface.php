<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Error of a library action, shown to the admin user.
 */
interface TranslatableExceptionInterface extends \Throwable, TranslatableInterface
{
}
