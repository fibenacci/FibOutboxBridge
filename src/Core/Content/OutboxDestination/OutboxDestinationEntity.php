<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxDestination;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OutboxDestinationEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $technicalName;

    protected string $type;

    protected bool $isActive = true;

    /**
     * @var null|array<string, mixed>
     */
    protected ?array $config = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    /**
     * @return null|array<string, mixed>
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param null|array<string, mixed> $config
     */
    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }
}
