<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Controller;

use Gingerminds\CoreBundle\Controller\AbstractCrudController;

final class ArticleController extends AbstractCrudController
{
    protected function getResourceName(): string
    {
        return 'article';
    }
}
