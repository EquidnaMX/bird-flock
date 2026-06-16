<?php

namespace Equidna\BirdFlock\Contracts;

interface SenderDefinitionInterface
{
    /**
     * @return class-string<MessageSenderInterface>
     */
    public function sender(): string;

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array;

    /**
     * @return class-string<SenderConfigValidatorInterface>|null
     */
    public function validator(): ?string;
}
