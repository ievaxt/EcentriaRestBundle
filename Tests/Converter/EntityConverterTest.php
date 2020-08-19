<?php
/*
 * This file is part of the ecentria group, inc. software.
 *
 * (c) 2015, ecentria group, inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ecentria\Libraries\EcentriaRestBundle\Tests\Converter;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Ecentria\Libraries\EcentriaRestBundle\Services\CRUD\CrudTransformer;
use Ecentria\Libraries\EcentriaRestBundle\Tests\Entity\CircularReferenceEntity;
use Ecentria\Libraries\EcentriaRestBundle\Tests\Entity\EntityConverterEntity;
use Ecentria\Libraries\EcentriaRestBundle\Converter\EntityConverter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Entity Converter test
 *
 * @author Ryan Wood <ryan.wood@opticsplanet.com>
 */
class EntityConverterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Crud transformer
     *
     * @var \PHPUnit_Framework_MockObject_MockObject|CrudTransformer
     */
    private $crudTransformer;

    /**
     * Doctrine Registry
     *
     * @var \PHPUnit_Framework_MockObject_MockObject|ManagerRegistry
     */
    private $managerRegistry;

    /**
     * Entity Converter
     *
     * @var \PHPUnit_Framework_MockObject_MockObject|EntityConverter
     */
    private $entityConverter;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @return void
     */
    protected function setUp()
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->crudTransformer = $this->createMock(CrudTransformer::class);
            //->disableOriginalConstructor()
            //->setMethods(array('arrayToObject', 'arrayToObjectPropertyValidation'))
            //->getMock();

        $this->entityConverter = new EntityConverter(
            $this->crudTransformer,
            $this->managerRegistry
        );
    }

    /**
     * Test conversion of external references
     *
     * @return void
     */
    public function testConvertExternalReferences()
    {
        $objectContent = ['id' => 'one', 'second_id' => 'two'];
        $object = new EntityConverterEntity();
        $object->setIds($objectContent);

        $referenceObject = new CircularReferenceEntity();

        $mockRepository = $this->createMock(ObjectRepository::class);
        $mockRepository
            ->method('find')
            ->willReturn($referenceObject);

        $mockManager = $this->createMock(ObjectManager::class);
        $mockManager
            ->method('getRepository')
            ->willReturn($mockRepository);

        $this->managerRegistry
            ->method('getManager')
            ->willReturn($mockManager);
        
        $this->crudTransformer
            ->method('getPropertySetter')
            ->willReturn('setCircularReferenceEntity');

        $this->entityConverter->convertExternalReferences(
            new Request([], [], ['some-id' => '123'], [], [], [], json_encode($objectContent)),
            $object,
            [
                'references'     => [
                    'class' => 'CircularReferenceEntity',
                    'name'  => 'CircularReferenceEntity'
                ],
                'mapping'        => [],
                'exclude'        => [],
                'id'             => 'some-id',
                'entity_manager' => 'some-manager',
                'evict_cache'    => false,
            ]
        );

        //test validation and references conversion
        $this->assertEquals($referenceObject, $object->getCircularReferenceEntity());
    }
}
