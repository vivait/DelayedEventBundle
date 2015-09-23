<?php

namespace Tests\Vivait\DelayedEventBundle\Models;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class SimpleEntity {
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    public $id;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $name;

    function __construct($name)
    {
        $this->name = $name;
    }
}
