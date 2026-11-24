<?php

/*
 * This file is part of the Austral Cache Bundle package.
 *
 * (c) Austral <support@austral.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Austral\CacheBundle\Tests\Fixtures;

use Austral\Contracts\Entity\EntityInterface;

/**
 * Minimal Austral entity. Without an id, $id is not initialized, like a new entity whose id is generated.
 *
 * @implements EntityInterface<TestEntity>
 */
class TestEntity implements EntityInterface
{
  protected string $id;

  public function __construct(?string $id = null)
  {
    if (null !== $id)
    {
      $this->id = $id;
    }
  }

  public function __toString(): string
  {
    return $this->id ?? '';
  }

  public function getId(): string
  {
    return $this->id;
  }

  public function setId(string $id): self
  {
    $this->id = $id;

    return $this;
  }

  public function getIsCreate(): bool
  {
    return false;
  }

  public function setIsCreate(bool $isCreate): self
  {
    return $this;
  }

  /**
   * @return array<string, mixed>
   */
  public function arrayObject(): array
  {
    return [];
  }

  public function json(): string
  {
    return '{}';
  }

  public function getClassname(): string
  {
    return 'TestEntity';
  }

  public function getClassnameForMapping(): string
  {
    return self::class;
  }

  public function getSnakeClassname(): string
  {
    return 'test_entity';
  }

  public function getSluggerClassname(): string
  {
    return 'test-entity';
  }

  public function getEntityName(): string
  {
    return 'TestEntity';
  }

  /**
   * @param array<string, mixed> $values
   */
  public function hydrate(array $values = []): self
  {
    return $this;
  }

  public function getValueByFieldname(string $fieldname): mixed
  {
    return null;
  }

  public function setValueByFieldname(string $fieldname, mixed $value = null): self
  {
    return $this;
  }
}
