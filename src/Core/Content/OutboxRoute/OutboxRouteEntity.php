<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxRoute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OutboxRouteEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected string $eventPattern;

    protected int $priority = 100;

    protected bool $isActive = true;

    /**
     * @var list<string>
     */
    protected array $targetKeys = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEventPattern(): string
    {
        return $this->eventPattern;
    }

    public function setEventPattern(string $eventPattern): void
    {
        $this->eventPattern = $eventPattern;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
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
     * @return list<string>
     */
    public function getTargetKeys(): array
    {
        return $this->targetKeys;
    }

    /**
     * @param list<string> $targetKeys
     */
    public function setTargetKeys(array $targetKeys): void
    {
        $this->targetKeys = $targetKeys;
    }
}
