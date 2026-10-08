<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gingerminds\MediaManagerBundle\Entity\Media\AbstractMediaLink;
use Gingerminds\MediaManagerBundle\Entity\Media\MediaInterface;

#[ORM\Entity]
#[ORM\Table(name: 'article_media')]
class ArticleMedia extends AbstractMediaLink
{
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'mediaLinks')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        public Article $article,
        MediaInterface $media,
        string $collection,
    ) {
        parent::__construct($media, $collection);
    }
}
