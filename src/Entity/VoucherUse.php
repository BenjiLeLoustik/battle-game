<?php

namespace App\Entity;

use App\Entity\Trait\IdTrait;
use App\Entity\Trait\TimestampTrait;
use App\Repository\VoucherUseRepository;
use NeoPHP\Package\Orm\Mapping as ORM;

#[ORM\Entity(repository: VoucherUseRepository::class)]
class VoucherUse
{
    use IdTrait;
    use TimestampTrait;

    #[ORM\ManyToOne(Voucher::class, nullable: false, onDelete: 'CASCADE')]
    private Voucher $voucher;

    #[ORM\ManyToOne(User::class, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\ManyToOne(ShopOrder::class, onDelete: 'SET NULL')]
    private ?ShopOrder $order = null;

    public function getVoucher(): Voucher
    {
        return $this->voucher;
    }

    public function setVoucher(Voucher $voucher): static
    {
        $this->voucher = $voucher;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getOrder(): ?ShopOrder
    {
        return $this->order;
    }

    public function setOrder(?ShopOrder $order): static
    {
        $this->order = $order;

        return $this;
    }
}
