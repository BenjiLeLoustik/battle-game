<?php

namespace App\Repository;

use App\Entity\ChatChannel;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ChatChannelRepository extends AbstractRepository
{
    protected string $entityClass = ChatChannel::class;

    public function findOrdered(): array
    {
        return $this->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
    }

    public function findActive(): array
    {
        return $this->findBy(['active' => true], ['position' => 'ASC', 'name' => 'ASC']);
    }

    public function findBySlug(string $slug): ?ChatChannel
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
