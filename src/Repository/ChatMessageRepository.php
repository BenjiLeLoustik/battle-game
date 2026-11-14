<?php

namespace App\Repository;

use App\Entity\ChatMessage;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class ChatMessageRepository extends AbstractRepository
{
    protected string $entityClass = ChatMessage::class;
}
