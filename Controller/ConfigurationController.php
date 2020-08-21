<?php

/*
 * This file is part of the ecentria group, inc. software.
 *
 * (c) 2015, ecentria group, inc.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ecentria\Libraries\EcentriaRestBundle\Controller;

use Ecentria\Libraries\EcentriaRestBundle\Annotation as EcentriaAnnotation;
use Ecentria\Libraries\EcentriaRestBundle\Services\ConfigurationManager;
use FOS\RestBundle\Controller\Annotations as FOS,
    FOS\RestBundle\Controller\AbstractFOSRestController,
    FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation as Nelmio;

/**
 * Configuration Controller
 *
 * @author Sergey Chernecov <sergey.chernecov@intexsys.lv>
 */
class ConfigurationController extends AbstractFOSRestController
{
    /**
     * Configuration manager
     *
     * @var ConfigurationManager
     */
    private $configurationManager;

    /**
     * ConfigurationController constructor
     *
     * @param ConfigurationManager $configurationManager
     */
    public function __construct(ConfigurationManager $configurationManager)
    {
        $this->configurationManager = $configurationManager;
    }

    /**
     * Return defined api configuration
     *
     * @EcentriaAnnotation\AvoidTransaction()
     *
     * @FOS\Route(
     *      path="configuration"
     * )
     *
     * @Nelmio\ApiDoc(
     *      section="Configuration",
     *      resource=true,
     *      statusCodes={
     *          200="Returned when successful",
     *          500="Returned when system failed"
     *      }
     * )
     *
     * @return View
     */
    public function getConfigurationAction()
    {
        $configuration = $this->configurationManager->getConfiguration();
        return $this->view($configuration);
    }
}
