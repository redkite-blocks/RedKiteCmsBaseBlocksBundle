<?php

namespace RedKiteLabs\RedKiteCms\RedKiteCmsBaseBlocksBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ElFinderFileController extends Controller
{
    public function connectFileAction()
    {
        $connector = $this->container->get('el_finder.file_connector');
        $connector->connect();
    }
}
