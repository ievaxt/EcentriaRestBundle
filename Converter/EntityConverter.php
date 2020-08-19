<?php
/*
 * This file is part of the ecentria group, inc. software.
 *
 * (c) 2015, ecentria group, inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ecentria\Libraries\EcentriaRestBundle\Converter;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Ecentria\Libraries\EcentriaRestBundle\Model\Alias;
use Ecentria\Libraries\EcentriaRestBundle\Model\CRUD\CrudEntityInterface;
use Ecentria\Libraries\EcentriaRestBundle\Services\CRUD\CrudTransformer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\DoctrineParamConverter as BaseDoctrineParamConverter;
use Symfony\Component\HttpFoundation\Request;

/**
 * Modified DoctrineParamConverter.
 *
 * @author Sergey Chernecov <sergey.chernecov@intexsys.lv>
 */
class EntityConverter extends BaseDoctrineParamConverter
{
    /**
     * CRUD Transformer
     *
     * @var CrudTransformer
     */
    private $crudTransformer;

    /**
     * Registry
     *
     * @var ManagerRegistry|null
     */
    private $registry;

    /**
     * @var array
     */
    private $defaultOptions;

    /**
     * Constructor
     *
     * @param CrudTransformer      $crudTransformer crudTransformer
     * @param ManagerRegistry|null $registry        Registry
     */
    public function __construct(
        CrudTransformer $crudTransformer,
        ManagerRegistry $registry = null
    ) {
        parent::__construct(
            $registry
        );

        $this->crudTransformer = $crudTransformer;
        $this->registry = $registry;

        $this->defaultOptions = [
            'entity_manager'       => null,
            'exclude'              => [],
            'mapping'              => [],
            'strip_null'           => false,
            'expr'                 => null,
            'id'                   => null,
            'repository_method'    => null,
            'map_method_signature' => false,
            'evict_cache'          => false,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $name    = $configuration->getName();
        $class   = $configuration->getClass();
        $options = $this->getOptions($configuration);
        $mode  = empty($options['mode']) ? CrudTransformer::MODE_RETRIEVE : $options['mode'];

        if (null === $request->attributes->get($name, false)) {
            $configuration->setIsOptional(true);
        }

        $object = $mode == CrudTransformer::MODE_CREATE ? null : $this->findObject($class, $request, $options, $name);
        if (empty($object) || $mode == CrudTransformer::MODE_UPDATE) {
            $data = $this->crudTransformer->getRequestData($request, $mode);
            $this->crudTransformer->convertArrayToEntityAndValidate($data, $class, $mode, $object);
            $this->crudTransformer->setIdsFromRequest($object, $request, $mode, !empty($options['generated_id']));
            if (isset($options['references'])) {
                $this->convertExternalReferences($request, $object, $options);
            }
        }

        $request->attributes->set($name, $object);

        /**
         * Alias to access current collection
         * Used by exception listener
         */
        $request->attributes->set(Alias::DATA, $name);

        return true;
    }

    /**
     * Convert external relationships from the request to associations on the object
     *
     * @param Request             $request Request
     * @param CrudEntityInterface $object  Object
     * @param array               $options Options
     *
     * @return void
     */
    public function convertExternalReferences(Request $request, $object, $options)
    {
        // Convert external entity references into associated objects
        $references = !is_array(current($options['references'])) ? array($options['references']) : $options['references'];
        foreach ($references as $reference) {
            $entity = $this->findObject(
                $reference['class'],
                $request,
                array_merge($reference, $options),
                $reference['name']
            );
            $setter = $this->crudTransformer->getPropertySetter($reference['name']);
            if (method_exists($object, $setter) && is_object($entity)) {
                $object->$setter($entity);
            }
        }
    }

    /**
     * Find object
     *
     * @param string  $class   Class name
     * @param Request $request HTTP request
     * @param array   $options Param converter options
     * @param string  $name    Name of object
     *
     * @return bool|mixed
     */
    private function findObject($class, Request $request, array $options, $name)
    {
        $object = null;
        // find by identifier?
        if (null === $object = $this->find($class, $request, $options, $name)) {
            // find by criteria
            $object = $this->findOneBy($class, $request, $options);
        }
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    private function getOptions(ParamConverter $configuration, $strict = true)
    {
        /*
         * Copied from parent class
         */

        $passedOptions = $configuration->getOptions();

        if (isset($passedOptions['repository_method'])) {
            @trigger_error('The repository_method option of @ParamConverter is deprecated and will be removed in 6.0. Use the expr option or @Entity.', E_USER_DEPRECATED);
        }

        if (isset($passedOptions['map_method_signature'])) {
            @trigger_error('The map_method_signature option of @ParamConverter is deprecated and will be removed in 6.0. Use the expr option or @Entity.', E_USER_DEPRECATED);
        }

        $extraKeys = array_diff(array_keys($passedOptions), array_keys($this->defaultOptions));
        if ($extraKeys && $strict) {
            throw new \InvalidArgumentException(sprintf('Invalid option(s) passed to @%s: %s', $this->getAnnotationName($configuration), implode(', ', $extraKeys)));
        }

        return array_replace($this->defaultOptions, $passedOptions);
    }

    /**
     * {@inheritdoc}
     */
    private function getAnnotationName(ParamConverter $configuration)
    {
        /*
         * Copied from parent class
         */

        $r = new \ReflectionClass($configuration);

        return $r->getShortName();
    }

    /**
     * {@inheritdoc}
     */
    private function find($class, Request $request, $options, $name)
    {
        /*
         * Copied from parent class
         */

        if ($options['mapping'] || $options['exclude']) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $name);

        if (false === $id || null === $id) {
            return false;
        }

        if (isset($options['repository_method'])) {
            $method = $options['repository_method'];
        } else {
            $method = 'find';
        }

        $om = $this->getManager($options['entity_manager'], $class);
        if ($options['evict_cache'] && $om instanceof EntityManagerInterface) {
            $cacheProvider = $om->getCache();
            if ($cacheProvider && $cacheProvider->containsEntity($class, $id)) {
                $cacheProvider->evictEntity($class, $id);
            }
        }

        try {
            return $om->getRepository($class)->$method($id);
        } catch (NoResultException $e) {
            return;
        } catch (ConversionException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    private function getIdentifier(Request $request, $options, $name)
    {
        /*
         * Copied from parent class
         */

        if (null !== $options['id']) {
            if (!\is_array($options['id'])) {
                $name = $options['id'];
            } elseif (\is_array($options['id'])) {
                $id = [];
                foreach ($options['id'] as $field) {
                    if (false !== strstr($field, '%s')) {
                        // Convert "%s_uuid" to "foobar_uuid"
                        $field = sprintf($field, $name);
                    }
                    $id[$field] = $request->attributes->get($field);
                }

                return $id;
            }
        }

        if ($request->attributes->has($name)) {
            return $request->attributes->get($name);
        }

        if ($request->attributes->has('id') && !$options['id']) {
            return $request->attributes->get('id');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    private function findOneBy($class, Request $request, $options)
    {
        /*
         * Copied from parent class
         */

        if (!$options['mapping']) {
            $keys = $request->attributes->keys();
            $options['mapping'] = $keys ? array_combine($keys, $keys) : [];
        }

        foreach ($options['exclude'] as $exclude) {
            unset($options['mapping'][$exclude]);
        }

        if (!$options['mapping']) {
            return false;
        }

        // if a specific id has been defined in the options and there is no corresponding attribute
        // return false in order to avoid a fallback to the id which might be of another object
        if ($options['id'] && null === $request->attributes->get($options['id'])) {
            return false;
        }

        $criteria = [];
        $em = $this->getManager($options['entity_manager'], $class);
        $metadata = $em->getClassMetadata($class);

        $mapMethodSignature = $options['repository_method']
            && $options['map_method_signature']
            && true === $options['map_method_signature'];

        foreach ($options['mapping'] as $attribute => $field) {
            if ($metadata->hasField($field)
                || ($metadata->hasAssociation($field) && $metadata->isSingleValuedAssociation($field))
                || $mapMethodSignature) {
                $criteria[$field] = $request->attributes->get($attribute);
            }
        }

        if ($options['strip_null']) {
            $criteria = array_filter($criteria, function ($value) {
                return null !== $value;
            });
        }

        if (!$criteria) {
            return false;
        }

        if ($options['repository_method']) {
            $repositoryMethod = $options['repository_method'];
        } else {
            $repositoryMethod = 'findOneBy';
        }

        try {
            if ($mapMethodSignature) {
                return $this->findDataByMapMethodSignature($em, $class, $repositoryMethod, $criteria);
            }

            return $em->getRepository($class)->$repositoryMethod($criteria);
        } catch (NoResultException $e) {
            return;
        } catch (ConversionException $e) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    private function findDataByMapMethodSignature($em, $class, $repositoryMethod, $criteria)
    {
        /*
         * Copied from parent class
         */

        $arguments = [];
        $repository = $em->getRepository($class);
        $ref = new \ReflectionMethod($repository, $repositoryMethod);
        foreach ($ref->getParameters() as $parameter) {
            if (\array_key_exists($parameter->name, $criteria)) {
                $arguments[] = $criteria[$parameter->name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } else {
                throw new \InvalidArgumentException(sprintf('Repository method "%s::%s" requires that you provide a value for the "$%s" argument.', \get_class($repository), $repositoryMethod, $parameter->name));
            }
        }

        return $ref->invokeArgs($repository, $arguments);
    }

    /**
     * {@inheritdoc}
     */
    private function getManager($name, $class)
    {
        /*
         * Copied from parent class
         */

        if (null === $name) {
            return $this->registry->getManagerForClass($class);
        }

        return $this->registry->getManager($name);
    }
}
