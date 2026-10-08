<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Basket\Security;

use Gingerminds\CoreBundle\Entity\User\UserInterface;
use Gingerminds\MediaManagerBundle\Basket\Entity\BasketInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A guest basket is open to whoever has its token, a user basket to its owner only.
 *
 * @extends Voter<string, BasketInterface>
 */
class BasketVoter extends Voter
{
    public const string VIEW = 'BASKET_VIEW';
    public const string MODIFY = 'BASKET_MODIFY';
    public const string DOWNLOAD = 'BASKET_DOWNLOAD';
    public const string DELETE = 'BASKET_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof BasketInterface && \in_array($attribute, [self::VIEW, self::MODIFY, self::DOWNLOAD, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $owner = $subject->getOwner();

        if (!$owner instanceof UserInterface) {
            return true;
        }

        $user = $token->getUser();

        return $user instanceof UserInterface && $user->getId() === $owner->getId();
    }
}
