<?php

declare(strict_types=1);

namespace Gingerminds\MediaManagerBundle\Exception;

use Gingerminds\MediaManagerBundle\GingermindsMediaManagerBundle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Message shown to the admin users (`error.*` keys of the bundle domain).
 */
trait TranslatableExceptionTrait
{
    private string $translationKey = '';

    /**
     * @var array<string, int|string>
     */
    private array $translationParameters = [];

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return '' === $this->translationKey
            ? $this->getMessage()
            : $translator->trans($this->translationKey, $this->translationParameters, GingermindsMediaManagerBundle::TRANSLATION_DOMAIN, $locale);
    }

    /**
     * @param array<string, int|string> $parameters
     */
    private function translated(string $key, array $parameters = []): static
    {
        $this->translationKey = $key;
        $this->translationParameters = $parameters;

        return $this;
    }
}
