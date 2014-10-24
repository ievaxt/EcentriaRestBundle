<?php
/*
 * This file is part of the Ecentria software.
 *
 * (c) 2014, OpticsPlanet, Inc
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Ecentria\Libraries\CoreRestBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Ecentria\Libraries\CoreRestBundle\Interfaces\CRUDEntityInterface;
use Ecentria\Libraries\CoreRestBundle\Traits\EmbeddedTrait;
use Ecentria\Libraries\CoreRestBundle\Traits\TimestampableTrait;
use Ecentria\Libraries\CoreRestBundle\Traits\TransactionalTrait;
use Ecentria\Libraries\CoreRestBundle\Validator\Constraints as EcentriaAssert;

/**
 * CircularReferenceEntity test
 *
 * @author Sergey Chernecov <sergey.chernecov@intexsys.lv>
 *
 * @EcentriaAssert\CircularReference(
 *      field="Parent"
 * )
 */
class CircularReferenceEntity implements CRUDEntityInterface
{
    use EmbeddedTrait;
    use TransactionalTrait;
    use TimestampableTrait;

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Identifier
     *
     * @var string
     */
    private $id;

    /**
     * Parent channel
     *
     * @var CircularReferenceEntity
     */
    private $Parent;

    /**
     * Children
     *
     * @var CircularReferenceEntity[]|ArrayCollection
     */
    private $Children;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->Children = new ArrayCollection();
    }

    /**
     * Sets parent channel
     *
     * @param CircularReferenceEntity $parent
     * @return CircularReferenceEntity
     */
    public function setParent(CircularReferenceEntity $parent = null)
    {
        if ($this->Parent !== $parent) {
            if (!is_null($this->Parent)) {
                $this->Parent->removeChild($this);
            }
            if (!is_null($parent)) {
                $parent->addChild($this);
            }
            $this->Parent = $parent;
        }
        return $this;
    }

    /**
     * Returns parent channel
     *
     * @return CircularReferenceEntity
     */
    public function getParent()
    {
        return $this->Parent;
    }

    /**
     * Returns direct children as array
     *
     * @return CircularReferenceEntity[]|ArrayCollection
     */
    public function getChildren()
    {
        return $this->Children;
    }

    /**
     * Adds child channel to collection
     *
     * @param CircularReferenceEntity $child
     */
    private function addChild(CircularReferenceEntity $child)
    {
        if ($this->Children) {
            if (!$this->getChildren()->contains($child)) {
                $this->Children->add($child);
            }
        }
    }

    /**
     * Removes child channel from collection
     *
     * @param CircularReferenceEntity $child
     */
    private function removeChild(CircularReferenceEntity $child)
    {
        $index = $this->getChildren()->indexOf($child);
        if ($index !== false) {
            $this->Children->remove($index);
        }
    }
}